<?php

Octopus::loadClass('Octopus_Html_Table_Action');

class Octopus_Html_Table_Toggle extends Octopus_Html_Table_Content {

    public static $defaults = array(

        'activeClass' => 'toggleActive',

        'inactiveClass' => 'toggleInactive',

        /**
         * Attribute in which to stash the alternate content.
         */
        'altContentAttrPrefix' => 'data-alt-'

    );

    public $options;
    private $_activeContent, $_inactiveContent;
    private $_activeUrl, $_inactiveUrl;

    public function __construct($id, $labels, $url = null, $options = null) {

        parent::__construct($id, null, $url, $options);

        $this->_activeContent = $this->_inactiveContent = null;

        if (is_array($labels)) {
            $options['label'] = $labels;
            $labels = null;
        }

        if ($url === null && $options === null) {

            if (is_array($labels)) {
                $options = $labels;
                $labels = null;
            } else {
                $url = $labels;
                $labels = null;
            }

        }

        if ($options === null && is_array($url)) {
            $options = $url;
            $url = $labels;
            $labels = null;
        }

        if ($options === null) {
            $this->options = self::$defaults;
        } else {
            $this->options = array_merge(self::$defaults, $options);
        }

        if ($labels === null) {

            if (isset($options['label'])) {
                $labels = $options['label'];
            } else if (isset($options['desc'])) {
                $labels = $options['desc'];
            } else {
                // TODO: better default labeling?
                $labels = array('Inactive', 'Active');
            }
        }

        if (isset($labels[0]) && isset($labels[1])) {
            list($this->_inactiveContent, $this->_activeContent) = $labels;
            $labels = null;
        } else if (isset($labels['active']) && isset($labels['inactive'])) {
            $this->_inactiveContent = $labels['inactive'];
            $this->_activeContent = $labels['active'];
            $labels = null;
        }


        if (isset($options['url'])) {
            $url = $options['url'];
        }

        list($this->_inactiveUrl, $this->_activeUrl) = $url;

        if (!isset($options['method'])) {

            if (!empty($options['post'])) {
                $options['method'] = 'post';
            } else {
                $options['method'] = 'get';
            }

            $options['method'] = strtolower($options['method']);
        }

        parent::__construct($id, 'a', array('href' => $url));
        $this->addClass('toggle', $id);

        if ($options['method'] != 'get') $this->addClass('method' . ucwords($options['method']));
    }

    /**
     * Renders this bit of content inside the given cell.
     */
    public function fillCell($table, $column, $cell, &$obj) {

        $activeClass = $this->options['activeClass'];
        $inactiveClass = $this->options['inactiveClass'];

        $altContentAttr = $this->options['altContentAttrPrefix'] . 'content';
        $altHrefAttr = $this->options['altContentAttrPrefix'] . 'href';

        if ($this->isActive($obj)) {
            $this->html($this->_activeContent);
            $this->attr('href', $this->_activeUrl);
            $this->setAttribute($altContentAttr, $this->_inactiveContent);
            $this->setAttribute($altHrefAttr, $this->_inactiveUrl);
            $this->removeClass($inactiveClass)->addClass($activeClass);
        } else {
            $this->html($this->_inactiveContent);
            $this->attr('href', $this->_inactiveUrl);
            $this->setAttribute($altContentAttr, $this->_activeContent);
            $this->setAttribute($altHrefAttr, $this->_activeUrl);
            $this->removeClass($activeClass)->addClass($inactiveClass);
        }

        parent::fillCell($table, $column, $cell, $obj);

    }

    public function getInactiveContent() {
        return $this->_inactiveContent;
    }

    public function getActiveContent() {
        return $this->_activeContent;
    }

    public function getActiveUrl() {
        return $this->_activeUrl;
    }

    public function getInactiveUrl() {
        return $this->_inactiveUrl;
    }


    public function isActive(&$obj) {

        $id = $this->getContentID();

        if (is_object($obj)) {

            // HACK: Octopus_Model doesn't support isset() right now
            if ($obj instanceof Octopus_Model) {
                return !!$obj->$id;
            }

            return isset($obj->$id) && $obj->$id;

        } else if (is_array($obj)) {
            return isset($obj[$id]) && $obj[$id];
        }

    }


}

?>
