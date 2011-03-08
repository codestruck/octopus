<?php

// Inspirations:
// http://www.recessframework.org/page/recess-models-at-a-glance
// http://book.cakephp.org/view/1000/Models
// http://www.yiiframework.com/doc/guide/1.1/en/database.ar
// http://www.akelos.org/api/ActiveRecord/Base/AkActiveRecord.html

class Post extends SG_Model {
    public $post_id; // unnecessary?
    public $title;
    public $body;
    public $author_id; // unnecessary?

    public $created; // always present?
    public $updated;

    // belongs to an author
    public function __construct() {
        $this->_belongsTo('author');
    }

}

class Author extends SG_Model {
    public $author_id; // unnecessary?
    public $name;
    public $birthdate;
    public $website;

    // has many posts
    public function __construct() {
        $this->_hasMany('posts');
    }
    // or
    public function relations() {
        $this->_hasMany('posts');
    }


}

// possible methods:
/*

save
exists
delete


beforeSave
afterSave
beforeValidate
afterValidate

*/

?>
