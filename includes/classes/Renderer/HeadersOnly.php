<?php

/**
 * A renderer that only outputs HTTP headers, no content. Used for 301/302
 * redirects.
 */
class Octopus_Renderer_HeadersOnly extends Octopus_Renderer {

	public function render(Octopus_Response $response, $return = true) {

		if ($return) {
			return '';
		}

		$this->outputHeaders($response);
		return $this;

	}

}