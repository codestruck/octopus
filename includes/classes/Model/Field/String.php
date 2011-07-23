<?php

class Octopus_Model_Field_String extends Octopus_Model_Field {

    public function migrate($schema, $table) {
        // TODO: What about large string fields?
        $table->newTextSmall($this->getFieldName());
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

        $op = strtoupper($operator);
        if ($op === 'LIKE' || $op === 'NOT LIKE') {
            $value = wildcardify($value);
        }

        return parent::restrict($expression, $operator, $value, $selectStatement, $params, $model);
    }

}

?>
