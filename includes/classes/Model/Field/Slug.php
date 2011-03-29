<?php

function to_unique_slug(SG_Model $model, SG_Model_Field $field) {
    $str = $model->getDisplayValue();

    $slug = to_slug($str);
    $return = $slug;

    $select = new SG_DB_Select();
    $select->table($model->getTableName(), array($model->getPrimaryKey()));
    $select->where($field->getFieldName() . ' = ?', $slug);
    $query = $select->query();

    $appendValue = 2;
    while ($query->numRows() > 0) {

        $return = $slug . '-' . $appendValue;

        $select = new SG_DB_Select();
        $select->table($model->getTableName(), array($model->getPrimaryKey()));
        $select->where($field->getFieldName() . ' = ?', $return);
        $query = $select->query();

        ++$appendValue;
    }

    return $return;

}

class SG_Model_Field_Slug extends SG_Model_Field {
}

?>
