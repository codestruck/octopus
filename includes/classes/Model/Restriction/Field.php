<?php

/**
 * A restriction implementation that does simple =, <, >, LIKE, etc comparisons
 * on fields.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Restriction_Field implements Octopus_Model_Restriction {

    private $model, $expression, $value;

    public function __construct(Octopus_Model $model, $expression, $value) {
        $this->model = $model;
        $this->expression = $expression;
        $this->value = $value;
    }

    /**
     * @param $s Octopus_DB_Select being constructed.
     * @param $params Array of parameters to be used by $s.
     * @return String SQL for use in the WHERE clause being generated.
     */
    public function getSql(Octopus_DB_Select $s, Array &$params) {

        $model = $this->model;
        $expr = $this->expression;
        $value = $this->value;

        if ($value instanceof Octopus_Model_ResultSet) {

            // TODO: Do this using a subquery rather than IN ()
            $resultSet = $value;
            $value = array();

            foreach($resultSet as $item) {
                $value[] = $item->id;
            }

        } else if ($value instanceof Octopus_Model) {
            $value = $value->id;
        }

        // Parse out field name, etc.
        $info = self::parseFieldExpression($expr, $model);
        extract($info);

        if ($field == $model->getPrimaryKey()) {

            // IDs don't have associated fields, so we use the default
            // restriction logic.
            return Octopus_Model_Field::defaultRestrict($field, $operator, '=', $value, $s, $params, $model);

        }

        $f = $model->getField($field);

        if ($f) {
            return $f->restrict($subexpression, $operator, $value, $s, $params, $model);
        }

        $modelClass = get_class($model);
        throw new Octopus_Exception("Field not found on model $modelClass: " . $field);
    }

    /**
     * @return bool Whether or not $str looks like it could be a field
     * expression of the type parsed by parseFieldExpression
     * @see parseFieldExpression
     */
    public static function looksLikeFieldExpression($str) {
        return !!preg_match('/^\s*[a-z0-9_]+(\.[a-z0-9_]+)?(\s+NOT)?(\s*[!<>=]+|\s+LIKE|\s+IN)?\s*$/i', $str);
    }


    /**
     * Takes a field expression (e.g., a key from a criteria array) and returns
     * an array with things about it.
     * @example
     * For "age <", returns array('field' => 'age', 'operator' => '<', 'function => null)
     * @return Array field, subexpression, operator, and function
     */
    public static function parseFieldExpression($expr, Octopus_Model $model) {

        /* Formats a key can take:
         * 'field'
         * 'field [not] operator'
         * (TODO) 'function(field) [not] operator
         */

        $expr = str_replace('`', '', $expr);
        $expr = preg_replace('/\s+/', ' ', $expr);
        $expr = trim($expr);

        $field = $subexpression = $operator = $function = null;

        $spacePos = strpos($expr, ' ');

        if ($spacePos === false) {
            $field = $expr;
        } else {
            $field = substr($expr, 0, $spacePos);
            $operator = substr($expr, $spacePos + 1);
        }

        // Special-case ID
        if (strcasecmp($field, 'id') == 0) {
            $field = $model->getPrimaryKey();
        }

        $dotPos = strpos($field, '.');
        if ($dotPos !== false) {
            $subexpression = substr($field, $dotPos + 1);
            $field = substr($field, 0, $dotPos);
        }

        return compact('field', 'subexpression', 'operator', 'function');
    }

}

