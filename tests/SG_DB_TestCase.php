<?php

require_once('PHPUnit/Extensions/Database/TestCase.php');

/**
 * Abstract base for writing a testcase that uses DB data and the SG_DB_*
 * infrastructure.
 */
abstract class SG_DB_TestCase extends PHPUnit_Extensions_Database_TestCase {
    private static $pdo = null;
    private $conn = null;
    private $_xmlFile;

    /**
     * @param $xmlFile string XML file to use to load data. Should reside in the
     * fixtures directory.
     */
    public function __construct($xmlFile = null)
    {
        $this->_xmlFile = $xmlFile;

        $db =& SG_DB::singleton();
        $this->dropTables($db);
        $this->createTables($db);
    }

    public function __destruct()
    {
        // Sometimes it might be nice to be able to inspect the DB after a failed test?
        $this->dropTables(SG_DB::singleton());
    }

    /**
     * Override to actually create the tables required by the testcase.
     * @param $db Object SG_DB instance to use.
     */
    abstract protected function createTables(&$db);

    /**
     * Override to drop any tables you create in createTables()
     * @param $db Object SG_DB instance to use.
     */
    abstract protected function dropTables(&$db);

    protected function getConnection()
    {
        $db = SG_DB::singleton();

        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = $db->driver->handle;
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $db->driver->database);
        }

        return $this->conn;

    }

    /**
     *
     */
    public function getDataSet()
    {
        return $this->createFlatXMLDataSet(TEST_FIXTURE_DIR . $this->_xmlFile);
    }

}

?>
