<?php

abstract class Octopus_Model_Field {

    protected $field;
    protected $options;
    protected $defaultOptions = array();

    /**
     * For fields w/ certain conventional names, some default options.
     */
    private static $magicFieldDefaults = array(

        'created' => array( 'type' => 'datetime'),
        'updated' => array( 'type' => 'datetime'),
        'active' => array( 'type' => 'boolean')

    );

    /**
     * Because I always forget what field types are, provide a few aliases.
     */
    private static $fieldTypeAliases = array(
        'text' => 'string',
        'bool' => 'boolean',
    );

    public function __construct($field, $options) {
        $this->field = $field;
        $this->options = $options;
    }

    public static function getField($name, $options) {

        if (is_string($options)) {
            $field = $options;
            $options = array();
        }

        if (isset(self::$magicFieldDefaults[$name])) {
            $options = array_merge(self::$magicFieldDefaults[$name], $options);
        }

        $type = isset($options['type']) ? $options['type'] : 'string';

        if (isset(self::$fieldTypeAliases[$type])) {
            $type = self::$fieldTypeAliases[$type];
        }

        $class = 'Octopus_Model_Field_' . camel_case($type);
        Octopus::loadClass($class);

        $options['type'] = $type;

        return new $class($name, $options);
    }

    public function accessValue($model, $saving = false) {
        $value = $model->getInternalValue($this->getFieldName());

        if ($saving) {

            $primaryKey = $model->getPrimaryKey();
            if (!$model->exists()) {
                $value = $this->handleTrigger('onCreate', $model, $value);
            } else {
                $value = $this->handleTrigger('onUpdate', $model, $value);
            }

            if (!$value) {
                $value = $this->handleTrigger('onEmpty', $model, $value);
            }

            $value = $this->handleTrigger('onSave', $model, $value);

        } else if ($model->escaped) {
            $value = $this->escape($value);
        }

        return $value;
    }

    public function save($model, $sqlQuery) {
        $sqlQuery->set($this->getFieldName(), $this->accessValue($model, true));
    }

    public function afterSave($model) {
        $this->handleTrigger('afterSave', $model);
    }

    public function setValue($model, $value) {
        $model->setInternalValue($this->getFieldName(), $value);
    }

    public function getFieldName() {
        return $this->field;
    }

    public function getOption($option, $default = null) {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        } else if (isset($this->defaultOptions[$option])) {
            return $this->defaultOptions[$option];
        } else {
            return $default;
        }
    }

    public function validate($model) {

        if ($this->getOption('required')) {
            $value = $this->accessValue($model);
            if (!$value) {
                return false;
            }
        }

        return true;
    }

    protected function escape($value) {
        return h($value);
    }

    protected function handleTrigger($type, $model, $default = null) {

        $fnc = $this->getOption($type);

        // check for standalone function
        if ($fnc && function_exists($fnc)) {
            $newValue = $fnc($model, $this);
            $model->setInternalValue($this->getFieldName(), $newValue);
            return $newValue;
        }

        // check for custom function on the model subclass
        if ($fnc && method_exists($model, $fnc)) {
            $newValue = $model->$fnc($model, $this);
            $model->setInternalValue($this->getFieldName(), $newValue);
            return $newValue;
        }

        // check for default function on the field subclass
        if ($fnc && method_exists($this, $fnc)) {
            $newValue = $this->$fnc($model, $this);
            $model->setInternalValue($this->getFieldName(), $newValue);
            return $newValue;
        }

        return $default;

    }

    /**
     * @param $expression string For relations, the subexpression being
     * used for filtering. For example, for a field named 'category', for the
     * expression 'category.name', 'name' would be passed as the subexpression
     * to the 'category' field.
     * @param $operator string Operator (=, LIKE, etc) to use. If null, the
     * field's default operator will be used.
     * @param $value Mixed value to restrict this field to.
     * @param $s Object Octopus_DB_Select being built, in case any joins are required.
     * Don't call where() or anything on this.
     * @param $params Array Set of parameters that will be passed to $s via
     * the where() method.
     * @return String A chunk of SQL for a WHERE clause.
     */
    public function restrict($expression, $operator, $value, &$s, &$params, $model) {
        $sql = self::defaultRestrict($this->getFieldName(), $operator, $this->getDefaultSearchOperator(), $value, $s, $params, $model);
        return $sql;
    }

    /**
     * A general-purpose implementation of restrict() that can be reused
     * in a static context. This is to support restricting by IDs, which don't
     * have associated Octopus_Model_Field instances.
     * @param $fieldName Mixed The field being restricted OR an array whose
     * first element is the table and second element is the field in that table
     * being restricted.
     */
    public static function defaultRestrict($fieldName, $operator, $defaultOperator, $value, &$s, &$params, $model) {

        if (is_array($fieldName)) {
            $table = array_shift($fieldName);
            $fieldName = array_shift($fieldName);
        } else {
            $table = $model->getTableName();
        }

        if (!$operator) {
            $operator = is_array($value) ? 'IN' : $defaultOperator;
        }

        $operator = strtoupper(trim($operator));
        if (!$operator) $operator = '=';
        $opLen = strlen($operator);

        // Handle stuff like 'NOT', 'NOT LIKE', 'NOT =', 'NOT IN' etc.
        $not = false;
        if (($opLen == 3 && strcmp($operator, 'NOT') == 0) || ($opLen > 3 && strncmp($operator, 'NOT ', 4) == 0)) {

            $not = true;
            $operator = trim(substr($operator,3));
            if ($operator == '') $operator = $defaultOperator;

        }

        // If no operator, and we get an array, then process it as an IN
        if (strcmp($operator, 'IN') == 0) {

            $value = is_array($value) ? $value : array($value);
            $expr = '';
            foreach($value as $item) {
                $params[] = $item;
                $expr .= ($expr == '' ? '' : ',') . '?';
            }

            $expr = "`$table`.`$fieldName` IN ($expr)";
        } else {
            $params[] = $value;
            $expr = "`$table`.`$fieldName` $operator ?";
        }

        if ($not) {
            $expr = "NOT ($expr)";
        }

        return $expr;
    }

    /**
     * Applies ordering to an Octopus_DB_Select being constructed for an
     * Octopus_Model_ResultSet.
     * @param $resultSet Object The result set being built.
     * @param $s Object Octopus_DB_Select being built.
     * @param $dir string Direction to order this field by.
     */
    public function orderBy($expression, $dir, $s, &$params, $model) {
        self::defaultOrderBy($this->getFieldName(), $dir, $s, $params, $model);
    }

    /**
     * Helper to support ordering by ids, which don't have associated fields.
     */
    public static function defaultOrderBy($fieldName, $dir, $s, &$params, $model) {
        $s->orderBy("`{$model->getTableName()}`.`{$fieldName}` $dir");
    }

    /**
     * @return String The default operator (e.g. '=' or 'LIKE') this field
     * should use when none is specified.
     */
    public function getDefaultSearchOperator() {
        return '=';
    }
}
?>
