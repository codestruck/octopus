<?php

/**
 * A chunk of content rendered inside a table.
 */
class Octopus_Html_Table_Content extends Octopus_Html_Element {

    private $_currentObj;
    private $_contentID;
    private $options;

    public function __construct($id, $tag, $attributes = array(), $content = array(), $options = array()) {
        $this->_contentID = $id;
        $this->options = $options;
        parent::__construct($tag, $attributes, $content);
    }

    public function getContentID() {
        return $this->_contentID;
    }

    /**
     * Renders this bit of content inside the given cell.
     */
    public function fillCell($table, $column, $cell, &$obj) {

        if (!empty($this->options['function'])) {

            // Use a function to render this cell's content
            $value = null;
            $content = self::applyFunction($this->options['function'], $value, $obj, $notUsed, $this, false);
            $this->html($content);

        }

        $pattern = '/\{\$([a-z0-9_\.\|\>\(\)-]+)\}/i';

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
            $key = str_replace('->', '.', $key);
            $parts = explode('.', $key);
            $value = $this->_currentObj;
            $path = '';
            $found = false;

            // Given something like key.key.value, resolve it.
            foreach($parts as $p) {

                $path .= ($path ? '.' : '') . $p;

                if (is_object($value)) {

                    // HACK: model doesn't support isset()
                    $method = null;

                    if (ends_with($p, '()', false, $method) && method_exists($value, $method)) {

                        $value = $value->$method();
                        $found = true;
                    } else if (isset($value->$p) || $value instanceof Octopus_Model) {
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

    /**
     * Runs an abitrary function against a single value or a matched item
     * in a resultset.
     *
     *    @param $function Callback to apply. If not set, $value will be
     *  returned. If set but not callable, an error message will be
     *  returned.
     *
     *    @param $value Value being rendered, if known. I.e., if applyFunction
     *  is being called for a column called 'name', this would be $row['name'].
     *
     *    @param $row Row of data being rendered. Could be an array or an
     *  Octopus_Model instance.
     *
     *    @param $escape Gets set to a boolean value indicating whether the
     *  resulting HTML should be escaped.
     *
     *    @param $context Execution context. Used to find a method to call
     *  if $function is a string.
     *
     *    @param $useValue Whether or not to pass $value to functions, or just
     *  $row.
     *
     *    @return String The result of calling $function.
     *
     */
    public static function applyFunction($function, &$value, &$row, &$escape, $context = null, $useValue = true) {

        if (empty($function)) {
            return $useValue ? $value : '';
        }

        $f = $function;

        if ($useValue && is_string($f) && is_object($value) && method_exists($value, $f)) {
            // TODO: should there be a way to supply arguments here?
            return $value->$f();
        } else if (is_string($f) && is_object($context) && method_exists($context, $f)) {

            if ($useValue) {
                return $context->$f($value, $row);
            } else {
                return $context->$f($row);
            }

        } else if ($row instanceof Octopus_Model && is_string($f) && method_exists($row, $f)) {

            return $row->$f($value, $row);

        }

        if (is_callable($f)) {

            /* HACK: not all built-in functions like receiving the row as
             *       the 2nd argument.
             *
             * TODO: Have more calling options, something like:
             *
             *      array(
             *          'function' => 'name of function',
             *          'args' => array(OCTOPUS_ARG_VALUE, OCTOPUS_ARG_ROW, $customVariable, new Octopus_Function(...))
             *      )
             */

            $useExtraArgs = true;
            if (is_string($f)) {
                $noExtraArgs = array('htmlspecialchars', 'htmlentities', 'trim', 'ltrim', 'rtrim', 'nl2br', 'basename');
                $useExtraArgs = !in_array($f, $noExtraArgs);
            }

            if ($useValue) {
                if ($useExtraArgs) {
                    return call_user_func($f, $value, $row, $context);
                } else {
                    return call_user_func($f, $value);
                }
            } else {
                if ($useExtraArgs) {
                    return call_user_func($f, $row, $context);
                } else {
                        return call_user_func($f, $row);
                }
            }

        }

        if (is_array($f)) {
            list($obj, $method) = $f;
            $f = get_class($obj) . '::' . $method;
        }

        $escape = false;
        return '<span style="color:red;">Function not found: ' . h($f) . '</span>';

    }

}

?>
