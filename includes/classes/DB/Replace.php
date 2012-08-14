<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Replace {

    function replace($from, $to) {

        $fields = $this->getFields();
        $schema = new Octopus_DB_Schema();
        $db = Octopus_DB::singleton();

        foreach ($fields as $field) {

            if (!$schema->checkTable($field[0])) {
                continue;
            }

            $reader = new Octopus_DB_Schema_Reader($field[0]);
            $dbFields = $reader->getFields();

            if (!in_array($field[1], array_keys($dbFields))) {
                continue;
            }

            $sql = "UPDATE {$field[0]} SET {$field[1]} = REPLACE({$field[1]}, '$from', '$to')";
            $db->query($sql, true);

        }

    }

    function getFields() {

        $fields = array();
        $fields[] = array('pages', 'content');
        $fields[] = array('pages', 'summary');
        $fields[] = array('forms', 'success');
        $fields[] = array('forms', 'introduction');
        $fields[] = array('forms', 'confirmation');
        $fields[] = array('news_category', 'description');
        $fields[] = array('news', 'description');
        $fields[] = array('news', 'content');
        $fields[] = array('news', 'foo');
        $fields[] = array('foo', 'bar');

        return $fields;

    }

}

