<?php

class SG_Model_Field {

    protected $data;
    private $field;
    private $options;

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
        $value = isset($this->data) ? $this->data : '';
        return $value;
    }

    function saveValue($model) {
        $value = isset($this->data) ? $this->data : '';

        if (!$value) {
            $value = $this->onEmpty($model);
            $this->data = $value;
        }

        return $value;
    }

    function setValue($model, $value) {
        $this->data = $value;
    }

    function getFieldName() {
        return $this->field;
    }

    function getOption($option, $default = null) {
        if (isset($this->options[$option])) {
            return $this->options[$option];
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


    // handlers
    function onEmpty($model) {

        $fnc = $this->getOption('onEmpty');
        if ($fnc && function_exists($fnc)) {
            return $fnc($model, $this);
        }

        return '';
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

        $fieldName = $this->getFieldName();

        if (!$operator) {
            $operator = is_array($value) ? 'IN' : $this->getDefaultSearchOperator();
        } else {
            $operator = trim($operator);
        }

        $not = false;
        if (strncasecmp($operator, 'NOT', 3) == 0) {
            $not = true;
            $operator = trim(substr($operator,3));
            if ($operator == '') $operator = $this->getDefaultSearchOperator(); // Handle NOT without an = or LIKE
        }

        // If no operator, and we get an array, then process it as an IN
        if (strcasecmp($operator, 'IN') == 0) {

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
