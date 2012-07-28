<?php

/**
 * An implementation of Octopus_Renderer that just spits out whatever's been
 * added to an Octopus_Response using Octopus_Response::appendContent().
 */
class Octopus_Renderer_AppendedContent extends Octopus_Renderer {

	public function renderContent(Octopus_Response $response) {
		return $response->getContent();
	}

}