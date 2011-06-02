<?php

Octopus::loadExternal('smarty');

class Octopus_Renderer_Smarty extends Octopus_Renderer {

    public function render($data) {

        $smarty = Octopus_Smarty::singleton()->smarty;
        $smartyData = $smarty->createData();

        if (is_array($data)) {
            foreach($data as $key => $value) {
                $smartyData->assign($key, $value);
            }
        }
        $smartyData->assign('OCTOPUS_VIEW_DATA', $data);

        $tpl = $smarty->createTemplate($this->_file, $smartyData);
        return $tpl->fetch();

    }

}

?>
