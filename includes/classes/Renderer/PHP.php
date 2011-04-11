<?php

class SG_Renderer_PHP extends SG_Renderer {

    public function render($data) {

        if (is_array($data)) {
            extract($data);
        }

        ob_start();
        include($this->_file);
        return ob_get_clean();

    }

}

?>
