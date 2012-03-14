<?php
/**
 * A standalone logging and debugging framework. This file has no Octopus
 * dependencies--it can be included in any project you like. If used in an
 * Octopus context, however, it is doubley awesome.
 *
 * Mode Flags
 *
 * Debugging supports Octopus's notion of an
 *
 */

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Manages logging for an app. Dispatches log messages out to listeners, which
 * do the actual physical logging.
 * @see ::addListener
 * @see ::write
 */
class Octopus_Log {

	/**
	 * Logging level reserved for debugging messages. Should be disabled
	 * in a production environment.
	 */
	const LEVEL_DEBUG = 0;

	/**
	 * Logging level for informational messages.
	 */
	const LEVEL_INFO = 1;

	/**
	 * Logging level for warnings.
	 */
	const LEVEL_WARN = 2;

	/**
	 * Alias for ::LEVEL_WARN
	 */
	const LEVEL_WARNING = 2;

	/**
	 * Logging level for errors.
	 */
	const LEVEL_ERROR = 3;

	/**
	 * Logging level for fatal errors.
	 */
	const LEVEL_FATAL = 4;

	/**
	 * Log level used for null logging. Messages logged at this level will
	 * never get logged.
	 */
	const LEVEL_NONE = PHP_INT_MAX;

	private static $listeners = array();
	private static $minLevel = self::LEVEL_NONE;
	private static $callCount = 0;
	private static $writeCount = 0;

	private static $levelsByName = null;
	private static $namesByLevel = null;

	private function __construct() { }

	/**
	 * Adds a logging listener. Listeners are responsible for actually doing
	 * something with log messages -- writing files, sending emails, etc.
	 *
	 * You can call this function like this to only receive messages for a
	 * particular log:
	 *
	 *	Octopus_Log::addListener('payments', Octopus_Log::INFO, 'myfunction')
	 *
	 * Or like this to receive messages for all logs:
	 *
	 *  Octopus_Log::addListener(Octopus_Log::INFO, 'myfunction')
	 *
	 * Or like this to receive messages for all levels in all logs:
	 *
	 * 	Octopus_Log::addListener('myfunction');
	 *
	 * @param $log The name of the log to which this listener should apply.
	 * @param $minLevel The minimum required level that messages must be at
	 * for this listener to be fired.
	 * @param Function $func The actual function that does the logging.
	 * Gets called like this:
	 *
	 *		$func($message, $log, $level)
	 *
	 * Where $message is the logging message, $log is the name of the log,
	 * and $level is the logging level of the message. This can also be an
	 * object with a 'write' method in the same format as above.
	 */
	public static function addListener($log, $minLevel = null, $func = null) {

		if ($minLevel === null && $func === null) {
			// Allow ::addListener('my_logging_function');
			$func = $log;
			$minLevel = self::LEVEL_DEBUG;
			$log = true;
		} else if (is_numeric($log) && $log >= self::LEVEL_DEBUG && $log <= self::LEVEL_FATAL) {
			$func = $minLevel;
			$minLevel = $log;
			$log = true;
		}

		if ($minLevel < self::$minLevel) {
			self::$minLevel = $minLevel;
		}

		if (is_object($func)) {
			$func = array($func, 'write');
		}

		self::$listeners[] = compact('log', 'minLevel', 'func');

	}

	/**
	 * Shortcut for writing debug messages. You can call it like this:
	 *
	 *	::debug('my log', 'here is my message content')
	 *
	 * or like this (gets written to app.log):
	 *
	 *	::debug('here is my message content')
	 */
	public static function debug() {
		if (self::$minLevel <= self::LEVEL_DEBUG) {
			return self::doShortcut(Octopus_Log::LEVEL_DEBUG, func_get_args());
		}
	}

	/**
	 * Shortcut for writing error messages. You can call it like this:
	 *
	 *	::error('my log', 'here is my message content')
	 *
	 * or like this (gets written to app.log):
	 *
	 *	::error('here is my message content')
	 */
	public static function error() {
		if (self::$minLevel <= self::LEVEL_ERROR) {
			return self::doShortcut(Octopus_Log::LEVEL_ERROR, func_get_args());
		}
	}

	/**
	 * Shortcut for writing fatal messages. You can call it like this:
	 *
	 *	::fatal('my log', 'here is my message content')
	 *
	 * or like this (gets written to app.log):
	 *
	 *	::fatal('here is my message content')
	 */
	public static function fatal() {
		if (self::$minLevel <= self::LEVEL_FATAL) {
			return self::doShortcut(Octopus_Log::LEVEL_FATAL, func_get_args());
		}
	}

