<?php

/**
 * Class responsible for finding template files to render for requests.
 * @TODO Abstract this out as a general file finder (with tests)
 */
class Octopus_Renderer_Template_Finder {

	private $dirs = array();
	private $extensions;

	public function __construct(Array $dirs, Array $extensions = array()) {

		foreach($dirs as $dir) {
			$dir = rtrim($dir, '/') . '/';
			$this->dirs[$dir] = true;
		}

		if (func_num_args() === 1) {
			$extensions = Octopus_Renderer_Template_Engine::getExtensions();
		}

		$this->extensions = $extensions;

	}


	/**
	 * @param String|Array $template Template path/identifier. This is basically the
	 * path of the template, relative to one of the directories supplied to the
	 * constructor, minus any file extension. If $template is an array, it is
	 * iterated over and the first file found is returned.
	 * @return String|Boolean The full path to the file for the given template,
	 * or false if it is not found.
	 */
	public function find($template) {

		$templates = is_array($template) ? $template : array($template);

		return $this->internalGetCandidateFiles($templates, true);

	}

	/**
	 * @param String|Array $template A single identifier or an array of
	 * identifiers.
	 * @return Array The list of potential filesystem locations that the view
	 * file for $template could be in (in the order Octopus checks for them).
	 */
	public function getCandidateFiles($template) {
		return $this->internalGetCandidateFiles(is_array($template) ? $template : array($template), true);
	}

    /**
     * Builds a list of potential file location for the templates present
     * in $templates.
     * @param  Array $templates Array of template identifiers.
     * @param Boolean $returnFirstFound If true, the first existing file will
     * be returned.
     * @return Array|String If $returnFirstFound is true, and a file is found,
     * this function returns the absolute path to the file. If $returnFirstFound
     * is false (or no files are found), returns an empty array.
     */
    private function internalGetCandidateFiles(Array $templates, $returnFirstFound) {

    	$result = array();
    	$extensions = Octopus_Renderer_Template_Engine::getExtensions();

    	foreach($templates as $template) {

    		foreach($this->dirs as $dir => $unused) {

    			if (!is_dir($dir)) {
    				continue;
    			}

    			foreach($this->extensions as $ext) {

    				$file = $dir . $template . $ext;

    				if (is_file($file)) {

    					if ($returnFirstFound) {
    						return $file;
    					} else {
    						$result[] = $file;
    					}

    				}

    			}

    		}

    	}

    	return $result;

    }

}