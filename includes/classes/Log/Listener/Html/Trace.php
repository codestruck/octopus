<?php

/**
 * Helper that renders a stack trace as HTML.
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Log_Listener_Html_Trace {

	private $trace;

	public function __construct($trace = null) {
		$this->trace = ($trace === null ? debug_backtrace(false) : $trace);
	}

	public function __toString() {

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
        $trace = Octopus_Debug::getNiceBacktrace($this->trace);

        foreach($trace as $b) {


            $func = '<td class="octopusDebugBacktraceFunction">' . $b['function'] . '()</td>';

            $b['file'] = htmlspecialchars($b['file'], ENT_QUOTES, 'UTF-8');

            $file = <<<END
<td class="octopusDebugBacktraceFile" title="{$b['file']}">
END;

            $file .= htmlspecialchars($b['nice_file'], ENT_QUOTES, 'UTF-8');
            $file .= '</td>';

            $line = '<td class="octopusDebugBacktraceLine">';

            if (!empty($b['line'])) {
            	$line .= 'Line ' . $b['line'];
            }

            $line .= '</td>';

            $class = ($i % 2 ? 'octopusDebugOdd' : 'octopusDebugEven');
            if (preg_match('~^octopus/~', $b['nice_file'])) {
                $class .= ' octopusDebugBacktraceSourceSys';
            } else if (preg_match('~^_private/smarty/~', $b['nice_file'])) {
            	$class .= ' octopusDebugBacktraceSourceSmarty';
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

}