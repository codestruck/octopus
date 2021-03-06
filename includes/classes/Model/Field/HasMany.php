<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_HasMany extends Octopus_Model_Field {

    public function save($model, $sqlQuery) {
        // do nothing
    }

    public function accessValue($model, $saving = false) {

        $type = $this->getOption('model', $this->field);
        $key = $this->getOption('key', underscore(get_class($model)));
        $value = $model->id;

        $search = array($key => $value);

        $filtering = $this->getOption('filter', false);
        if ($filtering) {
            $search = array(
                'item_type' => $key,
                'item_id' => $value,
            );
        }

        $resultSet = new Octopus_Model_ResultSet($type, $search);
        $resultSet->escaped = $model->escaped;
        return $resultSet;
    }

    public function loadValue(Octopus_Model $model, $row) {
        // NOOP
    }

    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {

        // TODO: should hasMany require a hasOne on the other class? Or can we
        // do the migration here as well?

    }


    public function handleRelation($action, $obj, Octopus_Model $model) {

        if ($action !== 'add') {
            throw new Octopus_Model_Exception('Can not call ' . $action . ' on model ' . get_class($model));
        }

        // just ignore junk values
        if ($obj === null) {
            return;
        }

        // handle array of objects
        if (!is_object($obj) && is_array($obj)) {
            foreach ($obj as $item) {
                $this->handleRelation($action, $item, $model);
            }
            return;
        }

        if (is_numeric($obj)) {
            // $obj is an id
            $class = $this->getOption('model');
            if (!$class) $class = camel_case(singularize($this->getFieldName()), true);
            $obj = new $class($obj);
        }

        $pkFields = $model->getPrimaryKeyFields();
        if (count($pkFields) !== 1) {
            throw new Octopus_Model_Exception("HasMany is not supported on models with compound primary keys.");
        }

        $key = $this->getOption('key');
        if (!$key) $key = underscore(get_class($model));
        $value = $model->id;

        if (!$value || $value < 1) {
            throw new Octopus_Model_Exception('Can not add ' . get_class($obj) . ' to unsaved ' . get_class($model));
        }

        $filtering = $this->getOption('filter', false);
        if ($filtering) {
            $obj->item_type = $key;
            $obj->item_id = $value;

            // TODO: let filter specify the field on the other end

        } else {
            $obj->$key = $value;
        }

            $obj->save();

    }

    public function getFieldName() {
        return pluralize($this->field);
    }

}
