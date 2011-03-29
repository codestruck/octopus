<?php

SG::loadClass('SG_Nav_Item_Action');

class SG_Nav_Item_Controller extends SG_Nav_item {

    private $_file;
    private $_controllerName;
    private $_controllerPath;

    private $_childrenByPath = array();

    public function __construct($path, $file) {
        parent::__construct(array('path' => $path));
        $this->_file = $file;
        $this->_controllerName = basename($file, '.php');
    }

    protected function &internalFind($path, $options) {

        list($action, $args) = $this->splitPath($path);
        if (!$action) {
            $action = false;
            return $action;
        }

        if (!isset($this->_childrenByPath[$path])) {
            $child = $this->createActionItem($action, $args);
            $this->internalAdd($child);
            $this->_childrenByPath[$path] = $child;
        }

        return $this->_childrenByPath[$path];
    }

    /**
     * @return Object Your primary action item.
     */
    protected function &createActionItem($action, $args) {
        $item = new SG_Nav_Item_Action($action, $args);
        return $item;
    }

    protected function getDefaultText() {
        return str_replace('_', ' ', $this->_controllerName);
    }

    public function getControllerInfo() {

        return array(
            'controller' => $this->_controllerName,
            'action' => 'index'
        );

    }


    protected function invalidateCaches() {
        parent::invalidateCaches();
        $this->_childrenByPath = array();
    }
}

?>
