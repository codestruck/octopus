<?php

SG::loadExternal('smarty');

class SG_Renderer_Smarty extends SG_Renderer {

    public function render($data) {

        $smarty = SG_Smarty::singleton()->smarty;
        $smartyData = $smarty->createData();

        if (is_array($data)) {
            foreach($data as $key => $value) {
                $smartyData->assign($key, $value);
            }
        }

        $tpl = $smarty->createTemplate($this->_file, $smartyData);
        return $tpl->fetch();

    }

}

?>
