<?php

class Octopus_Template_Renderer_PHP extends Octopus_Template_Renderer {

    public function render(Array $data) {

        extract($data);

        $OCTOPUS_VIEW_DATA =& $data;

        ob_start();
        include($this->_file);
        return ob_get_clean();

    }

}

?>