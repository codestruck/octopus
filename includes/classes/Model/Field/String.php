<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_String extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options);
        $this->defaultOptions['form'] = 'true';
    }

    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {

        if (!$name) $name = $this->getFieldName();

        $length = $this->getLength();

        if ($length <= 255) {
            $table->newTextSmall($name, $length);
        } else {
            $table->newTextLarge($name);
        }

    }

    public function getLength() {
        $len = $this->getOption('length', null);
        if ($len !== null) return $len;
        return $this->getOption('size', 250);
    }

    public function restrict($expression, $operator, $value, &$selectStatement, &$params, $model) {

        if (!$operator) {

            if ($regex = parse_regex($value)) {
                // Do a regex lookup by default
                $operator = strpos($regex['flags'], 'i') === false ? 'REGEXP BINARY' : 'REGEXP';
                $value = to_mysql_regex($regex['pattern']);
            } else {
                $operator = $this->getDefaultSearchOperator();
            }
        }

        return parent::restrict($expression, $operator, $value, $selectStatement, $params, $model);
    }

}

