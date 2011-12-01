<?php

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

        if (!isset($options['method'])) {

            if (!empty($options['post'])) {
                $options['method'] = 'post';
            } else {
                $options['method'] = 'get';
            }

            $options['method'] = strtolower($options['method']);
        }


        parent::__construct($id, 'a', array('href' => u($url), 'title' => $label), $label, $options);

        $this->addClass('action', to_css_class($id));

        if ($options['method'] != 'get') {
	        $this->addClass('method-' . to_css_class($options['method']));
	        // TODO: Remove
	        $this->addClass('method' . ucwords($options['method']));
	    }

        if (!empty($options['confirm'])) {

            $confirm = $options['confirm'];
            if ($confirm === true) {
                $confirm = "Are you sure you want to do that?";
            }
            $this->setAttribute('data-confirm', $confirm);

        }
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
