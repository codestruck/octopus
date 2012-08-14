<?php

/**
 * An implementation of Octopus_Renderer that just spits out whatever's been
 * added to an Octopus_Response using Octopus_Response::appendContent().
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Renderer_AppendedContent extends Octopus_Renderer {

    public function renderContent(Octopus_Response $response) {
        return $response->getContent();
    }

}