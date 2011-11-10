<?php

class Product extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true
        ),
        'group' => array(
            'type' => 'manyToMany',
        )
    );
}

class Group extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true,
        ),
        'product' => array(
            'type' => 'manyToMany',
        )
    );
}


class Nail extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true
        ),
        'hammer' => array(
            'type' => 'hasMany',
        ),
        'active' => array(
            'type' => 'boolean',
        ),
        'favorite' => array(
            'type' => 'hasMany',
            'model' => 'sledgehammer',
            'key' => 'favorite_nail',
        ),
    );
}

class Hammer extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true,
        ),
        'slug' => array(
            'type' => 'slug',
            'onEmpty' => 'to_unique_slug',
        ),
        'nail' => array(
            'type' => 'hasOne',
            // 'required' => true
        ),
        'active' => array(
            'type' => 'boolean',
        ),
        'display_order' => array(
            'type' => 'numeric',
        ),
        'created',
        'updated',
    );
}

class Sledgehammer extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            // 'required' => true,
        ),
        'favorite_nail' => array(
            'type' => 'hasOne',
            'model' => 'nail',
        ),
    );
}

class Lack extends Octopus_Model {
    protected $fields = array(
        'notitle' => array(),
    );
}

class Notext extends Octopus_Model {
    protected $fields = array(
        'number' => array(
            'type' => 'numeric',
        ),
    );
}

class FindAuthor extends Octopus_Model {

    protected $fields = array(
        'name' => array('type' => 'string'),
        'posts' => array(
            'type' => 'hasMany',
            'model' => 'FindPost'
        ),
        'active'
    );

}

class FindCategory extends Octopus_Model {

    protected $fields = array(
        'name',
    );

}

class FindPost extends Octopus_Model {

    protected $fields = array(
        'title' => array(
            'type' => 'string'
        ),
        'slug' => array(
            'type' => 'slug'
        ),
        'body' => array(
            'type' => 'html'
        ),
        'author' => array(
            'model' => 'FindAuthor',
            'type' => 'hasOne'
        ),
        'category' => array(
            'type' => 'manyToMany',
            'model' => 'FindCategory'
        ),
        'active' => array(
            'type' => 'boolean',
        ),
        'display_order' => array(
            'type' => 'order',
        ),
        'created',
        'updated',

    );

    public $search = array('title', 'author');

    public static function &create($row) {

        $obj = new FindPost();

        return $obj;

    }


}

class Comment extends Octopus_Model {
    protected $fields = array(
        'content' => array(
            'type' => 'text',
            'onCreate' => 'triggerCreate',
        ),
        'creator' => array(
            'type' => 'hasOne',
            'model' => 'CommentUser',
        ),
        'parent' => array(
            'type' => 'hasOne',
            'filter' => true
        ),
        'item_type',
        'item_id' => array(
            'type' => 'numeric',
        ),
    );

    public function triggerCreate($model, $field) {
        return $field->accessValue($model);
    }
}

class CommentUser extends Octopus_Model {
    protected $fields = array(
        'name',
        'comments' => array(
            'type' => 'hasMany',
        ),

    );
}

class Car extends Octopus_Model {
    protected $fields = array(
        'name',
        'comment' => array(
            'type' => 'hasMany',
            'filter' => true,
        ),
    );
}

class Boat extends Octopus_Model {
    protected $fields = array(
        'name',
        'comment' => array(
            'type' => 'hasMany',
            'filter' => true,
        ),
    );
}

?>
