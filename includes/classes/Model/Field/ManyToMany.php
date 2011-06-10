<?php

class Octopus_Model_Field_ManyToMany extends Octopus_Model_Field {

    public function save($model, $sqlQuery) {
        $values = $model->getInternalValue($this->getFieldName());
        if (is_array($values) && $model->exists()) {
            $this->handleRelation('removeAll', null, $model);
            $this->handleRelation('add', $values, $model);
        }
    }

    public function accessValue($model, $saving = false) {
        $type = $this->field;
        $key = pluralize(strtolower(get_class($model)));
        $value = $model->id;

        $search = array($key => $value);


        $resultSet = new Octopus_Model_ResultSet($type, $search);
        $resultSet->escaped = $model->escaped;
        return $resultSet;
    }

    public function restrict($operator, $value, &$s, &$params, $model) {

        $type = strtolower(get_class($model));
        $joinTable = $this->getJoinTableName(array($this->field, $type));
        $s->innerJoin($joinTable, $model->to_id($type), array());

        $foreignKey = $model->to_id($this->field);

        $sql = $this->defaultRestrict(array($joinTable, $foreignKey), $operator, $this->getDefaultSearchOperator(), $value, $s, $params, $model);
        return $sql;
    }

    public function getFieldName() {
        return pluralize($this->field);
    }

    public function getJoinTableName($tables) {
        sort($tables);
        return sprintf('%s_%s_join', $tables[0], $tables[1]);
    }

    public function handleRelation($action, $obj, $model) {

        //delete next row?
        $type = strtolower(get_class($model));
        $joinTable = $this->getJoinTableName(array($this->field, $type));

        if ($action == 'removeAll') {
            $d = new Octopus_DB_Delete();
            $d->table($joinTable);
            $d->where($model->getPrimaryKey() . ' = ?', $model->id);
            $d->execute();
            return;
        }

        // ignore junk values
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

