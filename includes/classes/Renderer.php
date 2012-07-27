<?php

/**
 * Class that takes an Octopus_Response and outputs it in the appropriate
 * format (HTML, JSON, whatever).
 */
class Octopus_Renderer {

	private static $registry = array(

		// JSON renderer
		'Octopus_Renderer_JSON' => array(
			'application/json' => false,
			'text/x-json' => false,
			'application/javascript' => false,
			'application/x-javascript' => false,
			'text/javascript' => false,
			'text/x-javascript' => false,
		),

		// Everything else renderer
		'Octopus_Renderer_Template' => true

	);

	/**
	 * @return Octopus_Renderer an appropriate renderer for the given content
	 * type.
	 */
	public static function getForContentType($type) {

		$type = strtolower($type);

		foreach(self::$registry as $class => &$contentTypes) {

			if ($contentTypes instanceof Octopus_Renderer) {
				return $contentTypes;
			} else if ($contentTypes === true) {
				return (self::$registry[$class] = new $class());
			}

			if (isset($contentTypes[$type])) {

				$r = $contentTypes[$type];

				if ($r) {
					return $r;
				} else {
					return ($contentTypes[$type] = new $class());
				}

			}

		}

	}

	/**
	 * Outputs/returns the rendered form of $response.
	 * @param  Octopus_Response $response Response to render.
	 * @param  boolean          $return   If true, the rendered content will
	 * be returned (no headers will be written). If false, headers will be
	 * written and the content will be echo'd to stdout.
	 * @return String|Octopus_Renderer If $return is true, the rendered content.
	 * Otherwise, returns $this.
	 */
	public function render(Octopus_Response $response, $return = true) {

		// NOTE: renderContent() can modify $response (e.g. set the status
		// to 404 if a view is not found). So we have to do an internal
		// buffered render, output headers, and then output/return.

		$content = $this->renderContent($response);

		if ($return) {
			return $content;
		} else {

			$this->outputHeaders($response);
			echo $content;

			return $this;

		}

	}

	/**
	 * Outputs the headers associated with $response.
	 */
	protected function outputHeaders(Octopus_Response $response) {

		foreach($response->getHeaders() as $key => $value) {
			header("$key: $value");
		}

	}

	/**
	 * Outputs or returns the content for $response.
	 */
	protected function renderContent(Octopus_Response $response) {
		return $response->getContent();
	}

}