<?php

/**
 * A renderer that just outputs the data attached to a response as straight
 * JSON.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Renderer_Json extends Octopus_Renderer {

    /**
     * @see Octopus_Renderer::renderContent()
     */
    protected function renderContent(Octopus_Response $response) {

        $values = $response->getValues();

        // TODO: http://us.php.net/manual/en/class.jsonserializable.php
        return pretty_json_encode($values);

    }

}