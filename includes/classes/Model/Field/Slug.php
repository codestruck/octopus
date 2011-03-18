<?php

function to_unique_slug(SG_Model $model, SG_Model_Field $field) {
    $str = $model->getDisplayValue();

    $slug = to_slug($str);
    $return = $slug;

    $s = new SG_DB_Select();
    $s->table($model->getTableName(), array($model->getPrimaryKey()));
    $s->where($field->getFieldName() . ' = ?', $slug);
    $query = $s->query();
    //dump_r($s->getSql(), $slug, $query->numRows());

    $i = 2;
    while ($query->numRows() > 0) {

        $return = $slug . '-' . $i;

        $s = new SG_DB_Select();
        $s->table($model->getTableName(), array($model->getPrimaryKey()));
        $s->where($field->getFieldName() . ' = ?', $return);
        $query = $s->query();

        ++$i;
    }

    return $return;

}

class SG_Model_Field_Slug extends SG_Model_Field {



}

?>
