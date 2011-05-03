<?php

/**
 * Base class for a renderer.
 */
class Octopus_Renderer {

    private static $_registry = array();
    protected $_file;

    public function __construct($file) {
        $this->_file = $file;
    }


    public function render($data) {
        return '';
    }

    public static function createForFile($filename) {

        foreach(self::$_registry as $pattern => $class) {
            if (preg_match($pattern, $filename)) {
                Octopus::loadClass($class);
                return new $class($filename);
            }
        }

        return false;
    }

    public static function register($pattern, $class) {
        self::$_registry[$pattern] = $class;
    }
}

Octopus_Renderer::register('/\.php$/i', 'Octopus_Renderer_PHP');
Octopus_Renderer::register('/\.tpl$/i', 'Octopus_Renderer_Smarty');

?>
