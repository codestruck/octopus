<?php

    // subclasses are used for one-to-many, many-to-many
    class SG_Model_Field {
    
        static $defaults = array(
            
            'required' => false,
            'unique' => false
            
        );
    
        static $_registry = array(
        );
        
        var $_options;
        
        function __construct($options) {
            $this->_options = $options ? $options : array();
        }
        
        /**
         * Static factory method for creating new SG_Model_Field instances.
         * @return Object An SG_Model_Field instance.
         */
        function create($type, $options) {
            $class = isset(self::$_registry[$type]) ? self::$_registry[$type] : 'SG_Model_Field';
            $field = new $class($options);
            return $field;
        }
        
        /**
         * Register a custom field type.
         * @param $name string Name of the field type.
         * @param $class string Class used by the field. Should subclass SG_Model_Field.
         */
        function register($name, $class) {
            self::$_registry[$name] = $class;
        }
        
        /**
         * Unregisters a custom field type.
         * @param $name string Type to unregister.
         */
        function unregister($name) {
            unset(self::$_registry[$name]);
        }
        
        /**
         * Returns the value of an option on this field. First checks the 
         * options passed to the constructor, then the $defaults for this class,
         * and finally returns $default if the option is not found.
         *
         * @param $name string Name of the option to return.
         * @param $default mixed Value to return if the option isn't set anywhere. 
         */
        function getOption($name, $default = null) {
            
            if (isset($this->_options[$name])) {
                return $this->_options[$name];
            } else if ($default !== null) {
                return $default;
            } else if (isset(static::$defaults[$name])) {
                return static::$defaults[$name];
            } else {
                return null;
            }
            
        }
        
        /**
         * Sets an option for this field.
         * @param $name string Name of the option to set.
         * @param $name string Value to set.
         * @return $value
         */
        function setOption($name, $value) {
            $this->_options[$name] = $value;
            return $value;
        }
        
        function validate($model) {
            
            if ($this->getOption('required')) {
                
                if (!$this->isPresentOn($model)) {
                    return array('success' => false, '{Field} is required.');
                }
                
            }
            
            if ($this->getOption('unique')) {
                // check for unique status
            }
            
            
        }
        
        function save($model, $u) {
        }
    
        function afterSave($model) {
        }
        
        function isPrimary() {
            
        }
        
        function get($model) {
            return $model->load()->_data[$this->name];
        }
        
        function set($model, $value) {
            $model->load()->_data[$this->name] = $value;
        }
        
        function addToForm($form) {
            
        }
        
        function handleFormPost($model, $data) {
        }

        /**
         * Returns the column this field should store its data in.
         * @return string The column name.
         */
        function getColumn() {
            
            $col = $this->getOption('column');
            if ($col) return $col;
            
            return $this->setOption('column', $this->name . '_id');
        }

        
    }

    SG_Model_Field::register('has_one', 'SG_Model_HasOne');
    SG_Model_Field::register('has_many', 'SG_Model_HasMany');
    
?>
