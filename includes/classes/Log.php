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
	public static function addListener($log, $minLevel, $func = null) {

		if (is_numeric($log) && $log >= self::LEVEL_DEBUG && $log <= self::LEVEL_FATAL) {
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
		return self::doShortcut(Octopus_Log::LEVEL_DEBUG, func_get_args());
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
		return self::doShortcut(Octopus_Log::LEVEL_ERROR, func_get_args());
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
		return self::doShortcut(Octopus_Log::LEVEL_FATAL, func_get_args());
	}

	/**
	 * Formats a log message for display.
	 */
	public static function formatMessage($message) {
		return trim($message);
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
		return self::doShortcut(Octopus_Log::LEVEL_INFO, func_get_args());
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
		return self::doShortcut(Octopus_Log::LEVEL_WARN, func_get_args());
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

		self::write($log, Octopus_Log::LEVEL_DEBUG, $message);


	}

}

?>