<?php

/**
 * An item in the nav that represents a single action on a controller.
 */
class SG_Nav_Item_Action extends SG_Nav_Item {

    private $_action;
    private $_args;

    public function __construct($action, $args) {

        parent::__construct(array('path' => $action));
        $this->_action = $action;
        $this->_args = $args ? explode('/', rawurldecode($args)) : array();
    }


    public function getArgs() {
        return $this->_args;
    }

    public function getFullPath() {

        $path = array();

        if ($parent = $this->getParent()) {
            $path[] = $parent->getFullPath();
        }
        $path[] = $this->getPath();

        if (!empty($this->_args)) {
            $args = array_map('rawurlencode', $this->_args);
            $path[] = implode('/', $args);
        }

        return trim(implode('/', $path), '/');
    }

}

?>
