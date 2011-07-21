<?php

class Octopus_Model_Field_Datetime extends Octopus_Model_Field {

    private $format = 'Y-m-d H:i:s';

    public function __construct($field, $modelClass, $options) {

        parent::__construct($field, $modelClass, $options);

        if ($field == 'created') {
            $this->defaultOptions = array(
                'onCreate' => '_setNow',
            );
        } else if ($field == 'updated') {
            $this->defaultOptions = array(
                'onSave' => '_setNow',
            );
        }
    }

    public function migrate($schema, $table) {
        $table->newDateTime($this->getFieldName());
    }

    public function restrict($expression, $operator, $value, &$s, &$params, $model) {
        $value = self::formatDate($value);
        return parent::restrict($expression, $operator, $value, $s, $params, $model);
    }

    public function save($model, $sqlQuery) {

        $value = $this->accessValue($model, true);
        $value = self::formatDate($value);

        $sqlQuery->set($this->getFieldName(), $value);
    }

    private static function formatDate($value) {

        if ($value === '' || $value === null) {
            return '';
        }

        if (!is_numeric($value)) {
            $value = strtotime($value);
        }

        return date('Y-m-d H:i:s', $value);
    }

    function _setNow() {
        return date($this->format, time());
    }
}
?>
