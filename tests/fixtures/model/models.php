<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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


/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Lack extends Octopus_Model {
    protected $fields = array(
        'notitle' => array(),
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Notext extends Octopus_Model {
    protected $fields = array(
        'number' => array(
            'type' => 'numeric',
        ),
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class FindAuthor extends Octopus_Model {

    protected $fields = array(
        'name' => array('type' => 'string'),
        'posts' => array(
            'type' => 'hasMany',
            'model' => 'FindPost'
        ),
        'favorite_food' => array('type' => 'hasOne', 'model' => 'FindFood'),
        'active'
    );

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class FindCategory extends Octopus_Model {

    protected $fields = array(
        'name',
    );

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class FindFood extends Octopus_Model {
	protected $fields = array('name');
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class CommentUser extends Octopus_Model {
    protected $fields = array(
        'name',
        'comments' => array(
            'type' => 'hasMany',
        ),

    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Car extends Octopus_Model {
    protected $fields = array(
        'name',
        'comment' => array(
            'type' => 'hasMany',
            'filter' => true,
        ),
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Boat extends Octopus_Model {
    protected $fields = array(
        'name',
        'comment' => array(
            'type' => 'hasMany',
            'filter' => true,
        ),
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Schemab extends Octopus_Model {
    protected $fields = array(
        'title',
        'display_order' => array(
            'type' => 'numeric',
        ),
        'cost' => array(
            'type' => 'numeric',
            'decimal_places' => 2,
        ),
        'lowcost' => array(
            'type' => 'numeric',
            'decimal_places' => 2,
            'precision' => 4,
        ),
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Schemac extends Octopus_Model {
    protected $indexes = array('display_order', 'title', array('one', 'two'));
    protected $fields = array(
        'title',
        'display_order' => array(
            'type' => 'numeric',
        ),
        'one',
        'two',
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Schemad extends Octopus_Model {
    protected $fields = array(
        'title' => array(
            'index' => 'unique',
        ),
        'display_order' => array(
            'type' => 'numeric',
            'index' => true,
        ),

    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HtmlTablePerson extends Octopus_Model {

    protected $fields = array(
        'name' => array(
            'sortable' => false,
            'filter' => true
        ),
        'age' => array('type' => 'numeric')
    );

    public function method_uppercase($str) {
        return strtoupper($str);
    }

}
