<?php
/**
 * A standalone logging and debugging framework. This file has no Octopus
 * dependencies--it can be included in any project you like. If used in an
 * Octopus context, however, it is doubley awesome.
 *
 * Mode Flags
 *
 * Debugging supports Octopus's DEV, STAGING, and LIVE flags. It uses Octopus's
 * methods for determining the current mode if available. If used outside an
 * Octopus context, the DEV, STAGING, and LIVE defines control the default
 * debugging behavior.
 *
 * Default Behavior
 *
 * DEV
 * 	- The log level defaults to DEBUG, and all logs are written to one of these
 * 	  directories (in order of preference): LOG_DIR, OCTOPUS_PRIVATE_DIR,
 * 	  PRIVATE_DIR.
 * 	- Log messages of the level WARNING or higher cancel any subsequent
 * 	  redirects.
 * 	- Messages of the level INFO or higher are displayed in-browser.
 * 	- (For Octopus apps.) PHP errors are relayed as log events (notice = info,
 * 	warning = warn, etc).
 *
 * STAGING
 *  - The log level defaults to DEBUG, and all logs are written to the
 *    filesystem (as in DEV)
 *  - No redirects are ever cancelled.
 *  - No messages are displayed in-browser.
 *
 * LIVE
 *  - The log level defaults to WARN, and all logs are written to the
 *    filesystem.
 *  - No redirects are ever cancelled
 *  - No messages are displayed in-browser.
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

		if (is_object($func) && !($func instanceof Closure)) {
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
	 * A custom PHP error handler function that reroutes PHP errors, warnings,
	 * and noticies into the logging system and suppresses them.
	 * To use this in your app, call ::registerErrorHandler()
	 * Octopus apps use this error handler automatically.
	 */
	public static function errorHandler($errno, $errstr, $errfile, $errline, $errcontext) {

		if (!(error_reporting() & $errno)) {
			// This error should not be shown.
			return true;
		}

		$level = self::getLogLevelForPhpError($errno);

		if ($level === Octopus_Log::LEVEL_NONE) {
			// We can't appropriately log it, so let PHP take over.
			return false;
		}

		// Write the message to the errors log
		self::write('errors', $level, $errstr);

		return true; // Suppress PHP's internal error handling logic
	}

	/**
	 * A custom PHP exception handler that routes exceptions through the
	 * logging infrastructure. Exceptions are logged, then die() is called.
	 */
	public static function exceptionHandler($ex) {

		self::error('errors', $ex);
		die();

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
	 * Registers a PHP error handler that logs errors, notices, and warnings
	 * as they come through and suppresses their display.
	 * Octopus apps use this by default.
	 * @see ::errorHandler()
	 */
	public function registerErrorHandler() {
		set_error_handler(array('Octopus_Log', 'errorHandler'));
	}

	/**
	 * Registers a PHP exception handler that logs all exceptions as
	 * ::LEVEL_ERROR.
	 * Octopus apps use this by default.
	 * @see ::exceptionHandler()
	 */
	public function registerExceptionHandler() {
		set_exception_handler(array('Octopus_Log', 'exceptionHandler'));
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

	private static function getLogLevelForPhpError($err) {

		switch($err) {

			case E_DEPRECATED:
			case E_USER_DEPRECATED:
			case E_STRICT:
				return Octopus_Log::LEVEL_DEBUG;

			case E_NOTICE:
			case E_USER_NOTICE:
				return Octopus_Log::LEVEL_INFO;

			case E_WARNING:
			case E_USER_WARNING:
				return Octopus_Log::LEVEL_WARN;

			case E_ERROR:
			case E_USER_ERROR:
				return Octopus_Log::LEVEL_ERROR;

			default:
				return Octopus_Log::LEVEL_NONE;

		}

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
		return Octopus_Debug::getNiceBacktrace();
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


	public function write($message, $log, $level) {

		if (!self::shouldWrite()) {
			return;
		}

		$html = new Octopus_Log_Listener_Html_Message();

		$var = new Octopus_Log_Listener_Html_Variable($message);

		$html->add('', $message);

		// Add metadata to the message
        foreach(array('_GET', '_POST', '_SERVER', '_SESSION', '_FILES') as $arname) {

            if (!empty($GLOBALS[$arname])) {
            	$var = new Octopus_Log_Listener_Html_Variable($GLOBALS[$arname]);
            	$html->add("\$$arname", $var);
            }

        }

        $html->addFooterElement('error_reporting', self::getErrorReportingHtml());

		if (Octopus_Debug::usingOctopus()) {

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
// Octopus_Log_Listener_Console
//
////////////////////////////////////////////////////////////////////////////////

/**
 *  A log listener that writes messages formatted for the console (the default
 *  output is on stderr).
 *  Does not do any log rotation or anything, just writes to an ouput stream.
 *  If you want 'true' file logging, use Octopus_Log_Listener_File.
 */
class Octopus_Log_Listener_Console {

	private $file;

	public function __construct($file = 'php://stderr') {
		$this->file = $file;
	}

	public function write($message, $log, $level) {

		if (!self::shouldWrite()) {
			return;
		}

		$trace = Octopus_Debug::getNiceBacktrace();
		$message = $this->formatMessage($message, $log, $level, $trace);

        $fp = fopen($this->file, 'w');
        fputs($fp, "\n$message\n");
        fclose($fp);

	}

	public function shouldWrite() {
		return (php_sapi_name() === 'cli');
	}

	private function formatMessage($message, $log, $level, Array $trace, $width = 80, $indent = 1) {

		$time = date('Y-M-d h:n:s');

		$boldLine = str_repeat('*', $width);
		$lightLine = str_repeat('-', $width);

		$level = Octopus_Log::getLevelName($level);

		$headerWidth = $width - ($indent / 2);
		$titleWidth = floor($headerWidth / 4);
		$title = str_pad("$log - $level", $titleWidth);

		// Find the first line of the stack trace that is not in this file
		// to display.
		while($traceLine = array_shift($trace)) {

			// Skip closures
			if (empty($traceLine['file'])) {
				continue;
			}

			// Skip stuff in this file
			if ($traceLine['file'] === __FILE__) {
				continue;
			}

			break;
		}

		if ($traceLine) {
			$traceFunction = str_pad($traceLine['scope_function'] . '()', $headerWidth - $titleWidth - $indent, ' ', STR_PAD_LEFT);
			$traceFile = <<<END
{$traceLine['nice_file']}, line {$traceLine['line']}
END;
			$traceFile = str_pad($traceFile, $headerWidth - $indent, ' ', STR_PAD_LEFT);

		} else {
			$traceFunction = $traceFile = '';
		}

		$indentChars = str_repeat(' ', $indent);

		return <<<END
{$boldLine}
{$indentChars}{$title}{$traceFunction}
{$indentChars}{$traceFile}
{$lightLine}
{$message}
{$boldLine}

END;

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

		$nav = array();
		$content = array();

		$nav = implode("\n", $nav);
		$content = implode("\n", $content);

		$classes = implode(' ', array_keys($classes));

		$html = <<<END
<div class="octopus-debug $classes">
	<div class="octopus-debug-inner">
		{$title}
		{$nav}
		{$content}
		{$footer}
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

	/**
	 * @return String A block of HTML describing the current error_reporting
	 * state.
	 */
    private static function getErrorReportingHtml() {

        $flags = implode(' | ', Octopus_Debug::getErrorReportingFlags());

        $display_errors = ini_get('display_errors') ? 'on' : 'off';

        $elapsed = round(microtime(true) - $_SERVER['REQUEST_TIME_MILLISECOND'], 3);

        // NOTE: the OCTOPUS_TOTAL_RENDER_TIME is replaced by render_page.

        return <<<END
        <ul class="octopusDebugErrorReporting">
        <li>error_reporting: $flags</li>
        <li>display_errors: $display_errors</li>
        <li>$elapsed <!-- OF_OCTOPUS_TOTAL_RENDER_TIME --> sec</li>
        </ul>
END;


    }

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log_Listener_Html_Variable
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Helper that renders a single variable as HTML.
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
		return Octopus_Debug::dumpToString($this->value, 'html', true);
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

class Octopus_Debug {

    private static $configured = false;
    private static $environment = null;
    private static $dumpEnabled = true;


    /**
     * Writes all arguments passed to it as special debug messages. Only
     * works when the app is in DEV mode.
     * @see dump_r
     * @return Mixed The first argument passed in.
     */
    public static function dump($x) {

    	if (!self::$dumpEnabled || !self::isDevEnvironment()) {
    		return $x;
    	}

    	// Ensure all log listeners are in place.
    	self::configure();

    	// Send a special Octopus_Debug_Dumped_Vars object through the logging
    	// infrastructure to the 'dump' log. This gets picked up by the file,
    	// html, and stderr listeners and rendered appropriately.

    	$vars = new Octopus_Debug_Dumped_Vars(func_get_args());
    	Octopus_Log::debug('dump', $vars);

    	return $x;
    }

    /**
     * Calls ::dump() and then exits.
     */
    public function dumpAndExit($x) {

    	if (!self::$dumpEnabled || !self::isDevEnvironment()) {
    		return $x;
    	}

    	call_user_func_array(array('Octopus_Debug', 'dump'), func_get_args());
    	self::flushBufferedOctopusResponse();

    	exit(1337);
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
                } else if (is_bool($x)) {
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
     * Enables/disables ::dump and ::dumpAndExit. While dumping is disabled,
     * calls to ::dump and ::dumpAndExit return immediately.
     * @param  boolean $enable Whether to enable or disable
     */
    public function enableDumping($enable = true) {
    	self::$dumpEnabled = $enable;
    }

    /**
     * Alias for ::enableDumping(false)
     */
    public function disableDumping() {
    	return self::enableDumping(false);
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
     * @param  Mixed $bt The backtrace to format. If not provided, a new
     * backtrace is generated.
     * @return Array A backtrace array with keys normalized and ROOT_DIR
     * stripped off any file names.
     */
    public static function getNiceBacktrace($bt = null) {

        if ($bt === null) {
            $bt = debug_backtrace();
            array_shift($bt);
        }

        $result = array();
        $rootDir = trim(self::getOption('ROOT_DIR'));

        if ($rootDir) {
        	$rootDir = rtrim($rootDir, '/') . '/';
        }

        $rootDirLen = strlen($rootDir);

        $base = array(
        	'function' => '',
        	'file' => '',
        	'line' => '',
        	'type' => '',
        	'octopus_type' => '',
        	'class' => '',
        );

        foreach($bt as $index => $item) {

        	$nextItem = ($index < (count($bt) - 1)) ? $bt[$index+1] : $base;
        	$niceItem = $base;

        	foreach(array('function', 'file', 'line', 'class', 'type') as $key) {
        		if (isset($item[$key])) {
        			$niceItem[$key] = $item[$key];
        		}
        	}

        	if ($nextItem['function']) {
        		if (isset($nextItem['class'])) {
        			$niceItem['scope_function'] = $nextItem['class'] . '::' . $nextItem['function'];
        		} else {
        			$niceItem['scope_function'] = $nextItem['function'];
        		}
        	}

            // Remove ROOT_DIR from beginning of file name if possible
            if ($rootDirLen && substr($niceItem['file'], 0, $rootDirLen) == $rootDir) {
                $niceItem['nice_file'] = substr($b['file'], $rootDirLen);
            } else {
                $niceItem['nice_file'] = $niceItem['file'];
            }

            // Also, remove everything before '/octopus/'
            // This helps keep nice_file nice even when octopus dir is outside
            // of root dir (for example, in tests where it is symlinked in).
            $niceItem['nice_file'] = preg_replace('#.*/octopus/#', 'octopus/', $niceItem['nice_file']);

            if (preg_match('~^octopus/~', $niceItem['nice_file'])) {
            	// This is an octopus system file
            	$niceItem['octopus_type'] = 'octopus';
            } else if (preg_match('~^_?private/smarty/~', $niceItem['nice_file'])) {
            	// This is a smarty temp file
            	$niceItem['octopus_type'] = 'smarty';
            }

            $result[] = $niceItem;
        }

        return $result;

    }

    /**
     * @return Boolean Whether the DEV flag is set.
     */
    public static function isDevEnvironment() {

    	if (self::$environment === 'DEV') {
    		return true;
    	}

    	if (self::$environment !== null) {
    		return false;
    	}

    	$isDev = false;

    	if (self::usingOctopus()) {
    		$app = Octopus_App::singleton();
    		$isDev = $app->isDevEnvironment();
    	} else {
    		$isDev = defined('DEV') && DEV;
    	}

    	if ($isDev) {
    		self::$environment = 'DEV';
    	}

    	return $isDev;
    }

	/**
     * @return Boolean Whether the LIVE flag is set. This is the default mode
     * if none is specified.
     */
    public static function isLiveEnvironment() {

    	if (self::$environment === 'LIVE') {
    		return true;
    	}

    	if (self::$environment !== null) {
    		return false;
    	}

    	$isLive = !($this->isDevEnvironment() || $this->isStagingEnvironment());

    	if ($isLive) {
    		self::$environment = 'LIVE';
    	}

    	return $isLive;
    }

    /**
     * @return boolean Whether the STAGING flag is set.
     */
    public static function isStagingEnvironment() {

    	if (self::$environment === 'STAGING') {
    		return true;
    	}

    	if (self::$environment !== null) {
    		return false;
    	}

    	$isStaging = false;

    	if (self::usingOctopus()) {
    		$app = Octopus_App::singleton();
    		$isStaging = $app->isStagingEnvironment();
    	} else {
    		$isStaging = defined('STAGING') && STAGING;
    	}

    	if ($isStaging) {
    		self::$environment = 'STAGING';
    	}

    	return $isStaging;
   	}

   	/**
   	 * Writes a slightly cleaned-up backtrace out to stderr.
   	 */
	public static function printBacktrace($limit, $file = 'php://stderr') {

        $bt = debug_backtrace();

        $count = 0;

        // Write to stderr
        $fp = fopen($file, 'w');

        if (!$fp) {
        	return;
        }

        fputs($fp, "\n");

        foreach(self::saneBacktrace($bt) as $item) {
            if ($limit && $count >= $limit) {
                break;
            }
            fputs($fp, "{$item['function']} at {$item['file']}, line {$item['line']}\n");
        }

        fclose($fp);

   }

    /**
     * Resets logging and debugging state. Used mostly for testing.
     * @see Octopus_Log::reset()
     */
	public static function reset() {
   		Octopus_Log::reset();
   		self::$configured = false;
   		self::$environment = null;
   		self::$dumpEnabled = true;
   	}

   /**
     * @return Whether we are currently operating in an Octopus environment.
     */
    public static function usingOctopus() {
    	return class_exists('Octopus_App') && Octopus_App::isStarted();
    }

    /**
     * @return boolean Whether a buffered Octopus response is currently being
     * constructed.
     */
    public static function usingBufferedOctopusResponse() {

    	if (!self::usingOctopus()) {
    		return false;
    	}

    	$app = Octopus_App::singleton();
    	$resp = $app->getCurrentResponse();

    	if (!$resp) {
    		return false;
    	}

    	// TODO: make this ->isBuffered()
    	return $resp->buffer();
    }

    /**
     * Sets up the debugging environment if it has not already been set up.
     */
    private static function configure() {

    	if (self::$configured) {
    		return;
    	}

    	self::$configured = true;

    	$logDir = null;
    	$fileListener = null;

    	if (defined('LOG_DIR') && is_dir(LOG_DIR)) {
    		$logDir = LOG_DIR;
    	} else if (defined('PRIVATE_DIR') && is_dir(PRIVATE_DIR)) {
    		$logDir = PRIVATE_DIR;
    	} else if (defined('OCTOPUS_PRIVATE_DIR') && is_dir(OCTOPUS_PRIVATE_DIR)) {
    		$logDir = OCTOPUS_PRIVATE_DIR;
    	}

    	if ($logDir) {
    		$fileListener = new Octopus_Log_Listener_File($logDir);
    	}


		if (self::isLiveEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::LEVEL_WARN, $fileListener);
			}

		} else if (self::isStagingEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::LEVEL_DEBUG, $fileListener);
			}

		} else if (self::isDevEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::LEVEL_DEBUG, $fileListener);
			}

			Octopus_Log::addListener(new Octopus_Log_Listener_Html());
			Octopus_Log::addListener(new Octopus_Log_Listener_Console());
		}

    }

    /**
     * @return String A nice HTML representation of an array.
     */
    private static function dumpArrayToHtml(Array $ar) {

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

            $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
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
    }

    /**
     * @return String Nice HTML for presenting an exception.
     */
    private static function dumpExceptionToHtml(Exception $ex) {

        $html = '<div class="octopusDebugException">';

        $html .= '<div class="octopusDebugExceptionMessage">' . htmlspecialchars($ex->getMessage()) . '</div>';

        $file = $ex->getFile();
        if (defined('ROOT_DIR') && ROOT_DIR && starts_with($file, ROOT_DIR)) {
            $file = substr($file, strlen(ROOT_DIR));
        }

        $file = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        $line = $ex->getLine();
        $trace = self::getBacktraceHtml($ex->getTrace());

        $html .= <<<END
<div class="octopusDebugExceptionTrace">
$trace
</div>
END;

        $html .= '</div>';

        return $html;
    }

    /**
     * @return Formats an exception as plain text.
     */
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

    }

    /**
     * @return String Nice HTML for a number.
     */
    private static function dumpNumberToHtml($x) {

        $result = htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars(gettype($x), ENT_QUOTES, 'UTF-8');

        $result .= <<<END
<span class="octopusDebugNumberType">&nbsp;&mdash;&nbsp;$type</span>
END;

		// Catch potential timestamps
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
    }

    /**
     * @return String A nice HTML representation of a string.
     */
    private static function dumpStringToHtml($str) {

        $length = self::getNiceStringLength($str);
        $safe = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');

        return <<<END
<span class="octopusDebugString">
&quot;$safe&quot;<span class="octopusDebugStringLength">&nbsp;&mdash;&nbsp;$length</span>
</span>
END;
    }

    /**
     * @return A string with some extra metadata.
     */
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

    }

    private static function flushBufferedOctopusResponse() {

    	if (!self::usingBufferedOctopusResponse()) {
    		return;
    	}

    	$app = Octopus_App::singleton();
    	$resp = $app->getCurrentResponse();
    	$resp->flush();

    }

    /**
     * @return String A bit of HTML describing the length of $str, in
     * characters.
     */
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
    }

    /**
     * @return
     */
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

    /**
     * When in an octopus context, checks the current Octopus_App instance for
     * an option value, falling back to defines otherwise.
     */
    private static function getOption($name) {

    	if (self::usingOctopus()) {
    		$app = Octopus_App::singleton();
    		return $app->$name;
    	}

    	if (defined($name)) {
    		return constant($name);
    	}

    }

    /**
     * Remove certain sensitive strings from debug output.
     */
    private static function sanitizeDebugOutput($output) {

        if (defined('DB_password') && DB_password) {
            $output = str_replace(DB_password, '[removed from debug output]', $output);
            $output = str_replace(htmlspecialchars(DB_password, ENT_QUOTES, 'UTF-8'), '[removed from debug output]', $output);
        }

        return $output;
    }

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Debug_Dumped_Vars
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Helper used to render variables dumped via dump_r or Octopus_Debug::dump
 */
class Octopus_Debug_Dumped_Vars implements Dumpable, ArrayAccess {

	private $vars;

	public function __construct(Array $vars) {
		$this->vars = $vars;
	}

	public function __dumpHtml() {
		return Octopus_Debug::dumpToString($this->vars, 'html');
	}

	public function __dumpText() {

		$result = array();
		foreach($this->vars as $key => $var) {
			if ($result) $result[] = str_repeat('-', 80);
			$result[] = Octopus_Debug::dumpToString($var, 'text');
		}

		return implode("\n", $result);
	}

	public function __toString() {
		return $this->__dumpText();
	}

	public function offsetExists($offset) {
		return array_key_exists($offset, $this->vars);
	}

	public function offsetGet($offset) {
		return $this->vars[$offset];
	}

	public function offsetSet($offset, $value) {
		throw new Octopus_Exception("Setting values on Octopus_Debug_Dumped_Vars is not supported.");
	}

	public function offsetUnset($offset) {
		throw new Octopus_Exception("Unsetting values on Octopus_Debug_Dumped_Vars is not supported.");
	}

}

////////////////////////////////////////////////////////////////////////////////
//
// interface Dumpable
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Implement this interface to have greater control over how your class is
 * displayed in log output.
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

////////////////////////////////////////////////////////////////////////////////
//
// Global functions - Mostly just aliases to static methods on Octopus_Debug
//
////////////////////////////////////////////////////////////////////////////////

if (!function_exists('dump_r')) {

    /**
     * @deprecated Is this in use anywhere?
     * @return String The results of var_dump for $var.
     */
    function debug_var($var) {
        return Octopus_Debug::dumpToString($var);
    }

    /**
     * @see Octopus_Debug::enableDumping
     */
    function enable_dump_r($enable = true) {
    	Octopus_Debug::enableDumping($enable);
    }

    /**
     * @see Octopus_Debug::disableDumping
     */
    function disable_dump_r() {
    	Octopus_Debug::disableDumping();
    }

    /**
     * Outputs the arguments passed to it along w/ debugging info.
     * @param mixed Any arguments you want dumped.
     * @return Mixed The first argument passed in.
     * @see Octopus_Debug::dump
     */
    function dump_r() {
    	return call_user_func_array(array('Octopus_Debug', 'dump'), func_get_args());
    }

    /**
     * Calls dump_r and then exit().
     * @param mixed Any values you want displayed.
     * @return If dumping is disabled, returns the first argument passed in.
     * @see Octopus_Debug::dumpAndExit()
     */
    function dump_x() {
    	return call_user_func_array(array('Octopus_Debug', 'dumpAndExit'), func_get_args());
    }

    /**
     * Prints out a slightly saner backtrace to stderr.
     * @see Octopus_Debug::printBacktrace
     */
    function print_backtrace($limit = 0) {
    	Octopus_Debug::printBacktrace($limit);
    }

} // if (!function_exists('dump_r))

////////////////////////////////////////////////////////////////////////////////
//
// Octopus Debug CSS
//
////////////////////////////////////////////////////////////////////////////////

Octopus_Log_Listener_Html::$css = <<<END

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

Octopus_Log_Listener_Html::$js = <<<END

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