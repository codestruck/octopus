<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_Datetime extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options, $migrateMethod = null) {

        if (!$migrateMethod) $migrateMethod = 'newDateTime';

        parent::__construct($field, $modelClass, $options, $migrateMethod);

        if ($field == 'created') {
            $this->defaultOptions = array(
                'onCreate' => '_setNow',
            );
        } else if ($field == 'updated') {
            $this->defaultOptions = array(
                'onSave' => '_setNow',
            );
        } else {
            $this->defaultOptions['form'] = true;
        }

        $this->defaultOptions['date_format'] = 'Y-m-d H:i:s';
    }

    public function setValue($model, $value) {
        $value  = $this->parseDateTime($value);
        $value = $this->formatDateTime($value);
        return parent::setValue($model, $value);
    }

    public function restrict($expression, $operator, $value, &$s, &$params, $model) {
        $value = $this->formatDateTime($value, '0000-00-00 00:00:00');
        return parent::restrict($expression, $operator, $value, $s, $params, $model);
    }

    public function save($model, $sqlQuery) {
        $value = $this->accessValue($model, true);
        $value = $this->formatDateTime($value);
        $sqlQuery->set($this->getFieldName(), $value);
    }

    protected function getDateFormat() {
        return $this->getOption('date_format', 'Y-m-d H:i:s');
    }

    /**
     * @return A timestamp, or '' if $str equates to a zero date.
     */
    protected function parseDateTime($str) {

        if ($str === '0000-00-00 00:00:00' ||
            $str === '0000-00-00' ||
            $str === null ||
            $str === false ||
            $str === '') {
            return '';
        }

        if (is_numeric($str)) {
            // $str is already a timestamp
            return $str;
        }

        $value = strtotime($str);
        if ($value === false) throw new Octopus_Model_Exception("Invalid datetime value: $str");
        return $value;

    }

    /**
     * @return A standard representation of a datetime value (YYYY-MM-DD HH:MM:SS).
     */
    protected function formatDateTime($value, $zeroValue = '') {

        if (!is_numeric($value)) {
            $value = $this->parseDateTime($value);
        }

        if (!$value) {
            return $zeroValue;
        }

        return date($this->getDateFormat(), $value);
    }

    public function _setNow() {
        return $this->formatDateTime(time());
    }

}
