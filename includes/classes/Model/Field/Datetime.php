<?php

class SG_Model_Field_Datetime extends SG_Model_Field {

    private $format = 'Y-m-d H:i:s';

// not sure why this is needed.  $this->data is different if set here and referenced in parent class?
    function accessValue($model) {
        $value = isset($this->data) ? $this->data : '';
        return $value;
    }

    public function saveValue($model) {
        $field = $this->getFieldName();

        if ($field == 'created' && !$model->isSaved()) {
            $this->data = date($this->format, time());
        }

        if ($field == 'updated') {
            $this->data = date($this->format, time());
        }

        return isset($this->data) ? $this->data : '';
    }

    function setValue($model, $value) {
        $this->data = $value;
    }

}

?>
