<?php

    class SG_Model {
        
        // $fields, $hasOne, $belongsTo, $hasMany all get parsed into these
        static $table = null;
        static $primaryKey = null;
        static $_titleField = null;
        static $_fields = null;
        
        var $_id = null;
        var $_data = null;
        var $_relations = null;
        var $_errors = null;
        
        function __construct($arg = null) {
            
            if (is_numeric($arg)) {
                $this->_id = $arg;
            }
            
        }
        
        function __get($name) {
            
            $this->load();
            
            $rel = $this->getRelation($name);
            if ($rel) {
                return $rel->resolve($this);
            }
            
            return $this->load()->_data[$name];
        }
        
        function __set($name, $value) {
            $field = $this->getField($name);
            $field->set($this, $value);
        }
        
        function __unset($name) {
            $field = $this->getField($name);
            $field->unset($this);
        }
        
        function __toString() {
            
            
            
        }
        
        function getTitleField() {
            
            if (static::$_titleField) {
                return static::$_titleField;
            }
            
        }
        
        /**
         * Loads data for this instance from the db.
         * @return Object The model instance, for method chaining.
         */
        function load() {
            
            if ($this->_data !== null) {
                return $this;
            }
            
            if (!$this->_id) {
                $this->_data = array();
                return $this;
            }
            
            $s = new SG_DB_Select();
            $s->table($this->getTable());
            $s->where($this->getPrimaryKey() . ' = ?', $this->_id);
            
            $row = $s->fetchRow();
            $this->_data = $row ? $row : null;
            
            // If no row found, return false?
            
            return $this;
        }
        
        /** 
         * @return Array Set of fields defined for this model. 
         */
        function getFields() {
            
            if (isset(static::$_fieldsCompiled)) {
                return static::$_fields;
            }
            static::$_fieldsCompiled = true;
            
            if (isset(static::$fields)) {
            
                $fields = array();
            
                foreach(static::$fields as $fieldDef) {
                    $field = SG_Model_Field::create($fieldDef);
                    if ($field) $fields[$field->name] = $field;
                }
                static::$_fields = $fields;
                
            }
            
            $relations = array();
            
            if (isset(static::$hasOne)) {
                
                foreach(static::$hasOne as $fieldDef) {
                    $field = new SG_Model_Field_OneToMany(
                }
                
            }
        }
        
        /**
         * @return string Table used by this model.
         */
        function getTable() {
            
            if (isset(static::$table)) {
                return static::$table;
            } else {
                return static::$table = underscore(pluralize(get_class($this)));
            }
            
        }
        
        function getPrimaryKey() {
            
            if (isset(static::$_primaryKey)) {
                return static::$_primaryKey;
            }
            
            foreach($this->getFields() as $f) {
                if ($f->isPrimary()) {
                    return static::$_primaryKey = $f;
                }
            }
            
            $default = underscore(get_class($this)) . '_id';
            static::$_primaryKey = static::getField($default);
            
            return static::$_primaryKey;
        }
        
        function validate() {
            
            $this->load();
            
            $this->_errors = array();
            $this->beforeValidate($this->_errors);
            
            
            foreach($this->getFields() as $f) {
                
                $result = $f->validate($this);
                if ($result === true) {
                    continue;
                }
                
                $errors[$f->name] = $result;
            }
            
            $this->afterValidate($this->_errors);
            
            return empty($this->_errors);
        }
        
        function getErrors() {
            $this->validate();
            return empty($this->_errors) ? false : $this->_errors;
        }
        
        function save() {
            
            $this->load();
            
            if (!$this->validate()) {
                return false;
            }
            
            if (!$this->beforeSave()) {
                return false;
            }
            
            if ($this->_id) {
                $u = new SG_DB_Update();
                $u->where($this->getPrimaryKey() . ' = ?', $this->_id);
            } else {
                $u = new SG_DB_Insert();
            }
            
            $u->table($this->getTable());
            
            foreach($this->getFields() as $f) {
                $f->save($this, $u);
            }
            
            $u->execute();
            
            if (!$this->_id) {
                $this->_id = $u->getId();
            }
            
            foreach($this->getFields() as $f) {
                $f->afterSave($this);
            }
            
            $this->afterSave();
            return $this->_id;
        }
        
        /**
         * Returns an array containing this object's data, sanitized for display.
         */
        function &sanitize() {
            
            $result = array();
            
            foreach($this->getFields() as $field) {
                $result[$field->name] = $field->sanitize($this);
            }
            
            return $result;
        }
        
        
        protected function setTable($table) {
            $this->_table = $table;
        }
        
        
    }

?>
