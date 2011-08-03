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
        return "octopusDebug" . (++self::$_idCounter);
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
                e.className = e.className.replace(/octopusDebugTabButtonSelected/g, '') + ' octopusDebugTabButtonSelected';
            } else {
                e.className = e.className.replace(/octopusDebugTabButtonSelected/g, '');
            }
        }
    }


}

function __octopus_toggleRaw(niceID, rawID, buttonID) {

    var nice = __octopus_getElement(niceID),
        raw = __octopus_getElement(rawID),
        button = __octopus_getElement(buttonID);

    if (!(raw && nice && button)) {
        return;
    }

    if (raw.style.display === 'none') {
        nice.style.display = 'none';
        raw.style.display = '';
        button.innerText = 'Hide Raw Data';
    } else {
        nice.style.display = '';
        raw.style.display = 'none';
        button.innerText = 'Show Raw Data';
    }

}

</script>
END;

        self::$css = <<<END

<style type="text/css">
<!--

    div.octopusDebug * {
        font-size: 1em;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    div.octopusDebug {
        background-color: #efefef;
        border: 1px solid #888;
        color: #000;
        font-size: 12px;
        font-family: 'Monaco', 'Andale Mono', 'Consolas', 'Courier New', monospace;
        margin-bottom: 10px;
        max-width: 600px;
        overflow: hidden;
        padding: 0;
        width: 50%;
    }

    div.octopusDebug div.octopusDebugTabs {
    }

    div.octopusDebug div.octopusDebugTabs div.octopusDebugTab {
        max-height: 300px;
        overflow: auto;
        padding: 10px;
        position: relative;
    }

    div.octopusDebug ul.octopusDebugTabButtons {
        background: #ccc;
        overflow: hidden;
    }

    div.octopusDebug ul.octopusDebugTabButtons li {
        float: left;
    }

    div.octopusDebug ul.octopusDebugTabButtons li a {
        color: #333;
        display: block;
        padding: 2px 10px;
        text-decoration: none;
    }

    div.octopusDebug ul.octopusDebugTabButtons li.octopusDebugTabButtonSelected a {
    background: #efefef;
    }

    div.octopusDebug table {
        margin-bottom: 10px;
    }
    div.octopusDebug table tr td,
    div.octopusDebug table tr th {
        padding: 2px;
    }
    div.octopusDebug table tr.octopusDebugOdd td {
        background: #fff;
    }
    div.octopusDebug table tr th {
        text-align: left;
    }

    table.octopusDebugArrayDump {
    }

    table.octopusDebugArrayDump td.octopusDebugArrayKey {
        color: #555;
        padding-right: 10px;
    }

    table.octopusDebugBacktrace {
        width: 100%;
    }

    table.octopusDebugBacktrace tr.octopusDebugBacktraceSourceSys td {
        color: #888;
    }

    table.octopusDebugBordered {
        border-collapse: collapse;
    }

    table.octopusDebugBordered td {
        border: 1px solid #aaa;
    }

    table.octopusDebugResultSetData {
        width: 100%;
    }

    table.octopusDebugResultSetData tbody {
        max-height: 100px;
        overflow: auto;
    }

    div.octopusDebug textarea {
        height: 35px;
        width: 100%;
    }

    div.octopusDebug h3 {
        font-size: 1.1em;
        font-weight: bold;
        margin-bottom: 10px;
    }

    a.octopusDebugToggleRaw {
        color: #888;
        position: absolute;
        right: 10px;
        top: 10px;
    }

    span.octopusDebugString {}

    span.octopusDebugString span.octopusDebugStringLength,
    span.octopusDebugDateFromNumber,
    .octopusDebugArrayIndex,
    .octopusDebugNumberType {
        color: #888;
        font-size: 0.9em;
    }

    pre.octopusDebugExceptionMessage {
        color: #800;
        font-size: 2em;
        margin-bottom: 10px;
    }

    .octopusDebugExceptionSource {
        font-weight: bold;
    }

    div.octopusDebugFooter {
        color: #999;
        font-size: 9px;
        padding: 4px;
    }

    div.octopusDebugFooter ul.octopusDebugErrorReporting {    }

    div.octopusDebugFooter ul.octopusDebugErrorReporting li {
        float: right;
        margin-left: 10px;
    }

-->
</style>

END;
    }

    public function add($name, $content, $raw = '') {
        $this->_content[$name] = compact('content', 'raw');
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
        <div id="{$this->_id}" class="octopusDebug">
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

        $buttons = '<ul class="octopusDebugTabButtons">';
        $tabs = '<div class="octopusDebugTabs">';

        $result = '';
        $index = 0;
        foreach($this->_content as $name => $values) {

            $id = self::getNewId();
            $safeName = htmlspecialchars($name);

            $buttonClass = 'octopusDebugTabButton';
            if ($index === 0) $buttonClass .= ' octopusDebugTabButtonSelected';

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
<div class="octopusDebugFooter">
{$this->_footer}
</div>
END;
        }

        return $buttons . $tabs . $footer;

    }

    private static function buildContentSection($id, $name, $content, $index, $count) {

        $sectionClass = 'octopusDebugTab';
        if ($index === 0) $sectionClass .= ' octopusDebugFirst';
        if ($index === $count - 1) $sectionClass .= ' octopusDebugLast';

        $styleAttr = '';
        if ($index !== 0) $styleAttr = ' style="display:none;"';

        $niceID = self::getNewId();
        $rawID = self::getNewId();

        $html = <<<END
<div id="$id" class="$sectionClass"$styleAttr>
END;

        $html .= <<<END
<div id="$niceID" class="octopusDebugNiceOutput">
{$content['content']}
</div>
END;

        if ($content['raw']) {

            $rawButtonID = self::getNewId();

            $html .= <<<END
<pre id="$rawID" class="octopusDebugRawOutput" style="display: none;">
{$content['raw']}
</pre>
<a id="$rawButtonID" class="octopusDebugToggleRaw" href="#raw" onclick="__octopus_toggleRaw('$niceID', '$rawID', '$rawButtonID'); return false;">Show Raw Data</a>
END;

        }

        $html .= '</div>';

        return $html;
    }

    public function renderText($return = false) {

        $line = str_repeat('-', 80);
        $result = "\n$line";

        foreach($this->_content as $name => $text) {

            $result .= "\n{$text['content']}";

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
<table class="octopusDebugBacktrace" border="0" cellpadding="0" cellspacing="0">
<thead>
    <tr>
        <th class="octopusDebugBacktraceFunction">Function</th>
        <th class="octopusDebugBacktraceFile">File</th>
        <th class="octopusDebugBacktraceLine">Line</th>
    </tr>
</thead>
<tbody>
END;

        $i = 0;
        $count = count($bt);

        foreach(self::saneBacktrace($bt) as $b) {


            $func = '<td class="octopusDebugBacktraceFunction">' . $b['function'] . '()</td>';

            $b['file'] = htmlspecialchars($b['file']);

            $file = <<<END
<td class="octopusDebugBacktraceFile" title="{$b['file']}">
END;

            $file .= htmlspecialchars($b['nice_file']);
            $file .= '</td>';

            $line = '<td class="octopusDebugBacktraceLine">Line ' .
                    (isset($b['line']) ? $b['line'] : '') .
                    '</td>';

            $class = ($i % 2 ? 'octopusDebugOdd' : 'octopusDebugEven');
            if (preg_match('~^octopus/~', $b['nice_file'])) {
                $class .= ' octopusDebugBacktraceSourceSys';
            }

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
        <ul class="octopusDebugErrorReporting">
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
           $html .= '<span class=\"octopusDebugMissingArray">' . $arname . ' (unavailable)</span>';
           return $html;
       }

       $id = self::getNewId();

       if (empty($ar)) {
           $html .= '<span class="octopusDebugEmptyArrayName">' . $arname . ' (Empty)</span><br>';
           continue;
       }

       $html .= "<a href=\"#$arname\" onclick=\"var c = document.getElementById('$id'); if (c.style.display == 'none') c.style.display = ''; else c.style.display = 'none'; return false;\">$arname</a><br />";
       $html .= '<table id="' . $id . '" style="display: none;" class="octopusDebugArrayTable">';


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

    public static function dumpToString($x, $escapeHtml = false, $fancy = true) {

        // var_export chokes on recursive references, but var_dump doesn't.
        // of course, var_dump involves output buffering. yay.

        if (function_exists('xdebug_call_class')) {
            $escapeHtml = false;
        }

        if ($fancy && Octopus_Debug::inWebContext()) {

            if (is_object($x) && $x instanceof Dumpable) {
                $mode = Octopus_Debug::inWebContext() ? 'html' : 'text';
                $result = $x->dump($mode);
                return self::sanitizeDebugOutput($result);
            } else if ($x instanceof Exception) {
                $result = self::dumpExceptionToHtml($x);
                return self::sanitizeDebugOutput($result);
            } else if (is_array($x)) {
                $result = self::dumpArrayToHtml($x);
                return self::sanitizeDebugOutput($result);
            } else if (is_string($x)) {
                $result = self::dumpStringToHtml($x);
                return self::sanitizeDebugOutput($result);
            } else if (is_numeric($x)) {
                $result = self::dumpNumberToHtml($x);
                return self::sanitizeDebugOutput($result);
            }
        }

        ob_start();
        var_dump($x);
        $content = trim(ob_get_clean());

        $result = $escapeHtml ? htmlspecialchars($content) : $content;
        return '<pre>' . self::sanitizeDebugOutput($result) . '</pre>';
    }

    private static function dumpExceptionToHtml($ex) {

        $html = '<div class="octopusDebugException">';

        $html .= '<pre class="octopusDebugExceptionMessage">' . htmlspecialchars($ex->getMessage()) . '</pre>';

        $file = $ex->getFile();
        if (defined('ROOT_DIR') && ROOT_DIR && starts_with($file, ROOT_DIR)) {
            $file = substr($file, strlen(ROOT_DIR));
        }

        $file = htmlspecialchars($file);
        $line = $ex->getLine();

        $html .= <<<END
<div class="octopusDebugExceptionSource">
    <span class="octopusDebugExceptionFile">$file</span>,&nbsp;<span class="octopusDebugExceptionLine">Line $line</span>
</div>
END;

        $html .= '</div>';

        return $html;
    }

    private static function sanitizeDebugOutput($output) {

        if (defined('DB_password') && DB_password) {
            $output = str_replace(DB_password, '[removed from debug output]', $output);
            $output = str_replace(htmlspecialchars(DB_password), '[removed from debug output]', $output);
        }

        return $output;
    }

    private static function dumpNumberToHtml($x) {

        $result = htmlspecialchars($x);
        $type = htmlspecialchars(gettype($x));

        $result .= <<<END
<span class="octopusDebugNumberType">&nbsp;&mdash;&nbsp;$type</span>
END;

        $minDate = strtotime('1990-1-1');
        $maxDate = strtotime('+10 years');

        if ($x >= $minDate && $x <= $maxDate) {
            $date = date('r', $x);
            $result .= '<span class="octopusDebugDateFromNumber">&nbsp;&mdash;&nbsp;' . htmlspecialchars($date) . "</span>";
        }

        return $result;
    }

    private static function dumpStringToHtml($str) {

        $safe = htmlspecialchars($str);
        $length = strlen($str);
        $mblength = mb_strlen($str);

        $niceLength = "$length char" . ($length === 1 ? '' : 's');

        if ($mblength !== $length) {
            $niceLength = "$niceLength ($mblength using mb_strlen)";
        }

        return <<<END
<span class="octopusDebugString">
&quot;$safe&quot;<span class="octopusDebugStringLength">&nbsp;&mdash;&nbsp;$niceLength</span>
</span>
END;
    }

    private static function dumpArrayToHtml($ar) {

        if (empty($ar)) {
            ob_start();
            var_dump($x);
            $content = trim(ob_get_clean());
            return htmlspecialchars($content);
        }

        $result = '<div class="octopusDebugArrayDump">';
        $result .= '<h3>Array - ' . count($ar) . ' item' . (count($ar) === 1 ? '' : 's') . '</h3>';
        $result .= '<table border="0" cellpadding="0" cellspacing="0">';

        $i = 0;
        foreach($ar as $key => $value) {

            $rowClass = ($i % 2) ? 'octopusDebugOdd' : 'octopusDebugEven';

            $key = htmlspecialchars($key);
            $value = self::dumpToString($value, true);

            $index = ($i === $key ? '' : $i);

            $result .= <<<END
<tr class="$rowClass">
    <td class="octopusDebugArrayIndex">$index</td>
    <td class="octopusDebugArrayKey">$key</td>
    <td class="octopusDebugArrayValue">$value</td>
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
            $index = 1;
            $trace = null;

            $trueArgs = array();
            foreach($args as $arg) {

                $trueArgs[] = $arg;

                if ($arg instanceof Exception) {

                    $ex = $arg;
                    while($ex = $ex->getPrevious()) {
                        $trueArgs[] = $ex;
                    }

                }

            }
            $args = $trueArgs;

            foreach($args as $arg) {

                $type = is_object($arg) ? get_class($arg) : gettype($arg);
                $type = preg_replace('/^Octopus_/', '', $type);

                if ($arg instanceof Exception && !$trace) {
                    $trace = $arg->getTrace();
                }

                $output = Octopus_Debug::dumpToString($arg, true);
                $html = $output;

                if (count($args) > 1) {
                    $tab = "#$index-$type";
                } else {
                    $tab = $type;
                }
                $raw = '';

                if ($arg && is_object($arg) && ($arg instanceof Dumpable || $arg instanceof Exception)) {
                    $raw = Octopus_Debug::dumpToString($arg, true, false);
                }

                $d->add($tab, $html, $raw);

                $index++;
            }


            if (!$trace) $trace = debug_backtrace();
            $d->add('Backtrace', Octopus_Debug::getBacktraceHtml($trace));

            foreach(array('_GET', '_POST', '_SERVER', '_SESSION') as $arname) {

                if (isset($GLOBALS[$arname]) && !empty($GLOBALS[$arname])) {
                    $html = '<pre class="octopusDebugArray">' .
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
