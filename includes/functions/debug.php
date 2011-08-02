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

// Dumpable interface {{{

interface Dumpable {

    /**
     * Returns debug info on this object.
     * @param $mode String 'html' or 'text'
     * @return String dumped content.
     */
    function dump($mode);

}

// }}}

// Octopus_Debug Class {{{

/**
 * Helper class used to render debug information on the client.
 */
class Octopus_Debug {

    public static $css;
    public static $js;
    private $_id;
    private $_content = array();
    private $_options;
    private $_footer;

    private static $_renderedCss = false;
    private static $_renderedJs = false;
    private static $_idCounter = 0;


    private static function getNewId() {
        return "sgDebug" . (++self::$_idCounter);
    }

    public function __construct($id) {
        $this->_id = $id;

        self::$js = <<<END
<script type="text/javascript">

function __octopus_getElement(id) {

    if (document.getElementById) {
        return document.getElementById(id);
    } else if (document.all) {
        return document.all(id);
    }
}

function __octopus_jQuery_exists() {
    return typeof($) !== 'undefined' && typeof($.fn) !== 'undefined' && typeof($.fn.show) !== 'undefined';
}

function __octopus_show(id) {

    if (__octopus_jQuery_exists()) {
        $('#' + id).show('fast');
        return;
    }

    var e = __octopus_getElement(id);

    if (e) {
        e.style.display = '';
    }

}

function __octopus_hide(id) {

    if (__octopus_jQuery_exists()) {
        $('#' + id).hide('fast');
        return;
    }

    var e = __octopus_getElement(id);

    if (e) {
        e.style.display = '';
    }
}

function __octopus_toggle(id) {

    if (__octopus_jQuery_exists()) {
        $('#' + id).toggle('fast');
        return;
    }

    var e = __octopus_getElement(id);

    if (e) {

        if (/^none$/i.test(e.style.display)) {
            e.style.display = '';
        } else {
            e.style.display = 'none';
        }

    }
}

function __octopus_openTab(id, buttonID) {

    var tab = __octopus_getElement(id);
    if (!tab) return;

    for(var e = tab.parentNode.firstChild; e; e = e.nextSibling) {

        if (e === tab) {
            e.style.display = '';
        } else {
            e.style.display = 'none';
        }

    }

    var button = __octopus_getElement(buttonID);
    if (button) {
        for(var e = button.parentNode.firstChild; e; e = e.nextSibling) {

            if (e === button) {
                e.className = e.className.replace(/sgDebugTabButtonSelected/g, '') + ' sgDebugTabButtonSelected';
            } else {
                e.className = e.className.replace(/sgDebugTabButtonSelected/g, '');
            }
        }
    }


}

</script>
END;

        self::$css = <<<END

<style type="text/css">
<!--

    div.sgDebug * {
        font-size: 1em;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    div.sgDebug {
        background-color: #efefef;
        border: 1px solid #888;
        color: #000;
        font-size: 12px;
        font-family: 'Monaco', 'Andale Mono', 'Consolas', 'Courier New', monospace;
        margin-bottom: 10px;
        max-width: 600px;
        padding: 0;
        width: 50%;
    }

    div.sgDebug div.sgDebugTabs {
    }

    div.sgDebug div.sgDebugTabs div.sgDebugTab {
        max-height: 300px;
        overflow: auto;
        padding: 10px;
    }

    div.sgDebug ul.sgDebugTabButtons {
        background: #ccc;
        overflow: hidden;
    }

    div.sgDebug ul.sgDebugTabButtons li {
        float: left;
    }

    div.sgDebug ul.sgDebugTabButtons li a {
        color: #333;
        display: block;
        padding: 5px 10px;
        text-decoration: none;
    }

    div.sgDebug ul.sgDebugTabButtons li.sgDebugTabButtonSelected a {
    background: #efefef;
    }

    .sgDebugErrorReporting li {
        float: right;
        list-style: none;
        margin-left: 20px;
    }

    div.sgDebug table {}
    div.sgDebug table tr td {
        padding: 2px;
    }
    div.sgDebug table tr.sgDebugOdd td {
        background: #fff;
    }
    div.sgDebug table tr th {
        text-align: left;
    }

    table.sgDebugArrayDump {
    }

    table.sgDebugArrayDump td.sgDebugArrayKey {
        color: #555;
        padding-right: 10px;
    }

    table.sgDebugBacktrace {
        width: 100%;
    }



-->
</style>

END;
    }

    public function add($name, $content) {
        $this->_content[$name] = $content;
    }

    public function setFooter($content) {
        $this->_footer = $content;
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

        if (!self::$_renderedJs || $return) {
            $result .= self::$js;
            if (!$return) self::$_renderedJs = true;
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

        $buttons = '<ul class="sgDebugTabButtons">';
        $tabs = '<div class="sgDebugTabs">';

        $result = '';
        $index = 0;
        foreach($this->_content as $name => $values) {

            $id = preg_replace('/[^a-z0-9_-]/i', '_', $name);
            $safeName = htmlspecialchars($name);

            $buttonClass = 'sgDebugTabButton';
            if ($index === 0) $buttonClass .= ' sgDebugTabButtonSelected';

            $buttonID = self::getNewId();

            $buttons .= <<<END
<li id="$buttonID" class="$buttonClass"><a href="#" onclick="__octopus_openTab('$id', '$buttonID'); return false;">$safeName</a></li>
END;
            $tabs .= self::buildContentSection($id, $name, $values, $index, $count);

            $index++;
        }

        $buttons .= '</ul>';
        $tabs .= '</div>';
        $footer = '';

        if ($this->_footer) {
            $footer = <<<END
<
END;
        }

        return $tabs . $buttons;

    }

    private static function buildContentSection($id, $name, $content, $index, $count) {

        $sectionClass = 'sgDebugTab';
        if ($index === 0) $sectionClass .= ' sgDebugFirst';
        if ($index === $count - 1) $sectionClass .= ' sgDebugLast';

        $styleAttr = '';
        if ($index !== 0) $styleAttr = ' style="display:none;"';

        $html = <<<END
<div id="$id" class="$sectionClass"$styleAttr>
END;

        $html .= $content;
        $html .= '</div>';

        return $html;
    }

    public function renderText($return = false) {

        $line = str_repeat('-', 80);
        $result = "\n$line";

        foreach($this->_content as $name => $text) {

            $result .= "\n$text";

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

        $id = self::getNewId();

        $html = <<<END
<table class="sgDebugBacktrace" border="0" cellpadding="0" cellspacing="0">
<thead>
    <tr>
        <th class="sgDebugBacktraceFunction">Function</th>
        <th class="sgDebugBacktraceFile">File</th>
        <th class="sgDebugBacktraceLine">Line</th>
    </tr>
</thead>
<tbody>
END;

        $i = 0;
        $count = count($bt);

        foreach(self::saneBacktrace($bt) as $b) {

            $class = ($i % 2 ? 'sgDebugOdd' : 'sgDebugEven');

            $func = '<td class="sgDebugBacktraceFunction">' . $b['function'] . '()</td>';

            $b['file'] = htmlspecialchars($b['file']);

            $file = <<<END
<td class="sgDebugBacktraceFile" title="{$b['file']}">
END;

            $file .= htmlspecialchars($b['nice_file']);
            $file .= '</td>';

            $line = '<td class="sgDebugBacktraceLine">Line ' .
                    (isset($b['line']) ? $b['line'] : '') .
                    '</td>';

            $html .= <<<END
            <tr class="$class">
                $func
                $file
                $line
            </tr>
END;

            $i++;

        }

        $html .= '</tbody></table>';

        return $html;
    }

    public static function getErrorReportingHtml() {

        $flags = implode(' | ', self::getErrorReportingFlags());

        $display_errors = ini_get('display_errors') ? 'on' : 'off';

        return <<<END
        <ul class="sgDebugErrorReporting">
        <li>error_reporting: $flags</li>
        <li>display_errors: $display_errors</li>
        </ul>
END;


    }

    /**
     * Returns HTML for the various system arrays
     */
    public static function getGlobalArrayHtml($arname) {

        $html = '';

       eval("\$ar = isset($arname) ? $arname : false;");
       if ($ar === false) {
           $html .= '<span class=\"sgDebugMissingArray">' . $arname . ' (unavailable)</span>';
           return $html;
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

       return $html;

    }

    public static function dumpToString($x, $escapeHtml = false, $useDumpable = true) {

        // var_export chokes on recursive references, but var_dump doesn't.
        // of course, var_dump involves output buffering. yay.

        if (function_exists('xdebug_call_class')) {
            $escapeHtml = false;
        }

        if ($useDumpable) {

            if (is_object($x) && $x instanceof Dumpable) {
                $mode = Octopus_Debug::inWebContext() ? 'html' : 'text';
                $content = $x->dump($mode);
                return $escapeHtml ? htmlspecialchars($content) : $content;
            }

        }

        if (Octopus_Debug::inWebContext() && is_array($x)) {
            return self::dumpArrayToHtml($x);
        }

        ob_start();
        var_dump($x);
        $content = trim(ob_get_clean());

        return $escapeHtml ? htmlspecialchars($content) : $content;
    }

    private static function dumpArrayToHtml($ar) {

        if (empty($ar)) {
            ob_start();
            var_dump($x);
            $content = trim(ob_get_clean());
            return htmlspecialchars($content);
        }

        $result = '<table class="sgDebugArrayDump" border="0" cellpadding="0" cellspacing="0">';

        $i = 0;
        foreach($ar as $key => $value) {

            $rowClass = ($i % 2) ? 'sgDebugOdd' : 'sgDebugEven';

            $key = htmlspecialchars($key);
            $value = self::dumpToString($value, true);

            $result .= <<<END
<tr class="$rowClass">
    <td class="sgDebugArrayKey">$key</td>
    <td class="sgDebugArrayValue">$value</td>
</tr>
END;
                $i++;

        }

        $result .= '</table>';

        return $result;
    }

    public static function saneBacktrace($bt = null) {

        if ($bt === null) {
            $bt = debug_backtrace();
        }

        $result = array();

        foreach($bt as $b) {

            $item = array(

                'function' => isset($b['function']) ? $b['function'] : null,
                'file' => isset($b['file']) ? $b['file'] : null,
                'line' => isset($b['line']) ? $b['line'] : null,

            );

            if (defined('ROOT_DIR') && starts_with($item['file'], ROOT_DIR)) {
                $item['nice_file'] = substr($b['file'], strlen(ROOT_DIR));
            } else {
                $item['nice_file'] = $item['file'];
            }

            $result[] = $item;
        }

        return $result;

    }

}

// }}}

    if (!function_exists('dump_r')) {

    function enable_dump_r($enable = true) {

        if (!isset($GLOBALS['__OCTOPUS_DISABLE_DUMP_R'])) {
            $GLOBALS['__OCTOPUS_DISABLE_DUMP_R'] = 0;
        }

        if ($enable) {
            $GLOBALS['__OCTOPUS_DISABLE_DUMP_R'] = max(0, $GLOBALS['__OCTOPUS_DISABLE_DUMP_R'] - 1);
        } else {
            $GLOBALS['__OCTOPUS_DISABLE_DUMP_R']++;
        }

    }

    function disable_dump_r() {
        enable_dump_r(false);
    }

    /**
     * Outputs the arguments passed to it along w/ debugging info.
     * @param mixed Any arguments you want dumped.
     */
    function dump_r() {

        if (!empty($GLOBALS['__OCTOPUS_DISABLE_DUMP_R'])) {
            return;
        }

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

                $output = Octopus_Debug::dumpToString($arg, true);

                $html = '<pre>' . $output . '</pre>';
                $d->add('Variable', $html);
            }

            $bt = debug_backtrace();
            $d->add('Backtrace', Octopus_Debug::getBacktraceHtml($bt));

            foreach(array('_GET', '_POST', '_SERVER', '_SESSION') as $arname) {

                if (isset($GLOBALS[$arname]) && !empty($GLOBALS[$arname])) {
                    $html = '<pre class="sgDebugArray">' .
                            Octopus_Debug::dumpToString($GLOBALS[$arname], true) .
                            '</pre>';
                    $d->add('$' . $arname, $html);
                }

            }


            $d->setFooter(Octopus_Debug::getErrorReportingHtml());

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
