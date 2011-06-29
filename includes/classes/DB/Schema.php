<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_DB_Schema_Writer');

class Octopus_DB_Schema {

    private $db;

    function Octopus_DB_Schema($db = null) {
        $this->db = $db ? $db : Octopus_DB::singleton();
    }

    /**
     * See if a table exists
     *
     * @param string $table Name of table to check
     * @return bool True if table exists
     */
    public function checkTable($table) {
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

    /**
     * @return Octopus_DB_Schema_Writer
     * @param $tableName string db table name to create or update
     */
    function newTable($tableName) {
        return new Octopus_DB_Schema_Writer($tableName);
    }

    function removeTable($tableName) {

        if ($this->checkTable($tableName)) {
            $sql = "DROP TABLE `$tableName`";
            $this->db->query($sql, true);
        }

    }

    function renameTable($old, $new) {
        $sql = sprintf('RENAME TABLE `%s` TO `%s`', $old, $new);
        $this->db->query($sql, true);
    }

}

?>
