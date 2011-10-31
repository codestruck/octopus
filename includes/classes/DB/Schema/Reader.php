<?php

class Octopus_DB_Schema_Reader {

    function Octopus_DB_Schema_Reader($tableName) {
        $this->tableName = $tableName;
        $this->db =& Octopus_DB::singleton();
    }

    function getFields() {

        $sql = "desc $this->tableName";
        $query = $this->db->query($sql, true);

        $fields = array();

        while ($result = $query->fetchRow()) {

            $field = $result['Field'];

            $options = array();

            if ($result['Null'] == 'NO') {
                $options[] = 'NOT NULL';
            }

            if ($result['Extra'] != '') {
                $options[] = strtoupper($result['Extra']);
            }

            if ($result['Key'] == 'PRI') {
                $result['Key'] = 'PRIMARY KEY';
            }

            if ($result['Key'] == 'UNI') {
                $result['Key'] = 'UNIQUE';
            }

            $size = '';

            if (preg_match('/[^\(]+\(([^\(]+)\)/', $result['Type'], $matches)) {
                $size = $matches[1];
            }

            $type = $result['Type'];
            $paren = strpos($result['Type'], '(');
            if ($paren) {
                $type = substr($result['Type'], 0, $paren);
            }

            $info = array();
            $info['field'] = trim($field);
            $info['type'] = trim($type);
            $info['size'] = trim($size);
            $info['options'] = trim(implode(' ', $options));
            $info['index'] = trim($result['Key']);

            $fields[ $field ] = $info;

        }

        return $fields;
    }

    function getIndexes() {

        $sql = "SHOW INDEXES IN `$this->tableName`";
        $query = $this->db->query($sql, true);

        $indexes = array();

        while ($result = $query->fetchRow()) {
            $indexes[] = $result;
        }

        return $indexes;

    }

}
