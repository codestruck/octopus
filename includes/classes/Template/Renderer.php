<?php

/**
 * Base for a class that renders a template file. Also servces as a registry
 * of file renderers.
 */
abstract class Octopus_Template_Renderer {

    private static $_registry = array();
    protected $_file;

    public function __construct($file) {
        $this->_file = $file;
    }

    abstract public function render(array $data);

    public static function createForFile($filename) {

        foreach(self::$_registry as $pattern => $class) {
            if (preg_match($pattern, $filename)) {
                return new $class($filename);
            }
        }

        return false;
    }

    public static function register($pattern, $class) {
        self::$_registry[$pattern] = $class;
    }
}

Octopus_Template_Renderer::register('/\.php$/i', 'Octopus_Template_Renderer_PHP');
Octopus_Template_Renderer::register('/\.tpl$/i', 'Octopus_Template_Renderer_Smarty');
Octopus_Template_Renderer::register('/\.mustache$/i', 'Octopus_Template_Renderer_Mustache');
