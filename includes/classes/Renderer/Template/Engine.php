<?php

/**
 * Base for a class that renders a template file.
 */
abstract class Octopus_Renderer_Template_Engine {

    private static $registry = array();
    protected $file;

    public function __construct($file) {
        $this->file = $file;
    }

    abstract public function render(Array $data);

    public static function createForFile($filename) {

    	$fileExt = pathinfo($filename, PATHINFO_EXTENSION);
    	if ($fileExt) $fileExt = '.' . $fileExt;

        foreach(self::$registry as $ext => $class) {

        	if (strcasecmp($ext, $fileExt) === 0) {
        		return new $class($filename);
        	}

        }

        return false;
    }

    /**
     * @return Array Registered extensions for rendering engines.
     */
    public static function getExtensions() {
    	return array_keys(self::$registry);
    }

    public static function register($extension, $class) {
        self::$registry[$extension] = $class;
    }
}

Octopus_Renderer_Template_Engine::register('.php', 'Octopus_Renderer_Template_Engine_PHP');
Octopus_Renderer_Template_Engine::register('.tpl', 'Octopus_Renderer_Template_Engine_Smarty');
Octopus_Renderer_Template_Engine::register('.mustache', 'Octopus_Renderer_Template_Engine_Mustache');
