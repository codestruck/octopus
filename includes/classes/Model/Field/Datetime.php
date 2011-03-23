<?php

class SG_Model_Field_Datetime extends SG_Model_Field {

    private $format = 'Y-m-d H:i:s';

    public function saveValue($model) {
        $field = $this->getFieldName();

        if ($field == 'created' && !$model->isSaved()) {
            $value = date($this->format, time());
            $model->setInternalValue($field, $value);
        }

        if ($field == 'updated') {
            $value = date($this->format, time());
            $model->setInternalValue($field, $value);
        }

        return $model->getInternalValue($field);
    }

}

?>
