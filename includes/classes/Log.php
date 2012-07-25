<?php

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
	const DEBUG = 5;

	/**
	 * Logging level for informational messages.
	 */
	const INFO = 4;

	/**
	 * Logging level for warnings.
	 */
	const WARN = 3;

	/**
	 * Alias for ::WARN
	 */
	const WARNING = 3;

	/**
	 * Logging level for errors.
	 */
	const ERROR = 2;

	/**
	 * Logging level for fatal errors.
	 */
	const FATAL = 1;

	/**
	 * Log level used for null logging. Messages logged at this level will
	 * never get logged.
	 */
	const NONE = 0;

	private static $listeners = array();
	private static $minLevel = self::NONE;
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
			// Allow ::addListener($func);
			$func = $log;
			$minLevel = self::DEBUG;
			$log = true;
		} else if (is_numeric($log) && $log > self::NONE) {
			// Allow ::addListener($minLevel, $func)
			$func = $minLevel;
			$minLevel = $log;
			$log = true;
		} else if (is_string($log) && !is_numeric($minLevel) && $func === null) {
			// Allow ::addListener($log, $func)
			$func = $minLevel;
			$minLevel = self::DEBUG;
		}

		if ($minLevel > self::$minLevel) {
			self::$minLevel = $minLevel;
		}

		if (is_object($func) && !($func instanceof Closure)) {
			$func = array($func, 'write');
		}

		self::$listeners[] = compact('log', 'minLevel', 'func');

	}

	/**
	 * Comparator function for items formatted using ::formatJson.
	 */
	public static function compareLogItems($x, $y) {

		if (!($x || $y)) {
			return 0;
		} else if ($x && !$y) {
			return 1;
		} else if ($y && !$x) {
			return -1;
		}

		$xTime = $x['time'];
		$yTime = $y['time'];

		$result = $xTime - $yTime;
		if ($result) return $result;

		$xIndex = isset($x['index']) ? $x['index'] : 0;
		$yIndex = isset($y['index']) ? $y['index'] : 0;

		return $xIndex - $yIndex;
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
		if (self::$minLevel >= self::DEBUG) {
			$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
			return self::doShortcut(Octopus_Log::DEBUG, $args);
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
		if (self::$minLevel >= self::ERROR) {
			$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
			return self::doShortcut(Octopus_Log::ERROR, $args);
		}
	}

	/**
	 * A custom PHP error handler function that reroutes PHP errors, warnings,
	 * and noticies into the logging system and suppresses them.
	 * To use this in your app, call ::registerErrorHandler()
	 * Octopus apps use this error handler automatically.
	 */
	public static function errorHandler($errno, $errstr, $errfile, $errline, $errcontext) {

		$errorReporting = error_reporting();

		if ($errorReporting === 0) {
			// This was a suppressed error
			return true;
		}

    	if (!($errorReporting & $errno)) {

			// This error should not be shown -- either it is out of the range
			// of error_reporting or the error suppression operator was used
			return false;
		}

		$level = self::getLogLevelForPhpError($errno);

		if ($level === Octopus_Log::NONE) {
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
		if (self::$minLevel >= self::FATAL) {
			$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
			return self::doShortcut(Octopus_Log::FATAL, $args);
		}
	}

	/**
	 * Formats a log entry as JSON.
	 * @param  String $id
	 * @param  Mixed $message   	Message being logged
	 * @param  String $log       	Name of the log being written
	 * @param  Number $level     	Level of the message
	 * @param  Number $timestamp 	Timestamp for the entry
	 * @param  Array  $stack		Stack trace array.
	 * @return String JSON for the log message.
	 */
	public static function formatJson($id, $message, $log, $level, $timestamp, $stack, $index = 0) {

		$message = array(
			'id' => $id,
			'index' => $index,
			'time' => $timestamp,
			'log' => $log,
			'level' => self::getLevelName($level),
			'message' => $message,
			'trace' => self::formatStackTrace($stack),

		);

		return @json_encode($message);
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

		if ($level <= self::NONE) {
			return '';
		}

		if (!self::$namesByLevel) {

			self::$namesByLevel = array(
				self::DEBUG => 'DEBUG',
				self::INFO => 'INFO',
				self::WARN => 'WARN',
				self::ERROR => 'ERROR',
				self::FATAL => 'FATAL'
			);

		}

		if (isset(self::$namesByLevel[$level])) {
			return self::$namesByLevel[$level];
		}

		// DEBUG1, DEBUG2, DEBUG3, etc.
		return 'DEBUG' . ($level - self::DEBUG);
	}

	/**
	 * @return Array All logging levels.
	 */
	public static function getLevels() {

		if (!self::$levelsByName) {

			self::$levelsByName = array(
				'DEBUG' => self::DEBUG,
				'INFO' => self::INFO,
				'WARN' => self::WARN,
				'ERROR' => self::ERROR,
				'FATAL' => self::FATAL,
			);
		}

		return self::$levelsByName;
	}

	/**
	 * @return Number The minimum level at which a message needs to be logged
	 * in order to actually do something.
	 */
	public static function getThreshold() {
		return self::$minLevel;
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
		if (self::$minLevel >= self::INFO) {
			$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
			return self::doShortcut(Octopus_Log::INFO, $args);
		}
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isDebugEnabled() {
		return self::$minLevel <= self::DEBUG;
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
		return self::$minLevel <= self::ERROR;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isFatalEnabled() {
		return self::$minLevel <= self::FATAL;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isInfoEnabled() {
		return self::$minLevel <= self::INFO;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isWarnEnabled() {
		return self::$minLevel <= self::WARN;
	}

	/**
	 * @see ::isEnabled
	 * @return boolean
	 */
	public static function isWarningEnabled() {
		return self::$minLevel <= self::WARN;
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
	 * ::ERROR.
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
		self::$minLevel = self::NONE;
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
		if (self::$minLevel >= self::WARN) {
			$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
			return self::doShortcut(Octopus_Log::WARN, $args);
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

		if ($level > self::$minLevel || $level <= self::NONE) {
			return;
		}

		$id = uniqid($log);

		self::$writeCount++;

		foreach(self::$listeners as $listener) {
			if ($level > $listener['minLevel']) {
				continue;
			}
			if ($listener['log'] === true || $listener['log'] === $log) {
				call_user_func($listener['func'], $id, $message, $log, $level, self::$writeCount);
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

		$hasItems = false;

		foreach($trace as $item) {

			if (!$hasItems && isset($item['file']) && $item['file'] === __FILE__) {
				continue;
			}

			unset($item['args']);

			$result[] = $item;
			$hasItems = true;

		}

		return $result;

	}

	private static function getLogLevelForPhpError($err) {

		switch($err) {

			case E_DEPRECATED:
			case E_USER_DEPRECATED:
			case E_STRICT:
				return Octopus_Log::DEBUG;

			case E_NOTICE:
			case E_USER_NOTICE:
				return Octopus_Log::INFO;

			case E_WARNING:
			case E_USER_WARNING:
				return Octopus_Log::WARN;

			case E_ERROR:
			case E_USER_ERROR:
				return Octopus_Log::ERROR;

			default:
				return Octopus_Log::NONE;

		}

	}

	private static function getNextIDForLog($log) {
		return uniqid($log);
	}

}