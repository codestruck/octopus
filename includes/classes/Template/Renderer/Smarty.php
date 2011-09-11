<?php

class Octopus_Template_Renderer_Smarty extends Octopus_Template_Renderer {

    public function render(Array $data) {

        Octopus::loadExternal('smarty');

        $smarty = Octopus_Smarty::singleton()->smarty;
        $smartyData = $smarty->createData();

        if (is_array($data)) {
            foreach($data as $key => $value) {
                $smartyData->assign($key, $value);
            }
        }
        $smartyData->assign('OCTOPUS_VIEW_DATA', $data);

        // Look for templates in the same directory the file is in.
        $smarty->template_dir = array(dirname($this->_file));

        $tpl = $smarty->createTemplate($this->_file, $smartyData);
        return $tpl->fetch();

    }

}

?>
