<?php

/**
 * Base class that common classes inherit from
 *
 * @package SG
 */
class SG_Base {

    /**
     * Common singleton function
     *
     * Subclasses call this by passing in their own class name
     *
     * @access protected
     * @param string $classname  Name of Subclass
     * @return object Reference to object of $classname
     */
    protected static function &base_singleton($classname) {

        static $instance;
        if (!isset($instance)) $instance = array();

        if (!isset($instance[$classname])) {
            $instance[$classname] = new $classname;
        }

        return $instance[$classname];
    }
}

?>
