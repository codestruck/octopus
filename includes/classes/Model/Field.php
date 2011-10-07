<?php

abstract class Octopus_Model_Field {

    protected $field;
    protected $modelClass;
    protected $options;
    protected $defaultOptions = array();

    /**
     * For fields w/ certain conventional names, some default options.
     */
    private static $magicFieldDefaults = array(

        'created' => array( 'type' => 'datetime'),
        'updated' => array( 'type' => 'datetime'),
        'active' => array( 'type' => 'boolean'),
        'order' => array( 'type' => 'order'),
        'slug' => array('type' => 'slug')

    );

    /**
     * Because I always forget what field types are, provide a few aliases.
     */
    private static $fieldTypeAliases = array(
        'text' => 'string',
        'bool' => 'boolean',
        'number' => 'numeric'
    );

    public function __construct($field, $modelClass, $options) {
        $this->field = $field;
        $this->modelClass = $modelClass;
        $this->options = $options;
    }

    public static function getField($name, $modelClass, $options) {

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

        $class = 'Octopus_Model_Field_' . camel_case($type, true);
        $options['type'] = $type;

        return new $class($name, $modelClass, $options);
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
            $value = $this->escape($model, $value);
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

    /**
     * Called before any migrations have been performed.
     */
    public function beforeMigrate($schema) {
    }

    /**
     * Creates DB resources required by this field.
     * @param $schema Octopus_DB_Schema
     * @param $table Main table being built.
     */
    abstract public function migrate($schema, $table);

    /**
     * Called after migrations have been performed and
     */
    public function afterMigrate($schema) {
    }

    public function migrateIndexes($schema, $table) {
        $index = $this->getOption('index', false);
        if ($index === 'unique') {
            $table->newIndex('UNIQUE', $this->getFieldName());
        } else if ($index === 'fulltext') {
        	$table->newIndex('FULLTEXT', $this->getFieldName());
        } else if ($index == 'index' || $index === true) {
            $table->newIndex($this->getFieldName());
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

    protected function escape($model, $value) {

        $func = $this->getOption('escape', true);

        if ($func === true) {

            $allow_tags = $this->getOption('allow_tags', false);
            if ($allow_tags !== false) {

                Octopus::loadExternal('htmlpurifier');

                $purifier = get_html_purifier(array(
                    'HTML.Allowed' => $allow_tags,
                ));
                return $purifier->purify($value);

            } else {
                return h($value);
            }

        } else if ($func === false) {
            return $value;
        } else if (method_exists($model, $func)) {
            return $model->$func($model, $value);
        } else if (function_exists($func)) {
            return call_user_func($func, $model, $value);
        }
    }

    protected function handleTrigger($type, $model, $default = null) {

        $fnc = $this->getOption($type);

        if (is_callable($fnc)) {
            $newValue = call_user_func($fnc, $model, $this);
            $model->setInternalValue($this->getFieldName(), $newValue);
            return $newValue;
        }

        // check for standalone function
        if (is_string($fnc)) {

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

        // TODO: if $value is a resultset, do a subquery

        // If no operator, and we get an array, then process it as an IN
        if (strcmp($operator, 'IN') == 0) {

            $value = is_array($value) ? $value : array($value);
            $expr = '';

            if (empty($value)) {

                // IN empty array = false
                $expr = '0';

            } else {

                foreach($value as $item) {
                    $params[] = $item;
                    $expr .= ($expr == '' ? '' : ',') . '?';
                }

                $expr = "`$table`.`$fieldName` IN ($expr)";
            }

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

    /**
     * Adds controls for this field to an Octopus_Html_Form.
     */
    public function addToForm($form) {

        if (!$this->getOption('form', true)) {
            return;
        }

        $field = $form->add('text', $this->getFieldName());

        if ($this->getOption('required')) {
            $field->required();
        }

        if ($this->getOption('unique')) {
            $field->mustPass(array($this, 'validateUniqueness'));
        }

    }

    public function addToTable($table) {

        if (!$this->getOption('table', true)) {
            return;
        }

        $table->addColumn($this->getFieldName());
    }

    public function validateUniqueness($value, $data, $formField) {

        $modelClass = $this->modelClass;
        $idName = to_id($modelClass);
        $id = isset($data[$idName]) ? $data[$idName] : null;

        $value = trim($value);
        if (!$value) return true;

        $criteria = array(
            $this->getFieldName() => $value
        );

        if ($id !== null) $criteria['id !='] = $id;

        $dummy = new $modelClass();
        $existing = $dummy->_get($criteria);

        if ($existing) return humanize($this->getFieldName()) . ' must be unique.';

        return true;
    }

    public function restrictFreetext($model, $text) {
        return new Octopus_Model_Restriction_Field($model, $this->getFieldname() . ' LIKE', $text);
    }

}

?>
