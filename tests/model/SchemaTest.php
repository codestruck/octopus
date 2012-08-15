<?php

/**
 * @group Model
 * @group schema
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class ModelSchemaTest extends PHPUnit_Framework_TestCase
{

    function testDefaultText() {

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('varchar', $fields['title']['type']);
        $this->assertEquals('250', $fields['title']['size']);
    }

    function testNumeric() {

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('bigint', $fields['display_order']['type']);
        $this->assertEquals('20', $fields['display_order']['size']);
    }

    function testDecimalDefaultPrecision() {

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('decimal', $fields['cost']['type']);
        $this->assertEquals('60,2', $fields['cost']['size']);
    }

    function testDecimalPrecision() {

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('decimal', $fields['lowcost']['type']);
        $this->assertEquals('4,2', $fields['lowcost']['size']);
    }

    function testIndexProperty() {

        $this->markTestSkipped();

        $r = new Octopus_DB_Schema_Reader('schemacs');
        $fields = $r->getFields();

        $this->assertEquals('INDEX', $fields['title']['index']);
        $this->assertEquals('INDEX', $fields['display_order']['index']);
        $this->assertEquals('INDEX', $fields['one']['index']);

        $indexes = $r->getIndexes();
        $this->assertEquals(5, count($indexes));

    }

    function testIndexAttributes() {

        $r = new Octopus_DB_Schema_Reader('schemads');
        $fields = $r->getFields();

        $indexes = $r->getIndexes();

        $this->assertEquals('UNIQUE', $fields['title']['index']);
        $this->assertEquals('INDEX', $fields['display_order']['index']);
    }

}
