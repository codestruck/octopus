<?php

define('DB_hostname', 'localhost');
define('DB_database', 'octopus_test');
define('DB_username', 'octopus');
define('DB_password', 'Cyn1Wruch');

require_once('includes/core.php');

bootstrap();

$table = 'rams';
$csv = "/Users/estesm/Desktop/big.csv";


$pdo = new PDO(sprintf('mysql:host=%s;dbname=%s', DB_hostname, DB_database), DB_username, DB_password);

$sql = "DROP TABLE IF EXISTS $table";
$pdo->prepare($sql)->execute();

$sql = "CREATE TABLE $table (
    ram_id INT(10) NOT NULL AUTO_INCREMENT,
    name varchar(250) NOT NULL,
    description text NOT NULL,
    PRIMARY KEY (ram_id),
    INDEX (name)
)";
$pdo->prepare($sql)->execute();

class Ram extends Octopus_Model {
    protected $fields = array(
        'name',
        'description' => array('type' => 'html'),
    );
}

function debug($str) {
    // print $str;
}

// raw pdo
function addRow1($data) {
    global $pdo;

    $sql = "SELECT * FROM rams WHERE `rams`.`name` = ?";
    $query = $pdo->prepare($sql);
    $query->execute(array($data[1]));
    if ($query->fetch(PDO::FETCH_ASSOC)) {
        debug('-');
    } else {
        debug('.');
    }

    $sql = "INSERT INTO rams SET `name` = ?, description = ?";
    $pdo->prepare($sql)->execute(array($data[1], $data[8]));

}

$pdoStmt = null;

// PDO reused prepared statement
function addRow2($data) {
    global $pdo, $pdoStmt;

    if (!$pdoStmt) {
        $sql = "SELECT * FROM rams WHERE `rams`.`name` = ?";
        $pdoStmt = $pdo->prepare($sql);
    }

    $pdoStmt->execute(array($data[1]));
    if ($pdoStmt->fetch(PDO::FETCH_ASSOC)) {
        debug('-');
    } else {
        debug('.');
    }

    $sql = "INSERT INTO rams SET `name` = ?, description = ?";
    $pdo->prepare($sql)->execute(array($data[1], $data[8]));

}

// DB
function addRow3($data) {

    $sql = "SELECT * FROM rams WHERE `rams`.`name` = {$data[1]}";
    $db = Octopus_DB::singleton();
    $query = $db->query($sql, true);
    if ($query->fetchRow()) {
        debug('-');
    } else {
        debug('.');
    }

    $sql = "INSERT INTO rams SET `name` = {$data[1]}, description = {$data[8]}";
    $db->query($sql, true);

}

// DB_Select
function addRow4($data) {

    $s = new Octopus_DB_Select();
    $s->table('rams');
    $s->where('name = ?', $data[1]);
    $query = $s->query();
    if ($query->fetchRow()) {
        debug('-');
    } else {
        debug('.');
    }

    $i = new Octopus_DB_Insert();
    $i->table('rams');
    $i->set('name', $data[1]);
    $i->set('description', $data[8]);
    $i->execute();
}

// Model
function addRow5($data) {

    $row = Ram::get(array('name' => $data[1]));
    if ($row) {
        debug('-');
    } else {
        debug('.');
    }

    $ram = new Ram();
    $ram->name = $data[1];
    $ram->description = $data[8];
    $ram->save();
}

$fnc = 'addRow1';

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
