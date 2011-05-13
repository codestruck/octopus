<?php

class Octopus_Model_Field_Virtual extends Octopus_Model_Field {

    public function save($model, $sqlQuery) {
        // do nothing
        return $this->handleTrigger('onSave', $model);
    }

    public function accessValue($model, $saving = false) {
        if ($this->getOption('onAccess')) {
            return $this->handleTrigger('onAccess', $model);
        } else {
            return null;
        }
    }

    public function restrict($operator, $value, &$s, &$params, $model) {
        return null;
    }

}

