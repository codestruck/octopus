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

error_reporting(E_ALL);

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
	public static function exceptionHandler(Exception $ex) {
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
	public static function registerErrorHandler() {
		set_error_handler(array('Octopus_Log', 'errorHandler'));
	}

	/**
	 * Registers a PHP exception handler that logs all exceptions as
	 * ::LEVEL_ERROR.
	 * Octopus apps use this by default.
	 * @see ::exceptionHandler()
	 */
	public static function registerExceptionHandler() {
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

			if (count($result) > 0) {
				$result[] = $item;
				continue;
			}

			if ($item['file'] === __FILE__) {
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

		if ($message instanceof Exception) {
			$trace = Octopus_Debug::getNiceBacktrace($message->getTrace());
			$message = Octopus_Debug::dumpToString($message, 'text', true);
		} else {
			$trace = $this->getStackTrace();
		}

		$entry = call_user_func($this->getFormatter(), $message, $log, $level, time(), $trace);

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

    private static $writtenCssAndJs = false;

	public function write($message, $log, $level) {

		// Don't allow redirecting after an HTML message is displayed (so you
		// have a chance to review it in-browser).
		Octopus_Debug::disableRedirects();

		$html = new Octopus_Log_Listener_Html_Message($log, $level);

		$niceMessage = ($message instanceof Dumpable) ? $message->__dumpHtml() : $message;
		$isException = ($message instanceof Exception);

		if ($message instanceof Octopus_Debug_Dumped_Vars) {
			$html->add('Variable(s)', $niceMessage);
		} else if ($isException) {
			$html->add(get_class($message), new Octopus_Debug_Html_Exception($message));
		} else {
			$html->add('Message', $niceMessage);
		}

		// Add metadata to the message
        foreach(array('_GET', '_POST', '_SERVER', '_SESSION', '_FILES') as $arname) {

            if (!empty($GLOBALS[$arname])) {
            	$var = new Octopus_Log_Listener_Html_Variable($GLOBALS[$arname]);
            	$html->add("\$$arname", $var);
            }

        }

     	if ($isException) {
       		$trace = Octopus_Debug::getNiceBacktrace($message->getTrace());
       		$html->title = get_class($message);
       	} else {
       		$trace = Octopus_Debug::getNiceBacktrace();
       	}

       	if ($trace) {
       		$html->add('Trace', new Octopus_Log_Listener_Html_Trace($trace));

       		$line = Octopus_Debug::getMostRelevantTraceLine($trace, array(__FILE__));
       		if ($line) {
       			$html->title .= ($html->title ? ' at ' : '') . "{$line['nice_file']}, line {$line['line']}";
       		}
       	}

        // Add console output in an HTML comment before the actual HTML
        $mem = fopen('php://temp', 'r+');
        if ($mem) {
        	$console = new Octopus_Log_Listener_Console($mem);
        	$console->write($message, $log, $level);
        	fseek($mem, 0);
        	$lines = array();
        	while(($line = fgets($mem)) !== false) {
        		$lines[] = $line;
        	}
        	$lines = trim(implode("\n", $lines));
        	fclose($mem);

        	// HACK: for light lines used by console logger, use em dashes
        	$lines = str_replace(str_repeat('-', 80), str_repeat('—', 80), $lines);
        	$lines = str_replace('--', '', $lines);

        	$html = <<<END
<!--
{$lines}
-->
{$html}
END;
        }


		if (Octopus_Debug::usingOctopus()) {

			$app = Octopus_App::singleton();
			$resp = $app->getCurrentResponse();

			if ($resp) {
				// Write message to the response and flush
				$resp->append($html);
				$resp->append(self::getCssAndJs());
				$resp->flush();
				return;
			}
		}

		echo($html);
		echo(self::getCssAndJs()) ;
	}

	private static function getCssAndJs() {

		if (self::$writtenCssAndJs) {
			return '';
		}

		self::$writtenCssAndJs = true;

		$css = file_get_contents(dirname(__FILE__) . '/debug.css');
		if ($css) {
			$css = <<<END
<link href="/octopus/includes/functions/debug.css" rel="stylesheet" type="text/css" />
END;
		} else {
			$css = '';
		}

		$js = self::$js;
		$js = str_replace(
				'%%JQUERY%%',
				'(function(w,x){var y=w.document,navigator=w.navigator,location=w.location;var z=(function(){var h=function(a,b){return new h.fn.init(a,b,rootjQuery)},_jQuery=w.jQuery,_$=w.$,rootjQuery,quickExpr=/^(?:[^#<]*(<[\\w\\W]+>)[^>]*$|#([\\w\\-]*)$)/,rnotwhite=/\\S/,trimLeft=/^\\s+/,trimRight=/\\s+$/,rsingleTag=/^<(\\w+)\\s*\\/?>(?:<\\/\\1>)?$/,rvalidchars=/^[\\],:{}\\s]*$/,rvalidescape=/\\\\(?:["\\\\\\/bfnrt]|u[0-9a-fA-F]{4})/g,rvalidtokens=/"[^"\\\\\\n\\r]*"|true|false|null|-?\\d+(?:\\.\\d*)?(?:[eE][+\\-]?\\d+)?/g,rvalidbraces=/(?:^|:|,)(?:\\s*\\[)+/g,rwebkit=/(webkit)[ \\/]([\\w.]+)/,ropera=/(opera)(?:.*version)?[ \\/]([\\w.]+)/,rmsie=/(msie) ([\\w.]+)/,rmozilla=/(mozilla)(?:.*? rv:([\\w.]+))?/,rdashAlpha=/-([a-z]|[0-9])/ig,rmsPrefix=/^-ms-/,fcamelCase=function(a,b){return(b+"").toUpperCase()},userAgent=navigator.userAgent,browserMatch,readyList,DOMContentLoaded,toString=Object.prototype.toString,hasOwn=Object.prototype.hasOwnProperty,push=Array.prototype.push,slice=Array.prototype.slice,trim=String.prototype.trim,indexOf=Array.prototype.indexOf,class2type={};h.fn=h.prototype={constructor:h,init:function(a,b,c){var d,elem,ret,doc;if(!a){return this}if(a.nodeType){this.context=this[0]=a;this.length=1;return this}if(a==="body"&&!b&&y.body){this.context=y;this[0]=y.body;this.selector=a;this.length=1;return this}if(typeof a==="string"){if(a.charAt(0)==="<"&&a.charAt(a.length-1)===">"&&a.length>=3){d=[null,a,null]}else{d=quickExpr.exec(a)}if(d&&(d[1]||!b)){if(d[1]){b=b instanceof h?b[0]:b;doc=(b?b.ownerDocument||b:y);ret=rsingleTag.exec(a);if(ret){if(h.isPlainObject(b)){a=[y.createElement(ret[1])];h.fn.attr.call(a,b,true)}else{a=[doc.createElement(ret[1])]}}else{ret=h.buildFragment([d[1]],[doc]);a=(ret.cacheable?h.clone(ret.fragment):ret.fragment).childNodes}return h.merge(this,a)}else{elem=y.getElementById(d[2]);if(elem&&elem.parentNode){if(elem.id!==d[2]){return c.find(a)}this.length=1;this[0]=elem}this.context=y;this.selector=a;return this}}else if(!b||b.jquery){return(b||c).find(a)}else{return this.constructor(b).find(a)}}else if(h.isFunction(a)){return c.ready(a)}if(a.selector!==x){this.selector=a.selector;this.context=a.context}return h.makeArray(a,this)},selector:"",jquery:"1.7.1",length:0,size:function(){return this.length},toArray:function(){return slice.call(this,0)},get:function(a){return a==null?this.toArray():(a<0?this[this.length+a]:this[a])},pushStack:function(a,b,c){var d=this.constructor();if(h.isArray(a)){push.apply(d,a)}else{h.merge(d,a)}d.prevObject=this;d.context=this.context;if(b==="find"){d.selector=this.selector+(this.selector?" ":"")+c}else if(b){d.selector=this.selector+"."+b+"("+c+")"}return d},each:function(a,b){return h.each(this,a,b)},ready:function(a){h.bindReady();readyList.add(a);return this},eq:function(i){i=+i;return i===-1?this.slice(i):this.slice(i,i+1)},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},slice:function(){return this.pushStack(slice.apply(this,arguments),"slice",slice.call(arguments).join(","))},map:function(b){return this.pushStack(h.map(this,function(a,i){return b.call(a,i,a)}))},end:function(){return this.prevObject||this.constructor(null)},push:push,sort:[].sort,splice:[].splice};h.fn.init.prototype=h.fn;h.extend=h.fn.extend=function(){var a,name,src,copy,copyIsArray,clone,target=arguments[0]||{},i=1,length=arguments.length,deep=false;if(typeof target==="boolean"){deep=target;target=arguments[1]||{};i=2}if(typeof target!=="object"&&!h.isFunction(target)){target={}}if(length===i){target=this;--i}for(;i<length;i++){if((a=arguments[i])!=null){for(name in a){src=target[name];copy=a[name];if(target===copy){continue}if(deep&&copy&&(h.isPlainObject(copy)||(copyIsArray=h.isArray(copy)))){if(copyIsArray){copyIsArray=false;clone=src&&h.isArray(src)?src:[]}else{clone=src&&h.isPlainObject(src)?src:{}}target[name]=h.extend(deep,clone,copy)}else if(copy!==x){target[name]=copy}}}}return target};h.extend({noConflict:function(a){if(w.$===h){w.$=_$}if(a&&w.jQuery===h){w.jQuery=_jQuery}return h},isReady:false,readyWait:1,holdReady:function(a){if(a){h.readyWait++}else{h.ready(true)}},ready:function(a){if((a===true&&!--h.readyWait)||(a!==true&&!h.isReady)){if(!y.body){return setTimeout(h.ready,1)}h.isReady=true;if(a!==true&&--h.readyWait>0){return}readyList.fireWith(y,[h]);if(h.fn.trigger){h(y).trigger("ready").off("ready")}}},bindReady:function(){if(readyList){return}readyList=h.Callbacks("once memory");if(y.readyState==="complete"){return setTimeout(h.ready,1)}if(y.addEventListener){y.addEventListener("DOMContentLoaded",DOMContentLoaded,false);w.addEventListener("load",h.ready,false)}else if(y.attachEvent){y.attachEvent("onreadystatechange",DOMContentLoaded);w.attachEvent("onload",h.ready);var a=false;try{a=w.frameElement==null}catch(e){}if(y.documentElement.doScroll&&a){doScrollCheck()}}},isFunction:function(a){return h.type(a)==="function"},isArray:Array.isArray||function(a){return h.type(a)==="array"},isWindow:function(a){return a&&typeof a==="object"&&"setInterval"in a},isNumeric:function(a){return!isNaN(parseFloat(a))&&isFinite(a)},type:function(a){return a==null?String(a):class2type[toString.call(a)]||"object"},isPlainObject:function(a){if(!a||h.type(a)!=="object"||a.nodeType||h.isWindow(a)){return false}try{if(a.constructor&&!hasOwn.call(a,"constructor")&&!hasOwn.call(a.constructor.prototype,"isPrototypeOf")){return false}}catch(e){return false}var b;for(b in a){}return b===x||hasOwn.call(a,b)},isEmptyObject:function(a){for(var b in a){return false}return true},error:function(a){throw new Error(a);},parseJSON:function(a){if(typeof a!=="string"||!a){return null}a=h.trim(a);if(w.JSON&&w.JSON.parse){return w.JSON.parse(a)}if(rvalidchars.test(a.replace(rvalidescape,"@").replace(rvalidtokens,"]").replace(rvalidbraces,""))){return(new Function("return "+a))()}h.error("Invalid JSON: "+a)},parseXML:function(a){var b,tmp;try{if(w.DOMParser){tmp=new DOMParser();b=tmp.parseFromString(a,"text/xml")}else{b=new ActiveXObject("Microsoft.XMLDOM");b.async="false";b.loadXML(a)}}catch(e){b=x}if(!b||!b.documentElement||b.getElementsByTagName("parsererror").length){h.error("Invalid XML: "+a)}return b},noop:function(){},globalEval:function(b){if(b&&rnotwhite.test(b)){(w.execScript||function(a){w["eval"].call(w,a)})(b)}},camelCase:function(a){return a.replace(rmsPrefix,"ms-").replace(rdashAlpha,fcamelCase)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toUpperCase()===b.toUpperCase()},each:function(a,b,c){var d,i=0,length=a.length,isObj=length===x||h.isFunction(a);if(c){if(isObj){for(d in a){if(b.apply(a[d],c)===false){break}}}else{for(;i<length;){if(b.apply(a[i++],c)===false){break}}}}else{if(isObj){for(d in a){if(b.call(a[d],d,a[d])===false){break}}}else{for(;i<length;){if(b.call(a[i],i,a[i++])===false){break}}}}return a},trim:trim?function(a){return a==null?"":trim.call(a)}:function(a){return a==null?"":a.toString().replace(trimLeft,"").replace(trimRight,"")},makeArray:function(a,b){var c=b||[];if(a!=null){var d=h.type(a);if(a.length==null||d==="string"||d==="function"||d==="regexp"||h.isWindow(a)){push.call(c,a)}else{h.merge(c,a)}}return c},inArray:function(a,b,i){var c;if(b){if(indexOf){return indexOf.call(b,a,i)}c=b.length;i=i?i<0?Math.max(0,c+i):i:0;for(;i<c;i++){if(i in b&&b[i]===a){return i}}}return-1},merge:function(a,b){var i=a.length,j=0;if(typeof b.length==="number"){for(var l=b.length;j<l;j++){a[i++]=b[j]}}else{while(b[j]!==x){a[i++]=b[j++]}}a.length=i;return a},grep:function(a,b,c){var d=[],retVal;c=!!c;for(var i=0,length=a.length;i<length;i++){retVal=!!b(a[i],i);if(c!==retVal){d.push(a[i])}}return d},map:function(a,b,c){var d,key,ret=[],i=0,length=a.length,isArray=a instanceof h||length!==x&&typeof length==="number"&&((length>0&&a[0]&&a[length-1])||length===0||h.isArray(a));if(isArray){for(;i<length;i++){d=b(a[i],i,c);if(d!=null){ret[ret.length]=d}}}else{for(key in a){d=b(a[key],key,c);if(d!=null){ret[ret.length]=d}}}return ret.concat.apply([],ret)},guid:1,proxy:function(a,b){if(typeof b==="string"){var c=a[b];b=a;a=c}if(!h.isFunction(a)){return x}var d=slice.call(arguments,2),proxy=function(){return a.apply(b,d.concat(slice.call(arguments)))};proxy.guid=a.guid=a.guid||proxy.guid||h.guid++;return proxy},access:function(a,b,c,d,e,f){var g=a.length;if(typeof b==="object"){for(var k in b){h.access(a,k,b[k],d,e,c)}return a}if(c!==x){d=!f&&d&&h.isFunction(c);for(var i=0;i<g;i++){e(a[i],b,d?c.call(a[i],i,e(a[i],b)):c,f)}return a}return g?e(a[0],b):x},now:function(){return(new Date()).getTime()},uaMatch:function(a){a=a.toLowerCase();var b=rwebkit.exec(a)||ropera.exec(a)||rmsie.exec(a)||a.indexOf("compatible")<0&&rmozilla.exec(a)||[];return{browser:b[1]||"",version:b[2]||"0"}},sub:function(){function jQuerySub(a,b){return new jQuerySub.fn.init(a,b)}h.extend(true,jQuerySub,this);jQuerySub.superclass=this;jQuerySub.fn=jQuerySub.prototype=this();jQuerySub.fn.constructor=jQuerySub;jQuerySub.sub=this.sub;jQuerySub.fn.init=function init(a,b){if(b&&b instanceof h&&!(b instanceof jQuerySub)){b=jQuerySub(b)}return h.fn.init.call(this,a,b,c)};jQuerySub.fn.init.prototype=jQuerySub.fn;var c=jQuerySub(y);return jQuerySub},browser:{}});h.each("Boolean Number String Function Array Date RegExp Object".split(" "),function(i,a){class2type["[object "+a+"]"]=a.toLowerCase()});browserMatch=h.uaMatch(userAgent);if(browserMatch.browser){h.browser[browserMatch.browser]=true;h.browser.version=browserMatch.version}if(h.browser.webkit){h.browser.safari=true}if(rnotwhite.test("\\xA0")){trimLeft=/^[\\s\\xA0]+/;trimRight=/[\\s\\xA0]+$/}rootjQuery=h(y);if(y.addEventListener){DOMContentLoaded=function(){y.removeEventListener("DOMContentLoaded",DOMContentLoaded,false);h.ready()}}else if(y.attachEvent){DOMContentLoaded=function(){if(y.readyState==="complete"){y.detachEvent("onreadystatechange",DOMContentLoaded);h.ready()}}}function doScrollCheck(){if(h.isReady){return}try{y.documentElement.doScroll("left")}catch(e){setTimeout(doScrollCheck,1);return}h.ready()}return h})();var A={};function createFlags(a){var b=A[a]={},i,length;a=a.split(/\\s+/);for(i=0,length=a.length;i<length;i++){b[a[i]]=true}return b}z.Callbacks=function(c){c=c?(A[c]||createFlags(c)):{};var d=[],stack=[],memory,firing,firingStart,firingLength,firingIndex,add=function(a){var i,length,elem,type,actual;for(i=0,length=a.length;i<length;i++){elem=a[i];type=z.type(elem);if(type==="array"){add(elem)}else if(type==="function"){if(!c.unique||!self.has(elem)){d.push(elem)}}}},fire=function(a,b){b=b||[];memory=!c.memory||[a,b];firing=true;firingIndex=firingStart||0;firingStart=0;firingLength=d.length;for(;d&&firingIndex<firingLength;firingIndex++){if(d[firingIndex].apply(a,b)===false&&c.stopOnFalse){memory=true;break}}firing=false;if(d){if(!c.once){if(stack&&stack.length){memory=stack.shift();self.fireWith(memory[0],memory[1])}}else if(memory===true){self.disable()}else{d=[]}}},self={add:function(){if(d){var a=d.length;add(arguments);if(firing){firingLength=d.length}else if(memory&&memory!==true){firingStart=a;fire(memory[0],memory[1])}}return this},remove:function(){if(d){var a=arguments,argIndex=0,argLength=a.length;for(;argIndex<argLength;argIndex++){for(var i=0;i<d.length;i++){if(a[argIndex]===d[i]){if(firing){if(i<=firingLength){firingLength--;if(i<=firingIndex){firingIndex--}}}d.splice(i--,1);if(c.unique){break}}}}}return this},has:function(a){if(d){var i=0,length=d.length;for(;i<length;i++){if(a===d[i]){return true}}}return false},empty:function(){d=[];return this},disable:function(){d=stack=memory=x;return this},disabled:function(){return!d},lock:function(){stack=x;if(!memory||memory===true){self.disable()}return this},locked:function(){return!stack},fireWith:function(a,b){if(stack){if(firing){if(!c.once){stack.push([a,b])}}else if(!(c.once&&memory)){fire(a,b)}}return this},fire:function(){self.fireWith(this,arguments);return this},fired:function(){return!!memory}};return self};var B=[].slice;z.extend({Deferred:function(h){var i=z.Callbacks("once memory"),failList=z.Callbacks("once memory"),progressList=z.Callbacks("memory"),state="pending",lists={resolve:i,reject:failList,notify:progressList},promise={done:i.add,fail:failList.add,progress:progressList.add,state:function(){return state},isResolved:i.fired,isRejected:failList.fired,then:function(a,b,c){deferred.done(a).fail(b).progress(c);return this},always:function(){deferred.done.apply(deferred,arguments).fail.apply(deferred,arguments);return this},pipe:function(e,f,g){return z.Deferred(function(d){z.each({done:[e,"resolve"],fail:[f,"reject"],progress:[g,"notify"]},function(a,b){var c=b[0],action=b[1],returned;if(z.isFunction(c)){deferred[a](function(){returned=c.apply(this,arguments);if(returned&&z.isFunction(returned.promise)){returned.promise().then(d.resolve,d.reject,d.notify)}else{d[action+"With"](this===deferred?d:this,[returned])}})}else{deferred[a](d[action])}})}).promise()},promise:function(a){if(a==null){a=promise}else{for(var b in promise){a[b]=promise[b]}}return a}},deferred=promise.promise({}),key;for(key in lists){deferred[key]=lists[key].fire;deferred[key+"With"]=lists[key].fireWith}deferred.done(function(){state="resolved"},failList.disable,progressList.lock).fail(function(){state="rejected"},i.disable,progressList.lock);if(h){h.call(deferred,deferred)}return deferred},when:function(b){var c=B.call(arguments,0),i=0,length=c.length,pValues=new Array(length),count=length,pCount=length,deferred=length<=1&&b&&z.isFunction(b.promise)?b:z.Deferred(),promise=deferred.promise();function resolveFunc(i){return function(a){c[i]=arguments.length>1?B.call(arguments,0):a;if(!(--count)){deferred.resolveWith(deferred,c)}}}function progressFunc(i){return function(a){pValues[i]=arguments.length>1?B.call(arguments,0):a;deferred.notifyWith(promise,pValues)}}if(length>1){for(;i<length;i++){if(c[i]&&c[i].promise&&z.isFunction(c[i].promise)){c[i].promise().then(resolveFunc(i),deferred.reject,progressFunc(i))}else{--count}}if(!count){deferred.resolveWith(deferred,c)}}else if(deferred!==b){deferred.resolveWith(deferred,length?[b]:[])}return promise}});z.support=(function(){var b,all,a,select,opt,input,marginDiv,fragment,tds,events,eventName,i,isSupported,div=y.createElement("div"),documentElement=y.documentElement;div.setAttribute("className","t");div.innerHTML="   <link/><table></table><a href=\'/a\' style=\'top:1px;float:left;opacity:.55;\'>a</a><input type=\'checkbox\'/>";all=div.getElementsByTagName("*");a=div.getElementsByTagName("a")[0];if(!all||!all.length||!a){return{}}select=y.createElement("select");opt=select.appendChild(y.createElement("option"));input=div.getElementsByTagName("input")[0];b={leadingWhitespace:(div.firstChild.nodeType===3),tbody:!div.getElementsByTagName("tbody").length,htmlSerialize:!!div.getElementsByTagName("link").length,style:/top/.test(a.getAttribute("style")),hrefNormalized:(a.getAttribute("href")==="/a"),opacity:/^0.55/.test(a.style.opacity),cssFloat:!!a.style.cssFloat,checkOn:(input.value==="on"),optSelected:opt.selected,getSetAttribute:div.className!=="t",enctype:!!y.createElement("form").enctype,html5Clone:y.createElement("nav").cloneNode(true).outerHTML!=="<:nav></:nav>",submitBubbles:true,changeBubbles:true,focusinBubbles:false,deleteExpando:true,noCloneEvent:true,inlineBlockNeedsLayout:false,shrinkWrapBlocks:false,reliableMarginRight:true};input.checked=true;b.noCloneChecked=input.cloneNode(true).checked;select.disabled=true;b.optDisabled=!opt.disabled;try{delete div.test}catch(e){b.deleteExpando=false}if(!div.addEventListener&&div.attachEvent&&div.fireEvent){div.attachEvent("onclick",function(){b.noCloneEvent=false});div.cloneNode(true).fireEvent("onclick")}input=y.createElement("input");input.value="t";input.setAttribute("type","radio");b.radioValue=input.value==="t";input.setAttribute("checked","checked");div.appendChild(input);fragment=y.createDocumentFragment();fragment.appendChild(div.lastChild);b.checkClone=fragment.cloneNode(true).cloneNode(true).lastChild.checked;b.appendChecked=input.checked;fragment.removeChild(input);fragment.appendChild(div);div.innerHTML="";if(w.getComputedStyle){marginDiv=y.createElement("div");marginDiv.style.width="0";marginDiv.style.marginRight="0";div.style.width="2px";div.appendChild(marginDiv);b.reliableMarginRight=(parseInt((w.getComputedStyle(marginDiv,null)||{marginRight:0}).marginRight,10)||0)===0}if(div.attachEvent){for(i in{submit:1,change:1,focusin:1}){eventName="on"+i;isSupported=(eventName in div);if(!isSupported){div.setAttribute(eventName,"return;");isSupported=(typeof div[eventName]==="function")}b[i+"Bubbles"]=isSupported}}fragment.removeChild(div);fragment=select=opt=marginDiv=div=input=null;z(function(){var a,outer,inner,table,td,offsetSupport,conMarginTop,ptlm,vb,style,html,body=y.getElementsByTagName("body")[0];if(!body){return}conMarginTop=1;ptlm="position:absolute;top:0;left:0;width:1px;height:1px;margin:0;";vb="visibility:hidden;border:0;";style="style=\'"+ptlm+"border:5px solid #000;padding:0;\'";html="<div "+style+"><div></div></div>"+"<table "+style+" cellpadding=\'0\' cellspacing=\'0\'>"+"<tr><td></td></tr></table>";a=y.createElement("div");a.style.cssText=vb+"width:0;height:0;position:static;top:0;margin-top:"+conMarginTop+"px";body.insertBefore(a,body.firstChild);div=y.createElement("div");a.appendChild(div);div.innerHTML="<table><tr><td style=\'padding:0;border:0;display:none\'></td><td>t</td></tr></table>";tds=div.getElementsByTagName("td");isSupported=(tds[0].offsetHeight===0);tds[0].style.display="";tds[1].style.display="none";b.reliableHiddenOffsets=isSupported&&(tds[0].offsetHeight===0);div.innerHTML="";div.style.width=div.style.paddingLeft="1px";z.boxModel=b.boxModel=div.offsetWidth===2;if(typeof div.style.zoom!=="undefined"){div.style.display="inline";div.style.zoom=1;b.inlineBlockNeedsLayout=(div.offsetWidth===2);div.style.display="";div.innerHTML="<div style=\'width:4px;\'></div>";b.shrinkWrapBlocks=(div.offsetWidth!==2)}div.style.cssText=ptlm+vb;div.innerHTML=html;outer=div.firstChild;inner=outer.firstChild;td=outer.nextSibling.firstChild.firstChild;offsetSupport={doesNotAddBorder:(inner.offsetTop!==5),doesAddBorderForTableAndCells:(td.offsetTop===5)};inner.style.position="fixed";inner.style.top="20px";offsetSupport.fixedPosition=(inner.offsetTop===20||inner.offsetTop===15);inner.style.position=inner.style.top="";outer.style.overflow="hidden";outer.style.position="relative";offsetSupport.subtractsBorderForOverflowNotVisible=(inner.offsetTop===-5);offsetSupport.doesNotIncludeMarginInBodyOffset=(body.offsetTop!==conMarginTop);body.removeChild(a);div=a=null;z.extend(b,offsetSupport)});return b})();var C=/^(?:\\{.*\\}|\\[.*\\])$/,rmultiDash=/([A-Z])/g;z.extend({cache:{},uuid:0,expando:"jQuery"+(z.fn.jquery+Math.random()).replace(/\\D/g,""),noData:{"embed":true,"object":"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000","applet":true},hasData:function(a){a=a.nodeType?z.cache[a[z.expando]]:a[z.expando];return!!a&&!isEmptyDataObject(a)},data:function(a,b,c,d){if(!z.acceptData(a)){return}var e,thisCache,ret,internalKey=z.expando,getByName=typeof b==="string",isNode=a.nodeType,cache=isNode?z.cache:a,id=isNode?a[internalKey]:a[internalKey]&&internalKey,isEvents=b==="events";if((!id||!cache[id]||(!isEvents&&!d&&!cache[id].data))&&getByName&&c===x){return}if(!id){if(isNode){a[internalKey]=id=++z.uuid}else{id=internalKey}}if(!cache[id]){cache[id]={};if(!isNode){cache[id].toJSON=z.noop}}if(typeof b==="object"||typeof b==="function"){if(d){cache[id]=z.extend(cache[id],b)}else{cache[id].data=z.extend(cache[id].data,b)}}e=thisCache=cache[id];if(!d){if(!thisCache.data){thisCache.data={}}thisCache=thisCache.data}if(c!==x){thisCache[z.camelCase(b)]=c}if(isEvents&&!thisCache[b]){return e.events}if(getByName){ret=thisCache[b];if(ret==null){ret=thisCache[z.camelCase(b)]}}else{ret=thisCache}return ret},removeData:function(a,b,c){if(!z.acceptData(a)){return}var d,i,l,internalKey=z.expando,isNode=a.nodeType,cache=isNode?z.cache:a,id=isNode?a[internalKey]:internalKey;if(!cache[id]){return}if(b){d=c?cache[id]:cache[id].data;if(d){if(!z.isArray(b)){if(b in d){b=[b]}else{b=z.camelCase(b);if(b in d){b=[b]}else{b=b.split(" ")}}}for(i=0,l=b.length;i<l;i++){delete d[b[i]]}if(!(c?isEmptyDataObject:z.isEmptyObject)(d)){return}}}if(!c){delete cache[id].data;if(!isEmptyDataObject(cache[id])){return}}if(z.support.deleteExpando||!cache.setInterval){delete cache[id]}else{cache[id]=null}if(isNode){if(z.support.deleteExpando){delete a[internalKey]}else if(a.removeAttribute){a.removeAttribute(internalKey)}else{a[internalKey]=null}}},_data:function(a,b,c){return z.data(a,b,c,true)},acceptData:function(a){if(a.nodeName){var b=z.noData[a.nodeName.toLowerCase()];if(b){return!(b===true||a.getAttribute("classid")!==b)}}return true}});z.fn.extend({data:function(b,c){var d,attr,name,data=null;if(typeof b==="undefined"){if(this.length){data=z.data(this[0]);if(this[0].nodeType===1&&!z._data(this[0],"parsedAttrs")){attr=this[0].attributes;for(var i=0,l=attr.length;i<l;i++){name=attr[i].name;if(name.indexOf("data-")===0){name=z.camelCase(name.substring(5));dataAttr(this[0],name,data[name])}}z._data(this[0],"parsedAttrs",true)}}return data}else if(typeof b==="object"){return this.each(function(){z.data(this,b)})}d=b.split(".");d[1]=d[1]?"."+d[1]:"";if(c===x){data=this.triggerHandler("getData"+d[1]+"!",[d[0]]);if(data===x&&this.length){data=z.data(this[0],b);data=dataAttr(this[0],b,data)}return data===x&&d[1]?this.data(d[0]):data}else{return this.each(function(){var a=z(this),args=[d[0],c];a.triggerHandler("setData"+d[1]+"!",args);z.data(this,b,c);a.triggerHandler("changeData"+d[1]+"!",args)})}},removeData:function(a){return this.each(function(){z.removeData(this,a)})}});function dataAttr(a,b,c){if(c===x&&a.nodeType===1){var d="data-"+b.replace(rmultiDash,"-$1").toLowerCase();c=a.getAttribute(d);if(typeof c==="string"){try{c=c==="true"?true:c==="false"?false:c==="null"?null:z.isNumeric(c)?parseFloat(c):C.test(c)?z.parseJSON(c):c}catch(e){}z.data(a,b,c)}else{c=x}}return c}function isEmptyDataObject(a){for(var b in a){if(b==="data"&&z.isEmptyObject(a[b])){continue}if(b!=="toJSON"){return false}}return true}function handleQueueMarkDefer(a,b,c){var d=b+"defer",queueDataKey=b+"queue",markDataKey=b+"mark",defer=z._data(a,d);if(defer&&(c==="queue"||!z._data(a,queueDataKey))&&(c==="mark"||!z._data(a,markDataKey))){setTimeout(function(){if(!z._data(a,queueDataKey)&&!z._data(a,markDataKey)){z.removeData(a,d,true);defer.fire()}},0)}}z.extend({_mark:function(a,b){if(a){b=(b||"fx")+"mark";z._data(a,b,(z._data(a,b)||0)+1)}},_unmark:function(a,b,c){if(a!==true){c=b;b=a;a=false}if(b){c=c||"fx";var d=c+"mark",count=a?0:((z._data(b,d)||1)-1);if(count){z._data(b,d,count)}else{z.removeData(b,d,true);handleQueueMarkDefer(b,c,"mark")}}},queue:function(a,b,c){var q;if(a){b=(b||"fx")+"queue";q=z._data(a,b);if(c){if(!q||z.isArray(c)){q=z._data(a,b,z.makeArray(c))}else{q.push(c)}}return q||[]}},dequeue:function(a,b){b=b||"fx";var c=z.queue(a,b),fn=c.shift(),hooks={};if(fn==="inprogress"){fn=c.shift()}if(fn){if(b==="fx"){c.unshift("inprogress")}z._data(a,b+".run",hooks);fn.call(a,function(){z.dequeue(a,b)},hooks)}if(!c.length){z.removeData(a,b+"queue "+b+".run",true);handleQueueMarkDefer(a,b,"queue")}}});z.fn.extend({queue:function(b,c){if(typeof b!=="string"){c=b;b="fx"}if(c===x){return z.queue(this[0],b)}return this.each(function(){var a=z.queue(this,b,c);if(b==="fx"&&a[0]!=="inprogress"){z.dequeue(this,b)}})},dequeue:function(a){return this.each(function(){z.dequeue(this,a)})},delay:function(d,e){d=z.fx?z.fx.speeds[d]||d:d;e=e||"fx";return this.queue(e,function(a,b){var c=setTimeout(a,d);b.stop=function(){clearTimeout(c)}})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,b){if(typeof a!=="string"){b=a;a=x}a=a||"fx";var c=z.Deferred(),elements=this,i=elements.length,count=1,deferDataKey=a+"defer",queueDataKey=a+"queue",markDataKey=a+"mark",tmp;function resolve(){if(!(--count)){c.resolveWith(elements,[elements])}}while(i--){if((tmp=z.data(elements[i],deferDataKey,x,true)||(z.data(elements[i],queueDataKey,x,true)||z.data(elements[i],markDataKey,x,true))&&z.data(elements[i],deferDataKey,z.Callbacks("once memory"),true))){count++;tmp.add(resolve)}}resolve();return c.promise()}});var D=/[\\n\\t\\r]/g,rspace=/\\s+/,rreturn=/\\r/g,rtype=/^(?:button|input)$/i,rfocusable=/^(?:button|input|object|select|textarea)$/i,rclickable=/^a(?:rea)?$/i,rboolean=/^(?:autofocus|autoplay|async|checked|controls|defer|disabled|hidden|loop|multiple|open|readonly|required|scoped|selected)$/i,getSetAttribute=z.support.getSetAttribute,nodeHook,boolHook,fixSpecified;z.fn.extend({attr:function(a,b){return z.access(this,a,b,true,z.attr)},removeAttr:function(a){return this.each(function(){z.removeAttr(this,a)})},prop:function(a,b){return z.access(this,a,b,true,z.prop)},removeProp:function(a){a=z.propFix[a]||a;return this.each(function(){try{this[a]=x;delete this[a]}catch(e){}})},addClass:function(a){var b,i,l,elem,setClass,c,cl;if(z.isFunction(a)){return this.each(function(j){z(this).addClass(a.call(this,j,this.className))})}if(a&&typeof a==="string"){b=a.split(rspace);for(i=0,l=this.length;i<l;i++){elem=this[i];if(elem.nodeType===1){if(!elem.className&&b.length===1){elem.className=a}else{setClass=" "+elem.className+" ";for(c=0,cl=b.length;c<cl;c++){if(!~setClass.indexOf(" "+b[c]+" ")){setClass+=b[c]+" "}}elem.className=z.trim(setClass)}}}}return this},removeClass:function(a){var b,i,l,elem,className,c,cl;if(z.isFunction(a)){return this.each(function(j){z(this).removeClass(a.call(this,j,this.className))})}if((a&&typeof a==="string")||a===x){b=(a||"").split(rspace);for(i=0,l=this.length;i<l;i++){elem=this[i];if(elem.nodeType===1&&elem.className){if(a){className=(" "+elem.className+" ").replace(D," ");for(c=0,cl=b.length;c<cl;c++){className=className.replace(" "+b[c]+" "," ")}elem.className=z.trim(className)}else{elem.className=""}}}}return this},toggleClass:function(b,c){var d=typeof b,isBool=typeof c==="boolean";if(z.isFunction(b)){return this.each(function(i){z(this).toggleClass(b.call(this,i,this.className,c),c)})}return this.each(function(){if(d==="string"){var a,i=0,self=z(this),state=c,classNames=b.split(rspace);while((a=classNames[i++])){state=isBool?state:!self.hasClass(a);self[state?"addClass":"removeClass"](a)}}else if(d==="undefined"||d==="boolean"){if(this.className){z._data(this,"__className__",this.className)}this.className=this.className||b===false?"":z._data(this,"__className__")||""}})},hasClass:function(a){var b=" "+a+" ",i=0,l=this.length;for(;i<l;i++){if(this[i].nodeType===1&&(" "+this[i].className+" ").replace(D," ").indexOf(b)>-1){return true}}return false},val:function(c){var d,ret,isFunction,elem=this[0];if(!arguments.length){if(elem){d=z.valHooks[elem.nodeName.toLowerCase()]||z.valHooks[elem.type];if(d&&"get"in d&&(ret=d.get(elem,"value"))!==x){return ret}ret=elem.value;return typeof ret==="string"?ret.replace(rreturn,""):ret==null?"":ret}return}isFunction=z.isFunction(c);return this.each(function(i){var b=z(this),val;if(this.nodeType!==1){return}if(isFunction){val=c.call(this,i,b.val())}else{val=c}if(val==null){val=""}else if(typeof val==="number"){val+=""}else if(z.isArray(val)){val=z.map(val,function(a){return a==null?"":a+""})}d=z.valHooks[this.nodeName.toLowerCase()]||z.valHooks[this.type];if(!d||!("set"in d)||d.set(this,val,"value")===x){this.value=val}})}});z.extend({valHooks:{option:{get:function(a){var b=a.attributes.value;return!b||b.specified?a.value:a.text}},select:{get:function(a){var b,i,max,option,index=a.selectedIndex,values=[],options=a.options,one=a.type==="select-one";if(index<0){return null}i=one?index:0;max=one?index+1:options.length;for(;i<max;i++){option=options[i];if(option.selected&&(z.support.optDisabled?!option.disabled:option.getAttribute("disabled")===null)&&(!option.parentNode.disabled||!z.nodeName(option.parentNode,"optgroup"))){b=z(option).val();if(one){return b}values.push(b)}}if(one&&!values.length&&options.length){return z(options[index]).val()}return values},set:function(a,b){var c=z.makeArray(b);z(a).find("option").each(function(){this.selected=z.inArray(z(this).val(),c)>=0});if(!c.length){a.selectedIndex=-1}return c}}},attrFn:{val:true,css:true,html:true,text:true,data:true,width:true,height:true,offset:true},attr:function(a,b,c,d){var e,hooks,notxml,nType=a.nodeType;if(!a||nType===3||nType===8||nType===2){return}if(d&&b in z.attrFn){return z(a)[b](c)}if(typeof a.getAttribute==="undefined"){return z.prop(a,b,c)}notxml=nType!==1||!z.isXMLDoc(a);if(notxml){b=b.toLowerCase();hooks=z.attrHooks[b]||(rboolean.test(b)?boolHook:nodeHook)}if(c!==x){if(c===null){z.removeAttr(a,b);return}else if(hooks&&"set"in hooks&&notxml&&(e=hooks.set(a,c,b))!==x){return e}else{a.setAttribute(b,""+c);return c}}else if(hooks&&"get"in hooks&&notxml&&(e=hooks.get(a,b))!==null){return e}else{e=a.getAttribute(b);return e===null?x:e}},removeAttr:function(a,b){var c,attrNames,name,l,i=0;if(b&&a.nodeType===1){attrNames=b.toLowerCase().split(rspace);l=attrNames.length;for(;i<l;i++){name=attrNames[i];if(name){c=z.propFix[name]||name;z.attr(a,name,"");a.removeAttribute(getSetAttribute?name:c);if(rboolean.test(name)&&c in a){a[c]=false}}}}},attrHooks:{type:{set:function(a,b){if(rtype.test(a.nodeName)&&a.parentNode){z.error("type property can\'t be changed")}else if(!z.support.radioValue&&b==="radio"&&z.nodeName(a,"input")){var c=a.value;a.setAttribute("type",b);if(c){a.value=c}return b}}},value:{get:function(a,b){if(nodeHook&&z.nodeName(a,"button")){return nodeHook.get(a,b)}return b in a?a.value:null},set:function(a,b,c){if(nodeHook&&z.nodeName(a,"button")){return nodeHook.set(a,b,c)}a.value=b}}},propFix:{tabindex:"tabIndex",readonly:"readOnly","for":"htmlFor","class":"className",maxlength:"maxLength",cellspacing:"cellSpacing",cellpadding:"cellPadding",rowspan:"rowSpan",colspan:"colSpan",usemap:"useMap",frameborder:"frameBorder",contenteditable:"contentEditable"},prop:function(a,b,c){var d,hooks,notxml,nType=a.nodeType;if(!a||nType===3||nType===8||nType===2){return}notxml=nType!==1||!z.isXMLDoc(a);if(notxml){b=z.propFix[b]||b;hooks=z.propHooks[b]}if(c!==x){if(hooks&&"set"in hooks&&(d=hooks.set(a,c,b))!==x){return d}else{return(a[b]=c)}}else{if(hooks&&"get"in hooks&&(d=hooks.get(a,b))!==null){return d}else{return a[b]}}},propHooks:{tabIndex:{get:function(a){var b=a.getAttributeNode("tabindex");return b&&b.specified?parseInt(b.value,10):rfocusable.test(a.nodeName)||rclickable.test(a.nodeName)&&a.href?0:x}}}});z.attrHooks.tabindex=z.propHooks.tabIndex;boolHook={get:function(a,b){var c,property=z.prop(a,b);return property===true||typeof property!=="boolean"&&(c=a.getAttributeNode(b))&&c.nodeValue!==false?b.toLowerCase():x},set:function(a,b,c){var d;if(b===false){z.removeAttr(a,c)}else{d=z.propFix[c]||c;if(d in a){a[d]=true}a.setAttribute(c,c.toLowerCase())}return c}};if(!getSetAttribute){fixSpecified={name:true,id:true};nodeHook=z.valHooks.button={get:function(a,b){var c;c=a.getAttributeNode(b);return c&&(fixSpecified[b]?c.nodeValue!=="":c.specified)?c.nodeValue:x},set:function(a,b,c){var d=a.getAttributeNode(c);if(!d){d=y.createAttribute(c);a.setAttributeNode(d)}return(d.nodeValue=b+"")}};z.attrHooks.tabindex.set=nodeHook.set;z.each(["width","height"],function(i,c){z.attrHooks[c]=z.extend(z.attrHooks[c],{set:function(a,b){if(b===""){a.setAttribute(c,"auto");return b}}})});z.attrHooks.contenteditable={get:nodeHook.get,set:function(a,b,c){if(b===""){b="false"}nodeHook.set(a,b,c)}}}if(!z.support.hrefNormalized){z.each(["href","src","width","height"],function(i,c){z.attrHooks[c]=z.extend(z.attrHooks[c],{get:function(a){var b=a.getAttribute(c,2);return b===null?x:b}})})}if(!z.support.style){z.attrHooks.style={get:function(a){return a.style.cssText.toLowerCase()||x},set:function(a,b){return(a.style.cssText=""+b)}}}if(!z.support.optSelected){z.propHooks.selected=z.extend(z.propHooks.selected,{get:function(a){var b=a.parentNode;if(b){b.selectedIndex;if(b.parentNode){b.parentNode.selectedIndex}}return null}})}if(!z.support.enctype){z.propFix.enctype="encoding"}if(!z.support.checkOn){z.each(["radio","checkbox"],function(){z.valHooks[this]={get:function(a){return a.getAttribute("value")===null?"on":a.value}}})}z.each(["radio","checkbox"],function(){z.valHooks[this]=z.extend(z.valHooks[this],{set:function(a,b){if(z.isArray(b)){return(a.checked=z.inArray(z(a).val(),b)>=0)}}})});var E=/^(?:textarea|input|select)$/i,rtypenamespace=/^([^\\.]*)?(?:\\.(.+))?$/,rhoverHack=/\\bhover(\\.\\S+)?\\b/,rkeyEvent=/^key/,rmouseEvent=/^(?:mouse|contextmenu)|click/,rfocusMorph=/^(?:focusinfocus|focusoutblur)$/,rquickIs=/^(\\w*)(?:#([\\w\\-]+))?(?:\\.([\\w\\-]+))?$/,quickParse=function(a){var b=rquickIs.exec(a);if(b){b[1]=(b[1]||"").toLowerCase();b[3]=b[3]&&new RegExp("(?:^|\\\\s)"+b[3]+"(?:\\\\s|$)")}return b},quickIs=function(a,m){var b=a.attributes||{};return((!m[1]||a.nodeName.toLowerCase()===m[1])&&(!m[2]||(b.id||{}).value===m[2])&&(!m[3]||m[3].test((b["class"]||{}).value)))},hoverHack=function(a){return z.event.special.hover?a:a.replace(rhoverHack,"mouseenter$1 mouseleave$1")};z.event={add:function(a,b,c,d,f){var g,eventHandle,events,t,tns,type,namespaces,handleObj,handleObjIn,quick,handlers,special;if(a.nodeType===3||a.nodeType===8||!b||!c||!(g=z._data(a))){return}if(c.handler){handleObjIn=c;c=handleObjIn.handler}if(!c.guid){c.guid=z.guid++}events=g.events;if(!events){g.events=events={}}eventHandle=g.handle;if(!eventHandle){g.handle=eventHandle=function(e){return typeof z!=="undefined"&&(!e||z.event.triggered!==e.type)?z.event.dispatch.apply(eventHandle.elem,arguments):x};eventHandle.elem=a}b=z.trim(hoverHack(b)).split(" ");for(t=0;t<b.length;t++){tns=rtypenamespace.exec(b[t])||[];type=tns[1];namespaces=(tns[2]||"").split(".").sort();special=z.event.special[type]||{};type=(f?special.delegateType:special.bindType)||type;special=z.event.special[type]||{};handleObj=z.extend({type:type,origType:tns[1],data:d,handler:c,guid:c.guid,selector:f,quick:quickParse(f),namespace:namespaces.join(".")},handleObjIn);handlers=events[type];if(!handlers){handlers=events[type]=[];handlers.delegateCount=0;if(!special.setup||special.setup.call(a,d,namespaces,eventHandle)===false){if(a.addEventListener){a.addEventListener(type,eventHandle,false)}else if(a.attachEvent){a.attachEvent("on"+type,eventHandle)}}}if(special.add){special.add.call(a,handleObj);if(!handleObj.handler.guid){handleObj.handler.guid=c.guid}}if(f){handlers.splice(handlers.delegateCount++,0,handleObj)}else{handlers.push(handleObj)}z.event.global[type]=true}a=null},global:{},remove:function(a,b,c,d,e){var f=z.hasData(a)&&z._data(a),t,tns,type,origType,namespaces,origCount,j,events,special,handle,eventType,handleObj;if(!f||!(events=f.events)){return}b=z.trim(hoverHack(b||"")).split(" ");for(t=0;t<b.length;t++){tns=rtypenamespace.exec(b[t])||[];type=origType=tns[1];namespaces=tns[2];if(!type){for(type in events){z.event.remove(a,type+b[t],c,d,true)}continue}special=z.event.special[type]||{};type=(d?special.delegateType:special.bindType)||type;eventType=events[type]||[];origCount=eventType.length;namespaces=namespaces?new RegExp("(^|\\\\.)"+namespaces.split(".").sort().join("\\\\.(?:.*\\\\.)?")+"(\\\\.|$)"):null;for(j=0;j<eventType.length;j++){handleObj=eventType[j];if((e||origType===handleObj.origType)&&(!c||c.guid===handleObj.guid)&&(!namespaces||namespaces.test(handleObj.namespace))&&(!d||d===handleObj.selector||d==="**"&&handleObj.selector)){eventType.splice(j--,1);if(handleObj.selector){eventType.delegateCount--}if(special.remove){special.remove.call(a,handleObj)}}}if(eventType.length===0&&origCount!==eventType.length){if(!special.teardown||special.teardown.call(a,namespaces)===false){z.removeEvent(a,type,f.handle)}delete events[type]}}if(z.isEmptyObject(events)){handle=f.handle;if(handle){handle.elem=null}z.removeData(a,["events","handle"],true)}},customEvent:{"getData":true,"setData":true,"changeData":true},trigger:function(a,b,c,d){if(c&&(c.nodeType===3||c.nodeType===8)){return}var e=a.type||a,namespaces=[],cache,exclusive,i,cur,old,ontype,special,handle,eventPath,bubbleType;if(rfocusMorph.test(e+z.event.triggered)){return}if(e.indexOf("!")>=0){e=e.slice(0,-1);exclusive=true}if(e.indexOf(".")>=0){namespaces=e.split(".");e=namespaces.shift();namespaces.sort()}if((!c||z.event.customEvent[e])&&!z.event.global[e]){return}a=typeof a==="object"?a[z.expando]?a:new z.Event(e,a):new z.Event(e);a.type=e;a.isTrigger=true;a.exclusive=exclusive;a.namespace=namespaces.join(".");a.namespace_re=a.namespace?new RegExp("(^|\\\\.)"+namespaces.join("\\\\.(?:.*\\\\.)?")+"(\\\\.|$)"):null;ontype=e.indexOf(":")<0?"on"+e:"";if(!c){cache=z.cache;for(i in cache){if(cache[i].events&&cache[i].events[e]){z.event.trigger(a,b,cache[i].handle.elem,true)}}return}a.result=x;if(!a.target){a.target=c}b=b!=null?z.makeArray(b):[];b.unshift(a);special=z.event.special[e]||{};if(special.trigger&&special.trigger.apply(c,b)===false){return}eventPath=[[c,special.bindType||e]];if(!d&&!special.noBubble&&!z.isWindow(c)){bubbleType=special.delegateType||e;cur=rfocusMorph.test(bubbleType+e)?c:c.parentNode;old=null;for(;cur;cur=cur.parentNode){eventPath.push([cur,bubbleType]);old=cur}if(old&&old===c.ownerDocument){eventPath.push([old.defaultView||old.parentWindow||w,bubbleType])}}for(i=0;i<eventPath.length&&!a.isPropagationStopped();i++){cur=eventPath[i][0];a.type=eventPath[i][1];handle=(z._data(cur,"events")||{})[a.type]&&z._data(cur,"handle");if(handle){handle.apply(cur,b)}handle=ontype&&cur[ontype];if(handle&&z.acceptData(cur)&&handle.apply(cur,b)===false){a.preventDefault()}}a.type=e;if(!d&&!a.isDefaultPrevented()){if((!special._default||special._default.apply(c.ownerDocument,b)===false)&&!(e==="click"&&z.nodeName(c,"a"))&&z.acceptData(c)){if(ontype&&c[e]&&((e!=="focus"&&e!=="blur")||a.target.offsetWidth!==0)&&!z.isWindow(c)){old=c[ontype];if(old){c[ontype]=null}z.event.triggered=e;c[e]();z.event.triggered=x;if(old){c[ontype]=old}}}}return a.result},dispatch:function(a){a=z.event.fix(a||w.event);var b=((z._data(this,"events")||{})[a.type]||[]),delegateCount=b.delegateCount,args=[].slice.call(arguments,0),run_all=!a.exclusive&&!a.namespace,handlerQueue=[],i,j,cur,jqcur,ret,selMatch,matched,matches,handleObj,sel,related;args[0]=a;a.delegateTarget=this;if(delegateCount&&!a.target.disabled&&!(a.button&&a.type==="click")){jqcur=z(this);jqcur.context=this.ownerDocument||this;for(cur=a.target;cur!=this;cur=cur.parentNode||this){selMatch={};matches=[];jqcur[0]=cur;for(i=0;i<delegateCount;i++){handleObj=b[i];sel=handleObj.selector;if(selMatch[sel]===x){selMatch[sel]=(handleObj.quick?quickIs(cur,handleObj.quick):jqcur.is(sel))}if(selMatch[sel]){matches.push(handleObj)}}if(matches.length){handlerQueue.push({elem:cur,matches:matches})}}}if(b.length>delegateCount){handlerQueue.push({elem:this,matches:b.slice(delegateCount)})}for(i=0;i<handlerQueue.length&&!a.isPropagationStopped();i++){matched=handlerQueue[i];a.currentTarget=matched.elem;for(j=0;j<matched.matches.length&&!a.isImmediatePropagationStopped();j++){handleObj=matched.matches[j];if(run_all||(!a.namespace&&!handleObj.namespace)||a.namespace_re&&a.namespace_re.test(handleObj.namespace)){a.data=handleObj.data;a.handleObj=handleObj;ret=((z.event.special[handleObj.origType]||{}).handle||handleObj.handler).apply(matched.elem,args);if(ret!==x){a.result=ret;if(ret===false){a.preventDefault();a.stopPropagation()}}}}}return a.result},props:"attrChange attrName relatedNode srcElement altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){if(a.which==null){a.which=b.charCode!=null?b.charCode:b.keyCode}return a}},mouseHooks:{props:"button buttons clientX clientY fromElement offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,b){var c,doc,body,button=b.button,fromElement=b.fromElement;if(a.pageX==null&&b.clientX!=null){c=a.target.ownerDocument||y;doc=c.documentElement;body=c.body;a.pageX=b.clientX+(doc&&doc.scrollLeft||body&&body.scrollLeft||0)-(doc&&doc.clientLeft||body&&body.clientLeft||0);a.pageY=b.clientY+(doc&&doc.scrollTop||body&&body.scrollTop||0)-(doc&&doc.clientTop||body&&body.clientTop||0)}if(!a.relatedTarget&&fromElement){a.relatedTarget=fromElement===a.target?b.toElement:fromElement}if(!a.which&&button!==x){a.which=(button&1?1:(button&2?3:(button&4?2:0)))}return a}},fix:function(a){if(a[z.expando]){return a}var i,prop,originalEvent=a,fixHook=z.event.fixHooks[a.type]||{},copy=fixHook.props?this.props.concat(fixHook.props):this.props;a=z.Event(originalEvent);for(i=copy.length;i;){prop=copy[--i];a[prop]=originalEvent[prop]}if(!a.target){a.target=originalEvent.srcElement||y}if(a.target.nodeType===3){a.target=a.target.parentNode}if(a.metaKey===x){a.metaKey=a.ctrlKey}return fixHook.filter?fixHook.filter(a,originalEvent):a},special:{ready:{setup:z.bindReady},load:{noBubble:true},focus:{delegateType:"focusin"},blur:{delegateType:"focusout"},beforeunload:{setup:function(a,b,c){if(z.isWindow(this)){this.onbeforeunload=c}},teardown:function(a,b){if(this.onbeforeunload===b){this.onbeforeunload=null}}}},simulate:function(a,b,c,d){var e=z.extend(new z.Event(),c,{type:a,isSimulated:true,originalEvent:{}});if(d){z.event.trigger(e,null,b)}else{z.event.dispatch.call(b,e)}if(e.isDefaultPrevented()){c.preventDefault()}}};z.event.handle=z.event.dispatch;z.removeEvent=y.removeEventListener?function(a,b,c){if(a.removeEventListener){a.removeEventListener(b,c,false)}}:function(a,b,c){if(a.detachEvent){a.detachEvent("on"+b,c)}};z.Event=function(a,b){if(!(this instanceof z.Event)){return new z.Event(a,b)}if(a&&a.type){this.originalEvent=a;this.type=a.type;this.isDefaultPrevented=(a.defaultPrevented||a.returnValue===false||a.getPreventDefault&&a.getPreventDefault())?returnTrue:returnFalse}else{this.type=a}if(b){z.extend(this,b)}this.timeStamp=a&&a.timeStamp||z.now();this[z.expando]=true};function returnFalse(){return false}function returnTrue(){return true}z.Event.prototype={preventDefault:function(){this.isDefaultPrevented=returnTrue;var e=this.originalEvent;if(!e){return}if(e.preventDefault){e.preventDefault()}else{e.returnValue=false}},stopPropagation:function(){this.isPropagationStopped=returnTrue;var e=this.originalEvent;if(!e){return}if(e.stopPropagation){e.stopPropagation()}e.cancelBubble=true},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=returnTrue;this.stopPropagation()},isDefaultPrevented:returnFalse,isPropagationStopped:returnFalse,isImmediatePropagationStopped:returnFalse};z.each({mouseenter:"mouseover",mouseleave:"mouseout"},function(c,d){z.event.special[c]={delegateType:d,bindType:d,handle:function(a){var b=this,related=a.relatedTarget,handleObj=a.handleObj,selector=handleObj.selector,ret;if(!related||(related!==b&&!z.contains(b,related))){a.type=handleObj.origType;ret=handleObj.handler.apply(this,arguments);a.type=d}return ret}}});if(!z.support.submitBubbles){z.event.special.submit={setup:function(){if(z.nodeName(this,"form")){return false}z.event.add(this,"click._submit keypress._submit",function(e){var b=e.target,form=z.nodeName(b,"input")||z.nodeName(b,"button")?b.form:x;if(form&&!form._submit_attached){z.event.add(form,"submit._submit",function(a){if(this.parentNode&&!a.isTrigger){z.event.simulate("submit",this.parentNode,a,true)}});form._submit_attached=true}})},teardown:function(){if(z.nodeName(this,"form")){return false}z.event.remove(this,"._submit")}}}if(!z.support.changeBubbles){z.event.special.change={setup:function(){if(E.test(this.nodeName)){if(this.type==="checkbox"||this.type==="radio"){z.event.add(this,"propertychange._change",function(a){if(a.originalEvent.propertyName==="checked"){this._just_changed=true}});z.event.add(this,"click._change",function(a){if(this._just_changed&&!a.isTrigger){this._just_changed=false;z.event.simulate("change",this,a,true)}})}return false}z.event.add(this,"beforeactivate._change",function(e){var b=e.target;if(E.test(b.nodeName)&&!b._change_attached){z.event.add(b,"change._change",function(a){if(this.parentNode&&!a.isSimulated&&!a.isTrigger){z.event.simulate("change",this.parentNode,a,true)}});b._change_attached=true}})},handle:function(a){var b=a.target;if(this!==b||a.isSimulated||a.isTrigger||(b.type!=="radio"&&b.type!=="checkbox")){return a.handleObj.handler.apply(this,arguments)}},teardown:function(){z.event.remove(this,"._change");return E.test(this.nodeName)}}}if(!z.support.focusinBubbles){z.each({focus:"focusin",blur:"focusout"},function(b,c){var d=0,handler=function(a){z.event.simulate(c,a.target,z.event.fix(a),true)};z.event.special[c]={setup:function(){if(d++===0){y.addEventListener(b,handler,true)}},teardown:function(){if(--d===0){y.removeEventListener(b,handler,true)}}}})}z.fn.extend({on:function(b,c,d,e,f){var g,type;if(typeof b==="object"){if(typeof c!=="string"){d=c;c=x}for(type in b){this.on(type,c,d,b[type],f)}return this}if(d==null&&e==null){e=c;d=c=x}else if(e==null){if(typeof c==="string"){e=d;d=x}else{e=d;d=c;c=x}}if(e===false){e=returnFalse}else if(!e){return this}if(f===1){g=e;e=function(a){z().off(a);return g.apply(this,arguments)};e.guid=g.guid||(g.guid=z.guid++)}return this.each(function(){z.event.add(this,b,e,d,c)})},one:function(a,b,c,d){return this.on.call(this,a,b,c,d,1)},off:function(a,b,c){if(a&&a.preventDefault&&a.handleObj){var d=a.handleObj;z(a.delegateTarget).off(d.namespace?d.type+"."+d.namespace:d.type,d.selector,d.handler);return this}if(typeof a==="object"){for(var e in a){this.off(e,b,a[e])}return this}if(b===false||typeof b==="function"){c=b;b=x}if(c===false){c=returnFalse}return this.each(function(){z.event.remove(this,a,c,b)})},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},live:function(a,b,c){z(this.context).on(a,this.selector,b,c);return this},die:function(a,b){z(this.context).off(a,this.selector||"**",b);return this},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return arguments.length==1?this.off(a,"**"):this.off(b,a,c)},trigger:function(a,b){return this.each(function(){z.event.trigger(a,b,this)})},triggerHandler:function(a,b){if(this[0]){return z.event.trigger(a,b,this[0],true)}},toggle:function(c){var d=arguments,guid=c.guid||z.guid++,i=0,toggler=function(a){var b=(z._data(this,"lastToggle"+c.guid)||0)%i;z._data(this,"lastToggle"+c.guid,b+1);a.preventDefault();return d[b].apply(this,arguments)||false};toggler.guid=guid;while(i<d.length){d[i++].guid=guid}return this.click(toggler)},hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)}});z.each(("blur focus focusin focusout load resize scroll unload click dblclick "+"mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave "+"change select submit keydown keypress keyup error contextmenu").split(" "),function(i,c){z.fn[c]=function(a,b){if(b==null){b=a;a=null}return arguments.length>0?this.on(c,null,a,b):this.trigger(c)};if(z.attrFn){z.attrFn[c]=true}if(rkeyEvent.test(c)){z.event.fixHooks[c]=z.event.keyHooks}if(rmouseEvent.test(c)){z.event.fixHooks[c]=z.event.mouseHooks}});(function(){var k=/((?:\\((?:\\([^()]+\\)|[^()]+)+\\)|\\[(?:\\[[^\\[\\]]*\\]|[\'"][^\'"]*[\'"]|[^\\[\\]\'"]+)+\\]|\\\\.|[^ >+~,(\\[\\\\]+)+|[>+~])(\\s*,\\s*)?((?:.|\\r|\\n)*)/g,expando="sizcache"+(Math.random()+\'\').replace(\'.\',\'\'),done=0,toString=Object.prototype.toString,hasDuplicate=false,baseHasDuplicate=true,rBackslash=/\\\\/g,rReturn=/\\r\\n/g,rNonWord=/\\W/;[0,0].sort(function(){baseHasDuplicate=false;return 0});var n=function(a,b,c,d){c=c||[];b=b||y;var e=b;if(b.nodeType!==1&&b.nodeType!==9){return[]}if(!a||typeof a!=="string"){return c}var m,set,checkSet,extra,ret,cur,pop,i,prune=true,contextXML=n.isXML(b),parts=[],soFar=a;do{k.exec("");m=k.exec(soFar);if(m){soFar=m[3];parts.push(m[1]);if(m[2]){extra=m[3];break}}}while(m);if(parts.length>1&&q.exec(a)){if(parts.length===2&&p.relative[parts[0]]){set=u(parts[0]+parts[1],b,d)}else{set=p.relative[parts[0]]?[b]:n(parts.shift(),b);while(parts.length){a=parts.shift();if(p.relative[a]){a+=parts.shift()}set=u(a,set,d)}}}else{if(!d&&parts.length>1&&b.nodeType===9&&!contextXML&&p.match.ID.test(parts[0])&&!p.match.ID.test(parts[parts.length-1])){ret=n.find(parts.shift(),b,contextXML);b=ret.expr?n.filter(ret.expr,ret.set)[0]:ret.set[0]}if(b){ret=d?{expr:parts.pop(),set:s(d)}:n.find(parts.pop(),parts.length===1&&(parts[0]==="~"||parts[0]==="+")&&b.parentNode?b.parentNode:b,contextXML);set=ret.expr?n.filter(ret.expr,ret.set):ret.set;if(parts.length>0){checkSet=s(set)}else{prune=false}while(parts.length){cur=parts.pop();pop=cur;if(!p.relative[cur]){cur=""}else{pop=parts.pop()}if(pop==null){pop=b}p.relative[cur](checkSet,pop,contextXML)}}else{checkSet=parts=[]}}if(!checkSet){checkSet=set}if(!checkSet){n.error(cur||a)}if(toString.call(checkSet)==="[object Array]"){if(!prune){c.push.apply(c,checkSet)}else if(b&&b.nodeType===1){for(i=0;checkSet[i]!=null;i++){if(checkSet[i]&&(checkSet[i]===true||checkSet[i].nodeType===1&&n.contains(b,checkSet[i]))){c.push(set[i])}}}else{for(i=0;checkSet[i]!=null;i++){if(checkSet[i]&&checkSet[i].nodeType===1){c.push(set[i])}}}}else{s(checkSet,c)}if(extra){n(extra,e,c,d);n.uniqueSort(c)}return c};n.uniqueSort=function(a){if(t){hasDuplicate=baseHasDuplicate;a.sort(t);if(hasDuplicate){for(var i=1;i<a.length;i++){if(a[i]===a[i-1]){a.splice(i--,1)}}}}return a};n.matches=function(a,b){return n(a,null,null,b)};n.matchesSelector=function(a,b){return n(b,null,null,[a]).length>0};n.find=function(a,b,c){var d,i,len,match,r,left;if(!a){return[]}for(i=0,len=p.order.length;i<len;i++){r=p.order[i];if((match=p.leftMatch[r].exec(a))){left=match[1];match.splice(1,1);if(left.substr(left.length-1)!=="\\\\"){match[1]=(match[1]||"").replace(rBackslash,"");d=p.find[r](match,b,c);if(d!=null){a=a.replace(p.match[r],"");break}}}}if(!d){d=typeof b.getElementsByTagName!=="undefined"?b.getElementsByTagName("*"):[]}return{set:d,expr:a}};n.filter=function(a,b,c,d){var e,anyFound,r,found,item,filter,left,i,pass,old=a,result=[],curLoop=b,isXMLFilter=b&&b[0]&&n.isXML(b[0]);while(a&&b.length){for(r in p.filter){if((e=p.leftMatch[r].exec(a))!=null&&e[2]){filter=p.filter[r];left=e[1];anyFound=false;e.splice(1,1);if(left.substr(left.length-1)==="\\\\"){continue}if(curLoop===result){result=[]}if(p.preFilter[r]){e=p.preFilter[r](e,curLoop,c,result,d,isXMLFilter);if(!e){anyFound=found=true}else if(e===true){continue}}if(e){for(i=0;(item=curLoop[i])!=null;i++){if(item){found=filter(item,e,i,curLoop);pass=d^found;if(c&&found!=null){if(pass){anyFound=true}else{curLoop[i]=false}}else if(pass){result.push(item);anyFound=true}}}}if(found!==x){if(!c){curLoop=result}a=a.replace(p.match[r],"");if(!anyFound){return[]}break}}}if(a===old){if(anyFound==null){n.error(a)}else{break}}old=a}return curLoop};n.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a);};var o=n.getText=function(a){var i,node,nodeType=a.nodeType,ret="";if(nodeType){if(nodeType===1||nodeType===9){if(typeof a.textContent===\'string\'){return a.textContent}else if(typeof a.innerText===\'string\'){return a.innerText.replace(rReturn,\'\')}else{for(a=a.firstChild;a;a=a.nextSibling){ret+=o(a)}}}else if(nodeType===3||nodeType===4){return a.nodeValue}}else{for(i=0;(node=a[i]);i++){if(node.nodeType!==8){ret+=o(node)}}}return ret};var p=n.selectors={order:["ID","NAME","TAG"],match:{ID:/#((?:[\\w\\u00c0-\\uFFFF\\-]|\\\\.)+)/,CLASS:/\\.((?:[\\w\\u00c0-\\uFFFF\\-]|\\\\.)+)/,NAME:/\\[name=[\'"]*((?:[\\w\\u00c0-\\uFFFF\\-]|\\\\.)+)[\'"]*\\]/,ATTR:/\\[\\s*((?:[\\w\\u00c0-\\uFFFF\\-]|\\\\.)+)\\s*(?:(\\S?=)\\s*(?:([\'"])(.*?)\\3|(#?(?:[\\w\\u00c0-\\uFFFF\\-]|\\\\.)*)|)|)\\s*\\]/,TAG:/^((?:[\\w\\u00c0-\\uFFFF\\*\\-]|\\\\.)+)/,CHILD:/:(only|nth|last|first)-child(?:\\(\\s*(even|odd|(?:[+\\-]?\\d+|(?:[+\\-]?\\d*)?n\\s*(?:[+\\-]\\s*\\d+)?))\\s*\\))?/,POS:/:(nth|eq|gt|lt|first|last|even|odd)(?:\\((\\d*)\\))?(?=[^\\-]|$)/,PSEUDO:/:((?:[\\w\\u00c0-\\uFFFF\\-]|\\\\.)+)(?:\\(([\'"]?)((?:\\([^\\)]+\\)|[^\\(\\)]*)+)\\2\\))?/},leftMatch:{},attrMap:{"class":"className","for":"htmlFor"},attrHandle:{href:function(a){return a.getAttribute("href")},type:function(a){return a.getAttribute("type")}},relative:{"+":function(a,b){var c=typeof b==="string",isTag=c&&!rNonWord.test(b),isPartStrNotTag=c&&!isTag;if(isTag){b=b.toLowerCase()}for(var i=0,l=a.length,elem;i<l;i++){if((elem=a[i])){while((elem=elem.previousSibling)&&elem.nodeType!==1){}a[i]=isPartStrNotTag||elem&&elem.nodeName.toLowerCase()===b?elem||false:elem===b}}if(isPartStrNotTag){n.filter(b,a,true)}},">":function(a,b){var c,isPartStr=typeof b==="string",i=0,l=a.length;if(isPartStr&&!rNonWord.test(b)){b=b.toLowerCase();for(;i<l;i++){c=a[i];if(c){var d=c.parentNode;a[i]=d.nodeName.toLowerCase()===b?d:false}}}else{for(;i<l;i++){c=a[i];if(c){a[i]=isPartStr?c.parentNode:c.parentNode===b}}if(isPartStr){n.filter(b,a,true)}}},"":function(a,b,c){var d,doneName=done++,checkFn=dirCheck;if(typeof b==="string"&&!rNonWord.test(b)){b=b.toLowerCase();d=b;checkFn=dirNodeCheck}checkFn("parentNode",b,doneName,a,d,c)},"~":function(a,b,c){var d,doneName=done++,checkFn=dirCheck;if(typeof b==="string"&&!rNonWord.test(b)){b=b.toLowerCase();d=b;checkFn=dirNodeCheck}checkFn("previousSibling",b,doneName,a,d,c)}},find:{ID:function(a,b,c){if(typeof b.getElementById!=="undefined"&&!c){var m=b.getElementById(a[1]);return m&&m.parentNode?[m]:[]}},NAME:function(a,b){if(typeof b.getElementsByName!=="undefined"){var c=[],results=b.getElementsByName(a[1]);for(var i=0,l=results.length;i<l;i++){if(results[i].getAttribute("name")===a[1]){c.push(results[i])}}return c.length===0?null:c}},TAG:function(a,b){if(typeof b.getElementsByTagName!=="undefined"){return b.getElementsByTagName(a[1])}}},preFilter:{CLASS:function(a,b,c,d,e,f){a=" "+a[1].replace(rBackslash,"")+" ";if(f){return a}for(var i=0,elem;(elem=b[i])!=null;i++){if(elem){if(e^(elem.className&&(" "+elem.className+" ").replace(/[\\t\\n\\r]/g," ").indexOf(a)>=0)){if(!c){d.push(elem)}}else if(c){b[i]=false}}}return false},ID:function(a){return a[1].replace(rBackslash,"")},TAG:function(a,b){return a[1].replace(rBackslash,"").toLowerCase()},CHILD:function(a){if(a[1]==="nth"){if(!a[2]){n.error(a[0])}a[2]=a[2].replace(/^\\+|\\s*/g,\'\');var b=/(-?)(\\d*)(?:n([+\\-]?\\d*))?/.exec(a[2]==="even"&&"2n"||a[2]==="odd"&&"2n+1"||!/\\D/.test(a[2])&&"0n+"+a[2]||a[2]);a[2]=(b[1]+(b[2]||1))-0;a[3]=b[3]-0}else if(a[2]){n.error(a[0])}a[0]=done++;return a},ATTR:function(a,b,c,d,e,f){var g=a[1]=a[1].replace(rBackslash,"");if(!f&&p.attrMap[g]){a[1]=p.attrMap[g]}a[4]=(a[4]||a[5]||"").replace(rBackslash,"");if(a[2]==="~="){a[4]=" "+a[4]+" "}return a},PSEUDO:function(a,b,c,d,e){if(a[1]==="not"){if((k.exec(a[3])||"").length>1||/^\\w/.test(a[3])){a[3]=n(a[3],null,null,b)}else{var f=n.filter(a[3],b,c,true^e);if(!c){d.push.apply(d,f)}return false}}else if(p.match.POS.test(a[0])||p.match.CHILD.test(a[0])){return true}return a},POS:function(a){a.unshift(true);return a}},filters:{enabled:function(a){return a.disabled===false&&a.type!=="hidden"},disabled:function(a){return a.disabled===true},checked:function(a){return a.checked===true},selected:function(a){if(a.parentNode){a.parentNode.selectedIndex}return a.selected===true},parent:function(a){return!!a.firstChild},empty:function(a){return!a.firstChild},has:function(a,i,b){return!!n(b[3],a).length},header:function(a){return(/h\\d/i).test(a.nodeName)},text:function(a){var b=a.getAttribute("type"),r=a.type;return a.nodeName.toLowerCase()==="input"&&"text"===r&&(b===r||b===null)},radio:function(a){return a.nodeName.toLowerCase()==="input"&&"radio"===a.type},checkbox:function(a){return a.nodeName.toLowerCase()==="input"&&"checkbox"===a.type},file:function(a){return a.nodeName.toLowerCase()==="input"&&"file"===a.type},password:function(a){return a.nodeName.toLowerCase()==="input"&&"password"===a.type},submit:function(a){var b=a.nodeName.toLowerCase();return(b==="input"||b==="button")&&"submit"===a.type},image:function(a){return a.nodeName.toLowerCase()==="input"&&"image"===a.type},reset:function(a){var b=a.nodeName.toLowerCase();return(b==="input"||b==="button")&&"reset"===a.type},button:function(a){var b=a.nodeName.toLowerCase();return b==="input"&&"button"===a.type||b==="button"},input:function(a){return(/input|select|textarea|button/i).test(a.nodeName)},focus:function(a){return a===a.ownerDocument.activeElement}},setFilters:{first:function(a,i){return i===0},last:function(a,i,b,c){return i===c.length-1},even:function(a,i){return i%2===0},odd:function(a,i){return i%2===1},lt:function(a,i,b){return i<b[3]-0},gt:function(a,i,b){return i>b[3]-0},nth:function(a,i,b){return b[3]-0===i},eq:function(a,i,b){return b[3]-0===i}},filter:{PSEUDO:function(a,b,i,c){var d=b[1],filter=p.filters[d];if(filter){return filter(a,i,b,c)}else if(d==="contains"){return(a.textContent||a.innerText||o([a])||"").indexOf(b[3])>=0}else if(d==="not"){var e=b[3];for(var j=0,l=e.length;j<l;j++){if(e[j]===a){return false}}return true}else{n.error(d)}},CHILD:function(a,b){var c,last,doneName,parent,cache,count,diff,r=b[1],node=a;switch(r){case"only":case"first":while((node=node.previousSibling)){if(node.nodeType===1){return false}}if(r==="first"){return true}node=a;case"last":while((node=node.nextSibling)){if(node.nodeType===1){return false}}return true;case"nth":c=b[2];last=b[3];if(c===1&&last===0){return true}doneName=b[0];parent=a.parentNode;if(parent&&(parent[expando]!==doneName||!a.nodeIndex)){count=0;for(node=parent.firstChild;node;node=node.nextSibling){if(node.nodeType===1){node.nodeIndex=++count}}parent[expando]=doneName}diff=a.nodeIndex-last;if(c===0){return diff===0}else{return(diff%c===0&&diff/c>=0)}}},ID:function(a,b){return a.nodeType===1&&a.getAttribute("id")===b},TAG:function(a,b){return(b==="*"&&a.nodeType===1)||!!a.nodeName&&a.nodeName.toLowerCase()===b},CLASS:function(a,b){return(" "+(a.className||a.getAttribute("class"))+" ").indexOf(b)>-1},ATTR:function(a,b){var c=b[1],result=n.attr?n.attr(a,c):p.attrHandle[c]?p.attrHandle[c](a):a[c]!=null?a[c]:a.getAttribute(c),value=result+"",r=b[2],check=b[4];return result==null?r==="!=":!r&&n.attr?result!=null:r==="="?value===check:r==="*="?value.indexOf(check)>=0:r==="~="?(" "+value+" ").indexOf(check)>=0:!check?value&&result!==false:r==="!="?value!==check:r==="^="?value.indexOf(check)===0:r==="$="?value.substr(value.length-check.length)===check:r==="|="?value===check||value.substr(0,check.length+1)===check+"-":false},POS:function(a,b,i,c){var d=b[2],filter=p.setFilters[d];if(filter){return filter(a,i,b,c)}}}};var q=p.match.POS,fescape=function(a,b){return"\\\\"+(b-0+1)};for(var r in p.match){p.match[r]=new RegExp(p.match[r].source+(/(?![^\\[]*\\])(?![^\\(]*\\))/.source));p.leftMatch[r]=new RegExp(/(^(?:.|\\r|\\n)*?)/.source+p.match[r].source.replace(/\\\\(\\d+)/g,fescape))}var s=function(a,b){a=Array.prototype.slice.call(a,0);if(b){b.push.apply(b,a);return b}return a};try{Array.prototype.slice.call(y.documentElement.childNodes,0)[0].nodeType}catch(e){s=function(a,b){var i=0,ret=b||[];if(toString.call(a)==="[object Array]"){Array.prototype.push.apply(ret,a)}else{if(typeof a.length==="number"){for(var l=a.length;i<l;i++){ret.push(a[i])}}else{for(;a[i];i++){ret.push(a[i])}}}return ret}}var t,siblingCheck;if(y.documentElement.compareDocumentPosition){t=function(a,b){if(a===b){hasDuplicate=true;return 0}if(!a.compareDocumentPosition||!b.compareDocumentPosition){return a.compareDocumentPosition?-1:1}return a.compareDocumentPosition(b)&4?-1:1}}else{t=function(a,b){if(a===b){hasDuplicate=true;return 0}else if(a.sourceIndex&&b.sourceIndex){return a.sourceIndex-b.sourceIndex}var c,bl,ap=[],bp=[],aup=a.parentNode,bup=b.parentNode,cur=aup;if(aup===bup){return siblingCheck(a,b)}else if(!aup){return-1}else if(!bup){return 1}while(cur){ap.unshift(cur);cur=cur.parentNode}cur=bup;while(cur){bp.unshift(cur);cur=cur.parentNode}c=ap.length;bl=bp.length;for(var i=0;i<c&&i<bl;i++){if(ap[i]!==bp[i]){return siblingCheck(ap[i],bp[i])}}return i===c?siblingCheck(a,bp[i],-1):siblingCheck(ap[i],b,1)};siblingCheck=function(a,b,c){if(a===b){return c}var d=a.nextSibling;while(d){if(d===b){return-1}d=d.nextSibling}return 1}}(function(){var d=y.createElement("div"),id="script"+(new Date()).getTime(),root=y.documentElement;d.innerHTML="<a name=\'"+id+"\'/>";root.insertBefore(d,root.firstChild);if(y.getElementById(id)){p.find.ID=function(a,b,c){if(typeof b.getElementById!=="undefined"&&!c){var m=b.getElementById(a[1]);return m?m.id===a[1]||typeof m.getAttributeNode!=="undefined"&&m.getAttributeNode("id").nodeValue===a[1]?[m]:x:[]}};p.filter.ID=function(a,b){var c=typeof a.getAttributeNode!=="undefined"&&a.getAttributeNode("id");return a.nodeType===1&&c&&c.nodeValue===b}}root.removeChild(d);root=d=null})();(function(){var e=y.createElement("div");e.appendChild(y.createComment(""));if(e.getElementsByTagName("*").length>0){p.find.TAG=function(a,b){var c=b.getElementsByTagName(a[1]);if(a[1]==="*"){var d=[];for(var i=0;c[i];i++){if(c[i].nodeType===1){d.push(c[i])}}c=d}return c}}e.innerHTML="<a href=\'#\'></a>";if(e.firstChild&&typeof e.firstChild.getAttribute!=="undefined"&&e.firstChild.getAttribute("href")!=="#"){p.attrHandle.href=function(a){return a.getAttribute("href",2)}}e=null})();if(y.querySelectorAll){(function(){var h=n,div=y.createElement("div"),id="__sizzle__";div.innerHTML="<p class=\'TEST\'></p>";if(div.querySelectorAll&&div.querySelectorAll(".TEST").length===0){return}n=function(a,b,c,d){b=b||y;if(!d&&!n.isXML(b)){var e=/^(\\w+$)|^\\.([\\w\\-]+$)|^#([\\w\\-]+$)/.exec(a);if(e&&(b.nodeType===1||b.nodeType===9)){if(e[1]){return s(b.getElementsByTagName(a),c)}else if(e[2]&&p.find.CLASS&&b.getElementsByClassName){return s(b.getElementsByClassName(e[2]),c)}}if(b.nodeType===9){if(a==="body"&&b.body){return s([b.body],c)}else if(e&&e[3]){var f=b.getElementById(e[3]);if(f&&f.parentNode){if(f.id===e[3]){return s([f],c)}}else{return s([],c)}}try{return s(b.querySelectorAll(a),c)}catch(qsaError){}}else if(b.nodeType===1&&b.nodeName.toLowerCase()!=="object"){var g=b,old=b.getAttribute("id"),nid=old||id,hasParent=b.parentNode,relativeHierarchySelector=/^\\s*[+~]/.test(a);if(!old){b.setAttribute("id",nid)}else{nid=nid.replace(/\'/g,"\\\\$&")}if(relativeHierarchySelector&&hasParent){b=b.parentNode}try{if(!relativeHierarchySelector||hasParent){return s(b.querySelectorAll("[id=\'"+nid+"\'] "+a),c)}}catch(pseudoError){}finally{if(!old){g.removeAttribute("id")}}}}return h(a,b,c,d)};for(var i in h){n[i]=h[i]}div=null})()}(function(){var d=y.documentElement,matches=d.matchesSelector||d.mozMatchesSelector||d.webkitMatchesSelector||d.msMatchesSelector;if(matches){var f=!matches.call(y.createElement("div"),"div"),pseudoWorks=false;try{matches.call(y.documentElement,"[test!=\'\']:sizzle")}catch(pseudoError){pseudoWorks=true}n.matchesSelector=function(a,b){b=b.replace(/\\=\\s*([^\'"\\]]*)\\s*\\]/g,"=\'$1\']");if(!n.isXML(a)){try{if(pseudoWorks||!p.match.PSEUDO.test(b)&&!/!=/.test(b)){var c=matches.call(a,b);if(c||!f||a.document&&a.document.nodeType!==11){return c}}}catch(e){}}return n(b,null,null,[a]).length>0}}})();(function(){var d=y.createElement("div");d.innerHTML="<div class=\'test e\'></div><div class=\'test\'></div>";if(!d.getElementsByClassName||d.getElementsByClassName("e").length===0){return}d.lastChild.className="e";if(d.getElementsByClassName("e").length===1){return}p.order.splice(1,0,"CLASS");p.find.CLASS=function(a,b,c){if(typeof b.getElementsByClassName!=="undefined"&&!c){return b.getElementsByClassName(a[1])}};d=null})();function dirNodeCheck(a,b,c,d,e,f){for(var i=0,l=d.length;i<l;i++){var g=d[i];if(g){var h=false;g=g[a];while(g){if(g[expando]===c){h=d[g.sizset];break}if(g.nodeType===1&&!f){g[expando]=c;g.sizset=i}if(g.nodeName.toLowerCase()===b){h=g;break}g=g[a]}d[i]=h}}}function dirCheck(a,b,c,d,e,f){for(var i=0,l=d.length;i<l;i++){var g=d[i];if(g){var h=false;g=g[a];while(g){if(g[expando]===c){h=d[g.sizset];break}if(g.nodeType===1){if(!f){g[expando]=c;g.sizset=i}if(typeof b!=="string"){if(g===b){h=true;break}}else if(n.filter(b,[g]).length>0){h=g;break}}g=g[a]}d[i]=h}}}if(y.documentElement.contains){n.contains=function(a,b){return a!==b&&(a.contains?a.contains(b):true)}}else if(y.documentElement.compareDocumentPosition){n.contains=function(a,b){return!!(a.compareDocumentPosition(b)&16)}}else{n.contains=function(){return false}}n.isXML=function(a){var b=(a?a.ownerDocument||a:0).documentElement;return b?b.nodeName!=="HTML":false};var u=function(a,b,c){var d,tmpSet=[],later="",root=b.nodeType?[b]:b;while((d=p.match.PSEUDO.exec(a))){later+=d[0];a=a.replace(p.match.PSEUDO,"")}a=p.relative[a]?a+"*":a;for(var i=0,l=root.length;i<l;i++){n(a,root[i],tmpSet,c)}return n.filter(later,tmpSet)};n.attr=z.attr;n.selectors.attrMap={};z.find=n;z.expr=n.selectors;z.expr[":"]=z.expr.filters;z.unique=n.uniqueSort;z.text=n.getText;z.isXMLDoc=n.isXML;z.contains=n.contains})();var F=/Until$/,rparentsprev=/^(?:parents|prevUntil|prevAll)/,rmultiselector=/,/,isSimple=/^.[^:#\\[\\.,]*$/,slice=Array.prototype.slice,POS=z.expr.match.POS,guaranteedUnique={children:true,contents:true,next:true,prev:true};z.fn.extend({find:function(a){var b=this,i,l;if(typeof a!=="string"){return z(a).filter(function(){for(i=0,l=b.length;i<l;i++){if(z.contains(b[i],this)){return true}}})}var c=this.pushStack("","find",a),length,n,r;for(i=0,l=this.length;i<l;i++){length=c.length;z.find(a,this[i],c);if(i>0){for(n=length;n<c.length;n++){for(r=0;r<length;r++){if(c[r]===c[n]){c.splice(n--,1);break}}}}}return c},has:function(a){var b=z(a);return this.filter(function(){for(var i=0,l=b.length;i<l;i++){if(z.contains(this,b[i])){return true}}})},not:function(a){return this.pushStack(winnow(this,a,false),"not",a)},filter:function(a){return this.pushStack(winnow(this,a,true),"filter",a)},is:function(a){return!!a&&(typeof a==="string"?POS.test(a)?z(a,this.context).index(this[0])>=0:z.filter(a,this).length>0:this.filter(a).length>0)},closest:function(a,b){var c=[],i,l,cur=this[0];if(z.isArray(a)){var d=1;while(cur&&cur.ownerDocument&&cur!==b){for(i=0;i<a.length;i++){if(z(cur).is(a[i])){c.push({selector:a[i],elem:cur,level:d})}}cur=cur.parentNode;d++}return c}var e=POS.test(a)||typeof a!=="string"?z(a,b||this.context):0;for(i=0,l=this.length;i<l;i++){cur=this[i];while(cur){if(e?e.index(cur)>-1:z.find.matchesSelector(cur,a)){c.push(cur);break}else{cur=cur.parentNode;if(!cur||!cur.ownerDocument||cur===b||cur.nodeType===11){break}}}}c=c.length>1?z.unique(c):c;return this.pushStack(c,"closest",a)},index:function(a){if(!a){return(this[0]&&this[0].parentNode)?this.prevAll().length:-1}if(typeof a==="string"){return z.inArray(this[0],z(a))}return z.inArray(a.jquery?a[0]:a,this)},add:function(a,b){var c=typeof a==="string"?z(a,b):z.makeArray(a&&a.nodeType?[a]:a),all=z.merge(this.get(),c);return this.pushStack(isDisconnected(c[0])||isDisconnected(all[0])?all:z.unique(all))},andSelf:function(){return this.add(this.prevObject)}});function isDisconnected(a){return!a||!a.parentNode||a.parentNode.nodeType===11}z.each({parent:function(a){var b=a.parentNode;return b&&b.nodeType!==11?b:null},parents:function(a){return z.dir(a,"parentNode")},parentsUntil:function(a,i,b){return z.dir(a,"parentNode",b)},next:function(a){return z.nth(a,2,"nextSibling")},prev:function(a){return z.nth(a,2,"previousSibling")},nextAll:function(a){return z.dir(a,"nextSibling")},prevAll:function(a){return z.dir(a,"previousSibling")},nextUntil:function(a,i,b){return z.dir(a,"nextSibling",b)},prevUntil:function(a,i,b){return z.dir(a,"previousSibling",b)},siblings:function(a){return z.sibling(a.parentNode.firstChild,a)},children:function(a){return z.sibling(a.firstChild)},contents:function(a){return z.nodeName(a,"iframe")?a.contentDocument||a.contentWindow.document:z.makeArray(a.childNodes)}},function(d,e){z.fn[d]=function(a,b){var c=z.map(this,e,a);if(!F.test(d)){b=a}if(b&&typeof b==="string"){c=z.filter(b,c)}c=this.length>1&&!guaranteedUnique[d]?z.unique(c):c;if((this.length>1||rmultiselector.test(b))&&rparentsprev.test(d)){c=c.reverse()}return this.pushStack(c,d,slice.call(arguments).join(","))}});z.extend({filter:function(a,b,c){if(c){a=":not("+a+")"}return b.length===1?z.find.matchesSelector(b[0],a)?[b[0]]:[]:z.find.matches(a,b)},dir:function(a,b,c){var d=[],cur=a[b];while(cur&&cur.nodeType!==9&&(c===x||cur.nodeType!==1||!z(cur).is(c))){if(cur.nodeType===1){d.push(cur)}cur=cur[b]}return d},nth:function(a,b,c,d){b=b||1;var e=0;for(;a;a=a[c]){if(a.nodeType===1&&++e===b){break}}return a},sibling:function(n,a){var r=[];for(;n;n=n.nextSibling){if(n.nodeType===1&&n!==a){r.push(n)}}return r}});function winnow(c,d,e){d=d||0;if(z.isFunction(d)){return z.grep(c,function(a,i){var b=!!d.call(a,i,a);return b===e})}else if(d.nodeType){return z.grep(c,function(a,i){return(a===d)===e})}else if(typeof d==="string"){var f=z.grep(c,function(a){return a.nodeType===1});if(isSimple.test(d)){return z.filter(d,f,!e)}else{d=z.filter(d,f)}}return z.grep(c,function(a,i){return(z.inArray(a,d)>=0)===e})}function createSafeFragment(a){var b=G.split("|"),safeFrag=a.createDocumentFragment();if(safeFrag.createElement){while(b.length){safeFrag.createElement(b.pop())}}return safeFrag}var G="abbr|article|aside|audio|canvas|datalist|details|figcaption|figure|footer|"+"header|hgroup|mark|meter|nav|output|progress|section|summary|time|video",rinlinejQuery=/ jQuery\\d+="(?:\\d+|null)"/g,rleadingWhitespace=/^\\s+/,rxhtmlTag=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\\w:]+)[^>]*)\\/>/ig,rtagName=/<([\\w:]+)/,rtbody=/<tbody/i,rhtml=/<|&#?\\w+;/,rnoInnerhtml=/<(?:script|style)/i,rnocache=/<(?:script|object|embed|option|style)/i,rnoshimcache=new RegExp("<(?:"+G+")","i"),rchecked=/checked\\s*(?:[^=]|=\\s*.checked.)/i,rscriptType=/\\/(java|ecma)script/i,rcleanScript=/^\\s*<!(?:\\[CDATA\\[|\\-\\-)/,wrapMap={option:[1,"<select multiple=\'multiple\'>","</select>"],legend:[1,"<fieldset>","</fieldset>"],thead:[1,"<table>","</table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],col:[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"],area:[1,"<map>","</map>"],_default:[0,"",""]},safeFragment=createSafeFragment(y);wrapMap.optgroup=wrapMap.option;wrapMap.tbody=wrapMap.tfoot=wrapMap.colgroup=wrapMap.caption=wrapMap.thead;wrapMap.th=wrapMap.td;if(!z.support.htmlSerialize){wrapMap._default=[1,"div<div>","</div>"]}z.fn.extend({text:function(b){if(z.isFunction(b)){return this.each(function(i){var a=z(this);a.text(b.call(this,i,a.text()))})}if(typeof b!=="object"&&b!==x){return this.empty().append((this[0]&&this[0].ownerDocument||y).createTextNode(b))}return z.text(this)},wrapAll:function(b){if(z.isFunction(b)){return this.each(function(i){z(this).wrapAll(b.call(this,i))})}if(this[0]){var c=z(b,this[0].ownerDocument).eq(0).clone(true);if(this[0].parentNode){c.insertBefore(this[0])}c.map(function(){var a=this;while(a.firstChild&&a.firstChild.nodeType===1){a=a.firstChild}return a}).append(this)}return this},wrapInner:function(b){if(z.isFunction(b)){return this.each(function(i){z(this).wrapInner(b.call(this,i))})}return this.each(function(){var a=z(this),contents=a.contents();if(contents.length){contents.wrapAll(b)}else{a.append(b)}})},wrap:function(a){var b=z.isFunction(a);return this.each(function(i){z(this).wrapAll(b?a.call(this,i):a)})},unwrap:function(){return this.parent().each(function(){if(!z.nodeName(this,"body")){z(this).replaceWith(this.childNodes)}}).end()},append:function(){return this.domManip(arguments,true,function(a){if(this.nodeType===1){this.appendChild(a)}})},prepend:function(){return this.domManip(arguments,true,function(a){if(this.nodeType===1){this.insertBefore(a,this.firstChild)}})},before:function(){if(this[0]&&this[0].parentNode){return this.domManip(arguments,false,function(a){this.parentNode.insertBefore(a,this)})}else if(arguments.length){var b=z.clean(arguments);b.push.apply(b,this.toArray());return this.pushStack(b,"before",arguments)}},after:function(){if(this[0]&&this[0].parentNode){return this.domManip(arguments,false,function(a){this.parentNode.insertBefore(a,this.nextSibling)})}else if(arguments.length){var b=this.pushStack(this,"after",arguments);b.push.apply(b,z.clean(arguments));return b}},remove:function(a,b){for(var i=0,elem;(elem=this[i])!=null;i++){if(!a||z.filter(a,[elem]).length){if(!b&&elem.nodeType===1){z.cleanData(elem.getElementsByTagName("*"));z.cleanData([elem])}if(elem.parentNode){elem.parentNode.removeChild(elem)}}}return this},empty:function(){for(var i=0,elem;(elem=this[i])!=null;i++){if(elem.nodeType===1){z.cleanData(elem.getElementsByTagName("*"))}while(elem.firstChild){elem.removeChild(elem.firstChild)}}return this},clone:function(a,b){a=a==null?false:a;b=b==null?a:b;return this.map(function(){return z.clone(this,a,b)})},html:function(b){if(b===x){return this[0]&&this[0].nodeType===1?this[0].innerHTML.replace(rinlinejQuery,""):null}else if(typeof b==="string"&&!rnoInnerhtml.test(b)&&(z.support.leadingWhitespace||!rleadingWhitespace.test(b))&&!wrapMap[(rtagName.exec(b)||["",""])[1].toLowerCase()]){b=b.replace(rxhtmlTag,"<$1></$2>");try{for(var i=0,l=this.length;i<l;i++){if(this[i].nodeType===1){z.cleanData(this[i].getElementsByTagName("*"));this[i].innerHTML=b}}}catch(e){this.empty().append(b)}}else if(z.isFunction(b)){this.each(function(i){var a=z(this);a.html(b.call(this,i,a.html()))})}else{this.empty().append(b)}return this},replaceWith:function(b){if(this[0]&&this[0].parentNode){if(z.isFunction(b)){return this.each(function(i){var a=z(this),old=a.html();a.replaceWith(b.call(this,i,old))})}if(typeof b!=="string"){b=z(b).detach()}return this.each(function(){var a=this.nextSibling,parent=this.parentNode;z(this).remove();if(a){z(a).before(b)}else{z(parent).append(b)}})}else{return this.length?this.pushStack(z(z.isFunction(b)?b():b),"replaceWith",b):this}},detach:function(a){return this.remove(a,true)},domManip:function(b,c,d){var e,first,fragment,parent,value=b[0],scripts=[];if(!z.support.checkClone&&arguments.length===3&&typeof value==="string"&&rchecked.test(value)){return this.each(function(){z(this).domManip(b,c,d,true)})}if(z.isFunction(value)){return this.each(function(i){var a=z(this);b[0]=value.call(this,i,c?a.html():x);a.domManip(b,c,d)})}if(this[0]){parent=value&&value.parentNode;if(z.support.parentNode&&parent&&parent.nodeType===11&&parent.childNodes.length===this.length){e={fragment:parent}}else{e=z.buildFragment(b,this,scripts)}fragment=e.fragment;if(fragment.childNodes.length===1){first=fragment=fragment.firstChild}else{first=fragment.firstChild}if(first){c=c&&z.nodeName(first,"tr");for(var i=0,l=this.length,lastIndex=l-1;i<l;i++){d.call(c?root(this[i],first):this[i],e.cacheable||(l>1&&i<lastIndex)?z.clone(fragment,true,true):fragment)}}if(scripts.length){z.each(scripts,evalScript)}}return this}});function root(a,b){return z.nodeName(a,"table")?(a.getElementsByTagName("tbody")[0]||a.appendChild(a.ownerDocument.createElement("tbody"))):a}function cloneCopyEvent(a,b){if(b.nodeType!==1||!z.hasData(a)){return}var c,i,l,oldData=z._data(a),curData=z._data(b,oldData),events=oldData.events;if(events){delete curData.handle;curData.events={};for(c in events){for(i=0,l=events[c].length;i<l;i++){z.event.add(b,c+(events[c][i].namespace?".":"")+events[c][i].namespace,events[c][i],events[c][i].data)}}}if(curData.data){curData.data=z.extend({},curData.data)}}function cloneFixAttributes(a,b){var c;if(b.nodeType!==1){return}if(b.clearAttributes){b.clearAttributes()}if(b.mergeAttributes){b.mergeAttributes(a)}c=b.nodeName.toLowerCase();if(c==="object"){b.outerHTML=a.outerHTML}else if(c==="input"&&(a.type==="checkbox"||a.type==="radio")){if(a.checked){b.defaultChecked=b.checked=a.checked}if(b.value!==a.value){b.value=a.value}}else if(c==="option"){b.selected=a.defaultSelected}else if(c==="input"||c==="textarea"){b.defaultValue=a.defaultValue}b.removeAttribute(z.expando)}z.buildFragment=function(a,b,c){var d,cacheable,cacheresults,doc,first=a[0];if(b&&b[0]){doc=b[0].ownerDocument||b[0]}if(!doc.createDocumentFragment){doc=y}if(a.length===1&&typeof first==="string"&&first.length<512&&doc===y&&first.charAt(0)==="<"&&!rnocache.test(first)&&(z.support.checkClone||!rchecked.test(first))&&(z.support.html5Clone||!rnoshimcache.test(first))){cacheable=true;cacheresults=z.fragments[first];if(cacheresults&&cacheresults!==1){d=cacheresults}}if(!d){d=doc.createDocumentFragment();z.clean(a,doc,d,c)}if(cacheable){z.fragments[first]=cacheresults?d:1}return{fragment:d,cacheable:cacheable}};z.fragments={};z.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(d,e){z.fn[d]=function(a){var b=[],insert=z(a),parent=this.length===1&&this[0].parentNode;if(parent&&parent.nodeType===11&&parent.childNodes.length===1&&insert.length===1){insert[e](this[0]);return this}else{for(var i=0,l=insert.length;i<l;i++){var c=(i>0?this.clone(true):this).get();z(insert[i])[e](c);b=b.concat(c)}return this.pushStack(b,d,insert.selector)}}});function getAll(a){if(typeof a.getElementsByTagName!=="undefined"){return a.getElementsByTagName("*")}else if(typeof a.querySelectorAll!=="undefined"){return a.querySelectorAll("*")}else{return[]}}function fixDefaultChecked(a){if(a.type==="checkbox"||a.type==="radio"){a.defaultChecked=a.checked}}function findInputs(a){var b=(a.nodeName||"").toLowerCase();if(b==="input"){fixDefaultChecked(a)}else if(b!=="script"&&typeof a.getElementsByTagName!=="undefined"){z.grep(a.getElementsByTagName("input"),fixDefaultChecked)}}function shimCloneNode(a){var b=y.createElement("div");safeFragment.appendChild(b);b.innerHTML=a.outerHTML;return b.firstChild}z.extend({clone:function(a,b,c){var d,destElements,i,clone=z.support.html5Clone||!rnoshimcache.test("<"+a.nodeName)?a.cloneNode(true):shimCloneNode(a);if((!z.support.noCloneEvent||!z.support.noCloneChecked)&&(a.nodeType===1||a.nodeType===11)&&!z.isXMLDoc(a)){cloneFixAttributes(a,clone);d=getAll(a);destElements=getAll(clone);for(i=0;d[i];++i){if(destElements[i]){cloneFixAttributes(d[i],destElements[i])}}}if(b){cloneCopyEvent(a,clone);if(c){d=getAll(a);destElements=getAll(clone);for(i=0;d[i];++i){cloneCopyEvent(d[i],destElements[i])}}}d=destElements=null;return clone},clean:function(b,c,d,e){var f;c=c||y;if(typeof c.createElement==="undefined"){c=c.ownerDocument||c[0]&&c[0].ownerDocument||y}var g=[],j;for(var i=0,elem;(elem=b[i])!=null;i++){if(typeof elem==="number"){elem+=""}if(!elem){continue}if(typeof elem==="string"){if(!rhtml.test(elem)){elem=c.createTextNode(elem)}else{elem=elem.replace(rxhtmlTag,"<$1></$2>");var h=(rtagName.exec(elem)||["",""])[1].toLowerCase(),wrap=wrapMap[h]||wrapMap._default,depth=wrap[0],div=c.createElement("div");if(c===y){safeFragment.appendChild(div)}else{createSafeFragment(c).appendChild(div)}div.innerHTML=wrap[1]+elem+wrap[2];while(depth--){div=div.lastChild}if(!z.support.tbody){var k=rtbody.test(elem),tbody=h==="table"&&!k?div.firstChild&&div.firstChild.childNodes:wrap[1]==="<table>"&&!k?div.childNodes:[];for(j=tbody.length-1;j>=0;--j){if(z.nodeName(tbody[j],"tbody")&&!tbody[j].childNodes.length){tbody[j].parentNode.removeChild(tbody[j])}}}if(!z.support.leadingWhitespace&&rleadingWhitespace.test(elem)){div.insertBefore(c.createTextNode(rleadingWhitespace.exec(elem)[0]),div.firstChild)}elem=div.childNodes}}var l;if(!z.support.appendChecked){if(elem[0]&&typeof(l=elem.length)==="number"){for(j=0;j<l;j++){findInputs(elem[j])}}else{findInputs(elem)}}if(elem.nodeType){g.push(elem)}else{g=z.merge(g,elem)}}if(d){f=function(a){return!a.type||rscriptType.test(a.type)};for(i=0;g[i];i++){if(e&&z.nodeName(g[i],"script")&&(!g[i].type||g[i].type.toLowerCase()==="text/javascript")){e.push(g[i].parentNode?g[i].parentNode.removeChild(g[i]):g[i])}else{if(g[i].nodeType===1){var m=z.grep(g[i].getElementsByTagName("script"),f);g.splice.apply(g,[i+1,0].concat(m))}d.appendChild(g[i])}}}return g},cleanData:function(a){var b,id,cache=z.cache,special=z.event.special,deleteExpando=z.support.deleteExpando;for(var i=0,elem;(elem=a[i])!=null;i++){if(elem.nodeName&&z.noData[elem.nodeName.toLowerCase()]){continue}id=elem[z.expando];if(id){b=cache[id];if(b&&b.events){for(var c in b.events){if(special[c]){z.event.remove(elem,c)}else{z.removeEvent(elem,c,b.handle)}}if(b.handle){b.handle.elem=null}}if(deleteExpando){delete elem[z.expando]}else if(elem.removeAttribute){elem.removeAttribute(z.expando)}delete cache[id]}}}});function evalScript(i,a){if(a.src){z.ajax({url:a.src,async:false,dataType:"script"})}else{z.globalEval((a.text||a.textContent||a.innerHTML||"").replace(rcleanScript,"/*$0*/"))}if(a.parentNode){a.parentNode.removeChild(a)}}var H=/alpha\\([^)]*\\)/i,ropacity=/opacity=([^)]*)/,rupper=/([A-Z]|^ms)/g,rnumpx=/^-?\\d+(?:px)?$/i,rnum=/^-?\\d/,rrelNum=/^([\\-+])=([\\-+.\\de]+)/,cssShow={position:"absolute",visibility:"hidden",display:"block"},cssWidth=["Left","Right"],cssHeight=["Top","Bottom"],curCSS,getComputedStyle,currentStyle;z.fn.css=function(d,e){if(arguments.length===2&&e===x){return this}return z.access(this,d,e,true,function(a,b,c){return c!==x?z.style(a,b,c):z.css(a,b)})};z.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=curCSS(a,"opacity","opacity");return c===""?"1":c}else{return a.style.opacity}}}},cssNumber:{"fillOpacity":true,"fontWeight":true,"lineHeight":true,"opacity":true,"orphans":true,"widows":true,"zIndex":true,"zoom":true},cssProps:{"float":z.support.cssFloat?"cssFloat":"styleFloat"},style:function(a,b,c,d){if(!a||a.nodeType===3||a.nodeType===8||!a.style){return}var f,type,origName=z.camelCase(b),style=a.style,hooks=z.cssHooks[origName];b=z.cssProps[origName]||origName;if(c!==x){type=typeof c;if(type==="string"&&(f=rrelNum.exec(c))){c=(+(f[1]+1)*+f[2])+parseFloat(z.css(a,b));type="number"}if(c==null||type==="number"&&isNaN(c)){return}if(type==="number"&&!z.cssNumber[origName]){c+="px"}if(!hooks||!("set"in hooks)||(c=hooks.set(a,c))!==x){try{style[b]=c}catch(e){}}}else{if(hooks&&"get"in hooks&&(f=hooks.get(a,false,d))!==x){return f}return style[b]}},css:function(a,b,c){var d,hooks;b=z.camelCase(b);hooks=z.cssHooks[b];b=z.cssProps[b]||b;if(b==="cssFloat"){b="float"}if(hooks&&"get"in hooks&&(d=hooks.get(a,true,c))!==x){return d}else if(curCSS){return curCSS(a,b)}},swap:function(a,b,c){var d={};for(var e in b){d[e]=a.style[e];a.style[e]=b[e]}c.call(a);for(e in b){a.style[e]=d[e]}}});z.curCSS=z.css;z.each(["height","width"],function(i,e){z.cssHooks[e]={get:function(a,b,c){var d;if(b){if(a.offsetWidth!==0){return getWH(a,e,c)}else{z.swap(a,cssShow,function(){d=getWH(a,e,c)})}return d}},set:function(a,b){if(rnumpx.test(b)){b=parseFloat(b);if(b>=0){return b+"px"}}else{return b}}}});if(!z.support.opacity){z.cssHooks.opacity={get:function(a,b){return ropacity.test((b&&a.currentStyle?a.currentStyle.filter:a.style.filter)||"")?(parseFloat(RegExp.$1)/100)+"":b?"1":""},set:function(a,b){var c=a.style,currentStyle=a.currentStyle,opacity=z.isNumeric(b)?"alpha(opacity="+b*100+")":"",filter=currentStyle&&currentStyle.filter||c.filter||"";c.zoom=1;if(b>=1&&z.trim(filter.replace(H,""))===""){c.removeAttribute("filter");if(currentStyle&&!currentStyle.filter){return}}c.filter=H.test(filter)?filter.replace(H,opacity):filter+" "+opacity}}}z(function(){if(!z.support.reliableMarginRight){z.cssHooks.marginRight={get:function(a,b){var c;z.swap(a,{"display":"inline-block"},function(){if(b){c=curCSS(a,"margin-right","marginRight")}else{c=a.style.marginRight}});return c}}}});if(y.defaultView&&y.defaultView.getComputedStyle){getComputedStyle=function(a,b){var c,defaultView,computedStyle;b=b.replace(rupper,"-$1").toLowerCase();if((defaultView=a.ownerDocument.defaultView)&&(computedStyle=defaultView.getComputedStyle(a,null))){c=computedStyle.getPropertyValue(b);if(c===""&&!z.contains(a.ownerDocument.documentElement,a)){c=z.style(a,b)}}return c}}if(y.documentElement.currentStyle){currentStyle=function(a,b){var c,rsLeft,uncomputed,ret=a.currentStyle&&a.currentStyle[b],style=a.style;if(ret===null&&style&&(uncomputed=style[b])){ret=uncomputed}if(!rnumpx.test(ret)&&rnum.test(ret)){c=style.left;rsLeft=a.runtimeStyle&&a.runtimeStyle.left;if(rsLeft){a.runtimeStyle.left=a.currentStyle.left}style.left=b==="fontSize"?"1em":(ret||0);ret=style.pixelLeft+"px";style.left=c;if(rsLeft){a.runtimeStyle.left=rsLeft}}return ret===""?"auto":ret}}curCSS=getComputedStyle||currentStyle;function getWH(a,b,c){var d=b==="width"?a.offsetWidth:a.offsetHeight,which=b==="width"?cssWidth:cssHeight,i=0,len=which.length;if(d>0){if(c!=="border"){for(;i<len;i++){if(!c){d-=parseFloat(z.css(a,"padding"+which[i]))||0}if(c==="margin"){d+=parseFloat(z.css(a,c+which[i]))||0}else{d-=parseFloat(z.css(a,"border"+which[i]+"Width"))||0}}}return d+"px"}d=curCSS(a,b,b);if(d<0||d==null){d=a.style[b]||0}d=parseFloat(d)||0;if(c){for(;i<len;i++){d+=parseFloat(z.css(a,"padding"+which[i]))||0;if(c!=="padding"){d+=parseFloat(z.css(a,"border"+which[i]+"Width"))||0}if(c==="margin"){d+=parseFloat(z.css(a,c+which[i]))||0}}}return d+"px"}if(z.expr&&z.expr.filters){z.expr.filters.hidden=function(a){var b=a.offsetWidth,height=a.offsetHeight;return(b===0&&height===0)||(!z.support.reliableHiddenOffsets&&((a.style&&a.style.display)||z.css(a,"display"))==="none")};z.expr.filters.visible=function(a){return!z.expr.filters.hidden(a)}}var I=/%20/g,rbracket=/\\[\\]$/,rCRLF=/\\r?\\n/g,rhash=/#.*$/,rheaders=/^(.*?):[ \\t]*([^\\r\\n]*)\\r?$/mg,rinput=/^(?:color|date|datetime|datetime-local|email|hidden|month|number|password|range|search|tel|text|time|url|week)$/i,rlocalProtocol=/^(?:about|app|app\\-storage|.+\\-extension|file|res|widget):$/,rnoContent=/^(?:GET|HEAD)$/,rprotocol=/^\\/\\//,rquery=/\\?/,rscript=/<script\\b[^<]*(?:(?!<\\/script>)<[^<]*)*<\\/script>/gi,rselectTextarea=/^(?:select|textarea)/i,rspacesAjax=/\\s+/,rts=/([?&])_=[^&]*/,rurl=/^([\\w\\+\\.\\-]+:)(?:\\/\\/([^\\/?#:]*)(?::(\\d+))?)?/,_load=z.fn.load,prefilters={},transports={},ajaxLocation,ajaxLocParts,allTypes=["*/"]+["*"];try{ajaxLocation=location.href}catch(e){ajaxLocation=y.createElement("a");ajaxLocation.href="";ajaxLocation=ajaxLocation.href}ajaxLocParts=rurl.exec(ajaxLocation.toLowerCase())||[];function addToPrefiltersOrTransports(d){return function(a,b){if(typeof a!=="string"){b=a;a="*"}if(z.isFunction(b)){var c=a.toLowerCase().split(rspacesAjax),i=0,length=c.length,dataType,list,placeBefore;for(;i<length;i++){dataType=c[i];placeBefore=/^\\+/.test(dataType);if(placeBefore){dataType=dataType.substr(1)||"*"}list=d[dataType]=d[dataType]||[];list[placeBefore?"unshift":"push"](b)}}}}function inspectPrefiltersOrTransports(a,b,c,d,e,f){e=e||b.dataTypes[0];f=f||{};f[e]=true;var g=a[e],i=0,length=g?g.length:0,executeOnly=(a===prefilters),selection;for(;i<length&&(executeOnly||!selection);i++){selection=g[i](b,c,d);if(typeof selection==="string"){if(!executeOnly||f[selection]){selection=x}else{b.dataTypes.unshift(selection);selection=inspectPrefiltersOrTransports(a,b,c,d,selection,f)}}}if((executeOnly||!selection)&&!f["*"]){selection=inspectPrefiltersOrTransports(a,b,c,d,"*",f)}return selection}function ajaxExtend(a,b){var c,deep,flatOptions=z.ajaxSettings.flatOptions||{};for(c in b){if(b[c]!==x){(flatOptions[c]?a:(deep||(deep={})))[c]=b[c]}}if(deep){z.extend(true,a,deep)}}z.fn.extend({load:function(d,e,f){if(typeof d!=="string"&&_load){return _load.apply(this,arguments)}else if(!this.length){return this}var g=d.indexOf(" ");if(g>=0){var h=d.slice(g,d.length);d=d.slice(0,g)}var i="GET";if(e){if(z.isFunction(e)){f=e;e=x}else if(typeof e==="object"){e=z.param(e,z.ajaxSettings.traditional);i="POST"}}var j=this;z.ajax({url:d,type:i,dataType:"html",data:e,complete:function(a,b,c){c=a.responseText;if(a.isResolved()){a.done(function(r){c=r});j.html(h?z("<div>").append(c.replace(rscript,"")).find(h):c)}if(f){j.each(f,[c,b,a])}}});return this},serialize:function(){return z.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return this.elements?z.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||rselectTextarea.test(this.nodeName)||rinput.test(this.type))}).map(function(i,b){var c=z(this).val();return c==null?null:z.isArray(c)?z.map(c,function(a,i){return{name:b.name,value:a.replace(rCRLF,"\\r\\n")}}):{name:b.name,value:c.replace(rCRLF,"\\r\\n")}}).get()}});z.each("ajaxStart ajaxStop ajaxComplete ajaxError ajaxSuccess ajaxSend".split(" "),function(i,o){z.fn[o]=function(f){return this.on(o,f)}});z.each(["get","post"],function(i,e){z[e]=function(a,b,c,d){if(z.isFunction(b)){d=d||c;c=b;b=x}return z.ajax({type:e,url:a,data:b,success:c,dataType:d})}});z.extend({getScript:function(a,b){return z.get(a,x,b,"script")},getJSON:function(a,b,c){return z.get(a,b,c,"json")},ajaxSetup:function(a,b){if(b){ajaxExtend(a,z.ajaxSettings)}else{b=a;a=z.ajaxSettings}ajaxExtend(a,b);return a},ajaxSettings:{url:ajaxLocation,isLocal:rlocalProtocol.test(ajaxLocParts[1]),global:true,type:"GET",contentType:"application/x-www-form-urlencoded",processData:true,async:true,accepts:{xml:"application/xml, text/xml",html:"text/html",text:"text/plain",json:"application/json, text/javascript","*":allTypes},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText"},converters:{"* text":w.String,"text html":true,"text json":z.parseJSON,"text xml":z.parseXML},flatOptions:{context:true,url:true}},ajaxPrefilter:addToPrefiltersOrTransports(prefilters),ajaxTransport:addToPrefiltersOrTransports(transports),ajax:function(g,h){if(typeof g==="object"){h=g;g=x}h=h||{};var s=z.ajaxSetup({},h),callbackContext=s.context||s,globalEventContext=callbackContext!==s&&(callbackContext.nodeType||callbackContext instanceof z)?z(callbackContext):z.event,deferred=z.Deferred(),completeDeferred=z.Callbacks("once memory"),statusCode=s.statusCode||{},ifModifiedKey,requestHeaders={},requestHeadersNames={},responseHeadersString,responseHeaders,transport,timeoutTimer,parts,state=0,fireGlobals,i,jqXHR={readyState:0,setRequestHeader:function(a,b){if(!state){var c=a.toLowerCase();a=requestHeadersNames[c]=requestHeadersNames[c]||a;requestHeaders[a]=b}return this},getAllResponseHeaders:function(){return state===2?responseHeadersString:null},getResponseHeader:function(a){var b;if(state===2){if(!responseHeaders){responseHeaders={};while((b=rheaders.exec(responseHeadersString))){responseHeaders[b[1].toLowerCase()]=b[2]}}b=responseHeaders[a.toLowerCase()]}return b===x?null:b},overrideMimeType:function(a){if(!state){s.mimeType=a}return this},abort:function(a){a=a||"abort";if(transport){transport.abort(a)}done(0,a);return this}};function done(a,b,c,d){if(state===2){return}state=2;if(timeoutTimer){clearTimeout(timeoutTimer)}transport=x;responseHeadersString=d||"";jqXHR.readyState=a>0?4:0;var f,success,error,statusText=b,response=c?ajaxHandleResponses(s,jqXHR,c):x,lastModified,etag;if(a>=200&&a<300||a===304){if(s.ifModified){if((lastModified=jqXHR.getResponseHeader("Last-Modified"))){z.lastModified[ifModifiedKey]=lastModified}if((etag=jqXHR.getResponseHeader("Etag"))){z.etag[ifModifiedKey]=etag}}if(a===304){statusText="notmodified";f=true}else{try{success=ajaxConvert(s,response);statusText="success";f=true}catch(e){statusText="parsererror";error=e}}}else{error=statusText;if(!statusText||a){statusText="error";if(a<0){a=0}}}jqXHR.status=a;jqXHR.statusText=""+(b||statusText);if(f){deferred.resolveWith(callbackContext,[success,statusText,jqXHR])}else{deferred.rejectWith(callbackContext,[jqXHR,statusText,error])}jqXHR.statusCode(statusCode);statusCode=x;if(fireGlobals){globalEventContext.trigger("ajax"+(f?"Success":"Error"),[jqXHR,s,f?success:error])}completeDeferred.fireWith(callbackContext,[jqXHR,statusText]);if(fireGlobals){globalEventContext.trigger("ajaxComplete",[jqXHR,s]);if(!(--z.active)){z.event.trigger("ajaxStop")}}}deferred.promise(jqXHR);jqXHR.success=jqXHR.done;jqXHR.error=jqXHR.fail;jqXHR.complete=completeDeferred.add;jqXHR.statusCode=function(a){if(a){var b;if(state<2){for(b in a){statusCode[b]=[statusCode[b],a[b]]}}else{b=a[jqXHR.status];jqXHR.then(b,b)}}return this};s.url=((g||s.url)+"").replace(rhash,"").replace(rprotocol,ajaxLocParts[1]+"//");s.dataTypes=z.trim(s.dataType||"*").toLowerCase().split(rspacesAjax);if(s.crossDomain==null){parts=rurl.exec(s.url.toLowerCase());s.crossDomain=!!(parts&&(parts[1]!=ajaxLocParts[1]||parts[2]!=ajaxLocParts[2]||(parts[3]||(parts[1]==="http:"?80:443))!=(ajaxLocParts[3]||(ajaxLocParts[1]==="http:"?80:443))))}if(s.data&&s.processData&&typeof s.data!=="string"){s.data=z.param(s.data,s.traditional)}inspectPrefiltersOrTransports(prefilters,s,h,jqXHR);if(state===2){return false}fireGlobals=s.global;s.type=s.type.toUpperCase();s.hasContent=!rnoContent.test(s.type);if(fireGlobals&&z.active++===0){z.event.trigger("ajaxStart")}if(!s.hasContent){if(s.data){s.url+=(rquery.test(s.url)?"&":"?")+s.data;delete s.data}ifModifiedKey=s.url;if(s.cache===false){var j=z.now(),ret=s.url.replace(rts,"$1_="+j);s.url=ret+((ret===s.url)?(rquery.test(s.url)?"&":"?")+"_="+j:"")}}if(s.data&&s.hasContent&&s.contentType!==false||h.contentType){jqXHR.setRequestHeader("Content-Type",s.contentType)}if(s.ifModified){ifModifiedKey=ifModifiedKey||s.url;if(z.lastModified[ifModifiedKey]){jqXHR.setRequestHeader("If-Modified-Since",z.lastModified[ifModifiedKey])}if(z.etag[ifModifiedKey]){jqXHR.setRequestHeader("If-None-Match",z.etag[ifModifiedKey])}}jqXHR.setRequestHeader("Accept",s.dataTypes[0]&&s.accepts[s.dataTypes[0]]?s.accepts[s.dataTypes[0]]+(s.dataTypes[0]!=="*"?", "+allTypes+"; q=0.01":""):s.accepts["*"]);for(i in s.headers){jqXHR.setRequestHeader(i,s.headers[i])}if(s.beforeSend&&(s.beforeSend.call(callbackContext,jqXHR,s)===false||state===2)){jqXHR.abort();return false}for(i in{success:1,error:1,complete:1}){jqXHR[i](s[i])}transport=inspectPrefiltersOrTransports(transports,s,h,jqXHR);if(!transport){done(-1,"No Transport")}else{jqXHR.readyState=1;if(fireGlobals){globalEventContext.trigger("ajaxSend",[jqXHR,s])}if(s.async&&s.timeout>0){timeoutTimer=setTimeout(function(){jqXHR.abort("timeout")},s.timeout)}try{state=1;transport.send(requestHeaders,done)}catch(e){if(state<2){done(-1,e)}else{throw e;}}}return jqXHR},param:function(a,c){var s=[],add=function(a,b){b=z.isFunction(b)?b():b;s[s.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};if(c===x){c=z.ajaxSettings.traditional}if(z.isArray(a)||(a.jquery&&!z.isPlainObject(a))){z.each(a,function(){add(this.name,this.value)})}else{for(var d in a){buildParams(d,a[d],c,add)}}return s.join("&").replace(I,"+")}});function buildParams(a,b,c,d){if(z.isArray(b)){z.each(b,function(i,v){if(c||rbracket.test(a)){d(a,v)}else{buildParams(a+"["+(typeof v==="object"||z.isArray(v)?i:"")+"]",v,c,d)}})}else if(!c&&b!=null&&typeof b==="object"){for(var e in b){buildParams(a+"["+e+"]",b[e],c,d)}}else{d(a,b)}}z.extend({active:0,lastModified:{},etag:{}});function ajaxHandleResponses(s,a,b){var c=s.contents,dataTypes=s.dataTypes,responseFields=s.responseFields,ct,type,finalDataType,firstDataType;for(type in responseFields){if(type in b){a[responseFields[type]]=b[type]}}while(dataTypes[0]==="*"){dataTypes.shift();if(ct===x){ct=s.mimeType||a.getResponseHeader("content-type")}}if(ct){for(type in c){if(c[type]&&c[type].test(ct)){dataTypes.unshift(type);break}}}if(dataTypes[0]in b){finalDataType=dataTypes[0]}else{for(type in b){if(!dataTypes[0]||s.converters[type+" "+dataTypes[0]]){finalDataType=type;break}if(!firstDataType){firstDataType=type}}finalDataType=finalDataType||firstDataType}if(finalDataType){if(finalDataType!==dataTypes[0]){dataTypes.unshift(finalDataType)}return b[finalDataType]}}function ajaxConvert(s,a){if(s.dataFilter){a=s.dataFilter(a,s.dataType)}var b=s.dataTypes,converters={},i,key,length=b.length,tmp,current=b[0],prev,conversion,conv,conv1,conv2;for(i=1;i<length;i++){if(i===1){for(key in s.converters){if(typeof key==="string"){converters[key.toLowerCase()]=s.converters[key]}}}prev=current;current=b[i];if(current==="*"){current=prev}else if(prev!=="*"&&prev!==current){conversion=prev+" "+current;conv=converters[conversion]||converters["* "+current];if(!conv){conv2=x;for(conv1 in converters){tmp=conv1.split(" ");if(tmp[0]===prev||tmp[0]==="*"){conv2=converters[tmp[1]+" "+current];if(conv2){conv1=converters[conv1];if(conv1===true){conv=conv2}else if(conv2===true){conv=conv1}break}}}}if(!(conv||conv2)){z.error("No conversion from "+conversion.replace(" "," to "))}if(conv!==true){a=conv?conv(a):conv2(conv1(a))}}}return a}var J=z.now(),jsre=/(\\=)\\?(&|$)|\\?\\?/i;z.ajaxSetup({jsonp:"callback",jsonpCallback:function(){return z.expando+"_"+(J++)}});z.ajaxPrefilter("json jsonp",function(s,b,c){var d=s.contentType==="application/x-www-form-urlencoded"&&(typeof s.data==="string");if(s.dataTypes[0]==="jsonp"||s.jsonp!==false&&(jsre.test(s.url)||d&&jsre.test(s.data))){var e,jsonpCallback=s.jsonpCallback=z.isFunction(s.jsonpCallback)?s.jsonpCallback():s.jsonpCallback,previous=w[jsonpCallback],url=s.url,data=s.data,replace="$1"+jsonpCallback+"$2";if(s.jsonp!==false){url=url.replace(jsre,replace);if(s.url===url){if(d){data=data.replace(jsre,replace)}if(s.data===data){url+=(/\\?/.test(url)?"&":"?")+s.jsonp+"="+jsonpCallback}}}s.url=url;s.data=data;w[jsonpCallback]=function(a){e=[a]};c.always(function(){w[jsonpCallback]=previous;if(e&&z.isFunction(previous)){w[jsonpCallback](e[0])}});s.converters["script json"]=function(){if(!e){z.error(jsonpCallback+" was not called")}return e[0]};s.dataTypes[0]="json";return"script"}});z.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/javascript|ecmascript/},converters:{"text script":function(a){z.globalEval(a);return a}}});z.ajaxPrefilter("script",function(s){if(s.cache===x){s.cache=false}if(s.crossDomain){s.type="GET";s.global=false}});z.ajaxTransport("script",function(s){if(s.crossDomain){var c,head=y.head||y.getElementsByTagName("head")[0]||y.documentElement;return{send:function(_,b){c=y.createElement("script");c.async="async";if(s.scriptCharset){c.charset=s.scriptCharset}c.src=s.url;c.onload=c.onreadystatechange=function(_,a){if(a||!c.readyState||/loaded|complete/.test(c.readyState)){c.onload=c.onreadystatechange=null;if(head&&c.parentNode){head.removeChild(c)}c=x;if(!a){b(200,"success")}}};head.insertBefore(c,head.firstChild)},abort:function(){if(c){c.onload(0,1)}}}}});var K=w.ActiveXObject?function(){for(var a in xhrCallbacks){xhrCallbacks[a](0,1)}}:false,xhrId=0,xhrCallbacks;function createStandardXHR(){try{return new w.XMLHttpRequest()}catch(e){}}function createActiveXHR(){try{return new w.ActiveXObject("Microsoft.XMLHTTP")}catch(e){}}z.ajaxSettings.xhr=w.ActiveXObject?function(){return!this.isLocal&&createStandardXHR()||createActiveXHR()}:createStandardXHR;(function(a){z.extend(z.support,{ajax:!!a,cors:!!a&&("withCredentials"in a)})})(z.ajaxSettings.xhr());if(z.support.ajax){z.ajaxTransport(function(s){if(!s.crossDomain||z.support.cors){var g;return{send:function(c,d){var f=s.xhr(),handle,i;if(s.username){f.open(s.type,s.url,s.async,s.username,s.password)}else{f.open(s.type,s.url,s.async)}if(s.xhrFields){for(i in s.xhrFields){f[i]=s.xhrFields[i]}}if(s.mimeType&&f.overrideMimeType){f.overrideMimeType(s.mimeType)}if(!s.crossDomain&&!c["X-Requested-With"]){c["X-Requested-With"]="XMLHttpRequest"}try{for(i in c){f.setRequestHeader(i,c[i])}}catch(_){}f.send((s.hasContent&&s.data)||null);g=function(_,a){var b,statusText,responseHeaders,responses,xml;try{if(g&&(a||f.readyState===4)){g=x;if(handle){f.onreadystatechange=z.noop;if(K){delete xhrCallbacks[handle]}}if(a){if(f.readyState!==4){f.abort()}}else{b=f.status;responseHeaders=f.getAllResponseHeaders();responses={};xml=f.responseXML;if(xml&&xml.documentElement){responses.xml=xml}responses.text=f.responseText;try{statusText=f.statusText}catch(e){statusText=""}if(!b&&s.isLocal&&!s.crossDomain){b=responses.text?200:404}else if(b===1223){b=204}}}}catch(firefoxAccessException){if(!a){d(-1,firefoxAccessException)}}if(responses){d(b,statusText,responses,responseHeaders)}};if(!s.async||f.readyState===4){g()}else{handle=++xhrId;if(K){if(!xhrCallbacks){xhrCallbacks={};z(w).unload(K)}xhrCallbacks[handle]=g}f.onreadystatechange=g}},abort:function(){if(g){g(0,1)}}}}})}var L={},iframe,iframeDoc,rfxtypes=/^(?:toggle|show|hide)$/,rfxnum=/^([+\\-]=)?([\\d+.\\-]+)([a-z%]*)$/i,timerId,fxAttrs=[["height","marginTop","marginBottom","paddingTop","paddingBottom"],["width","marginLeft","marginRight","paddingLeft","paddingRight"],["opacity"]],fxNow;z.fn.extend({show:function(a,b,c){var d,display;if(a||a===0){return this.animate(genFx("show",3),a,b,c)}else{for(var i=0,j=this.length;i<j;i++){d=this[i];if(d.style){display=d.style.display;if(!z._data(d,"olddisplay")&&display==="none"){display=d.style.display=""}if(display===""&&z.css(d,"display")==="none"){z._data(d,"olddisplay",defaultDisplay(d.nodeName))}}}for(i=0;i<j;i++){d=this[i];if(d.style){display=d.style.display;if(display===""||display==="none"){d.style.display=z._data(d,"olddisplay")||""}}}return this}},hide:function(a,b,c){if(a||a===0){return this.animate(genFx("hide",3),a,b,c)}else{var d,display,i=0,j=this.length;for(;i<j;i++){d=this[i];if(d.style){display=z.css(d,"display");if(display!=="none"&&!z._data(d,"olddisplay")){z._data(d,"olddisplay",display)}}}for(i=0;i<j;i++){if(this[i].style){this[i].style.display="none"}}return this}},_toggle:z.fn.toggle,toggle:function(b,c,d){var e=typeof b==="boolean";if(z.isFunction(b)&&z.isFunction(c)){this._toggle.apply(this,arguments)}else if(b==null||e){this.each(function(){var a=e?b:z(this).is(":hidden");z(this)[a?"show":"hide"]()})}else{this.animate(genFx("toggle",3),b,c,d)}return this},fadeTo:function(a,b,c,d){return this.filter(":hidden").css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(b,c,d,f){var g=z.speed(c,d,f);if(z.isEmptyObject(b)){return this.each(g.complete,[false])}b=z.extend({},b);function doAnimation(){if(g.queue===false){z._mark(this)}var a=z.extend({},g),isElement=this.nodeType===1,hidden=isElement&&z(this).is(":hidden"),name,val,p,e,parts,start,end,unit,method;a.animatedProperties={};for(p in b){name=z.camelCase(p);if(p!==name){b[name]=b[p];delete b[p]}val=b[name];if(z.isArray(val)){a.animatedProperties[name]=val[1];val=b[name]=val[0]}else{a.animatedProperties[name]=a.specialEasing&&a.specialEasing[name]||a.easing||\'swing\'}if(val==="hide"&&hidden||val==="show"&&!hidden){return a.complete.call(this)}if(isElement&&(name==="height"||name==="width")){a.overflow=[this.style.overflow,this.style.overflowX,this.style.overflowY];if(z.css(this,"display")==="inline"&&z.css(this,"float")==="none"){if(!z.support.inlineBlockNeedsLayout||defaultDisplay(this.nodeName)==="inline"){this.style.display="inline-block"}else{this.style.zoom=1}}}}if(a.overflow!=null){this.style.overflow="hidden"}for(p in b){e=new z.fx(this,a,p);val=b[p];if(rfxtypes.test(val)){method=z._data(this,"toggle"+p)||(val==="toggle"?hidden?"show":"hide":0);if(method){z._data(this,"toggle"+p,method==="show"?"hide":"show");e[method]()}else{e[val]()}}else{parts=rfxnum.exec(val);start=e.cur();if(parts){end=parseFloat(parts[2]);unit=parts[3]||(z.cssNumber[p]?"":"px");if(unit!=="px"){z.style(this,p,(end||1)+unit);start=((end||1)/e.cur())*start;z.style(this,p,start+unit)}if(parts[1]){end=((parts[1]==="-="?-1:1)*end)+start}e.custom(start,end,unit)}else{e.custom(start,val,"")}}}return true}return g.queue===false?this.each(doAnimation):this.queue(g.queue,doAnimation)},stop:function(f,g,h){if(typeof f!=="string"){h=g;g=f;f=x}if(g&&f!==false){this.queue(f||"fx",[])}return this.each(function(){var e,hadTimers=false,timers=z.timers,data=z._data(this);if(!h){z._unmark(true,this)}function stopQueue(a,b,c){var d=b[c];z.removeData(a,c,true);d.stop(h)}if(f==null){for(e in data){if(data[e]&&data[e].stop&&e.indexOf(".run")===e.length-4){stopQueue(this,data,e)}}}else if(data[e=f+".run"]&&data[e].stop){stopQueue(this,data,e)}for(e=timers.length;e--;){if(timers[e].elem===this&&(f==null||timers[e].queue===f)){if(h){timers[e](true)}else{timers[e].saveState()}hadTimers=true;timers.splice(e,1)}}if(!(h&&hadTimers)){z.dequeue(this,f)}})}});function createFxNow(){setTimeout(clearFxNow,0);return(fxNow=z.now())}function clearFxNow(){fxNow=x}function genFx(a,b){var c={};z.each(fxAttrs.concat.apply([],fxAttrs.slice(0,b)),function(){c[this]=a});return c}z.each({slideDown:genFx("show",1),slideUp:genFx("hide",1),slideToggle:genFx("toggle",1),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(d,e){z.fn[d]=function(a,b,c){return this.animate(e,a,b,c)}});z.extend({speed:function(b,c,d){var e=b&&typeof b==="object"?z.extend({},b):{complete:d||!d&&c||z.isFunction(b)&&b,duration:b,easing:d&&c||c&&!z.isFunction(c)&&c};e.duration=z.fx.off?0:typeof e.duration==="number"?e.duration:e.duration in z.fx.speeds?z.fx.speeds[e.duration]:z.fx.speeds._default;if(e.queue==null||e.queue===true){e.queue="fx"}e.old=e.complete;e.complete=function(a){if(z.isFunction(e.old)){e.old.call(this)}if(e.queue){z.dequeue(this,e.queue)}else if(a!==false){z._unmark(this)}};return e},easing:{linear:function(p,n,a,b){return a+b*p},swing:function(p,n,a,b){return((-Math.cos(p*Math.PI)/2)+0.5)*b+a}},timers:[],fx:function(a,b,c){this.options=b;this.elem=a;this.prop=c;b.orig=b.orig||{}}});z.fx.prototype={update:function(){if(this.options.step){this.options.step.call(this.elem,this.now,this)}(z.fx.step[this.prop]||z.fx.step._default)(this)},cur:function(){if(this.elem[this.prop]!=null&&(!this.elem.style||this.elem.style[this.prop]==null)){return this.elem[this.prop]}var a,r=z.css(this.elem,this.prop);return isNaN(a=parseFloat(r))?!r||r==="auto"?0:r:a},custom:function(b,c,d){var e=this,fx=z.fx;this.startTime=fxNow||createFxNow();this.end=c;this.now=this.start=b;this.pos=this.state=0;this.unit=d||this.unit||(z.cssNumber[this.prop]?"":"px");function t(a){return e.step(a)}t.queue=this.options.queue;t.elem=this.elem;t.saveState=function(){if(e.options.hide&&z._data(e.elem,"fxshow"+e.prop)===x){z._data(e.elem,"fxshow"+e.prop,e.start)}};if(t()&&z.timers.push(t)&&!timerId){timerId=setInterval(fx.tick,fx.interval)}},show:function(){var a=z._data(this.elem,"fxshow"+this.prop);this.options.orig[this.prop]=a||z.style(this.elem,this.prop);this.options.show=true;if(a!==x){this.custom(this.cur(),a)}else{this.custom(this.prop==="width"||this.prop==="height"?1:0,this.cur())}z(this.elem).show()},hide:function(){this.options.orig[this.prop]=z._data(this.elem,"fxshow"+this.prop)||z.style(this.elem,this.prop);this.options.hide=true;this.custom(this.cur(),0)},step:function(c){var p,n,complete,t=fxNow||createFxNow(),done=true,elem=this.elem,options=this.options;if(c||t>=options.duration+this.startTime){this.now=this.end;this.pos=this.state=1;this.update();options.animatedProperties[this.prop]=true;for(p in options.animatedProperties){if(options.animatedProperties[p]!==true){done=false}}if(done){if(options.overflow!=null&&!z.support.shrinkWrapBlocks){z.each(["","X","Y"],function(a,b){elem.style["overflow"+b]=options.overflow[a]})}if(options.hide){z(elem).hide()}if(options.hide||options.show){for(p in options.animatedProperties){z.style(elem,p,options.orig[p]);z.removeData(elem,"fxshow"+p,true);z.removeData(elem,"toggle"+p,true)}}complete=options.complete;if(complete){options.complete=false;complete.call(elem)}}return false}else{if(options.duration==Infinity){this.now=t}else{n=t-this.startTime;this.state=n/options.duration;this.pos=z.easing[options.animatedProperties[this.prop]](this.state,n,0,1,options.duration);this.now=this.start+((this.end-this.start)*this.pos)}this.update()}return true}};z.extend(z.fx,{tick:function(){var a,timers=z.timers,i=0;for(;i<timers.length;i++){a=timers[i];if(!a()&&timers[i]===a){timers.splice(i--,1)}}if(!timers.length){z.fx.stop()}},interval:13,stop:function(){clearInterval(timerId);timerId=null},speeds:{slow:600,fast:200,_default:400},step:{opacity:function(a){z.style(a.elem,"opacity",a.now)},_default:function(a){if(a.elem.style&&a.elem.style[a.prop]!=null){a.elem.style[a.prop]=a.now+a.unit}else{a.elem[a.prop]=a.now}}}});z.each(["width","height"],function(i,b){z.fx.step[b]=function(a){z.style(a.elem,b,Math.max(0,a.now)+a.unit)}});if(z.expr&&z.expr.filters){z.expr.filters.animated=function(b){return z.grep(z.timers,function(a){return b===a.elem}).length}}function defaultDisplay(a){if(!L[a]){var b=y.body,elem=z("<"+a+">").appendTo(b),display=elem.css("display");elem.remove();if(display==="none"||display===""){if(!iframe){iframe=y.createElement("iframe");iframe.frameBorder=iframe.width=iframe.height=0}b.appendChild(iframe);if(!iframeDoc||!iframe.createElement){iframeDoc=(iframe.contentWindow||iframe.contentDocument).document;iframeDoc.write((y.compatMode==="CSS1Compat"?"<!doctype html>":"")+"<html><body>");iframeDoc.close()}elem=iframeDoc.createElement(a);iframeDoc.body.appendChild(elem);display=z.css(elem,"display");b.removeChild(iframe)}L[a]=display}return L[a]}var M=/^t(?:able|d|h)$/i,rroot=/^(?:body|html)$/i;if("getBoundingClientRect"in y.documentElement){z.fn.offset=function(a){var b=this[0],box;if(a){return this.each(function(i){z.offset.setOffset(this,a,i)})}if(!b||!b.ownerDocument){return null}if(b===b.ownerDocument.body){return z.offset.bodyOffset(b)}try{box=b.getBoundingClientRect()}catch(e){}var c=b.ownerDocument,docElem=c.documentElement;if(!box||!z.contains(docElem,b)){return box?{top:box.top,left:box.left}:{top:0,left:0}}var d=c.body,win=getWindow(c),clientTop=docElem.clientTop||d.clientTop||0,clientLeft=docElem.clientLeft||d.clientLeft||0,scrollTop=win.pageYOffset||z.support.boxModel&&docElem.scrollTop||d.scrollTop,scrollLeft=win.pageXOffset||z.support.boxModel&&docElem.scrollLeft||d.scrollLeft,top=box.top+scrollTop-clientTop,left=box.left+scrollLeft-clientLeft;return{top:top,left:left}}}else{z.fn.offset=function(a){var b=this[0];if(a){return this.each(function(i){z.offset.setOffset(this,a,i)})}if(!b||!b.ownerDocument){return null}if(b===b.ownerDocument.body){return z.offset.bodyOffset(b)}var c,offsetParent=b.offsetParent,prevOffsetParent=b,doc=b.ownerDocument,docElem=doc.documentElement,body=doc.body,defaultView=doc.defaultView,prevComputedStyle=defaultView?defaultView.getComputedStyle(b,null):b.currentStyle,top=b.offsetTop,left=b.offsetLeft;while((b=b.parentNode)&&b!==body&&b!==docElem){if(z.support.fixedPosition&&prevComputedStyle.position==="fixed"){break}c=defaultView?defaultView.getComputedStyle(b,null):b.currentStyle;top-=b.scrollTop;left-=b.scrollLeft;if(b===offsetParent){top+=b.offsetTop;left+=b.offsetLeft;if(z.support.doesNotAddBorder&&!(z.support.doesAddBorderForTableAndCells&&M.test(b.nodeName))){top+=parseFloat(c.borderTopWidth)||0;left+=parseFloat(c.borderLeftWidth)||0}prevOffsetParent=offsetParent;offsetParent=b.offsetParent}if(z.support.subtractsBorderForOverflowNotVisible&&c.overflow!=="visible"){top+=parseFloat(c.borderTopWidth)||0;left+=parseFloat(c.borderLeftWidth)||0}prevComputedStyle=c}if(prevComputedStyle.position==="relative"||prevComputedStyle.position==="static"){top+=body.offsetTop;left+=body.offsetLeft}if(z.support.fixedPosition&&prevComputedStyle.position==="fixed"){top+=Math.max(docElem.scrollTop,body.scrollTop);left+=Math.max(docElem.scrollLeft,body.scrollLeft)}return{top:top,left:left}}}z.offset={bodyOffset:function(a){var b=a.offsetTop,left=a.offsetLeft;if(z.support.doesNotIncludeMarginInBodyOffset){b+=parseFloat(z.css(a,"marginTop"))||0;left+=parseFloat(z.css(a,"marginLeft"))||0}return{top:b,left:left}},setOffset:function(a,b,i){var c=z.css(a,"position");if(c==="static"){a.style.position="relative"}var d=z(a),curOffset=d.offset(),curCSSTop=z.css(a,"top"),curCSSLeft=z.css(a,"left"),calculatePosition=(c==="absolute"||c==="fixed")&&z.inArray("auto",[curCSSTop,curCSSLeft])>-1,props={},curPosition={},curTop,curLeft;if(calculatePosition){curPosition=d.position();curTop=curPosition.top;curLeft=curPosition.left}else{curTop=parseFloat(curCSSTop)||0;curLeft=parseFloat(curCSSLeft)||0}if(z.isFunction(b)){b=b.call(a,i,curOffset)}if(b.top!=null){props.top=(b.top-curOffset.top)+curTop}if(b.left!=null){props.left=(b.left-curOffset.left)+curLeft}if("using"in b){b.using.call(a,props)}else{d.css(props)}}};z.fn.extend({position:function(){if(!this[0]){return null}var a=this[0],offsetParent=this.offsetParent(),offset=this.offset(),parentOffset=rroot.test(offsetParent[0].nodeName)?{top:0,left:0}:offsetParent.offset();offset.top-=parseFloat(z.css(a,"marginTop"))||0;offset.left-=parseFloat(z.css(a,"marginLeft"))||0;parentOffset.top+=parseFloat(z.css(offsetParent[0],"borderTopWidth"))||0;parentOffset.left+=parseFloat(z.css(offsetParent[0],"borderLeftWidth"))||0;return{top:offset.top-parentOffset.top,left:offset.left-parentOffset.left}},offsetParent:function(){return this.map(function(){var a=this.offsetParent||y.body;while(a&&(!rroot.test(a.nodeName)&&z.css(a,"position")==="static")){a=a.offsetParent}return a})}});z.each(["Left","Top"],function(i,c){var d="scroll"+c;z.fn[d]=function(a){var b,win;if(a===x){b=this[0];if(!b){return null}win=getWindow(b);return win?("pageXOffset"in win)?win[i?"pageYOffset":"pageXOffset"]:z.support.boxModel&&win.document.documentElement[d]||win.document.body[d]:b[d]}return this.each(function(){win=getWindow(this);if(win){win.scrollTo(!i?a:z(win).scrollLeft(),i?a:z(win).scrollTop())}else{this[d]=a}})}});function getWindow(a){return z.isWindow(a)?a:a.nodeType===9?a.defaultView||a.parentWindow:false}z.each(["Height","Width"],function(i,f){var g=f.toLowerCase();z.fn["inner"+f]=function(){var a=this[0];return a?a.style?parseFloat(z.css(a,g,"padding")):this[g]():null};z.fn["outer"+f]=function(a){var b=this[0];return b?b.style?parseFloat(z.css(b,g,a?"margin":"border")):this[g]():null};z.fn[g]=function(b){var c=this[0];if(!c){return b==null?null:this}if(z.isFunction(b)){return this.each(function(i){var a=z(this);a[g](b.call(this,i,a[g]()))})}if(z.isWindow(c)){var d=c.document.documentElement["client"+f],body=c.document.body;return c.document.compatMode==="CSS1Compat"&&d||body&&body["client"+f]||d}else if(c.nodeType===9){return Math.max(c.documentElement["client"+f],c.body["scroll"+f],c.documentElement["scroll"+f],c.body["offset"+f],c.documentElement["offset"+f])}else if(b===x){var e=z.css(c,g),ret=parseFloat(e);return z.isNumeric(ret)?ret:e}else{return this.css(g,typeof b==="string"?b:b+"px")}}});return z})(window);',
				$js
		);

		return <<<END

{$css}

{$js}

END;

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
    const CHAR_BOLD_LINE = '=';
    const CHAR_LIGHT_LINE = '-';


	public function __construct($file = 'php://stderr') {
		$this->file = $file;
	}

	/**
	 * @return String What would be written for the given message.
	 */
	public function getOutput($message, $log, $level) {
		$trace = Octopus_Debug::getNiceBacktrace();
		$time = time();
		return self::formatForDisplay($message, $log, $level, $time, $trace);
	}

	public function write($message, $log, $level) {

		$message = $this->getOutput($message, $log, $level);

		if (is_resource($this->file)) {
			fputs($this->file, "\n$message\n");
		} else {
	        $fp = fopen($this->file, 'w');
        	fputs($fp, "\n$message\n");
        	fclose($fp);
        }

	}

	public static function formatForDisplay($message, $log, $level, $time, $trace, $color = false, $width = 80) {

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
				'INFO' => 	"\033[34m", // blue
				'WARN' => 	"\033[31m", // yellow
				'ERROR' => 	"\033[31m", // red
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

		$boldLine = 	str_repeat(self::CHAR_BOLD_LINE, $width);
		$lightLine = 	str_repeat(self::CHAR_LIGHT_LINE, $width);
		$space = 		' ';

		$time = is_numeric($time) ? date('r', $time) : $time;
		$time = str_pad($time, ($width / 2) - 1);
		$logAndLevel = "{$log} {$level}";
		$logAndLevel = str_pad($logAndLevel, ($width / 2) - 1, ' ', STR_PAD_LEFT);

		if ($trace) {

			$trace = Octopus_Debug::getMostRelevantTraceLine($trace, array(__FILE__));
			if ($trace) {
				$trace = "{$trace['nice_file']}, line {$trace['line']}";
				$trace = ' ' . str_pad($trace, $width - 1);
				$trace = "\n{$lightLine}\n{$trace}";
			} else {
				$trace = '';
			}
		}

		return <<<END
{$defaultFormat}{$levelColor}
{$boldLine}
 {$time}{$logAndLevel}{$space}
{$lightLine}
{$message}{$trace}
{$boldLine}{$reset}

END;

	}

}

////////////////////////////////////////////////////////////////////////////////
//
// class Octopus_Log_Listener_Mail
//
////////////////////////////////////////////////////////////////////////////////

/**
 * A log listener that fires off emails.
 */
class Octopus_Log_Listener_Mail {

	private $to = array();

	public function __construct($to = null) {

		foreach(func_get_args() as $arg) {
			$this->addRecipient($arg);
		}

	}

	public function addRecipient($addr) {

		if (!is_array($addr)) {
			return $this->addRecipient(explode(',', $addr));
		}

		foreach($addr as $to) {
			$to = trim($to);
			$this->to[$to] = true;
		}

	}

	public function write($message, $log, $level) {

		if (class_exists('Octopus_Mail')) {
			$this->writeUsingOctopusMail($message, $log, $level);
		} else {
			// TODO: native php mail()
		}

	}

	private function writeUsingOctopusMail($message, $log, $level) {

		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : trim(`hostname`);

		$body = Octopus_Log_Listener_Console::getOutput($message, $log, $level);
		$subject = Octopus_Log::getLevelName($level) . ' in ' . $log . ' on ' . $host;

		$htmlBody = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
		$htmlBody = nl2br($htmlBody);
		$htmlBody = "<pre>{$htmlBody}</pre>";

		$mail = new Octopus_Mail();
		$mail->to(array_keys($this->to));
		$mail->subject($subject);
		$mail->text($body);
		$mail->html($htmlBody);
		$mail->from(Octopus_Log::getLevelName($level));
		$mail->send();

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
        $trace = Octopus_Debug::getNiceBacktrace($this->trace);

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
    private static $redirectsEnabled = true;

    /**
     * Sets up the debugging environment if it has not already been set up.
     */
    public static function configure($options = array()) {

    	if (self::$configured && empty($options)) {
    		return;
    	}

    	self::reset();
    	self::$configured = true;

    	$logDir = null;
    	$fileListener = null;

    	if (isset($options['LOG_DIR'])) {
    		$logDir = $options['LOG_DIR'];
    	} else if (isset($options['PRIVATE_DIR'])) {
    		$logDir = rtrim($options['PRIVATE_DIR'], '/') . '/log';
    	} else if (isset($options['OCTOPUS_PRIVATE_DIR'])) {
    		$logDir = rtrim($options['OCTOPUS_PRIVATE_DIR'], '/') . '/log';
    	} else if (defined('LOG_DIR') && is_dir(LOG_DIR)) {
    		$logDir = LOG_DIR;
    	} else if (defined('PRIVATE_DIR') && is_dir(PRIVATE_DIR)) {
    		$logDir = PRIVATE_DIR;
    	} else if (defined('OCTOPUS_PRIVATE_DIR') && is_dir(OCTOPUS_PRIVATE_DIR)) {
    		$logDir = OCTOPUS_PRIVATE_DIR;
    	}

    	if ($logDir) {
    		$fileListener = new Octopus_Log_Listener_File($logDir);
    	}

		if (!empty($options['LIVE']) || self::isLiveEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::LEVEL_WARN, $fileListener);
			}

		} else if (!empty($options['STAGING']) || self::isStagingEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::LEVEL_DEBUG, $fileListener);
			}

		} else if (!empty($options['DEV']) || self::isDevEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::LEVEL_DEBUG, $fileListener);
			}

			Octopus_Log::addListener(new Octopus_Log_Listener_Console());

			if (self::shouldUseHtmlLogging()) {
				Octopus_Log::addListener(new Octopus_Log_Listener_Html());
			}
		}

		Octopus_Log::registerExceptionHandler();
		Octopus_Log::registerErrorHandler();
    }

    /**
     * Writes all arguments passed to it as special debug messages. Only
     * works when the app is in DEV mode.
     * @see dump_r
     * @return Mixed The first argument passed in, unless called from a
     * smarty template, in which case nothing is returned.
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

    	// This is kind of a hack. When we are calling dump_r from smarty
    	// templates, we don't want to return the value because it will get
    	// rendered.
    	$line = self::getMostRelevantTraceLine();

    	if ($line && preg_match('/\.tpl\.php/', $line['file'])) {
    		return;
    	}

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
    public static function dumpToString($x, $format, $fancy = true) {

    	if ($format === 'html') {
    		$result = self::dumpToHtmlString($x, $fancy);
    	} else {
    		$result = self::dumpToPlainTextString($x, $fancy);
    	}

        return self::sanitizeDebugOutput($result);
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
     * @return Mixed An item from a backtrace array that is the best thing to
     * show the user.
     */
    public static function getMostRelevantTraceLine($trace = null, $filesToIgnore = array()) {

    	if ($trace === null) {
    		$trace = self::getNiceBacktrace();
    	}

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

			if (in_array($traceLine['file'], $filesToIgnore)) {
				continue;
			}

			return $traceLine;
		}

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
                $niceItem['nice_file'] = substr($niceItem['file'], $rootDirLen);
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

    	$isLive = !(self::isDevEnvironment() || self::isStagingEnvironment());

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
   	 * Enables/disables redirects. When redirects are disabled, ::shouldRedirect
   	 * will return false.
   	 */
    public static function enableRedirects($enable = true) {
    	self::$redirectsEnabled = !!$enable;
    }

    public static function disableRedirects() {
    	return self::enableRedirects(false);
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

        foreach(self::getNiceBacktrace($bt) as $item) {
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
   		self::$configured = false;
   		self::$environment = null;
   		self::$dumpEnabled = true;
   		self::$redirectsEnabled = true;
   		Octopus_Log::reset();
   	}

   	/**
   	 * @return Boolean Whether or not the app should redirect the user to
   	 * $location.
   	 * @param String $location Location the user is to be sent to.
   	 */
   	public static function shouldRedirect($location) {

   		if (self::$redirectsEnabled) {
   			return true;
   		}

   		// Notify about cancelled redirect!
   		Octopus_Log::debug('dump', "Cancelled redirect to $location");
   		return false;

   	}

   	/**
   	 * @return Boolean Whether or not HTML-formatted log messages should be
   	 * sent to output. This is true for web requests that are NOT XHR requests.
   	 */
   	public static function shouldUseHtmlLogging() {

   		return

   			// Don't use on command line
   			php_sapi_name() != 'cli' &&
   			!empty($_SERVER['HTTP_USER_AGENT']) &&

   			// Don't use for XHR requests
   			empty($_SERVER['HTTP_X_REQUESTED_WITH']);

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
        $trace = new Octopus_Log_Listener_Html_Trace($ex->getTrace());

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
    private static function dumpExceptionToText(Exception $ex) {

    	$result = '';
		$filterTraceLocations = '#^(/usr/local/pear/|/usr/bin/phpunit$)#';

    	do {

	        $class = get_class($ex);
	        $message = $ex->getMessage();
	        $trace = self::getNiceBacktrace($ex->getTrace());

	        $result .= <<<END
{$message}


END;

	        foreach($trace as $i) {

	            if (preg_match($filterTraceLocations, $i['file'])) {
	                continue;
	            }

	            if (empty($i['nice_file'])) {
	            	continue;
	            }

	            $result .= <<<END
	  {$i['nice_file']}: {$i['line']}

END;
	        }

	        $result .= "\n\n";

    		$ex = $ex->getPrevious();
    	} while($ex);

        return trim($result);
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

    private static function dumpNumberToText($x) {

    	$result = self::dumpToPlainTextString($x, false);

    	if (is_int($x)) {

    		$result .=
    			"\n\t" .
    			sprintf('octal:        0%o', $x) .
    			"\n\t" .
    			sprintf('hex:          0x%X', $x);

    	}

    	if (self::looksLikeTimestamp($x)) {
    		$result .=
    			"\n\t" .
    			        "timestamp:    " . date('r', $x);
    	}

    	if (self::looksLikeFilePermissions($x)) {
    		$result .=
    			"\n\t" .
    					"permissions:  " . self::getNumberAsFilePermissions($x);
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
        $result = <<<END
"$str" ($length)
END;

		if (strlen($result) > 80) {
			$result = <<<END
"$str"
	Length: $length
END;
		}

		$time = @strtotime($str);
		if ($time !== false && $time !== -1) {
			$result .= <<<END

	Timestamp: $time
END;
		}

        if (strlen($str) > 1 && $str[0] === '/' && file_exists($str)) {

            $isDir = is_dir($str);
            $isLink = is_link($str);

            $type = 'File';
            if ($isDir) $type = 'Directory';
            if ($isLink) $type .= ' (link)';

            $info = array('exists');

            $perms = @fileperms($str);

            if ($perms) {
            	$info[] = self::getNumberAsFilePermissions($perms);
            }

            if ($isDir) {

            	$handle = @opendir($str);

            	if ($handle) {

            		$count = 0;
            		while(($entry = readdir($handle)) !== false) {
            			if ($entry != '.' && $entry != '..') {
            				$count++;
            			}
            		}
            		closedir($handle);

            		$info[] = 'contains ' . number_format($count) . ' file' . ($count === 1 ? '' : 's');
            	}

            } else {

            	$size = @filesize($str);
            	if ($size !== false) {

            		$levels = array('G', 'M', 'K', 'B');
            		$niceSize = '';

            		foreach($levels as $index => $symbol) {

            			$index = count($levels) - $index - 1;
            			$threshold = ($index === 0 ? 0 : pow(1024, $index));

            			if ($size >= $threshold) {
            				$niceSize = $size / $threshold;

            				$niceSize =
            					(floor($niceSize) === $niceSize ? '' : '~') .
            					number_format($niceSize) .
            					$symbol .
            					($threshold > 0 ? ' (' . number_format($size) . ' bytes)' : '');
            					break;
            			}
            		}

            		if ($niceSize) {
            			$info[] = $niceSize;
            		}
            	}

            }

            if ($info) {
            	$info = ': ' . implode(', ', $info);
            } else {
            	$info = '';
            }

            $result .= "\n\t$type{$info}";

        }

        return $result;

    }

    private static function dumpToHtmlString($x, $fancy) {

    	if ($fancy) {

            if ($x === null) {
                return '<span class="octopusDebugNull">NULL</span>';
            } else if (is_bool($x)) {
                return  '<span class="octopusDebugBoolean">' . ($x ? 'TRUE' : 'FALSE') . '</span>';
            } else if (is_object($x) && $x instanceof Dumpable) {
                return $x->__dumpHtml();
            } else if ($x instanceof Exception) {
                return self::dumpExceptionToHtml($x);
            } else if (is_array($x)) {
                return self::dumpArrayToHtml($x);
            } else if (is_string($x)) {
                return self::dumpStringToHtml($x);
            } else if (is_numeric($x)) {
                return self::dumpNumberToHtml($x);
            }

        }

        return
        	'<pre class="octopusDebugRawVarDump">' .
        	htmlspecialchars(self::dumpToPlainTextString($x, $fancy), ENT_QUOTES, 'UTF-8') .
        	'</pre>';
    }

    private static function dumpToPlainTextString($x, $fancy) {

    	if ($fancy) {

	        if ($x === null) {
	            return 'NULL';
	        } else if ($x === true || $x === false) {
	            return $x ? 'TRUE' : 'FALSE';
	        } else if (is_string($x)) {
	            return self::dumpStringToText($x);
	        } else if (is_numeric($x)) {
	        	return self::dumpNumberToText($x);
	        } else if (is_object($x) && $x instanceof Dumpable) {
	            $result = $x->__dumpText();
	            return ($result === null ? '' : $result);
	        } else if ($x instanceof Exception) {
	            return self::dumpExceptionToText($x);
	        }

	    }

        ob_start();
        // NOTE: var_export chokes on recursive references, and var_dump is
        // slightly better at handling them.
        var_dump($x);
        return trim(ob_get_clean());

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
            $info = '';
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

    private static function looksLikeFilePermissions($x) {

    	if (!is_int($x) || $x < 0 || $x > 0777) {
    		return false;
    	}

    	// basic sanity filter to avoid interpreting everything as
    	// permissions
    	$x = self::getNumberAsFilePermissions($x);
    	return !preg_match('/(-w-)/', $x);

    }

    private static function looksLikeTimestamp($x) {

    	if (!is_int($x)) {
    		return false;
    	}

        $minDate = strtotime('-10 years');
        $maxDate = strtotime('+10 years');

        return ($x >= $minDate && $x <= $maxDate);
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

		$result = array();

		foreach($this->vars as $key => $var) {
			$result[] = Octopus_Debug::dumpToString($var, 'html');
			if (count($result) > 1) $result[] = '<hr />';
		}

		return implode("\n", $result);
	}

	public function __dumpText() {

		$result = array();
		foreach($this->vars as $key => $var) {
			if ($result) $result[] = str_repeat(Octopus_Log_Listener_Console::CHAR_LIGHT_LINE, 80);
			$result[] = Octopus_Debug::dumpToString($var, 'text', true);
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
// class Octopus_Debug_Html_Exception
//
////////////////////////////////////////////////////////////////////////////////

class Octopus_Debug_Html_Exception {

	private $exception;

	public function __construct(Exception $ex) {
		$this->exception = $ex;
	}

	public function render() {

		$ex = $this->exception;
		$first = true;

		$result = array();

		do {

			if (!$first) {
				$result[] = '<hr class="octopus-debug-exception-sep" />';

				$result[] = '<h3 class="octopus-inner-exception">';
				$result[] = get_class($ex);

				$trace = Octopus_Debug::getNiceBacktrace($ex->getTrace());
				$line = Octopus_Debug::getMostRelevantTraceLine($trace, array(__FILE__));
				if ($line) {
					$result[] = "at {$line['nice_file']}, line {$line['line']}";
				}

				$result[] = '</h3>';
			}
			$first = false;

			$result[] = htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8');

			$ex = $ex->getPrevious();
		} while($ex);

		return implode(' ', $result);
	}

	public function __toString() {
		return $this->render();
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
        return Octopus_Debug::dumpToString($var, 'text');
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
// class Octopus_Log_Listener_Html_Message
//
////////////////////////////////////////////////////////////////////////////////

/**
 * Helper used to represent a single message being rendered as HTML.
 */
class Octopus_Log_Listener_Html_Message {

	public $title = '';

	private $sections = array();
	private $footer = array();
	private $classes = array();
	private $log;
	private $level;
	private static $nextID = 0;

	/**
	 * Creates a new block with the given title.
	 * @param String $title Title for this debug
	 */
	public function __construct($log, $level) {
		$this->log = $log;
		$this->level = $level;

		$this->addClass('octopus-debug-level-' . strtolower(Octopus_Log::getLevelName($level)));
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

		$log = htmlspecialchars($this->log, ENT_QUOTES, 'UTF-8');
		$level = Octopus_Log::getLevelName($this->level);

		$title = trim($this->title);
		$loc = '';
		if (!$title) {
			$line = self::getMostRelevantTraceLine();
			if ($line) {
				$file = $line['nice_file'] ? $line['nice_file'] : basename($line['file']);
				$title = "{$file}, line {$line['line']}";
				$loc = $line['file'];
			}
		}
		$loc = htmlspecialchars($loc, ENT_QUOTES, 'UTF-8');
		$title = "<h1 title=\"{$loc}\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';

		$header = <<<END
<div class="octopus-debug-header">
{$title}
	<h2>
		<span class="octopus-debug-log" title="Log name">{$log}</span>
		<span class="octopus-debug-log-level" title="Log level">{$level}</span>
	</h2>
</div>
END;
		if ($this->title) {
			$title = '<h1 class="octopus-debug-title">' . htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') . '</h1>';
		} else {
			$classes['octopus-debug-no-title'] = true;
		}

		$title .= <<<END
<h2 class="octopus-debug-subtitle">{$this->log}</h2>
END;

		$nav = array();
		$content = array();

		foreach($this->sections as $section) {
			if (empty($nav)) {
				$liClass = ' class="octopus-debug-active"';
				$contentClass = '';
			} else {
				$liClass = '';
				$contentClass = ' octopus-debug-hidden';
			}

			$id = self::getNextID();

			$nav[] = <<<END
<li{$liClass}><a href="#{$id}">{$section['name']}</a></li>
END;


			$content[] = <<<END
<div id="{$id}" class="octopus-debug-content-item{$contentClass}">
{$section['content']}
</div>
END;
		}

		$nav = implode("\n", $nav);
		$content = implode("\n", $content);

		$nav = <<<END
<ul class="octopus-debug-nav">
{$nav}
</ul>
END;

		$content = <<<END
<div class="octopus-debug-content">
{$content}
</div>
END;

		//$classes['octopus-debug-level-error'] = true;

		$classes = implode(' ', array_keys($classes));

		$html = <<<END
<div class="octopus-debug $classes">
	<div class="octopus-debug-inner">
		{$header}
		{$content}
		{$nav}
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

    private static function getMostRelevantTraceLine() {

    	foreach(Octopus_Debug::getNiceBacktrace() as $line) {

    		if (empty($line['file'])) {
    			continue;
    		}

    		if ($line['file'] === __FILE__) {
    			continue;
    		}

    		if (preg_match('/Response\.php$/', $line['file'])) {
    			continue;
    		}

    		return $line;


    	}

    }

    private static function getNextID() {
    	return 'octopus-debug-item-' . (self::$nextID++);
    }

}

////////////////////////////////////////////////////////////////////////////////
//
// Octopus Debug JS
//
////////////////////////////////////////////////////////////////////////////////

Octopus_Log_Listener_Html::$js = "
<script type=\"text/javascript\">

(function(window, undefined) {

/*******************************************************************************
 *
 * jQuery
 *
 ******************************************************************************/

var jQuery = %%JQUERY%%;

if (!jQuery) {
	return;
}

/*******************************************************************************
 *
 * Actual code
 *
 ******************************************************************************/

jQuery(function() {

	jQuery(document)
		.on('click', '.octopus-debug-nav a', function(evt) {

			evt.preventDefault();

			var link = jQuery(this),
				id = link.attr('href').replace(/^.*#/, '#'),
				target = jQuery(id);

			target
				.siblings().addClass('octopus-debug-hidden').end()
				.removeClass('octopus-debug-hidden');

			link.parent()
				.siblings().removeClass('octopus-debug-active').end()
				.addClass('octopus-debug-active');

		})

})

})(window);
</script>
";