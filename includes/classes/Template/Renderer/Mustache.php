<?php

function mustache_escaper($value) {
    return htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
}

class Octopus_Template_Renderer_Mustache extends Octopus_Template_Renderer {

    public function render(Array $data) {

        $data['OCTOPUS_VIEW_DATA'] = $data;

        Octopus::loadExternal('mustache');

        $pathInfo = pathinfo($this->_file);

        $root = $pathInfo['dirname'];
        $relPath = $pathInfo['basename'];

        $m = new Mustache_Engine(array(
            'loader'          => new Mustache_Loader_FilesystemLoader("/{$root}"),
            'partials_loader' => new Mustache_Loader_FilesystemLoader("/{$root}"),
            'cache'           => OCTOPUS_PRIVATE_DIR . 'mustache/',
        ));

        return $m->render($relPath, $data);

    }

}