	/**
	 * Formats a log entry as JSON.
	 * @param  Mixed $message   	Message being logged
	 * @param  String $log       	Name of the log being written
	 * @param  Number $level     	Level of the message
	 * @param  Number $timestamp 	Timestamp for the entry
	 * @param  Array  $stack		Stack trace array.
	 * @return String JSON for the log message.
	 */
	public static function formatJson($message, $log, $level, $timestamp, $stack) {

		$message = array(
			'time' => date('r', $timestamp),
			'log' => $log,
			'level' => self::getLevelName($level),
			'message' => $message,
			'trace' => self::formatStackTrace($stack),
		);

		return json_encode($message);
	}

	/**
	 * @return Number The # of times ::write has been called.
	 */
	public static function getCallCount() {
		return self::$callCount;
	}

	/**
	 * @return String The name that corresponds to a logging level.
	 */
	public static function getLevelName($level) {

		if (!self::$namesByLevel) {

			self::$namesByLevel = array(
				self::LEVEL_DEBUG => 'DEBUG',
				self::LEVEL_INFO => 'INFO',
				self::LEVEL_WARN => 'WARN',
				self::LEVEL_ERROR => 'ERROR',
				self::LEVEL_FATAL => 'FATAL'
			);

		}

		return isset(self::$namesByLevel[$level]) ? self::$namesByLevel[$level] : '';
	}

	/**
	 * @return Array All logging levels.
	 */
	public static function getLevels() {

		if (!self::$levelsByName) {

			self::$levelsByName = array(
				'DEBUG' => self::LEVEL_DEBUG,
				'INFO' => self::LEVEL_INFO,
				'WARN' => self::LEVEL_WARN,
				'ERROR' => self::LEVEL_ERROR,
				'FATAL' => self::LEVEL_FATAL,
			);
		}

		return self::$levelsByName;
	}

	/**
	 * @return Number The # of times ::write has actually resulted in a write.
	 * That is, the # of times the level arg passed to ::write has been within
	 * our log level threshold.
	 */
	public static function getWriteCount() {
		return self::$writeCount;
	}

