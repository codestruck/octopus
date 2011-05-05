<?php

class Octopus_Model_Field_String extends Octopus_Model_Field {

    public function getDefaultSearchOperator() {
        return 'LIKE';
    }

    /**
     * @param $operator string Operator (=, LIKE, etc) to use. If null, the
     * field's default operator will be used.
     * @param $value Mixed value to restrict this field to.
     * @param $s Object Octopus_DB_Select being built, in case any joins are required.
     * Don't call where() or anything on this.
     * @param $params Array Set of parameters that will be passed to $s via
     * the where() method.
     * @return String A chunk of SQL for a WHERE clause.
     */
    public function restrict($operator, $value, &$selectStatement, &$params, $model) {

        if (!$operator) {

            if ($regex = parse_regex($value)) {
                // Do a regex lookup by default
                $operator = strpos($regex['flags'], 'i') === false ? 'REGEXP BINARY' : 'REGEXP';
                $value = to_mysql_regex($regex['pattern']);
            } else {
                $operator = $this->getDefaultSearchOperator();
            }
        }

        if (strtoupper($operator) == 'LIKE') {
            $value = str_replace('*', '%', $value);
            $value = str_replace('?', '_', $value);
        }

        return parent::restrict($operator, $value, $selectStatement, $params, $model);
    }

}

?>
