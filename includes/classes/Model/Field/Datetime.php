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

   public function save($model, $sqlQuery) {

       $value = $this->accessValue($model, true);

       if (is_string($value)) {
           $value = strtotime($value);
       }

       $value = date('Y-m-d H:i:s', $value);

       $sqlQuery->set($this->getFieldName(), $value);
    }

    function _setNow() {
        return date($this->format, time());
    }
}

?>
