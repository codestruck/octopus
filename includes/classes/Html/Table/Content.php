<?php

Octopus::loadClass('Octopus_Html_Element');
Octopus::loadClass('Octopus_Html_Table');
Octopus::loadClass('Octopus_Html_Table_Column');

/**
 * A chunk of content rendered inside a table.
 */
class Octopus_Html_Table_Content extends Octopus_Html_Element {

    private $_currentObj;

    private $_contentID;

    public function __construct($id, $tag, $attributes = array(), $content = array()) {
        $this->_contentID = $id;
        parent::__construct($tag, $attributes, $content);
    }

    public function getContentID() {
        return $this->_contentID;
    }

    /**
     * Renders this bit of content inside the given cell.
     */
    public function fillCell($table, $column, $cell, &$obj) {

        $pattern = '/\{\$([a-z0-9_\.]+)\}/i';

        $html = $this->render(true);

        $this->_currentObj = $obj;
        $html = preg_replace_callback($pattern, array($this, 'replaceCallback'), $html);
        unset($this->_currentObj);

        $cell->append($html);
    }

    private function replaceCallback($matches) {

        $key = $matches[1];

        $parts = explode('.', $key);
        $value = $this->_currentObj;

        foreach($parts as $p) {

            // HACK: model doesn't support isset()

            if (is_object($value)) {
                if (isset($value->$p) || $value instanceof Octopus_Model) {
                    $value = $value->$p;
                } else {
                    return '(Key ' . htmlspecialchars($p) . ' not found)';
                }
            } else if (is_array($value)) {
                if (isset($value[$p])) {
                    $value = $value[$p];
                } else {
                    return '(Key ' . htmlspecialchars($p) . ' not found)';
                }
            }

        }

        return $value;
    }

}

?>

