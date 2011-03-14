<?php

    
    class Post extends SG_Model {
        
        // Implicit, but configurable
        // static $table = 'posts';
        
        static $fields = array(
            
            // Implicit
            // 'post_id' => array(
            //    'type' => 'int',
            //    'primary' => true,
            //    'auto_increment' => true
            // ),
            
            'created', 'updated', // implied datetime type, 
            
            'title' => array(
                'type' => 'string',
                'length' => 250,
                'required' => true
            ),
            
            'slug' => array(
                'type' => 'slug',
                'required' => true,
                'unique' => true,
                'default' =>  '{title}',  
                // Function / array of functions to run input through before writing to the db. 
                'filter' => 'to_slug'
            ),
            
            'body' => array(
                'type' => 'html', /* e.g., use tinymce when editing */
                
                // Function / array of functions to pass field data through
                // when sanitizing for display
                'sanitizer' => 'mce_hook_cleaner', 
            ),
            
            'author' => array(
                'type' => 'has_one',
                'cascade' => array('save', 'delete')
            ),
            
            'categories' => array(
                'type' => 'has_many',
                // 'class' => 'Category',
                // 'table' => 'post_categories',
                // 'columns' => array('post_id', 'category_id'),
                // 'columns' => array('Post' => 'post_id', 'Category' => 'category_id')
                'cascade' => 'none'
            )
        );
        
        
        // SG_Model compiles each field into an SG_Model_Field instance, which
        // we can then interrogate for e.g. how to render in a form, how to 
        // display in a view, how to display in a table.
        
    }

?>
