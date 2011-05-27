<?php

Octopus::loadClass('Octopus_DB_Schema');

class Octopus_DB_Schema_Model {

    public static function makeTable($model) {

        $model = strtolower($model);

        $d = new Octopus_DB_Schema();
        $t = $d->newTable(pluralize($model));
        $t->newKey($model . '_id', true);
        $t->newPrimaryKey($model . '_id');

        $modelClass = camel_case($model, true);
        Octopus::loadModel($modelClass);
        $obj = new $modelClass();

        foreach ($obj->getFields() as $field) {
            $fieldName = $field->getFieldName();

            if (is_a($field, 'Octopus_Model_Field_String')) {
                $t->newTextSmall($fieldName);
            } else if (is_a($field, 'Octopus_Model_Field_Slug')) {
                $t->newTextSmall($fieldName);
            } else if (is_a($field, 'Octopus_Model_Field_Html')) {
                $t->newTextLarge($fieldName);
            } else if (is_a($field, 'Octopus_Model_Field_Numeric')) {
                $t->newBigInt($fieldName);
            } else if (is_a($field, 'Octopus_Model_Field_Boolean')) {
                $t->newBool($fieldName);
            } else if (is_a($field, 'Octopus_Model_Field_HasOne')) {
                $t->newKey($fieldName . '_id');
                $t->newIndex($fieldName . '_id');
            } else if ($fieldName == 'created') {
                $t->newDateTime($fieldName);
            } else if ($fieldName == 'updated') {
                $t->newDateTime($fieldName);
            } else if (is_a($field, 'Octopus_Model_Field_ManyToMany')) {
                $tableA = singularize($fieldName);
                $joinTable = $field->getJoinTableName(array($tableA, $model));

                $j = $d->newTable($joinTable);
                $j->newKey($obj->to_id($tableA));
                $j->newIndex($obj->to_id($tableA));
                $j->newKey($obj->to_id($model));
                $j->newIndex($obj->to_id($model));
                $j->create();

            }
        }

        $t->create();

    }

}

