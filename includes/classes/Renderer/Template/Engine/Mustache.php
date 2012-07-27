<?php

function mustache_escaper($value) {
    return htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
}

class Octopus_Renderer_Template_Engine_Mustache extends Octopus_Renderer_Template_Engine {

    public function render(Array $data) {

        Octopus::loadExternal('mustache');

        $pathInfo = pathinfo($this->file);

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
