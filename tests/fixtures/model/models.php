<?php

class Product extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true
        ),
        'group' => array(
            'type' => 'manyToMany',
        )
    );
}

class Group extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true,
        ),
        'product' => array(
            'type' => 'manyToMany',
        )
    );
}


class Nail extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true
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
            'required' => true,
        ),
        'slug' => array(
            'type' => 'slug',
            'onEmpty' => 'to_unique_slug',
        ),
        'nail' => array(
            'type' => 'hasOne',
            'required' => true
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
            'required' => true,
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


