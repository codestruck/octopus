<?php

Octopus::loadClass('Octopus_Html_Table_Content');

class Octopus_Html_Table_Action extends Octopus_Html_Table_Content {

    public function __construct($id, $label = null, $url = null, $options = null) {

        if ($url === null && $options === null) {

            if (is_array($label)) {
                $options = $label;
                $label = null;
            } else {
                $url = $label;
                $label = null;
            }

        }

        if ($options === null && is_array($url)) {
            $options = $url;
            $url = $label;
            $label = null;
        }

        if ($label === null) {
            $label = humanize($id);
        }

        parent::__construct($id, 'a', array('href' => $url), $label);
        $this->addClass('action', $id);
    }

}

?>
