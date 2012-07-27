<?php

class Octopus_Renderer_Template_Engine_Smarty extends Octopus_Renderer_Template_Engine {

    public function render(Array $data) {

        $data['OCTOPUS_VIEW_DATA'] = $data;

        Octopus::loadExternal('smarty');
        $smarty = Octopus_Smarty::trusted();

        return $smarty->render($this->file, $data);
    }

}
