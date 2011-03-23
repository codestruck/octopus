<?php

class SG_Model_Field {

    private $field;
    private $options;
    protected $defaultOptions = array();

    public function __construct($field, $options) {
        $this->field = $field;
        $this->options = $options;
    }

    public static function getField($field, $options) {

        if (is_string($options)) {
            $field = $options;
            $options = array();
        }

        $type = 'string';

        if (isset($options['type'])) {
            $type = $options['type'];
        }

        if (($field === 'created' || $field === 'updated') && count($options) == 0) {
            $type = 'datetime';
        }

        $class = 'SG_Model_Field_' . ucfirst($type);
        SG::loadClass($class);

        $obj = new $class($field, $options);
        return $obj;
    }

    function accessValue($model) {
        $value = $model->getInternalValue($this->getFieldName());
        return $value;
    }

    function saveValue($model) {
        $value = $model->getInternalValue($this->getFieldName());

        $primaryKey = $model->getPrimaryKey();
        if ($model->$primaryKey === null) {
            $value = $this->handleTrigger('onCreate', $model);
        } else {
            $value = $this->handleTrigger('onUpdate', $model);
        }

        if (!$value) {
            $value = $this->handleTrigger('onEmpty', $model);
        }

        $value = $this->handleTrigger('onSave', $model);

        return $value;
    }

    function setValue($model, $value) {
        $model->setInternalValue($this->getFieldName(), $value);
    }

    function getFieldName() {
        return $this->field;
    }

    function getOption($option, $default = null) {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        } else if (isset($this->defaultOptions[$option])) {
            return $this->defaultOptions[$option];
        } else {
            return $default;
        }
    }

    function validate($model) {

        if ($this->getOption('required')) {
            $value = $this->accessValue($model);
            if (!$value) {
                return false;
            }
        }

        return true;
    }

    protected function handleTrigger($type, $model) {

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

        return $this->accessValue($model);

    }

    /**
     * @param $operator string Operator (=, LIKE, etc) to use. If null, the
     * field's default operator will be used.
     * @param $value Mixed value to restrict this field to.
     * @param $s Object SG_DB_Select being built, in case any joins are required.
     * Don't call where() or anything on this.
     * @param $params Array Set of parameters that will be passed to $s via
     * the where() method.
     * @return String A chunk of SQL for a WHERE clause.
     */
    public function restrict($operator, $value, &$s, &$params) {
        $sql = self::defaultRestrict($this->getFieldName(), $operator, $this->getDefaultSearchOperator(), $value, $s, $params);
        return $sql;
    }

    /**
     * A general-purpose implementation of restrict() that can be reused
     * in a static context. This is to support restricting by IDs, which don't
     * have associated SG_Model_Field instances.
     */
    public static function defaultRestrict($fieldName, $operator, $defaultOperator, $value, &$s, &$params) {

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

            $expr = "(`$fieldName` IN ($expr))";
        } else {
            $params[] = $value;
            $expr = "(`$fieldName` $operator ?)";
        }

        if ($not) {
            $expr = "(NOT $expr)";
        }

        return $expr;
    }

    /**
     * Applies ordering to the given SG_DB_Select.
     * @param $s Object SG_DB_Select being built.
     * @param $dir string Direction to order this field by.
     */
    public function orderBy(&$s, $dir = 'ASC') {
        $n = $this->getFieldName();
        $s->orderBy("`$n` $dir");
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
