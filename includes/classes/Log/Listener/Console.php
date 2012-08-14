<?php

/**
 * A log listener that writes messages formatted for the console (the default
 * output is on stderr).
 * Does not do any log rotation or anything, just writes to an ouput stream.
 * If you want 'true' file logging, use Octopus_Log_Listener_File.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Log_Listener_Console {

    /**
     * The number of lines of stack trace to render. Less than zero means
     * render all lines.
     * @var Number
     */
    public $stackTraceLines = 1;

    /**
     * Whether to render output in color using ANSI terminal codes. If null,
     * color is used only on stdout and stderr.
     * @var
     */
    public $renderInColor = null;

    /**
     * Width, in characters, of output.
     * @var integer
     */
    public $width = 80;

    private $file;

    const CHAR_BOLD_LINE = '=';

    const CHAR_LIGHT_LINE = '-';


    public function __construct($file = 'php://stderr') {
        $this->file = $file;
    }

    public function write($id, $message, $log, $level) {

        $message = $this->formatForDisplay($message, $log, $level);

        if (is_resource($this->file)) {
            fputs($this->file, "\n$message\n");
        } else if (is_string($this->file)) {
            $fp = fopen($this->file, 'w');
            fputs($fp, "\n$message\n");
            fclose($fp);
        }

    }

    /**
     * @param  Mixed  $message  Message being displayed
     * @param  String  $log     Name of log being written to.
     * @param  Number  $level   Log level
     * @return String The formatted output.
     */
    public function formatForDisplay($message, $log, $level, $time = null, $trace = null) {

        if ($time === null) $time = time();

        $width = $this->width;
        $color = $this->renderInColor;

        if ($color === null) {
            $color = ($this->file === 'php://stderr' || $this->file === 'php://stdout');
        }

        if (is_numeric($level)) {
            $level = Octopus_Log::getLevelName($level);
        }

        $dim = $bright = $reset = $whiteBG = $blackText = $levelColor = '';
        $defaultFormat = '';
        $levelColors = array();

        if ($message instanceof Dumpable) {
            $message = $message->__dumpText();
        } else if (is_array($message)) {
            $message = Octopus_Debug::dumpToString($message, 'text', false);
        } else {
            $message = (string)$message;
        }

        if ($color) {

            $dim = "\033[2m";
            $bright = "\033[1m";
            $reset = "\033[0m";

            $whiteBG = "\033[47m";
            $blackText = "\033[30m";

            $levelColors = array(
                'INFO' =>     "\033[34m", // blue
                'WARN' =>     "\033[31m", // yellow
                'ERROR' =>     "\033[31m", // red
            );

            if (isset($levelColors[$level])) {
                $levelColor = $levelColors[$level];
            }

            $defaultFormat = "{$bright}{$whiteBG}{$blackText}";

            // Since we're using colors, break into 80-char lines and
            // pad each one for nice BG colors
            $message = str_replace("\t", "    ", $message);
            $message = wordwrap($message, $width, "\n", true);
            $lines = array();
            foreach(explode("\n", $message) as $line) {

                $lineLen = strlen($line);
                $pad = floor($lineLen / $width);
                if ($pad < $lineLen / $width) {
                    $pad++;
                }
                $lines[] = str_pad($line, $pad * $width);

            }
            $message = implode("\n", $lines);
        }

        $boldLine =     str_repeat(self::CHAR_BOLD_LINE, $width);
        $lightLine =     str_repeat(self::CHAR_LIGHT_LINE, $width);
        $space =         ' ';

        $time = is_numeric($time) ? date('r', floor($time)) : $time;
        $time = str_pad($time, ($width / 2));
        $logAndLevel = "{$log} {$level}";
        $logAndLevel = str_pad($logAndLevel, ($width / 2), ' ', STR_PAD_LEFT);

        $traceAsText = '';

        if ($this->stackTraceLines) {

            $lines = Octopus_Debug::getNiceBacktrace($trace);
            $lines = Octopus_Debug::getMostRelevantTraceLines($this->stackTraceLines > 0 ? $this->stackTraceLines : count($lines), $lines);

            if ($this->stackTraceLines > 0 && count($lines) > $this->stackTraceLines) {
                $lines = array_slice($lines, 0, $this->stackTraceLines);
            }

            foreach($lines as $l) {

                $line = "{$l['nice_file']}, line {$l['line']}";

                if (!empty($l['scope_function'])) {
                    $line .= ' - ' . $l['scope_function'];
                }

                $traceAsText .= "\n" . self::padLinesToWidth($line, $width);;

            }


            if ($traceAsText) {
                $traceAsText = "\n{$lightLine}{$traceAsText}";
            }

        }


        return <<<END
{$defaultFormat}{$levelColor}
{$boldLine}
{$time}{$logAndLevel}
{$lightLine}
{$message}{$traceAsText}
{$boldLine}{$reset}

END;

    }

    private static function padLinesToWidth($lines, $width = 80) {

        $result = array();

        if (is_string($lines)) $lines = explode("\n", $lines);

        foreach($lines as $line) {

            do {

                $slice = substr($line, 0, $width);
                $line = substr($line, $width);

                $result[] = str_pad($slice, $width);

            } while(strlen($line) > 0);

        }

        return implode("\n", $result);

    }

}
