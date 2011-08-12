<?php
/*:folding=explicit:collapseFolds=1:*/

$_OCTOPUS_DUMPED_CONTENT = array();

// Dumpable interface {{{

/**
 * Implement this interface to have greater control over how you class is
 * displayed in dump_r output.
 *
 */
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
 * Helper class used to render debug information, either as HTML or plain text.
 */
class Octopus_Debug {

    // Fields {{{

    // Standard Debug CSS {{{
    public static $css = <<<END


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
        max-width: 1000px;
        overflow: hidden;
        padding: 0;
    }

    div.octopusDebug div.octopusDebugTabs {
    }

    div.octopusDebug div.octopusDebugTabs div.octopusDebugTab {
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

    div.octopusDebugArrayDump table td.octopusDebugArrayKey {
        color: #555;
        padding-right: 10px;
    }


    table.octopusDebugBacktrace {
        width: 100%;
    }

    table.octopusDebugBacktrace tr.octopusDebugFirst td {
        font-weight: bold;
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
        background: #888;
        color: #efefef;
        padding: 3px;
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

    div.octopusDebugExceptionMessage {
        color: #800;
        font-size: 1.2em;
        margin-bottom: 10px;
    }

    div.octopusDebugExceptionSource {
        font-weight: bold;
        margin-bottom: 10px;
    }

    div.octopusDebugExceptionTrace {
        border-top: 1px dotted #888;
        padding-top: 10px;
    }

    div.octopusDebugFooter {
        color: #999;
        font-size: 9px;
        overflow: hidden;
        padding: 5px;
    }

    div.octopusDebugFooter ul.octopusDebugErrorReporting {    }

    div.octopusDebugFooter ul.octopusDebugErrorReporting li {
        float: right;
        margin-left: 10px;
    }

    div.octopusDebugLine {
        border-bottom: 1px dotted #888;
        padding: 4px 0;
        margin: 4px 0;
    }

    div.octopusDebugLine.octopusDebugLast {
        border-bottom: 0;
        margin-bottom: 0;
    }

    span.octopusDebugNull,
    span.octopusDebugBoolean {
        color: #005;
        font-weight: bold;
    }

-->
</style>


END;
    // End Standard Debug CSS }}}

    // Standard Debug Javascript {{{

    public static $js = <<<END

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
        } else if (e.style) {
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

    private $_id;
    private $_content = array();
    private $_variables = array();
    private $_options;
    private $_footer;


    private static $_renderedCss = false;
    private static $_renderedJs = false;
    private static $_idCounter = 0;

    // }}}

    // }}}

    // Constructor {{{

    public function __construct($id = '__octopus_debug') {

        $this->_id = $id;
    }

    // }}}

    // Public Methods {{{

    public function add($name, $content, $raw = '') {
        $this->_content[$name] = compact('content', 'raw');
    }

    public function addSquashedRedirect($path, $reason = '') {
        $name = 'Suppressed Redirect';
        if ($reason) $name = "$name ($reason)";
        $this->add($name, $path);
    }

    /**
     * Adds a single variable to the debug output.
     */
    public function addVariable($value, $name = null, $raw = null) {

        $type = is_object($value) ? get_class($value) : gettype($value);

        if ($raw === null) {

            if ($value && is_object($value) && ($value instanceof Dumpable || $value instanceof Exception)) {
                $raw = trim(Octopus_Debug::dumpToString($value, true, false));
            }

        }

        $this->_variables[] = compact('type', 'name', 'value', 'raw');
    }

    public static function dumpToString($x, $escapeHtml = false, $fancy = true) {

        if (function_exists('xdebug_call_class')) {
            $escapeHtml = false;
        }

        if ($fancy) {

            $result = null;

            if (Octopus_Debug::inWebContext()) {

                if ($x === null) {
                    $result = '<span class="octopusDebugNull">NULL</span>';
                } else if ($x === true || $x === false) {
                    $result =  '<span class="octopusDebugBoolean">' . ($x ? 'TRUE' : 'FALSE') . '</span>';
                } else if (is_object($x) && $x instanceof Dumpable) {
                    $result = $x->dump('html');
                } else if ($x instanceof Exception) {
                    $result = self::dumpExceptionToHtml($x);
                } else if (is_array($x)) {
                    $result = self::dumpArrayToHtml($x);
                } else if (is_string($x)) {
                    $result = self::dumpStringToHtml($x);
                } else if (is_numeric($x)) {
                    $result = self::dumpNumberToHtml($x);
                }

            } else {

                if ($x === null) {
                    $result = 'NULL';
                } else if ($x === true || $x === false) {
                    $result = $x ? 'TRUE' : 'FALSE';
                } else if (is_string($x)) {
                    $result = self::dumpStringToText($x);
                } else if (is_object($x) && $x instanceof Dumpable) {
                    $result = $x->dump('text');
                    if ($result === null) $result = '';
                }

            }

            if ($result !== null) {
                return self::sanitizeDebugOutput($result);
            }

        }

        ob_start();
        // NOTE: var_export chokes on recursive references, and var_dump is
        // slightly better at handling them.
        var_dump($x);
        $result = self::sanitizeDebugOutput(trim(ob_get_clean()));

        if ($escapeHtml) {
            $result = htmlspecialchars($result);
        }

        if (Octopus_Debug::inWebContext()) {
            $result = "<pre>$result</pre>";
        }

        return $result;
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
            if ($i === 0) $class .= ' octopusDebugFirst';

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

    public static function inJavascriptContext() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public static function inWebContext() {
        return isset($_SERVER['HTTP_USER_AGENT']) && !isset($_GET['callback']) && !isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function render($return = false) {

        if (self::inJavascriptContext()) {
            return $this->renderJson($return);
        } else if (self::inWebContext()) {
            return $this->renderHtml($return);
        }  else {
            return $this->renderText($return);
        }
    }

    /* renderHtml($return = false) {{{ */
    public function renderHtml($return = false) {

        $text = $this->renderText(true);
        $text = preg_replace('/-{2,}/', '-', $text);

        $result = <<<END
<!--

BEGIN dump_r Output {{{

Here is a plain-text version of what's below in case you need it:

$text
-->

END;

        if (!self::$_renderedCss || $return) {
            $result .= self::$css;
            if (!$return) self::$_renderedCss = true;
        }

        if (!self::$_renderedJs || $return) {
            $result .= self::$js;
            if (!$return) self::$_renderedJs = true;
        }

        $content = $this->getContentHtml();

        $result .= <<<ENDHTML
        <div id="{$this->_id}" class="octopusDebug">
            $content
        </div>
<!-- END dump_r Output }}} -->
ENDHTML;

        if ($return) {
            return $result;
        } else {
            echo $result;
        }

    } /* }}} */

    /* renderJson($return = false) {{{ */
    public function renderJson($return = false) {

        global $_OCTOPUS_DUMPED_CONTENT;

        $result = '';

        foreach($this->_content as $name => $c) {

            foreach($c as $text) {
                $result .= $text;
            }

        }

        $_OCTOPUS_DUMPED_CONTENT[] = $result;
    }/* }}} */

    /* renderText($return = false) {{{ */
    public function renderText($return = false) {

        $content = array();
        foreach($this->_variables as $var) {

            if (empty($var['name'])) {
                $var['name'] = $var['type'];
            } else {
                $var['name'] .= " ({$var['type']})";
            }

            $content[$var['name']] = self::dumpToString($var['value'], false, true);
        }
        foreach($this->_content as $name => $c) {
            $content[$name] = $c['content'];
        }

        if (empty($content)) {
            return;
        }

        $borderChar = '*';
        $hLineChar = '*';
        $vLineChar = '|';

        $width = 80;
        $maxLabelWidth = floor($width / 3) - (strlen($borderChar)  + strlen($vLineChar) + 2);

        $hBorder = str_repeat($borderChar, $width);
        $hLine = "$borderChar" . str_repeat($hLineChar, $width - ((strlen($borderChar) * 2))) . "$borderChar";

        $labelWidth = 0;
        foreach($content as $label => $text) {
            $l = min(strlen($label), $maxLabelWidth);
            if ($l > $labelWidth) {
                $labelWidth = $l;
            }
        }

        $textWidth = $width - ($labelWidth + strlen($borderChar) + 2) - 4;

        $result = "$hBorder\n";
        $first = true;

        foreach($content as $label => $text) {

            if (!$first) {
                $result .= "$hLine\n";
            }

            $label = wordwrap($label, $labelWidth, "\n", true);
            $text = wordwrap($text, $textWidth, "\n", true);

            $label = explode("\n", $label);
            $text = explode("\n", $text);

            while(count($label) || count($text)) {

                $labelLine = count($label) ? array_shift($label) : false;
                $textLine = count($text) ? array_shift($text) : false;

                $labelLine .= str_repeat(' ', $labelWidth - strlen($labelLine));
                $textLine .= str_repeat(' ', $textWidth - strlen($textLine));

                $line = "$borderChar ";

                if ($labelLine !== false) {
                    $line .= $labelLine . " $vLineChar ";
                }

                $line .= "$textLine $borderChar";

                $result .= "$line\n";
            }

            $first = false;
        }

        $result .= "$hBorder";

        if ($return) {
            return $result;
        } else {
            echo "\n$result\n";
        }
    } /* }}} */

    /* saneBacktrace($bt = null) {{{ */
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
    } /* }}} */

    public function setFooter($content) {
        $this->_footer = $content;
    }

    // End Public Methods }}}

    // Private Methods {{{

    /* buildTab($tab, $id, $index, $count) {{{ */
    private static function buildTab($tab, $id, $index, $count) {

        $tabClass = 'octopusDebugTab';
        if ($index === 0) $tabClass .= ' octopusDebugFirst';
        if ($index === $count - 1) $tabClass .= ' octopusDebugLast';

        $styleAttr = '';
        if ($index !== 0) $styleAttr = ' style="display:none;"';

        if (is_string($tab)) {
            $tab = array('content' => $tab);
        } else if (is_array($tab['content'])) {

            $lineIndex = 0;
            $linesHtml = '';
            foreach($tab['content'] as $line) {

                $lineClass = 'octopusDebugLine';
                if ($lineIndex === 0) $lineClass .= ' octopusDebugFirst';
                if ($lineIndex === count($tab['content']) - 1) $lineClass .= ' octopusDebugLast';

                $linesHtml .= <<<END
<div class="$lineClass">
$line
</div>
END;

                $lineIndex++;
            }

            $tab['content'] = $linesHtml;
        }

        $niceBlockID = self::getNewId();

        $nice = <<<END
<div id="$niceBlockID" class="octopusDebugNiceOutput">
{$tab['content']}
</div>
END;

        $raw = '';
        if (!empty($tab['raw'])) {

            $rawButtonID = self::getNewId();
            $rawBlockID = self::getNewId();

            $raw = <<<END
<pre id="$rawBlockID" class="octopusDebugRawOutput" style="display: none;">
{$tab['raw']}
</pre>
<a id="$rawButtonID" class="octopusDebugToggleRaw" href="#raw" onclick="__octopus_toggleRaw('$niceBlockID', '$rawBlockID', '$rawButtonID'); return false;">Show Raw Data</a>
END;

        }



        return <<<END
<div id="$id" class="$tabClass"$styleAttr>
    $nice
    $raw
</div>
END;
    } /* }}} */

    /* dumpArrayToHtml($ar) {{{ */
    private static function dumpArrayToHtml($ar) {

        if (empty($ar)) {
            ob_start();
            var_dump($ar);
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

        $result .= '</table></div>';

        return $result;
    } /* }}} */

    /* dumpExceptionToHtml($ex) {{{ */
    private static function dumpExceptionToHtml($ex) {

        $html = '<div class="octopusDebugException">';

        $html .= '<div class="octopusDebugExceptionMessage">' . htmlspecialchars($ex->getMessage()) . '</div>';

        $file = $ex->getFile();
        if (defined('ROOT_DIR') && ROOT_DIR && starts_with($file, ROOT_DIR)) {
            $file = substr($file, strlen(ROOT_DIR));
        }

        $file = htmlspecialchars($file);
        $line = $ex->getLine();
        $trace = self::getBacktraceHtml($ex->getTrace());

        $html .= <<<END
<div class="octopusDebugExceptionTrace">
$trace
</div>
END;

        $html .= '</div>';

        return $html;
    } /* }}} */

    /* dumpNumberToHtml($x) {{{ */
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
    } /* }}} */

    /* dumpStringToHtml($str) {{{ */
    private static function dumpStringToHtml($str) {

        $length = self::getNiceStringLength($str);
        $safe = htmlspecialchars($str);

        return <<<END
<span class="octopusDebugString">
&quot;$safe&quot;<span class="octopusDebugStringLength">&nbsp;&mdash;&nbsp;$length</span>
</span>
END;
    } /* }}} */

    /* dumpStringToText($str) {{{ */
    private static function dumpStringToText($str) {
        $length = self::getNiceStringLength($str);
        return '"' . $str . '" - ' . $length;
    } /* }}} */

    /* getContentHtml() {{{ */
    private function getContentHtml() {

        $buttons = array();
        $tabs = array();

        $varsTab = array('content' => array());


        foreach($this->_variables as $var) {

            $hasName = !empty($var['name']);

            if (empty($var['name'])) {
                $var['name'] = $var['type'];
            }

            if (!empty($var['raw']) || $hasName) {
                // for named things, or things with raw content, display them
                // on their own tab.
                $buttons[] = $var['name'];
                $tabs[] = array('content' => self::dumpToString($var['value']), 'raw' => $var['raw']);
            } else {
                $varsTab['content'][] = self::dumpToString($var['value']);
            }

        }

        if (!empty($varsTab['content'])) {
            // prepend simple variable list to tabs
            array_unshift($tabs, $varsTab);
            array_unshift($buttons, 'Variable' . (count($varsTab['content']) === 1 ? '' : 's'));
        }

        foreach($this->_content as $name => $content) {
            $buttons[] = $name;
            $tabs[] = $content;
        }

        $buttonsHtml = '<ul class="octopusDebugTabButtons">';
        $tabsHtml = '<div class="octopusDebugTabs">';
        $index = 0;
        $count = count($tabs);

        while(!empty($tabs)) {

            $button = htmlspecialchars(array_shift($buttons));
            $tab = array_shift($tabs);

            $buttonID = self::getNewId();
            $tabID = self::getNewId();

            $buttonClass = 'octopusDebugTabButton';
            if ($index === 0) $buttonClass .= ' octopusDebugTabButtonSelected';

            $buttonsHtml .= <<<END
<li id="$buttonID" class="$buttonClass"><a href="#" onclick="__octopus_openTab('$tabID', '$buttonID'); return false;">$button</a></li>
END;

            $tabsHtml .= self::buildTab($tab, $tabID, $index, $count);

            $index++;
        }

        $buttonsHtml .= '</ul>';
        $tabsHtml .= '</div>';

        $footerHtml = '';

        if ($this->_footer) {
            $footerHtml = <<<END
<div class="octopusDebugFooter">
{$this->_footer}
</div>
END;
        }

        return $buttonsHtml . $tabsHtml . $footerHtml;
    } /* }}} */

    /* getNewId() {{{ */
    private static function getNewId() {
        return "octopusDebug" . (++self::$_idCounter);
    } /* }}} */

    /* getNiceStringLength($str) {{{ */
    private static function getNiceStringLength($str) {

        $length = strlen($str);
        $mblength = mb_strlen($str);

        $niceLength = "$length char" . ($length === 1 ? '' : 's');

        if ($mblength !== $length) {
            $niceLength = "$niceLength ($mblength using mb_strlen)";
        }

        return $niceLength;
    } /* }}} */

    /* sanitizeDebugOutput($output) {{{ */
    private static function sanitizeDebugOutput($output) {

        if (defined('DB_password') && DB_password) {
            $output = str_replace(DB_password, '[removed from debug output]', $output);
            $output = str_replace(htmlspecialchars(DB_password), '[removed from debug output]', $output);
        }

        return $output;
    } /* }}} */

    // End Private Methods }}}

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

        if ((defined('LIVE') && LIVE) || (defined('STAGING') && STAGING)) {
            // TODO: Log?
            return;
        }

        if (!empty($GLOBALS['__OCTOPUS_DISABLE_DUMP_R'])) {
            return;
        }

        $args = func_get_args();
        if (empty($args)) return;

        if (function_exists('cancel_redirects')) {
            cancel_redirects();
        }

        if (Octopus_Debug::inWebContext()) {

            $d = new Octopus_Debug('dump_r');
            $index = 1;
            $showTrace = null;

            $trueArgs = array();
            foreach($args as $arg) {

                $trueArgs[] = $arg;

                if ($arg instanceof Exception) {

                    $ex = $arg;
                    while($ex = $ex->getPrevious()) {
                        $trueArgs[] = $ex;
                    }
                    if ($showTrace === null) $showTrace = false;

                } else {
                    $showTrace = true;
                }

            }
            $args = $trueArgs;

            foreach($args as $arg) {
                $d->addVariable($arg);
            }

            if ($showTrace !== false) {
                $trace = debug_backtrace();
                $d->add('Backtrace', Octopus_Debug::getBacktraceHtml($trace));
            }

            foreach(array('_GET', '_POST', '_SERVER', '_SESSION') as $arname) {

                if (isset($GLOBALS[$arname]) && !empty($GLOBALS[$arname])) {
                    $d->addVariable($GLOBALS[$arname], "\$$arname");
                }

            }

            $d->setFooter(Octopus_Debug::getErrorReportingHtml());

        } else {

            ini_set('html_errors', 0);

            $d = new Octopus_Debug('dump_r');
            foreach($args as $arg) {
                $d->addVariable($arg);
            }

        }

        $d->render();

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
     * @return Array When in a JSON/Javascript context, all content passed to
     * dump_r so far.
     */
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

} // if (!function_exists('dump_r))

?>
