<?php /*:folding=explicit:collapseFolds=1:*/

// SG_Debug Class {{{

/**
 * Helper class used to render debug information on the client.
 */
class SG_Debug {

    public static $css;
    private $_id;
    private $_content = array();
    private $_options;
    private static $_renderedCss = false;
    private static $_idCounter = 0;

    private static function getNewId() {
        return "sgDebug" . (++self::$_idCounter);
    }

    public function __construct($id) {
        $this->_id = $id;
        self::$css = <<<END

<style type="text/css">
<!--

    .sgDebug {
        background: #ccc;
        border: 1px solid #333;
        color: #000;
        font-family: monospace;
        margin: 0 0 10px 0;
        padding: 10px;
    }

    .sgDebugContentTable {
        width: 100%;
    }

    table.sgDebugContentTable tr td {
        vertical-align: top;
        text-align: center;
    }
    table.sgDebugContentTable tr td.sgDebugLast { text-align: right; }
    table.sgDebugContentTable tr td.sgDebugFirst { text-align: left; }


    .sgDebug_var {
        background: #eee;
        padding: 5px;
        margin-bottom: 10px;
    }

    table.sgDebugArrayTable {
        background: #eee;
        margin: 5px 0;
    }
    table.sgDebugArrayTable tr td, table.sgDebugArrayTable tr th {
        text-align: left;
    }

    .sgDebugMissingArray { color: #444; }

    ul.sgDebugBacktrace {
        list-style-type: none;
        padding: 0;
    }
    ul.sgDebugBacktrace li.sgDebugFirst { font-weight: bold; }

    .sgDebugErrorReporting {
        border-top: 1px dotted #555;
        color: #555;
        font-size: 0.9em;
        margin-top: 10px;
        padding-top: 10px;
        text-align: right;
    }

-->
</style>

END;
    }

    public function add($name, $content) {

        if (!isset($this->_content[$name])) {
            $this->_content[$name] = array();
        }

        $this->_content[$name][] = $content;
    }

    public function render() {
        if (self::inWebContext()) {
            $this->renderHtml();
        } else {
            $this->renderText();
        }
    }

    public function renderHtml() {

        if (!self::$_renderedCss) {
            echo self::$css;
            self::$_renderedCss = true;
        }

        $content = $this->getContentHtml();

        echo <<<END
        <div id="{$this->_id}" class="sgDebug">
            $content
        </div>
END;
    }

    private function getContentHtml() {

        $count = count($this->_content);

        if (!$count) {
            return '';
        }

        $result = '';
        foreach($this->_content as $name => $values) {
            $html = self::buildContentTable($name, $values);
            $result .= $html;
        }

        return $result;

    }

    private static function buildContentTable($name, $values) {

        $html = <<<END
<table class="sgDebugContentTable sgDebug_$name">
<tr>
END;
        $count = count($values);
        $width = 100.0 / $count;

        $i = 0;
        foreach($values as $v) {

            $classAttr = '';
            if ($i == 0) $classAttr = 'sgDebugFirst';
            if ($i == count($values) - 1) $classAttr .= ' sgDebugLast';
            $classAttr = $classAttr ? " class=\"$classAttr\"" : '';

            $html .= "<td style=\"width: {$width}%;\" $classAttr>";
            $html .= $v;
            $html .= "</td>";

            $i++;
        }

        $html .= '</tr></table>';

        return $html;
    }

    public function renderText() {

        $line = str_repeat('-', 80);

        echo "\n$line";
        foreach($this->_content as $name => $c) {

            foreach($c as $text) {
                echo "\n$text";
            }

        }
        echo "\n$line";

    }

    public static function inWebContext() {
        return isset($_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * @return array The names of all enabled error reporting flags.
     */
    private static function &getErrorReportingFlags($er = null) {

        $allExceptDeprecated = E_ALL & ~E_DEPRECATED;

        $er = $er == null ? error_reporting() : $er;
        $flags = array();

        if (($er & E_ALL) === E_ALL) {
            $flags[] = 'E_ALL';
        } else if ($er & $allExceptDeprecated === $allExceptDeprecated) {
            $flags[] = 'E_ALL (except E_DEPRECATED)';
        }


        if (empty($flags)) {
            foreach(array('E_NOTICE', 'E_ERROR', 'E_WARNING', 'E_PARSE', 'E_DEPRECATED') as $level) {
                $val = constant($level);
                if (($er & $val) === $val) {
                    $flags[] = $level;
                }
            }
        }

        if (($er & E_STRICT) === E_STRICT) {
            $flags[] = 'E_STRICT';
        }

        return $flags;
    }

    /**
     * @return string A backtrace rendered as HTML.
     */
    public static function getBacktraceHtml(&$bt = null) {

        $bt = $bt ? $bt : debug_backtrace();

        $html = '';
        $currentLine = '';
        $currentFile = '';

        $skipFunction = true;
        $first = true;

        $html = '<ul class="sgDebugBacktrace">';

        $i = 0;
        $count = count($bt);

        foreach($bt as $b) {

            $class = '';
            if ($i == 0) $class = ' sgDebugFirst';
            if ($i == $count - 1) $class .= ' sgDebugLast';
            $class = $class ? ' class="' . $class . '"' : '';

            $func = '';
            if (isset($b['function'])) {
                $func = '<span class="sgDebugBacktraceFunction">' . $b['function'] . '()</span>';

            }

            $file = '';
            if (isset($b['file'])) {
                $file = '<span class="sgDebugBacktraceFile">' . htmlspecialchars($b['file']) . '</span>';
            }

            $line = '';
            if (isset($b['line'])) {
                $line = '<span class="sgDebugBacktraceLine">Line ' . $b['line'] . '</span>';
            }

            $html .= <<<END
            <li$class>
                $func
                $file
                $line
            </li>
END;

            $i++;

        }

        $html .= '</ul>';

        return $html;
    }

    public static function getErrorReportingHtml() {

        $flags = implode(' | ', self::getErrorReportingFlags());

        return <<<END
        <div class="sgDebugErrorReporting">
        error_reporting: $flags
        </div>
END;


    }

    /**
     * Returns HTML for the various system arrays
     */
    public static function getArraysHtml() {

        $html = '';

        foreach(array('$_GET', '$_POST', '$_SERVER', '$_SESSION') as $arname) {

           eval("\$ar = isset($arname) ? $arname : false;");
           if ($ar === false) {
               $html .= '<span class=\"sgDebugMissingArray">' . $arname . ' (unavailable)</span>';
               continue;
           }

           $id = self::getNewId();

           if (empty($ar)) {
               $html .= '<span class="sgDebugEmptyArrayName">' . $arname . ' (Empty)</span><br>';
               continue;
           }

           $html .= "<a href=\"#$arname\" onclick=\"var c = document.getElementById('$id'); if (c.style.display == 'none') c.style.display = ''; else c.style.display = 'none'; return false;\">$arname</a><br />";
           $html .= '<table id="' . $id . '" style="display: none;" class="sgDebugArrayTable">';


           foreach($ar as $key => $value) {

               $value = var_export($value, true);

               $html .=
                '<tr>' .
                    '<th>' . htmlspecialchars($key) . '</th>' .
                    '<td>=></td>' .
                    '<td>' . htmlspecialchars(stripslashes($value)) . '</td>';
                '</tr>';

           }

           $html .= '</table>';


        }

       return $html;

    }

    public static function dumpToString($x) {

        // var_export chokes on recursive references, but var_dump doesn't.
        // of course, var_dump involves output buffering. yay.

        ob_start();
        var_dump($x);
        return ob_get_clean();
    }

}

// }}}

    if (!function_exists('dump_r')) {

    /**
     * Outputs the arguments passed to it along w/ debugging info.
     * @param mixed Any arguments you want dumped.
     */
    function dump_r() {

        $args = func_get_args();
        if (empty($args)) return;

        if ((defined('LIVE') && LIVE) || (defined('STAGING') && STAGING)) {
            // TODO: Log?
            return;
        }

        if (function_exists('cancel_redirects')) {
            cancel_redirects();
        }

        if (SG_Debug::inWebContext()) {

            $d = new SG_Debug('dump_r');
            foreach($args as $arg) {
                $html = '<pre>' . htmlspecialchars(SG_Debug::dumpToString($arg)) . '</pre>';
                $d->add('var', $html);
            }
            $d->add('context', SG_Debug::getArraysHtml());
            $bt = debug_backtrace();
            $d->add('context', SG_Debug::getBacktraceHtml($bt));

            $d->add('footer', SG_Debug::getErrorReportingHtml());

        } else {

            $d = new SG_Debug('dump_r');
            foreach($args as $arg) {
                $d->add('var', SG_Debug::dumpToString($arg));
            }

        }

        $d->render();

    }

    }

    /**
     * Calls dump_r and then exit().
     * @param mixed Any values you want displayed.
     */
    function dump_x() {
        $args = func_get_args();
        call_user_func_array('dump_r', $args);
        exit();
    }

?>
