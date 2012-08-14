<?php

db_error_reporting(DB_PRINT_ERRORS);

/**
 * @group Model
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HierarchyTest extends Octopus_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/hierarchy-data.xml');
    }

    function testDeepChainFind()
    {
        $containers = Container::find(array('user' => 1));
        $things = Thing::find(array('container' => $containers));
        $this->assertEquals(2, count($things));

    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class User extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true
        ),
        'group' => array(
            'type' => 'hasMany',
        ),
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Container extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true,
        ),
        'user' => array(
            'type' => 'hasOne',
        ),
        'thing' => array(
            'type' => 'hasMany',
        ),
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Thing extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true,
        ),
        'container' => array(
            'type' => 'hasOne',
        ),
    );
}
