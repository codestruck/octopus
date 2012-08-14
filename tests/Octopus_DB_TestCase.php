<?php

require_once('PHPUnit/Framework/SelfDescribing.php');
require_once('PHPUnit/Framework/Test.php');
require_once('PHPUnit/Framework/Assert.php');
require_once('PHPUnit/Framework/TestCase.php');
require_once('PHPUnit/Extensions/Database/TestCase.php');

/**
 * Abstract base for writing a testcase that uses DB data and the Octopus_DB_*
 * infrastructure.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
abstract class Octopus_DB_TestCase extends PHPUnit_Extensions_Database_TestCase {
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
    }

    protected function getConnection()
    {
        $db = Octopus_DB::singleton();

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
        return $this->createFlatXMLDataSet(dirname(__FILE__) . '/fixtures/' . $this->_xmlFile);
    }

}

?>
