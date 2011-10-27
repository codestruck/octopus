<?php

define('DB_hostname', 'localhost');
define('DB_database', 'octopus_test');
define('DB_username', 'octopus');
define('DB_password', 'Cyn1Wruch');

require_once('includes/core.php');

bootstrap();

$DO_LOOKUPS = true;

$table = 'rams';
$csv = "/Users/estesm/Desktop/big.csv";
$csv = "/Users/estesm/Desktop/CSV/Electronics_Product.csv";

class Foo extends Octopus_Model {
    protected $fields = array(
        'title',
        'ram' => array(
            'type' => 'hasOne',
        ),
    );
}

class Ram extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'index' => true,
        ),
        'description' => array('type' => 'html'),
        'foo' => array(
            'type' => 'hasMany',
        ),
    );
}

Octopus_DB_Schema_Model::makeTable('Ram');
Octopus_DB_Schema_Model::makeTable('Foo');

$db =& Octopus_DB::singleton();
$db->query('TRUNCATE TABLE `rams`', true);


function debug($str) {
    // print $str;
}

// Model
function addRow($data) {
    global $DO_LOOKUPS;

    if ($DO_LOOKUPS) {

        $row = Ram::get(array('name' => $data[1]));
        if ($row) {
            debug('-');
        } else {
            debug('.');
        }

    }

    $foo = new Foo();
    $foo->title = $data[1];
    $foo->save();

    $ram = new Ram();
    $ram->name = $data[1];
    $ram->description = $data[8];
    $ram->save();

    $ram->addFoo($foo);

}

$fnc = 'addRow';

if (isset($argv[1])) {
    $fnc = 'addRow' . $argv[1];
}

$fp = fopen($csv, 'r');

$i = 0;
while ($line = fgetcsv($fp, 4196)) {
    $fnc($line);
    $i++;
}

print "\n";

print number_format(memory_get_usage()) . "\n";
print number_format(memory_get_peak_usage()) . "\n";
