<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
abstract class Octopus_Feed {

    private $items = array();
    private $options;

    public function __construct($title = '', $options = array()) {

        if (is_array($title)) {
            $options = array_merge($title, $options);
        } else {
            $options['title'] = $title;
        }

        $this->options = $options;
    }

    public function addItem($item) {

        if (is_array($item)) {
            $item = new Octopus_Feed_Item_Array($item);
        }

        if (!($item instanceof Octopus_Feed_Item)) {
            throw new Octopus_Exception('$item must implement Octopus_Feed_Item');
        }

        $this->items[$this->getGuid($item)] = $item;
    }

    public function addItems($items) {
        foreach($items as $item) {
            $this->addItem($item);
        }
    }

    public function getItems() {
        return array_values($this->items);
    }

    public function removeAllItems() {
        $this->items = array();
    }

    public function removeItem(Octopus_Feed_Item $item) {
        unset($this->items[$item->getGuid()]);
    }

    abstract public function render($return = false);

    protected function getOption($name, $default = null) {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    protected function getOptions() {
        return $this->options;
    }

    public function __toString() {
        return $this->render(true);
    }

    private function getGuid(Octopus_Feed_Item $item) {
        $guid = $item->getGuid();
        if ($guid) return $guid;
        return $item->getLink();
    }

}

