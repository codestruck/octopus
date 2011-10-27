<?php

require_once( dirname(dirname(__FILE__)) . '/bootstrap.php' );

class Perf extends Octopus_Model {
    protected $fields = array(
        'title' => array(
            'required' => true,
        ),
        'slug' => array(
            'type' => 'slug', // implies hidden input
            //'onCreate' => 'to_unique_slug',
            //'onSave' => 'to_slug',
            //'onEmpty' => 'to_unique_slug',
            //'' => 'dealwith'
        ),
        'body' => array(
            'type' => 'html',
            'sanitize' => 'mce_cleanup',
        ),
        //'author' => array(
        //    'type' => 'has_one'
        //),
        'active' => array(
            'type' => 'boolean',
        ),
        'display_order' => array(
            'type' => 'order',
        ),
        'created',
        'updated',
    );
}

$sql = "CREATE TABLE perfs (
                `perf_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `slug` varchar ( 255 ) NOT NULL,
                `body` text NOT NULL,
                `author_id` INT( 10 ) NOT NULL,
                `active` TINYINT NOT NULL,
                `display_order` INT( 10 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

$db =& Octopus_DB::singleton();
$db->query($sql);

function getTime() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

$limit = 1000;

$db->query('TRUNCATE perfs');
$start = getTime();

for ($i = 0; $i < $limit; $i++) {
    $sql = "INSERT into perfs SET title = 'Foobar', created = NOW(), updated = NOW()";
    $db->query($sql);
}

$end = getTime();
$diff = $end - $start;
print "Octopus_DB Inserting $limit rows: $diff seconds\n";

$db->query('TRUNCATE perfs');
$start = getTime();

for ($i = 0; $i < $limit; $i++) {
    $p = new Perf();
    $p->title = 'Foobar';
    $p->save();
}

$end = getTime();
$insertMultiple = round(($end - $start) / $diff, 2);
$diff = $end - $start;
print "Octopus_Model Inserting $limit rows: $diff seconds\n";


$start = getTime();

for ($i = 1; $i < $limit; $i++) {
    $sql = "SELECT * FROM perfs WHERE perf_id = $i";
    $query = $db->query($sql);
    $result = $query->fetchRow();
    $foo = $result['title'];
}

$end = getTime();
$diff = $end - $start;
print "Octopus_DB Selecting $limit rows: $diff seconds\n";

$start = getTime();

for ($i = 1; $i < $limit; $i++) {
    $p = new Perf($i);
    $foo = $p->title;
}

$end = getTime();
$selectMultiple = round(($end - $start) / $diff, 2);
$diff = $end - $start;
print "Octopus_Model Selecting $limit rows: $diff seconds\n";

$start = getTime();

for ($i = 1; $i < $limit; $i++) {
    $p = new Perf($i);
}

$end = getTime();
$selectMultiple = round(($end - $start) / $diff, 2);
$diff = $end - $start;
print "Octopus_Model LAZY Selecting $limit rows: $diff seconds\n";





/*
print "\n\n$insertMultiple times slower inserting\n";
print "$selectMultiple times slower selecting\n";
*/

$start = getTime();

$all = Perf::all();
foreach ($all as $item) {
    $foo = $item->title;
}

$end = getTime();
$selectMultiple = round(($end - $start) / $diff, 2);
$diff = $end - $start;
print "Octopus_Model foreach $limit rows: $diff seconds\n";


$start = getTime();

$all = Perf::all();
foreach ($all as $item) {
}

$end = getTime();
$selectMultiple = round(($end - $start) / $diff, 2);
$diff = $end - $start;
print "Octopus_Model LAZY foreach $limit rows: $diff seconds\n";

$db->query('DROP TABLE IF EXISTS perfs');
