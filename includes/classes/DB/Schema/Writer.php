<?php

Octopus::loadClass('Octopus_DB_Schema_Reader');

class Octopus_DB_Schema_Writer {

    function Octopus_DB_Schema_Writer($tableName) {
        $this->tableName = $tableName;
        $this->fields = array();
        $this->indexes = array();
        $this->hasIndexes = array();

        $this->db =& Octopus_DB::singleton();
    }

    function newBool($fieldName) {
        $this->newField($fieldName, 'tinyint', 1, 'NOT NULL');
    }

    function newGuid($fieldName) {
        $this->newField($fieldName, 'char', 36, 'NOT NULL');
    }

    function newInt($fieldName, $size = 11) {
        $this->newField($fieldName, 'int', $size, 'NOT NULL');
    }

    function newBigInt($fieldName) {
        $this->newField($fieldName, 'bigint', null, 'NOT NULL');
    }

    function newDate($fieldName) {
        $this->newField($fieldName, 'date', null, 'NOT NULL');
    }

    function newDateTime($fieldName) {
        $this->newField($fieldName, 'datetime', null, 'NOT NULL');
    }

    function newTime($fieldName) {
        $this->newField($fieldName, 'time', null, 'NOT NULL');
    }

    function newKey($fieldName, $autoincrement = false) {

        $auto = $autoincrement ? ' AUTO_INCREMENT' : '';
        $this->newField($fieldName, 'int', 10, 'NOT NULL' . $auto);
    }

    function newTextSmall($fieldName, $length=250) {
        $this->newField($fieldName, 'varchar', $length, 'NOT NULL');
    }

    function newTextLarge($fieldName) {
        $this->newField($fieldName, 'text', null, 'NOT NULL');
    }

    function newBinarySmall($fieldName, $length=250) {
        $this->newField($fieldName, 'varbinary', $length, 'NOT NULL');
    }

    function newDecimal($fieldName, $whole, $part) {
        $this->newField($fieldName, 'decimal', $whole . ',' . $part, 'NOT NULL');
    }

    function newFloat($fieldName, $whole='', $part='', $default = '') {
        $default_value = ($default != '') ? " default '$default'" : '';
        $whole_part = ($whole != '' && $part != '' && $whole > $part) ? "$whole,$part" : '';
        $this->newField($fieldName, 'float', $whole_part, "NOT NULL $default_value");
    }

    function newEnum($fieldName, $items) {
        $enumItems = "'" . implode("','", $items) . "'";
        $this->newField($fieldName, 'enum', $enumItems, "NOT NULL default '{$items[0]}'");
    }

    function newField($fieldName, $type, $size = null, $options) {

        $this->fields[$fieldName] = array(
                                          'field' => $fieldName,
                                          'type' => $type,
                                          'size' => $size,
                                          'options' => $options
                                          );

    }

    function newIndex($type, $name = '', $elements = null) {

        if ($elements === null && $name == '') {
            $field = $type;
            $elements = array($field);
            $type = 'INDEX';
        } else if ($elements === null) {
            $field = $name;
            $elements = array($field);
            $name = '';
        } else {

            $field = null;

            if (!is_array($elements)) {
                $field = $elements;
                $elements = array($elements);
            }
        }

        $this->hasIndexes = array_merge($this->hasIndexes, $elements);

        if (strlen($name) > 0) {
            $name = ' `' . $name . '`';
        }

        $elements = '`' . implode('`,`', $elements) . '`';
        $line = sprintf("%s%s (%s)", $type, $name, $elements);

        if ($field) {
            $this->indexes[$field] = $line;
        } else {
            $this->indexes[] = $line;
        }
    }

    function newPrimaryKey($field) {
        $this->newIndex('PRIMARY KEY', null, $field);
    }

    function fieldsMatch($info, $current) {

        if ($info['field'] != $current['field']) {
            return false;
        }

        if ($info['type'] != $current['type']) {
            return false;
        }

        if ($info['size'] != $current['size']) {
            return false;
        }

        if ($info['options'] != $current['options']) {
            return false;
        }

        $field = $info['field'];

        if (isset($this->indexes[ $field ]) && empty($current['index'])) {
            return false;
        }

        if (!isset($this->indexes[ $field ]) && !empty($current['index'])) {
            return false;
        }

        return true;
    }

