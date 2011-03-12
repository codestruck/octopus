<?php

    /**
     * SG_Model_Field implementation that handles one-to-many relationships.
     */
    class SG_Model_Field_HasOne extends SG_Model_Field {

        /**
         * Creates a new instance of the associated model class.
         */
        function createModel($id) {
            
            $class = $this->getOption('class');
            if (!$class) {
                // Infer class from field name
                $class = whatever_infer_class_or_something();
            }
            
            return new $class($id);
            
        }
        
        /**
         * For a given SG_Model instance, returns the value of this field as
         * another model instance.
         */
        function resolve($model) {
            
            // maybe need to check in model's __get() / __set() for double-underscore as a cue to act normally?
            if (!isset($model->__hasOne)) {
                $model->__hasOne = array();
            }
            
            $col = $this->getColumn();
            
            if (isset($model->__hasOne[$col])) {
                return $model->__hasOne[$col];
            }
            
            if (!isset($model->__data[$col])) {
                return null;
            }
            
            $id = $model->__data[$col];
            return $model->__hasOne[$col] = $this->createModel($id);
            
        }
        

        /**
         * 
         */
        function save($model, $u) {
            
            // One-to-Many = id stored in single column, saved along with everything else.
            
            $otherModel = $this->resolve($model);
            $u->set($this->name, $otherModel ? $otherModel->id : null);
        }
        
    }    


?>
