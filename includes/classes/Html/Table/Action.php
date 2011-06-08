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

        if (isset($options['url'])) {
            $url = $options['url'];
        }

        parent::__construct($id, 'a', array('href' => $url), $label);
        $this->addClass('action', $id);
    }

    public function url(/* polymorphic */) {
        switch(func_num_args()) {
            case 0:
                return $this->attr('href');
            default:
                return $this->attr('href', func_get_arg(0));
        }
    }

}

?>
