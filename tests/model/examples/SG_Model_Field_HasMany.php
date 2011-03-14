<?php

    /**
     * SG_Model_Field subclass used to represent a many-to-many relationship.
     */
    class SG_Model_Field_ManyToMany extends SG_Model_Field {
        
 
        function save($model) {
        }
        
        function get($model) {
            
            if (isset($model->__data[$this->name])) {
                return $model->__data[$this->name];
            }
            
        }
        
        function getTable() {
            
            $table = $this->getOption('table');
            if ($table) return $table;
            
            return $table->setOption('table', underscore($model) . '_' . underscore(pluralize($otherModel)));
        }
        
        /**
         * Actually saves the changes to the model.
         */
        function afterSave($model) {
            
            if (!isset($model->__data[$this->name]) {
                // No changes!
                return;
            }
            
            $items = $model->__data[$this->name];
            $table = $this->getTable();
            
            $d = new SG_DB_Delete();
            $d->table($table);
            $d->where(
            
        }
        
    }

?>
