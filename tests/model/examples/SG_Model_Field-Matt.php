<?php

    // subclasses are used for one-to-many, many-to-many
    class SG_Model_Field {
    
        static $defaults = array(
        );
    
        function __construct($options) {
        }
        
        function create($options) {
        }
        
        function validate($model) {
        }
        
        function save($model, $u) {
        }
    
        function afterSave($model) {
        }
        
        function get($model) {
            return $model->load()->_data[$this->name];
        }
        
        function set($model, $value) {
            $model->load()->_data[$this->name] = $value;
        }
        
        function addToForm($form) {
            
        }
        
        
    }

?>
