<?php

class Octopus_Model_Field_ManyToMany extends Octopus_Model_Field {

    public function save($model, $sqlQuery) {
        // do nothing
    }

    public function accessValue($model, $saving = false) {
        $type = strtolower(get_class($model));
        $value = $model->id;

        return new Octopus_Model_ResultSet($this->field, array(pluralize($type) => $value));
    }

    public function restrict($operator, $value, &$s, &$params, $model) {
        $type = strtolower(get_class($model));
        $joinTable = $this->getJoinTableName(array($this->field, $type));
        $s->innerJoin($joinTable, $model->to_id($type), array());
        $sql = $this->defaultRestrict($model->to_id($this->field), $operator, $this->getDefaultSearchOperator(), $value, $s, $params, $model);
        return $sql;
    }

    public function getFieldName() {
        return pluralize($this->field);
    }

    private function getJoinTableName($tables) {
        sort($tables);
        return sprintf('%s_%s_join', $tables[0], $tables[1]);
    }

    public function handleRelation($action, $obj, $model) {

        // handle array of objects
        if (!is_object($obj) && is_array($obj)) {
            foreach ($obj as $item) {
                $this->handleRelation($action, $item, $model);
            }
        } else {

            if (!is_object($obj) && is_numeric($obj)) {
                $class = ucfirst($this->field);
                $obj = new $class($obj);
            } else {
                // TODO: always save? Check for dirty state?
                $obj->save();
            }

            $type = strtolower(get_class($model));
            $joinTable = $this->getJoinTableName(array($this->field, $type));

            $d = new Octopus_DB_Delete();
            $d->table($joinTable);
            $d->where($model->getPrimaryKey() . ' = ?', $model->id);
            $d->where($obj->getPrimaryKey() . ' = ?', $obj->id);
            $d->execute();

            if ($action == 'add') {
                $i = new Octopus_DB_Insert();
                $i->table($joinTable);
                $i->set($model->getPrimaryKey(), $model->id);
                $i->set($obj->getPrimaryKey(), $obj->id);
                $i->execute();
            }

        }

    }

    public function checkHas($obj, $model) {

        $type = strtolower(get_class($model));
        $table = $this->getJoinTableName(array($this->field, $type));

        if (is_object($obj)) {
            $obj = $obj->id;
        }

        $s = new Octopus_DB_Select();
        $s->table($table);
        $s->where($model->to_id($this->field) . ' = ?', $obj);
        $s->where($model->to_id($type) . ' = ?', $model->id);
        $query = $s->query();
        return $query->numRows() > 0;

    }

}

