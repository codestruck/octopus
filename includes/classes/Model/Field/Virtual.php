<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_Virtual extends Octopus_Model_Field {

    public function save($model, $sqlQuery) {
        // do nothing
        return null;
    }

    public function accessValue($model, $saving = false) {
        if ($this->getOption('onAccess')) {
            return $this->handleTrigger('onAccess', $model);
        } else {
            return $model->getInternalValue($this->getFieldName());
        }
    }

    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {
    }

    public function restrict($expression, $operator, $value, &$s, &$params, $model) {
        return null;
    }

}

