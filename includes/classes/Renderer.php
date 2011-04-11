<?php

/**
 * Base class for a renderer.
 */
class SG_Renderer {

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
                SG::loadClass($class);
                return new $class($filename);
            }
        }

        return false;
    }

    public static function register($pattern, $class) {
        self::$_registry[$pattern] = $class;
    }
}

SG_Renderer::register('/\.php$/i', 'SG_Renderer_PHP');
SG_Renderer::register('/\.tpl$/i', 'SG_Renderer_Smarty');

?>
