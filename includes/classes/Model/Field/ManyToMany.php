<?php

class SG_Model_Field_ManyToMany extends SG_Model_Field {

    public function accessValue($model, $saving = false) {
        $type = strtolower(get_class($model));
        $value = $model->id;

        return new SG_Model_ResultSet($this->field, array(pluralize($type) => $value));
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

}

