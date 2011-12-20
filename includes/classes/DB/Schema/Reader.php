<?php

class Octopus_DB_Schema_Reader {

    function Octopus_DB_Schema_Reader($tableName) {
        $this->tableName = $tableName;
        $this->db = Octopus_DB::singleton();
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
            $info['index'] = $this->getColumnIndex($field);

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

    private function getColumnIndex($field) {

        $indexes = $this->getIndexes();
        foreach ($indexes as $index) {
            if ($index['Column_name'] == $field) {

                if ($index['Key_name'] === 'PRIMARY') {
                    return $index['Key_name'];
                }

                if ($index['Non_unique'] == 0) {
                    return 'UNIQUE';
                }

                return 'INDEX';
            }
        }

        return '';

    }

}
