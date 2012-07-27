<?php

abstract class Octopus_Model_Field {

    protected $field;
    protected $modelClass;
    protected $options;
    protected $defaultOptions = array();
    private $migrateMethod = null;

    /**
     * For fields w/ certain conventional names, some default options.
     */
    private static $magicFieldDefaults = array(

        'created' => array( 'type' => 'datetime'),
        'updated' => array( 'type' => 'datetime'),
        'active' => array( 'type' => 'boolean'),
        'order' => array( 'type' => 'order'),
        'slug' => array('type' => 'slug'),
    );

    /**
     * Because I always forget what field types are, provide a few aliases.
     */
    private static $fieldTypeAliases = array(
        'text' => 'string',
        'bool' => 'boolean',
        'number' => 'numeric',
        // 'file' => 'virtual',
    );

    private static $helperFieldTypes = array(
        'money' => array('type' => 'numeric', 'decimal_places' => 2),
        'currency' => array('type' => 'numeric', 'decimal_places' => 2),
        'file' => array('type' => 'virtual', 'form_type' => 'file'),
    );

    /**
     * @param $field Field name
     * @param $modelClass Class to which this field is being added
     * @param $options Array of options
     * @param $migrateMethod Method on Octopus_DB_Schema_Writer to use to create
     * a column for this field, if any.
     */
    public function __construct($field, $modelClass, $options, $migrateMethod = null) {
        $this->field = $field;
        $this->modelClass = $modelClass;
        $this->options = $options;
        $this->migrateMethod = $migrateMethod;
    }

    /**
     * Hook called after $model is deleted.
     */
    public function afterDelete(Octopus_Model $model) {
    }

    /**
     * @return String The model class this field is defined on.
     */
    public function getModelClass() {
        return $this->modelClass;
    }

    public static function getField($name, $modelClass, $options) {

        if (isset(self::$magicFieldDefaults[$name])) {
            $options = array_merge(self::$magicFieldDefaults[$name], $options);
        }

        $type = isset($options['type']) ? $options['type'] : 'string';
        $originalType = $type;

        if (isset(self::$fieldTypeAliases[$type])) {
            $type = self::$fieldTypeAliases[$type];
        }

        if (isset(self::$helperFieldTypes[$type])) {
            $help = self::$helperFieldTypes[$type];
            if (isset($help['type'])) $type = $help['type'];
            $options = array_merge($help, $options);
        }

        $class = 'Octopus_Model_Field_' . camel_case($type, true);
        $options['type'] = $type;
        $options['original_type'] = $originalType;

        return new $class($name, $modelClass, $options);
    }

    public function accessValue($model, $saving = false) {

        $value = $model->getInternalValue($this->getFieldName());

        if ($saving) {

            $primaryKey = $model->getPrimaryKey();
            if (!$model->exists()) {
                $value = $this->handleTrigger('onCreate', $model, $value);
            } else {
                $value = $this->handleTrigger('onUpdate', $model, $value);
            }

            if (!$value) {
                $value = $this->handleTrigger('onEmpty', $model, $value);
            }

            $value = $this->handleTrigger('onSave', $model, $value);

        } else if ($model->escaped) {
            $value = $this->escape($model, $value);
        }

        return $value;
    }

    /**
     * Called when $model tries to load data from the DB, but the record is
     * not where it expected it to be.
     */
    public function recordDisappeared(Octopus_Model $model) {

    }

    public function save($model, $sqlQuery) {
        $sqlQuery->set($this->getFieldName(), $this->accessValue($model, true));
    }

    public function afterSave($model) {
        $this->handleTrigger('afterSave', $model);
    }

    public function setValue($model, $value) {
        $model->setInternalValue($this->getFieldName(), $value);
    }

    /**
     * Loads data into this field from a DB row. For certain field types,
     * setValue($model, $row[$field->getFieldname()]) will not necessarily work.
     * (e.g. HasOne)
     */
    public function loadValue(Octopus_Model $model, $row) {
        $name = $this->getFieldName();
        if (isset($row[$name])) {
            $this->setValue($model, $row[$name]);
        }
    }

    public function getFieldName() {
        return $this->field;
    }

