<?php

class Octopus_Model_Field_Date extends Octopus_Model_Field {

    private static $format = 'Y-m-d';

    public function migrate($schema, $table) {
        $table->newDate($this->getFieldName());
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

        return date(self::$format, $value);
    }

    function _setNow() {
        return date(self::$format, time());
    }
}
