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

        $pattern = '/\{\$([a-z0-9_\.\|]+)\}/i';

        $html = $this->render(true);

        $this->_currentObj = $obj;
        $html = preg_replace_callback($pattern, array($this, 'replaceCallback'), $html);
        unset($this->_currentObj);

        $cell->append($html);
    }

    private function replaceCallback($matches) {

        $keys = explode('|', $matches[1]);
        $notFound = array();

        while($keys) {

            $key = array_shift($keys);
            $parts = explode('.', $key);
            $value = $this->_currentObj;
            $path = '';
            $found = false;

            // Given something like key.key.value, resolve it.
            foreach($parts as $p) {

                $path .= ($path ? '.' : '') . $p;

                if (is_object($value)) {
                    // HACK: model doesn't support isset()
                    if (isset($value->$p) || $value instanceof Octopus_Model) {
                        $value = $value->$p;
                        $found = true;
                    } else {
                        $notFound[] = $path;
                        $found = false;
                        break;
                    }
                } else if (is_array($value)) {
                    if (isset($value[$p])) {
                        $value = $value[$p];
                        $found = true;
                    } else {
                        $notFound[] = $path;
                        $found = false;
                        break;
                    }
                }

            }

            if ($found) {
                $value = trim($value);
                if ($value === '') {
                    if (empty($keys)) {
                        return $value;
                    }
                } else {
                    return $value;
                }
            }
        }

        return 'Key(s) not found: ' . implode(', ', $notFound);
    }

}

?>