    public function getOption($option, $default = null) {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        } else if (isset($this->defaultOptions[$option])) {
            return $this->defaultOptions[$option];
        } else {
            return $default;
        }
    }

    /**
     * Called before any migrations have been performed.
     */
    public function beforeMigrate($schema) {
    }

    /**
     * Creates DB resources required by this field.
     * @param $schema Octopus_DB_Schema
     * @param $table Main table being built.
     * @param $name If provided, this should be used as the column name for
     * this field. This functionality is used by Octopus_Model_Field_HasOne.
     * @param $autoIncrement If false, disables auto incrementing on the
     * generated field. If null, defaults to the field's current auto
     * incrementing option.
     */
    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {

    	if (!$this->migrateMethod) {
    		return;
    	}

    	if (!$name) $name = $this->getFieldName();
    	$table->{$this->migrateMethod}($name);

    }

    /**
     * Called after migrations have been performed and
     */
    public function afterMigrate($schema) {
    }

    public function migrateIndexes($schema, $table) {
        $index = $this->getOption('index', false);
        if ($index === 'unique') {
            $table->newIndex('UNIQUE', $this->getFieldName());
        } else if ($index === 'fulltext') {
            $table->newIndex('FULLTEXT', $this->getFieldName());
        } else if ($index == 'index' || $index === true) {
            $table->newIndex($this->getFieldName());
        }
    }

    protected function escape($model, $value) {

        $func = $this->getOption('escape', true);

        if ($func === true) {

            $allow_tags = $this->getOption('allow_tags', false);
            if ($allow_tags !== false) {

                Octopus::loadExternal('htmlpurifier');

                $purifier = get_html_purifier(array(
                    'HTML.Allowed' => $allow_tags,
                ));
                return $purifier->purify($value);

            } else {
                return h($value);
            }

        } else if ($func === false) {
            return $value;
        } else if (method_exists($model, $func)) {
            return $model->$func($model, $value);
        } else if (is_callable($func)) {
            return call_user_func($func, $model, $value);
        }
    }

    protected function handleTrigger($type, $model, $default = null) {

        $fnc = $this->getOption($type);

        if (is_callable($fnc)) {
            $newValue = call_user_func($fnc, $model, $this);
            $model->setInternalValue($this->getFieldName(), $newValue);
            return $newValue;
        }

        // check for standalone function
        if (is_string($fnc)) {

            if ($fnc && function_exists($fnc)) {
                $newValue = $fnc($model, $this);
                $model->setInternalValue($this->getFieldName(), $newValue);
                return $newValue;
            }

            // check for custom function on the model subclass
            if ($fnc && method_exists($model, $fnc)) {
                $newValue = $model->$fnc($model, $this);
                $model->setInternalValue($this->getFieldName(), $newValue);
                return $newValue;
            }

            // check for default function on the field subclass
            if ($fnc && method_exists($this, $fnc)) {
                $newValue = $this->$fnc($model, $this);
                $model->setInternalValue($this->getFieldName(), $newValue);
                return $newValue;
            }

        }

        return $default;

    }

    /**
     * @param $expression string For relations, the subexpression being
     * used for filtering. For example, for a field named 'category', for the
     * expression 'category.name', 'name' would be passed as the subexpression
     * to the 'category' field.
     * @param $operator string Operator (=, LIKE, etc) to use. If null, the
     * field's default operator will be used.
     * @param $value Mixed value to restrict this field to.
     * @param $s Object Octopus_DB_Select being built, in case any joins are required.
     * Don't call where() or anything on this.
     * @param $params Array Set of parameters that will be passed to $s via
     * the where() method.
     * @return String A chunk of SQL for a WHERE clause.
     */
    public function restrict($expression, $operator, $value, &$s, &$params, $model) {
        $sql = self::defaultRestrict($this->getFieldName(), $operator, $this->getDefaultSearchOperator(), $value, $s, $params, $model);
        return $sql;
    }

    /**
     * A general-purpose implementation of restrict() that can be reused
     * in a static context. This is to support restricting by IDs, which don't
     * have associated Octopus_Model_Field instances.
     * @param $fieldName Mixed The field being restricted OR an array whose
     * first element is the table and second element is the field in that table
     * being restricted.
     */
    public static function defaultRestrict($fieldName, $operator, $defaultOperator, $value, &$s, &$params, $model) {

        if (is_array($fieldName)) {
            $table = array_shift($fieldName);
            $fieldName = array_shift($fieldName);
        } else {
            $table = $model->getTableName();
        }

        if (is_array($fieldName)) {

        	if (empty($fieldName)) {
        		return '';
        	}

        	if (!is_array($value)) {
        		throw new Octopus_Model_Exception("For arrays of field names, values must be an array as well.");
        	}

        	// Many-to-many uses this for restriction
        	$result = array();
        	foreach($fieldName as $field) {
        		$fieldValue = array_shift($value);
        		$result[] = self::defaultRestrict(
        			array($table, $field),
        			$operator,
        			$defaultOperator,
        			$fieldValue,
        			$s,
        			$params,
        			$model
        		);
        	}

        	return '(' . implode(') AND (', $result) . ')';
        }


        if (!$operator) {
            $operator = is_array($value) ? 'IN' : $defaultOperator;
        }

        $operator = strtoupper(trim($operator));
        if (!$operator) $operator = '=';
        $opLen = strlen($operator);

        // Handle stuff like 'NOT', 'NOT LIKE', 'NOT =', 'NOT IN' etc.
        $not = false;
        if (($opLen == 3 && strcmp($operator, 'NOT') == 0) || ($opLen > 3 && strncmp($operator, 'NOT ', 4) == 0)) {

            $not = true;
            $operator = trim(substr($operator,3));
            if ($operator == '') $operator = $defaultOperator;

        }

        // TODO: if $value is a resultset, do a subquery

        // If no operator, and we get an array, then process it as an IN
        if (strcmp($operator, 'IN') === 0) {

            $value = is_array($value) ? $value : array($value);
            $expr = '';

            if (empty($value)) {

                // IN empty array = false
                $expr = '0';

            } else {

                foreach($value as $item) {
                    $params[] = $item;
                    $expr .= ($expr == '' ? '' : ',') . '?';
                }

                $expr = "`$table`.`$fieldName` IN ($expr)";
            }

        } else {

	        if (($operator === '=' || $operator === '!=') && $value === null) {
	        	// Do is null check
	        	$n = ($operator === '!=' ? 'NOT ' : '');
				$expr = "`$table`.`$fieldName` IS {$n}NULL";
	        } else {
	        	$params[] = $value;
	        	$expr = "`$table`.`$fieldName` $operator ?";
	        }

        }

        if ($not) {
            $expr = "NOT ($expr)";
        }

        return $expr;
    }

    /**
     * Applies ordering to an Octopus_DB_Select being constructed for an
     * Octopus_Model_ResultSet.
     * @param $resultSet Object The result set being built.
     * @param $s Object Octopus_DB_Select being built.
     * @param $dir string Direction to order this field by.
     */
    public function orderBy($expression, $dir, $s, &$params, $model) {
        self::defaultOrderBy($this->getFieldName(), $dir, $s, $params, $model);
    }

    /**
     * Helper to support ordering by ids, which don't have associated fields.
     */
    public static function defaultOrderBy($fieldName, $dir, $s, &$params, $model) {
        $s->orderBy("`{$model->getTableName()}`.`{$fieldName}` $dir");
    }

    /**
     * @return String The default operator (e.g. '=' or 'LIKE') this field
     * should use when none is specified.
     */
    public function getDefaultSearchOperator() {
        return '=';
    }

    /**
     * Adds controls for this field to a form.
     */
    public function addToForm(Octopus_Html_Form $form) {

        if (!$this->shouldAddToForm()) {
            return;
        }

        $field = $form->add('text', $this->getFieldName());

        if ($this->getOption('required')) {
            $field->required();
        }

        if ($this->getOption('unique')) {
            $field->mustPass(array($this, 'validateUniqueness'));
        }

    }

    /**
     * Adds a column for this field to a table.
     */
    public function addToTable(Octopus_Html_Table $table) {

        if (!$this->shouldAddToTable()) {
            return;
        }

        $table->addColumn($this->getFieldName());
    }

    /**
     * Form validation callback used to verify that the contents of this field
     * are unique.
     */
    public function validateUniqueness($value, $data, $formField) {

        $modelClass = $this->modelClass;
        $idName = to_id($modelClass);
        $id = isset($data[$idName]) ? $data[$idName] : null;

        $value = trim($value);
        if (!$value) return true;

        $criteria = array(
            $this->getFieldName() => $value
        );

        if ($id !== null) $criteria['id !='] = $id;

        $dummy = new $modelClass();
        $existing = $dummy->_get($criteria);

        if ($existing) return humanize($this->getFieldName()) . ' must be unique.';

        return true;
    }

    public function restrictFreetext($model, $text) {
        return new Octopus_Model_Restriction_Field($model, $this->getFieldname() . ' LIKE', wildcardify($text));
    }

    protected function shouldAddToForm() {

        $defaultValue = !in_array(
            $this->getFieldName(),
            array(
                'created',
                'updated',
                'password',
            )
        );

        return !!$this->getOption('form', $defaultValue);
    }

    protected function shouldAddToTable() {

        $defaultValue = !in_array(
            $this->getFieldName(),
            array(
                'password',
            )
        );

        return !!$this->getOption('table', $defaultValue);
    }

}

