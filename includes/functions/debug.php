<?php /*:folding=explicit:collapseFolds=1:*/

$_OCTOPUS_DUMPED_CONTENT = array();

function get_dumped_content() {
    global $_OCTOPUS_DUMPED_CONTENT;
    if (count($_OCTOPUS_DUMPED_CONTENT)) {
        return array('_octopus_dumped_content' => $_OCTOPUS_DUMPED_CONTENT);
    }
    return array();
}

function output_dumped_content_header($data, $response) {
    $key = 'X-Dumped-Content';
    if (!count($data)) {
        return;
    }
    $value = print_r($data, true);
    $lines = explode("\n", trim($value));
    $padding = ceil(log(count($lines), 10));

    $i = 0;
    foreach ($lines as $line) {
        $response->addHeader($key . sprintf('-%0' . $padding . 'd', $i), $line);

        $i++;
    }

}

// Octopus_Debug Class {{{

/**
 * Helper class used to render debug information on the client.
 */
class Octopus_Debug {

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

    table.sgDebugContentTable {
        border-spacing: 10px;
    }

    table.sgDebugContentTable tr td {
        background: #DDD;
        border: 1px solid #333;
        padding: 5px;
        text-align: left;
        vertical-align: top;
    }
    table.sgDebugContentTable tr td.sgDebugFirst { text-align: left; }


    .sgDebug_var {
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


    table.sgDebug_footer {
        width: 100%;
    }
    table.sgDebug_footer tr td {
        background: none;
        border: none;
    }

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

    public function render($return = false) {
        if (self::inWebContext()) {
            return $this->renderHtml($return);
        } else if (self::inJavascriptContext()) {
            return $this->renderJson($return);
        } else {
            return $this->renderText($return);
        }
    }

    public function renderHtml($return = false) {

        $result = '';

        if (!self::$_renderedCss || $return) {
            $result .= self::$css;
            if (!$return) self::$_renderedCss = true;
        }

        $content = $this->getContentHtml();

        $result .= <<<END
        <div id="{$this->_id}" class="sgDebug">
            $content
        </div>
END;

        if ($return) {
            return $result;
        } else {
            echo $result;
        }

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

    public function renderText($return = false) {

        $line = str_repeat('-', 80);
        $result = "\n$line";

        foreach($this->_content as $name => $c) {

            foreach($c as $text) {
                $result .= "\n$text";
            }

        }
        $result .= "\n$line";

        if ($return) {
            return $result;
        } else {
            echo $result;
        }
    }

    public function renderJson($return = false) {
        global $_OCTOPUS_DUMPED_CONTENT;

        $result = '';

        foreach($this->_content as $name => $c) {

            foreach($c as $text) {
                $result .= $text;
            }

        }

        $_OCTOPUS_DUMPED_CONTENT[] = $result;

    }

    public static function inJavascriptContext() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public static function inWebContext() {
        return isset($_SERVER['HTTP_USER_AGENT']) && !isset($_GET['callback']) && !isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    /**
     * @return array The names of all enabled error reporting flags.
     */
    public static function &getErrorReportingFlags($er = null) {

        $allExceptDeprecated = E_ALL;

        if (defined('E_DEPRECATED')) {
            $allExceptDeprecated = $allExceptDeprecated & ~E_DEPRECATED;
        }

        $er = $er == null ? error_reporting() : $er;
        $flags = array();

        if (($er & E_ALL) === E_ALL) {
            $flags[] = 'E_ALL';
        } else if ($er & $allExceptDeprecated === $allExceptDeprecated) {
            $flags[] = 'E_ALL (except E_DEPRECATED)';
        }


        if (empty($flags)) {

            $all = array(
                'E_NOTICE', 'E_ERROR', 'E_WARNING', 'E_PARSE', 'E_DEPRECATED',
                'E_USER_NOTICE', 'E_USER_ERROR', 'E_USER_WARNING', 'E_USER_DEPRECATED'
            );

            foreach($all as $level) {

                if (defined($level)) {
                    $val = constant($level);
                    if (($er & $val) === $val) {
                        $flags[] = $level;
                    }
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

        foreach(self::saneBacktrace($bt) as $b) {

            $class = '';
            if ($i == 0) $class = ' sgDebugFirst';
            if ($i == $count - 1) $class .= ' sgDebugLast';
            $class = $class ? ' class="' . $class . '"' : '';

            $func = '';
            if ($b['function']) {
                $func = '<span class="sgDebugBacktraceFunction">' . $b['function'] . '()</span>';

            }

            $file = '';
            if ($b['file']) {
                $file = '<span class="sgDebugBacktraceFile">' . htmlspecialchars($b['file']) . '</span>';
            }

            $line = '';
            if ($b['line']) {
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
        return trim(ob_get_clean());
    }

    public static function saneBacktrace($bt = null) {

        if ($bt === null) {
            $bt = debug_backtrace();
        }

        $result = array();

        foreach($bt as $b) {

            $result[] = array(

                'function' => isset($b['function']) ? $b['function'] : null,
                'file' => isset($b['file']) ? $b['file'] : null,
                'line' => isset($b['line']) ? $b['line'] : null,

            );

        }

        return $result;

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

        if (Octopus_Debug::inWebContext()) {

            $d = new Octopus_Debug('dump_r');
            foreach($args as $arg) {

                $output = Octopus_Debug::dumpToString($arg);
                if (!function_exists('xdebug_call_class')) {
                    $output = htmlspecialchars($output);
                }

                $html = '<pre>' . $output . '</pre>';
                $d->add('var', $html);
            }
            $d->add('context', Octopus_Debug::getArraysHtml());
            $bt = debug_backtrace();
            $d->add('context', Octopus_Debug::getBacktraceHtml($bt));

            $d->add('footer', Octopus_Debug::getErrorReportingHtml());

        } else {

            ini_set('html_errors', 0);

            $d = new Octopus_Debug('dump_r');
            foreach($args as $arg) {
                $d->add('var', Octopus_Debug::dumpToString($arg));
            }

        }

        $d->render();

    }

    }

    /**
     * @return String The results of var_dump for $var.
     */
    function debug_var($var) {
        return Octopus_Debug::dumpToString($var);
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

    /**
     * Prints out a slightly saner backtrace.
     */
    function print_backtrace($limit = 0) {

        $bt = debug_backtrace();

        $count = 0;

        echo "\n";
        foreach(Octopus_Debug::saneBacktrace($bt) as $item) {
            if ($limit && $count >= $limit) {
                break;
            }
            echo "{$item['function']} at {$item['file']}, line {$item['line']}\n";
        }

    }

?>