    function alterChangeField($info, $current) {

        if ($info['size'] != '') {
            $sql = sprintf("CHANGE `%s` `%s` %s(%s) %s", $info['field'], $info['field'], $info['type'], $info['size'], $info['options']);
        } else {
            $sql = sprintf("CHANGE `%s` `%s` %s %s", $info['field'], $info['field'], $info['type'], $info['options']);
        }

        $field = $info['field'];
        if (!empty($current['index']) && !in_array($field, $this->hasIndexes)) {
            if ($current['index'] == 'PRIMARY KEY') {
                $sql .= sprintf(', DROP %s', $current['index']);
            } else {
                $sql .= sprintf(', DROP INDEX `%s`', $field);
            }
        }

        if (empty($current['index']) && !empty($this->indexes[ $field ])) {
            $sql .= ', ADD ' . $this->indexes[ $field ];
        }

        return $sql;
    }

    function alterAddField($info) {

        if ($info['size'] != '') {
            $sql = sprintf("ADD COLUMN `%s` %s(%s) %s", $info['field'], $info['type'], $info['size'], $info['options']);
        } else {
            $sql = sprintf("ADD COLUMN `%s` %s %s", $info['field'], $info['type'], $info['options']);
        }

        $field = $info['field'];
        if (!empty($this->indexes[ $field ])) {
            $sql .= ', ADD ' . $this->indexes[ $field ];
        }

        return $sql;
    }

    function alterDropField($info) {

        $field = $info['field'];

        $sql = sprintf("DROP COLUMN `%s`", $field);

        if (!empty($info['index'])) {
            if ($info['index'] == 'PRIMARY KEY') {
                $sql .= sprintf(', DROP %s', $info['index']);
            } else {
                $sql .= sprintf(', DROP INDEX `%s`', $field);
            }
        }

        return $sql;
    }


    function createAddField($info) {

        if ($info['size'] != '') {
            $sql = sprintf("`%s` %s(%s) %s", $info['field'], $info['type'], $info['size'], $info['options']);
        } else {
            $sql = sprintf("`%s` %s %s", $info['field'], $info['type'], $info['options']);
        }

        return $sql;
    }

    function create() {

        $modificationFile = SITE_DIR . 'upgrades/' . $this->tableName . '.php';
        $fnc = 'modify_database_upgrade_' . $this->tableName;

        if (is_file($modificationFile)) {
            require_once($modificationFile);

            if (function_exists($fnc)) {
                $obj = $fnc($this);
                $this->fields = $obj->fields;
            }
        }

        $sql = $this->toSql();
        if (trim($sql) != '') {
            $this->db->query($sql, true);
        }
    }

    function toSql() {
        if ($this->tableExists($this->tableName)) {
            return $this->alterTable();
        } else {
            return $this->createTable();
        }
    }

    function createTable() {

        $sql = '';
        $sql .= sprintf("CREATE TABLE `%s` (\n", $this->tableName);

        $fieldLines = array();
        foreach ($this->fields as $field) {
            $fieldLines[] = $this->createAddField($field);
        }

        $lines = array_merge($fieldLines, $this->indexes);

        $sql .= implode(",\n", $lines);
        $sql .= "\n);";

        return $sql;
    }

    /**
     * See if a table exists
     *
     * @param string $table Name of table to check
     * @return bool True if table exists
     */
    function tableExists($table) {
        $database = $this->db->driver->database;
        $sql = "show tables";
        $query = $this->db->query($sql, true);

        $col = "Tables_in_$database";

        while ($result = $query->fetchRow()) {
            if ($result[$col] == $table) {
                return true;
            }
        }

        return false;
    }

    function alterTable() {

        $reader = new Octopus_DB_Schema_Reader($this->tableName);

        $sql = array();

        $existingFields = $reader->getFields();
        $fields = $this->fields;

        foreach ($existingFields as $existingField => $data) {

            if (isset($fields[ $existingField ])) {
                if (!$this->fieldsMatch($fields[ $existingField ], $data)) {
                    $sql[] = $this->alterChangeField($fields[ $existingField ], $data);
                }
            } else {
                $sql[] = $this->alterDropField($data);
            }

            unset($fields[ $existingField ]);
        }

        foreach ($fields as $field) {
            $sql[] = $this->alterAddField($field);
        }

        #$sql = array_merge($sql, $this->indexes);

        if (count($sql)) {

            $output = sprintf('ALTER TABLE `%s`%s', $this->tableName, "\n");
            $output .= implode(",\n", $sql);
            $output .= "\n";

            return $output;
        }
    }

}

?>
