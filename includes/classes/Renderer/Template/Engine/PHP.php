<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Renderer_Template_Engine_PHP extends Octopus_Renderer_Template_Engine {

    public function render(Array $data) {

        extract($data);

        $OCTOPUS_VIEW_DATA =& $data;

        ob_start();
        include($this->file);
        return ob_get_clean();

    }

}
