<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Feed_Item_Array implements Octopus_Feed_Item {

    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function getTitle() {
        return $this->get(array('title', 'name'));
    }

    public function getDescription() {
        return $this->get(array('description'));
    }

    public function getFullContent() {
        return $this->get(array('content', 'body'));
    }

    public function getLink() {
        return $this->get(array('url', 'link'));
    }

    public function getGuid() {
        return $this->get(array('guid', 'id'), $this->getLink());
    }

    public function getDate() {
        return $this->get(array('date'), time());
    }

    public function getExtra() {
        return $this->get(array('rss_extra', array()));
    }

    private function get($keys, $default = '') {

        foreach($keys as $key) {

            if (isset($this->data[$key])) {
                return $this->data[$key];
            }

        }

        return $default;

    }

}

