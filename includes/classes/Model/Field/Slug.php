<?php

function to_unique_slug(Octopus_Model $model, Octopus_Model_Field $field, $forceField = null) {

    if ($forceField) {
        $str = $model->$forceField;
    } else {
        $str = $model->getDisplayValue();
    }

    $slug = to_slug($str);
    $return = $slug;

    $select = new Octopus_DB_Select();
    $select->table($model->getTableName(), array($model->getPrimaryKey()));
    $select->where($field->getFieldName() . ' = ?', $slug);
    $query = $select->query();

    $appendValue = 2;
    while ($query->numRows() > 0) {

        $return = $slug . '-' . $appendValue;

        $select = new Octopus_DB_Select();
        $select->table($model->getTableName(), array($model->getPrimaryKey()));
        $select->where($field->getFieldName() . ' = ?', $return);
        $query = $select->query();

        ++$appendValue;
    }

    return $return;

}

class Octopus_Model_Field_Slug extends Octopus_Model_Field {

    public function migrate($schema, $table) {
        $table->newTextSmall($this->getFieldName());
    }

}

?>
