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
    private static $database = null;
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
        if ($this->conn === null) {

            if (self::$pdo == null) {
                $pdo_driver = new Octopus_DB_Driver_Pdo();
                $pdo_driver->connect();
                self::$pdo = $pdo_driver->handle;
                self::$database = $pdo_driver->database;
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, self::$database);
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
