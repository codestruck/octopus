<?php

class SG_Model_Field_Datetime extends SG_Model_Field {

    private $format = 'Y-m-d H:i:s';

    public function getValue($model) {
        $field = $this->getFieldName();

        if ($field == 'created' && !$model->isSaved()) {
            $this->data = date($this->format, time());
        }

        return isset($this->data) ? $this->data : '';
    }

}

?>
