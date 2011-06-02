<?php

class Octopus_Renderer_PHP extends Octopus_Renderer {

    public function render($data) {

        if (is_array($data)) {
            extract($data);
        }

        $OCTOPUS_VIEW_DATA =& $data;

        ob_start();
        include($this->_file);
        return ob_get_clean();

    }

}

?>
