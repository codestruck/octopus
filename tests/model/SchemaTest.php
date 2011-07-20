<?php

class Schemab extends Octopus_Model {
    protected $fields = array(
        'title',
        'display_order' => array(
            'type' => 'numeric',
        ),
        'cost' => array(
            'type' => 'numeric',
            'decimal_places' => 2,
        ),
        'lowcost' => array(
            'type' => 'numeric',
            'decimal_places' => 2,
            'precision' => 4,
        ),
    );
}

/**
 * @group Model
 */
class ModelSchemaTest extends PHPUnit_Framework_TestCase
{
    function testDefaultText() {
        Octopus_DB_Schema_Model::makeTable('Schemab');

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('varchar', $fields['title']['type']);
        $this->assertEquals('250', $fields['title']['size']);
    }

    function testNumeric() {
        Octopus_DB_Schema_Model::makeTable('Schemab');

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('bigint', $fields['display_order']['type']);
        $this->assertEquals('20', $fields['display_order']['size']);
    }

    function testDecimalDefaultPrecision() {
        Octopus_DB_Schema_Model::makeTable('Schemab');

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('decimal', $fields['cost']['type']);
        $this->assertEquals('60,2', $fields['cost']['size']);
    }

    function testDecimalPrecision() {
        Octopus_DB_Schema_Model::makeTable('Schemab');

        $r = new Octopus_DB_Schema_Reader('schemabs');
        $fields = $r->getFields();

        $this->assertEquals('decimal', $fields['lowcost']['type']);
        $this->assertEquals('4,2', $fields['lowcost']['size']);
    }

}