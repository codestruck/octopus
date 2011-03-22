<?php


/**
 * SG_Nav_Item that uses a regular expression to match its path.
 */
class SG_Nav_Item_Regex {

    public function __construct($nav, $parent, $options) {
        parent::__construct($nav, $parent, $options);
    }

    public function getRegex() {
        return $this->options['regex'];
    }

    public function matchesPath($path) {
        return preg_match($path);
    }


}