	/**
	 * Shortcut for writing info messages. You can call it like this:
	 *
	 *	::info('my log', 'here is my message content')
	 *
	 * or like this (gets written to app.log):
	 *
	 *	::info('here is my message content')
	 */
	public static function info() {
		if (self::$minLevel <= self::LEVEL_INFO) {
			return self::doShortcut(Octopus_Log::LEVEL_INFO, func_get_args());
		}
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isDebugEnabled() {
		return self::$minLevel <= self::LEVEL_DEBUG;
	}

	/**
	 * @param  Number  $level Level to check
	 * @return boolean Whether the given logging level is enabled.
	 */
	public static function isEnabled($level) {
		return self::$minLevel <= $level;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isErrorEnabled() {
		return self::$minLevel <= self::LEVEL_ERROR;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isFatalEnabled() {
		return self::$minLevel <= self::LEVEL_FATAL;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isInfoEnabled() {
		return self::$minLevel <= self::LEVEL_INFO;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isWarnEnabled() {
		return self::$minLevel <= self::LEVEL_WARN;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isWarningEnabled() {
		return self::$minLevel <= self::LEVEL_WARN;
	}

	/**
	 * Removes all log listeners and resets call count and write count.
	 */
	public static function reset() {
		self::$callCount = self::$writeCount = 0;
		self::$listeners = array();
		self::$minLevel = self::LEVEL_NONE;
	}

	/**
	 * Shortcut for writing warning messages. You can call it like this:
	 *
	 *	::warning('my log', 'here is my message content')
	 *
	 * or like this (gets written to app.log):
	 *
	 *	::warning('here is my message content')
	 */
	public static function warn() {
		if (self::$minLevel <= self::LEVEL_WARN) {
			return self::doShortcut(Octopus_Log::LEVEL_WARN, func_get_args());
		}
	}

	/**
	 * Writes a message to a log.
	 * @param String $log The name of the log being written to.
	 * @param Number $level The logging level of this message. Only listeners
	 * registered for this level (or more important) will be called.
	 * @param Mixed $message The actual log message payload
	 * @return Mixed $message is returned.
	 */
	public static function write($log, $level, $message) {

		self::$callCount++;

		if ($level < self::$minLevel || $level === self::LEVEL_NONE) {
			return;
		}

		self::$writeCount++;

		foreach(self::$listeners as $listener) {

			if ($level < $listener['minLevel']) {
				continue;
			}
			if ($listener['log'] === true || $listener['log'] === $log) {
				call_user_func($listener['func'], $message, $log, $level);
			}

		}

		return $message;

	}

	private static function doShortcut($level, $args) {

		$argCount = count($args);

		switch($argCount) {

			case 0:
				return;

			case 1:
				// Assume message is only arg
				return self::write('app', $level, $args[0]);

			case 2:
				// log is first arg, message is 2nd
				return self::write($args[0], $level, $args[1]);

			default:
				// first arg is log, subsequent args get logged individually
				$log = $args[0];

				for($i = 1; $i < $argCount; $i++) {
					self::write($args[$i], $level, $log);
				}

				return $args[1];

		}
	}

	private static function formatStackTrace($trace) {

		$result = array();
		if (empty($trace)) {
			return $result;
		}

		foreach($trace as $item) {

			$item['file'] = $item['nice_file'];
			unset($item['nice_file']);

			if (count($result) > 0) {
				$result[] = $item;
				continue;
			}

			if ($item['function'] === 'saneBacktrace') {
				// don't bother including
				continue;
			}

			$result[] = $item;

		}

		return $result;

	}

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log_Listener_File
//
////////////////////////////////////////////////////////////////////////////////

/**
 * A log listener that writes to a file. Writes log entries (by default in
 * JSON format) to files in a directory based on the name of the log. For
 * example, anything written to the 'app' log gets written to a file called
 * 'app.log' in the directory passed to the constructor.
 * Log files are rotated out automatically.
 */
class Octopus_Log_Listener_File {

	private $logDir;
	private $maxFileSize;
	private $rotationDepth = 2;
	private $logFiles = array();
	private $extension = '.log';
	private $formatter = array('Octopus_Log', 'formatJson');

	/**
	 * Creates a new listener that creates log files in the given directory.
	 * @param $logDir Directory in which to create log files. If not specified,
	 * the LOG_DIR option is used. If LOG_DIR is not set, a directory called
	 * 'log' in the app's private dir is used.
	 */
	public function __construct($logDir = null) {

		$this->maxFileSize = 1024 * 1024; // 1M

		if ($logDir === null) {
			$logDir = get_option('LOG_DIR');
			if (!$logDir) {
				$logDir = get_option('OCTOPUS_PRIVATE_DIR' . 'log/');
			}
		}

		$this->logDir = $logDir;
	}

	public function getExtension() {
		return $this->extension;
	}

	/**
	 * Sets the extension used for files generated by this logger.
	 */
	public function setExtension($ext) {
		$this->extension = ($ext ? start_in('.', $ext) : '');
	}

	/**
	 * @return Function The callable used to format log entries for this
	 * logger. Defaults to Octopus_Log::formatJson
	 */
	public function getFormatter() {
		return $this->formatter;
	}

	/**
	 * Sets the formatter function used to format entries for this logger.
	 * @param Function $formatter Formatting function. Receives args like this:
	 *
	 *	function my_formatter($message, $log, $level, $timestamp, $stackTrace);
	 *
	 */
	public function setFormatter($formatter) {
		$this->formatter = $formatter;
	}

	/**
	 * @return String The full path to the file being written to.
	 */
	public function getLogFile($log) {

		if (isset($this->logFiles[$log])) {
			return $this->logFiles[$log];
		}

		$file = strtolower($log);

		$ext = $this->getExtension();
		if ($ext) {
			$file = preg_replace('/' . preg_quote($ext, '/') . '$/', '', $file);
		}

		$file = preg_replace('/[^a-z0-9\._]/i', '-', $file);
		$file = preg_replace('/-{2,}/', '-', $file);
		$file = trim($file, '-');

		return (
			$this->logFiles[$log] =
				end_in('/', $this->logDir) .
				$file .
				$ext
		);

		return $this->logFile;
	}

	/**
	 * @return Number The maximum allowed log file size (in bytes).
	 * @see ::setMaxFileSize
	 */
	public function getMaxFileSize() {
		return $this->maxFileSize;
	}

	/**
	 * Sets the maximum allowed file size for log files generated by this
	 * class. Note that in practice a log file might be bigger than this,
	 * but once it hits this threshold no further messages will be written to
	 * that file.
	 */
	public function setMaxFileSize($size) {
		$this->maxFileSize = $size;
	}

	/**
	 * @return The max # of rotation files to keep (in addition to the primary
	 * log file).
	 */
	public function getRotationDepth() {
		return $this->rotationDepth;
	}

	/**
	 * Sets the # of files (in addition to the primary one) to keep when
	 * logging. Once a log file hits the size specified by ::getMaxFileSize,
	 * It gets renamed to 'file.1.log', with any existing rotation files
	 * being renamed down the line (so the old 'file.1.log' becomes
	 * 'file.2.log', the old 'file.2.log' becomes 'file.3.log', etc.).
	 */
	public function setRotationDepth($depth) {
		$this->rotationDepth = $depth;
	}

	/**
	 * Writes a message to this logger.
	 * @param $message The message to write
	 * @param $log The name of the log being written
	 * @param $level The level of the message.
	 * @see Octopus_Log::write
	 * @return Boolean Whether the write succeeded.
	 */
	public function write($message, $log, $level) {

		$file = $this->getLogFile($log);

		if (is_file($file)) {

			$size = @filesize($file);

			if ($size > $this->maxFileSize) {

				if (!$this->rotateLog($file, $failureReason)) {
					return false;
				}

			}

		}

		$dir = dirname($file);
		if (!is_dir($dir)) {
			if (!@mkdir(dirname($file), 0777, true)) {
				return false;
			}
		}

		$handle = @fopen($file, 'a');

		if ($handle === false) {
			// Opening the file for writing failed, so fail silently
			return false;
		}

		$entry = call_user_func($this->getFormatter(), $message, $log, $level, time(), $this->getStackTrace());

		fwrite($handle, $entry);
		fwrite($handle, ",\n");
		fclose($handle);

		// Ensure the file is 0666
		@chmod($file, 0666);

		return true;

	}

	private function getStackTrace() {
		return Octopus_Debug::saneBacktrace();
	}

	/**
	 * Rotates log files around $currentFile.
	 * @return Boolean true on success, false on failure.
	 */
	private function rotateLog($currentFile, &$failureReason) {

		$depth = $this->getRotationDepth();

		if (!$depth || $depth <= 0) {
			// no rotation - just remove the current file
			return @unlink($currentFile);
		}

		// Create a base for forming new log file names

		$stub = $currentFile;
		$ext = $this->getExtension();

		if ($ext) {
			$stub = preg_replace('/' . preg_quote($ext, '/') . '$/', '', $stub);
		}

		// find files in the format logname.number.extension
		$files = @glob($stub . '.*' . $ext);

		if ($files === false) {
			$failureReason = "Could not glob existing log files";
			return false;
		}

		// Clean up any files over the threshold

		foreach($files as $file) {

			if (!preg_match('/\.(\d+)\.log$/', $file, $m)) {
				continue;
			}

			$num = @intval($m[1]);

			if ($num >= $depth) {
				if (!@unlink($file)) {
					$failureReason = "unlink failed: $file";
					return false;
				}
			}

		}

		$zeroes = 3;

		// Rename existing files down the line
		for($i = $depth; $i >= 1; $i--) {

			$num = sprintf(".%0{$zeroes}d", $i);
			$file = $stub . $num . $ext;

			if (is_file($file)) {

				$newFile = $stub . sprintf(".%0{$zeroes}d", $i + 1) . $ext;

				if (!@rename($file, $newFile)) {
					$failureReason = "rename failed: $file -> $newFile";
					return false;
				}

			}

		}

		$newFile = $stub . sprintf(".%0{$zeroes}d", 1) . $ext;

		// Move the existing log file to .1.log
		if (!@rename($currentFile, $newFile)) {
			$failureReason = "rename failed: $file -> $newFile";
			return false;
		}

		return true;
	}

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log_Listener_Html
//
////////////////////////////////////////////////////////////////////////////////

/**
 * A log listener that renders anything passed to it as HTML.
 */
class Octopus_Log_Listener_Html {

	public function write($message, $log, $level) {

		if (!self::shouldWrite()) {
			return;
		}

		$html = new Octopus_Log_Listener_Html_Message();

		$var = new Octopus_Log_Listener_Html_Variable($message);
		$html->add('', )

		$html->add('', $message);

		// Add metadata to the message
        foreach(array('_GET', '_POST', '_SERVER', '_SESSION', '_FILES') as $arname) {

            if (!empty($GLOBALS[$arname])) {
            	$var = new Octopus_Log_Listener_Html_Variable($GLOBALS[$arname]);
            	$html->add("\$$arname", $var);
            }

        }

		if (class_exists('Octopus_App') && Octopus_App::isStarted()) {

			$app = Octopus_App::singleton();
			$resp = $app->getCurrentResponse();

			if ($resp) {
				// Write message to the response and flush
				$resp->append($html);
				$resp->flush();
				return;
			}
		}

		echo ($html);
	}

	/**
	 * @return Boolean Whether this thing should actually write stuff out.
	 */
	public static function shouldWrite() {
		return (
				// Write HTML if this is a web request...
				!empty($_SERVER['HTTP_USER_AGENT']) &&

				// ...but not for XHR
			   	empty($_SERVER['HTTP_X_REQUESTED_WITH'])
		);
	}

}

////////////////////////////////////////////////////////////////////////////////
//
// Octopus_Log_Listener_StdErr
//
////////////////////////////////////////////////////////////////////////////////

/**
 *  A log listener that writes to stderr. For use on the command-line and in
 *  unit tests.
 */
class Octopus_Log_Listener_StdErr {

	public function write($message, $log, $level) {

		if (!self::shouldWrite()) {
			return;
		}

	}

	public function shouldWrite() {
		return (php_sapi_name() === 'cli');
	}

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log_Listener_Html_Message
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Helper used to represent a single message being rendered as HTML.
 */
class Octopus_Log_Listener_Html_Message {

	private $title;
	private $sections = array();
	private $footer = array();
	private $classes = array();

	/**
	 * Creates a new block with the given title.
	 * @param String $title Title for this debug
	 */
	public function __construct($title = '') {
		$this->title = $title;
	}

	/**
	 * Adds a named section to this message.
	 * @param  String $name    Name of the section ('$_GET', 'Trace', etc)
	 * @param  String $content HTML content for the section
	 * @param  Array $alternateViews Key/value pairs for named alternate views
	 * of the same content.
	 */
	public function add($name, $content, $alternateViews = array()) {
		$this->sections[] = compact('name', 'content', 'alternateViews');
	}

	/**
	 * Adds one or more CSS classes to this message.
	 */
	public function addClass($class) {
		foreach(func_get_args() as $arg) {
			if (is_array($arg)) {
				call_user_func_array(array($this, 'addClass'), $arg);
			} else {
				$class = trim($arg);
				if ($class) $this->classes[$class] = true;
			}
		}
	}

	/**
	 * Adds a named section to the footer of this message.
	 */
	public function addFooterElement($name, $content) {
		$this->footer[] = compact('name', 'content');
	}

	/**
	 * Renders this message.
	 * @param  boolean $return If true, the HTML is returned. Otherwise it is
	 * outputted directly.
	 */
	public function render($return = false) {

		$classes = $this->classes;

		if ($this->title) {
			$title = '<h1 class="octopus-debug-title">' . htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') . '</h1>';
		} else {
			$title = '';
			$classes['octopus-debug-no-title'] = true;
		}

		$classes = implode(' ', array_keys($classes));

		$html = <<<END
<div class="octopus-debug $classes">
	<div class="octopus-debug-inner">
		{$title}
	</div>
</div>
END;

		if ($return) {
			return $html;
		} else {
			echo $html;
		}

	}

	public function __toString() {
		return $this->render(true);
	}

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log_Listener_Html_Variable
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Renders a single variable as HTML.
 */
class Octopus_Log_Listener_Html_Variable {

	private $value;

	public function __construct($value) {
		$this->value = $value;
	}

	/**
	 * @return String The HTML for the 'raw' view of the variable, if any.
	 */
	public function getRawContent() {

	}

	public function __toString() {
		return Octopus_Debug::dumpToString($this->value);
	}

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log_Listener_Html_Trace
//
////////////////////////////////////////////////////////////////////////////////

/**
 *  Helper that renders a stack trace as HTML.
 */
class Octopus_Log_Listener_Html_Trace {

	private $trace;

	public function __construct($trace = null) {
		$this->trace = ($trace === null ? debug_backtrace() : $trace);
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
        $trace = Octopus_Debug::getSaneBacktrace($this->trace);

        foreach($trace as $b) {


            $func = '<td class="octopusDebugBacktraceFunction">' . $b['function'] . '()</td>';

            $b['file'] = htmlspecialchars($b['file'], ENT_QUOTES, 'UTF-8');

            $file = <<<END
<td class="octopusDebugBacktraceFile" title="{$b['file']}">
END;

            $file .= htmlspecialchars($b['nice_file'], ENT_QUOTES, 'UTF-8');
            $file .= '</td>';

            $line = '<td class="octopusDebugBacktraceLine">Line ' .
                    (isset($b['line']) ? $b['line'] : '') .
                    '</td>';

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

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Debug
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Class encapsulating all Octopus debug functionality.
 */
class Octopus_Debug {

	/**
	 * The full <style> tag written out when Octopus renders debug/logging
	 * output as HTML.
	 * @var String
	 */
    public static $css;

    /**
     * The full <script> tag written out when Octopus renders debug/logging
     * output as HTML.
     * @var String
     */
    public static $js;

    private static $configured = false;

    /**
     *  Sets up the debugging environment if it has not already been set up.
     */
    public static configure() {

    	if (self::$configured) {
    		return;
    	}

    	self::$configured = true;

    	// Add debug listeners based on app state

    }

    /**
     * @param  Mixed  $x Variable to dump.
     * @param  String $format Format in which to dump. Current 'html' and 'text'
     *                are supported.
     * @param  boolean $fancy  [description]
     * @return String String representation of $x
     */
    public static function dumpToString($x, $format = null, $fancy = true) {

        if (!$format) {
            $format = Octopus_Debug::inWebContext() ? 'html' : 'text';
        }

        if ($fancy) {

            $result = null;

            if ($format === 'html') {

                if ($x === null) {
                    $result = '<span class="octopusDebugNull">NULL</span>';
                } else if ($x === true || $x === false) {
                    $result =  '<span class="octopusDebugBoolean">' . ($x ? 'TRUE' : 'FALSE') . '</span>';
                } else if (is_object($x) && $x instanceof Dumpable) {
                    $result = $x->__dumpHtml();
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
                    $result = $x->__dumpText();
                    if ($result === null) $result = '';
                } else if ($x instanceof Exception) {
                    $result = self::dumpExceptionToText($x);
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

        if ($format === 'html') {
            $result = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
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

        $elapsed = round(microtime(true) - $_SERVER['REQUEST_TIME_MILLISECOND'], 3);

        // NOTE: the OCTOPUS_TOTAL_RENDER_TIME is replaced by render_page, and
        // only when we are running in DEV mode.

        return <<<END
        <ul class="octopusDebugErrorReporting">
        <li>error_reporting: $flags</li>
        <li>display_errors: $display_errors</li>
        <li>$elapsed <!-- OF_OCTOPUS_TOTAL_RENDER_TIME --> sec</li>
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

        $content = '';

        if (self::inJavascriptContext()) {
            $content = $this->renderJson(true);
        } else if (self::inWebContext()) {
            $content = $this->renderHtml(true);
        }  else {
            $result = $this->renderText($return);
            return $result;
        }

        if ($return) {
            return $content;
        }

        if (class_exists('Octopus_App') && Octopus_App::isStarted()) {
            $app = Octopus_App::singleton();
            $resp = $app->getCurrentResponse();
            if ($resp) {
                $resp->append($content);
                return;
            }
        }

        echo $content;
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


        if (!self::$_renderedCss) {
            $result .= self::$css;
        }

        if (!self::$_renderedJs) {
            $result .= self::$js;
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
            self::$_renderedJs = true;
            self::$_renderedCss = true;
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

            $content[] = array('label' => $var['name'], 'text' => self::dumpToString($var['value'], 'text', true));
        }
        foreach($this->_content as $name => $c) {
            $content[] = array('label' => $name, 'text' => $c['content']);
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
        foreach($content as $item) {
            $l = min(strlen($item['label']), $maxLabelWidth);
            if ($l > $labelWidth) {
                $labelWidth = $l;
            }
        }

        $textWidth = $width - ($labelWidth + strlen($borderChar) + 2) - 4;

        $result = "$hBorder\n";
        $first = true;

        foreach($content as $item) {

            if (!$first) {
                $result .= "$hLine\n";
            }

            $label = $item['label'];
            $text = $item['text'];

            $label = str_replace("\t", "    ", $label);
            $text = str_replace("\t", "    ", $text);

            $label = wordwrap($label, $labelWidth, "\n", true);
            $text = wordwrap($text, $textWidth, "\n", true);

            $text = str_replace("{__octopus_debug_line__}", str_repeat('-', $textWidth), $text);

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
            // Write to stderr
            $fp = fopen('php://stderr', 'w');
            fputs($fp, "\n$result\n");
            fclose($fp);

        }
    } /* }}} */

    /* saneBacktrace($bt = null) {{{ */
    public static function saneBacktrace($bt = null) {

        if ($bt === null) {
            $bt = debug_backtrace();
        }

        $result = array();

        if (function_exists('get_option')) {
        	$rootDir = get_option('ROOT_DIR');
        } else if (defined('ROOT_DIR')) {
        	$rootDir = ROOT_DIR;
        } else {
        	$rootDir = '';
        }

        $rootDirLen = strlen($rootDir);

        foreach($bt as $b) {

            $item = array(

                'function' => isset($b['function']) ? $b['function'] : null,
                'file' => isset($b['file']) ? $b['file'] : null,
                'line' => isset($b['line']) ? $b['line'] : null,

            );

            if ($rootDirLen && substr($item['file'], 0, $rootDirLen) == $rootDir) {
                $item['nice_file'] = substr($b['file'], $rootDirLen);
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
            $value = self::dumpToString($value, 'html');

            if ($i !== $key) {
                $index = $i;
                $rowClass .= ' octopusDebugArrayKeyDiffers';
            } else {
                $index = '';
            }

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

    /* dumpExceptionToText($ex) {{{ */
    private static function dumpExceptionToText($ex) {

        $class = get_class($ex);
        $message = $ex->getMessage();
        $trace = self::saneBacktrace($ex->getTrace());

        $filterTraceLocations = '#^(/usr/local/pear/|/usr/bin/phpunit$)#';

        $result = <<<END
{$message}
{__octopus_debug_line__}

END;

        foreach($trace as $i) {

            if (preg_match($filterTraceLocations, $i['file'])) {
                continue;
            }

            $result .= <<<END
{$i['nice_file']}: {$i['line']}

END;
        }

        return $result;

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

        if ($type === 'integer') {

            $hex = sprintf('%X', $x);
            $result .= '<span class="octopusDebugHexNumber">&nbsp;&mdash;&nbsp;hex #' . $hex . '</span>';

            $octal = sprintf('%o', $x);
            $result .= '<span class="octopusDebugOctalNumber">&nbsp;&mdash;&nbsp;octal ' . $octal;
            if ($x >= 0100111 && $x <= 0100777) {
                $result .= ' <span class="octopusDebugNumberAsPermissions">(' . self::getNumberAsFilePermissions($x) . ')</span>';
            }
            $result .= '</span>';
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
        $result = '"' . $str . '" - ' . $length;

        if (strlen($str) > 1 && $str[0] === '/' && file_exists($str)) {

            $isDir = is_dir($str);
            $isLink = is_link($str);

            $type = 'file';
            if ($isDir) $type = 'directory';
            if ($isLink) $type .= ' (link)';

            $result .= "\n\tExists and is a $type";

            if ($isDir) {
                $contents = @glob(rtrim($str, '/') . '/*');
                if ($contents) {
                    $result .= "\n\t" . count($contents) . ' file(s):';
                    foreach($contents as $f) {
                        $result .= "\n\t\t" . basename($f);
                    }
                }
            }
        }

        return $result;

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
                $tabs[] = array('content' => self::dumpToString($var['value'], 'html'), 'raw' => $var['raw']);
            } else {
                $varsTab['content'][] = self::dumpToString($var['value'], 'html');
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
        $niceLength = "$length char" . ($length === 1 ? '' : 's');

        if (function_exists('mb_strlen')) {

        	$mbLength = mb_strlen($str);

        	if ($mbLength !== $length) {
            	$niceLength .= " ($mbLength using mb_strlen)";
            }
        }

        return $niceLength;
    } /* }}} */

    private static function getNumberAsFilePermissions($perms) {

        if (($perms & 0xC000) == 0xC000) {
            // Socket
            $info = 's';
        } elseif (($perms & 0xA000) == 0xA000) {
            // Symbolic Link
            $info = 'l';
        } elseif (($perms & 0x8000) == 0x8000) {
            // Regular
            $info = '-';
        } elseif (($perms & 0x6000) == 0x6000) {
            // Block special
            $info = 'b';
        } elseif (($perms & 0x4000) == 0x4000) {
            // Directory
            $info = 'd';
        } elseif (($perms & 0x2000) == 0x2000) {
            // Character special
            $info = 'c';
        } elseif (($perms & 0x1000) == 0x1000) {
            // FIFO pipe
            $info = 'p';
        } else {
            // Unknown
            $info = 'u';
        }

        // Owner
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ?
                    (($perms & 0x0800) ? 's' : 'x' ) :
                    (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ?
                    (($perms & 0x0400) ? 's' : 'x' ) :
                    (($perms & 0x0400) ? 'S' : '-'));

        // World
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ?
                    (($perms & 0x0200) ? 't' : 'x' ) :
                    (($perms & 0x0200) ? 'T' : '-'));

        return $info;
    }

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


////////////////////////////////////////////////////////////////////////////////
//
// interface Dumpable
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Implement this interface to have greater control over how you class is
 * displayed in dump_r output.
 *
 */
interface Dumpable {

    /**
     * @return String debugging info for this object, formatted as HTML.
     */
    function __dumpHtml();

    /**
     * @return String debugging info for this object, formatted as plain text.
     */
    function __dumpText();

}

// }}}

// Octopus_Debug Class {{{


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
                    while(method_exists($ex, 'getPrevious') && $ex = $ex->getPrevious()) {
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

            foreach(array('_GET', '_POST', '_SERVER', '_SESSION', '_FILES') as $arname) {

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

        // Write a log file for e.g. api calls etc.
        if (defined('OCTOPUS_PRIVATE_DIR')) {

            $logFile = OCTOPUS_PRIVATE_DIR . 'dump_r.log';

            $d = new Octopus_Debug('dump_r');
            foreach($args as $arg) {
                $d->addVariable($arg);
            }

            if (is_file($logFile)) {
                $size = @filesize($logFile);
                if ($size && $size > (1 * 1024 * 1024) * 5) {
                    @unlink($logFile);
                }
            } else {

                @touch($logFile);

                // Make log file writable by both command line and
                // apache phps
                @chmod($logFile, 0666);
            }

            $fp = @fopen($logFile, 'a');
            if ($fp) {

                if (empty($GLOBALS['__OCTOPUS_DUMP_R_CALLED'])) {

                    $GLOBALS['__OCTOPUS_DUMP_R_CALLED'] = true;

                    $now = date('r');

                    @fwrite(
                        $fp,
                        <<<END

********************************************************************************
$now

END
                    );
                }

                $text = $d->renderText(true);
                @fwrite($fp, $text . "\n");
                @fclose($fp);
            }
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

        if (class_exists('Octopus_Response')) {
            $resp = Octopus_Response::current();
            if ($resp) $resp->flush();
        }

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

        // Write to stderr
        $fp = fopen('php://stderr', 'w');
        fputs($fp, "\n");

        foreach(Octopus_Debug::saneBacktrace($bt) as $item) {
            if ($limit && $count >= $limit) {
                break;
            }
            fputs($fp, "{$item['function']} at {$item['file']}, line {$item['line']}\n");
        }

        fclose($fp);

    }

    /**
     * Helper for debugging stack overflows.
     */
    function print_backtrace_after($calls, $die = true) {

        if (empty($GLOBALS['__OCTOPUS_PRINT_BACKTRACE_AFTER'])) {
            $GLOBALS['__OCTOPUS_PRINT_BACKTRACE_AFTER'] = 0;
        }

        if ($GLOBALS['__OCTOPUS_PRINT_BACKTRACE_AFTER'] === $calls) {

            print_backtrace();
            if ($die) die();

        }

        $GLOBALS['__OCTOPUS_PRINT_BACKTRACE_AFTER']++;
    }

    function reset_print_backtrace_after() {
        unset($GLOBALS['__OCTOPUS_PRINT_BACKTRACE_AFTER']);
    }

} // if (!function_exists('dump_r))

////////////////////////////////////////////////////////////////////////////////
//
// Octopus Debug CSS
//
////////////////////////////////////////////////////////////////////////////////

Octopus_Debug::css = <<<END

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
        margin: 10px auto 10px auto;
        max-width: 1000px;
        overflow: hidden;
        padding: 0;
        position: relative;
        text-align: left;
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

    div.octopusDebug table tr td {
        vertical-align: top;
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

    table.octopusDebugBacktrace tr.octopusDebugBacktraceSourceSmarty td {
    	color: #666;
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
        background: #aaa;
        color: #efefef;
        float: right;
        padding: 3px;
        right: 10px;
        top: 10px;
    }

    span.octopusDebugString {}

    span.octopusDebugString span.octopusDebugStringLength,
    span.octopusDebugDateFromNumber,
    .octopusDebugNumberType,
    .octopusDebugOctalNumber, .octopusDebugHexNumber {
        color: #888;
        font-size: 0.9em;
    }

    td.octopusDebugArrayIndex {
        color: #888;
        font-size: 0.9em;
    }

    tr.octopusDebugArrayKeyDiffers td.octopusDebugArrayIndex {
        color: #AAA;
        font-size: 0.8em;
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

    a.octopusDebugCollectLink {
        color: #fff;
        position: absolute;
        font-size: 20px;
        font-weight: bold;
        right: 5px;
        top: -7px;

    }

-->
</style>

END;

////////////////////////////////////////////////////////////////////////////////
//
// Octopus Debug JS
//
////////////////////////////////////////////////////////////////////////////////

Octopus_Debug::$js = <<<END

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

function __octopus_debug_collect_at_bottom() {

    if (!document.getElementsByClassName) {
        return;
    }

    var els = document.getElementsByClassName("octopusDebug");
    var ar = [];

    for(var i = 0; i < els.length; i++) {
        ar[i] = els[i];
    }

    var lastInserted = null;

    for(var i = 0; i < ar.length; i++) {
        var el = ar[i];
        document.body.insertBefore(el, lastInserted ? lastInserted.nextSibling : null);
        lastInserted = el;
    }

    var links = document.getElementsByClassName('octopusDebugCollectLink');
    while(links.length) {
        links[0].parentNode.removeChild(links[0]);
    }

}

function __octopus_debug_onload() {

    if (document.getElementsByClassName) {

        var blocks = document.getElementsByClassName("octopusDebug");
        for(var i = 0; i < blocks.length; i++) {

            var block = blocks[i]
            if (block.__octopus_collectLink) {
                continue;
            }
            var collectLink = document.createElement('a');
            collectLink.className = 'octopusDebugCollectLink';
            collectLink.href="#moveToBottom";
            collectLink.title = 'Move debug output to the bottom';
            collectLink.appendChild(document.createTextNode('\u2193'));
            collectLink.onclick = function() { __octopus_debug_collect_at_bottom(); return false; };
            block.appendChild(collectLink);
            block.__octopus_collectLink = collectLink;
        }
    }

}
if (window.attachEvent) {
    window.attachEvent('onload', __octopus_debug_onload);
} else if (window.addEventListener) {
    window.addEventListener('load', __octopus_debug_onload, true);
}
</script>

END;

?>