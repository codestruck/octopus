<?php

class Octopus_DB_Schema {

    private $db;

    public function __construct($db = null) {
        $this->db = $db ? $db : Octopus_DB::singleton();
    }

    /**
     * See if a table exists
     *
     * @param string $table Name of table to check
     * @return bool True if table exists
     */
    public function checkTable($table) {

        foreach($this->getTableNames() as $t) {
            if (strcasecmp($table, $t) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Array Table names in this database.
     */
    public function getTableNames() {

        $database = $this->db->driver->database;
        $sql = "show tables";
        $query = $this->db->query($sql, true);

        $col = "Tables_in_$database";

        $result = array();

        while ($row = $query->fetchRow()) {
            $result[] = $row[$col];
        }

        return $result;
    }

    /**
     * @return Octopus_DB_Schema_Writer
     * @param $tableName string db table name to create or update
     */
    public function newTable($tableName, $engine = '') {
        return new Octopus_DB_Schema_Writer($tableName, $this->db, $engine);
    }

    public function removeTable($tableName) {

        if ($this->checkTable($tableName)) {
            $sql = "DROP TABLE `$tableName`";
            $this->db->query($sql, true);
        }

    }

    public function renameTable($old, $new) {
        $sql = sprintf('RENAME TABLE `%s` TO `%s`', $old, $new);
        $this->db->query($sql, true);
    }

}

?>
