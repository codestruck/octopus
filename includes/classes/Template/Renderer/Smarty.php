<?php

class Octopus_Template_Renderer_Smarty extends Octopus_Template_Renderer {

    public function render(Array $data) {

        $data['OCTOPUS_VIEW_DATA'] = $data;

        Octopus::loadExternal('smarty');
        $smarty = Octopus_Smarty::trusted();

        return $smarty->render($this->_file, $data);
    }

}

?>
