<?php
/**
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
class Octopus_Debug {

    private static $configured = false;
    private static $environment = null;
    private static $dumpEnabled = true;
    private static $redirectsEnabled = true;
    private static $inDump = false;

    /**
     * Sets up the debugging environment if it has not already been set up.
     * @param Array $options Options, including directory locations etc.
     * @param Boolean $reset Whether to reset the environment before
     * configuring.
     */
    public static function configure($options = array(), $reset = true) {

    	if (self::$configured && empty($options)) {
    		return;
    	}

    	if ($reset) self::reset();
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

    	if (self::isCommandLineEnvironment()) {
    		$console = new Octopus_Log_Listener_Console();
    		$console->stackTraceLines = -1;
    		Octopus_Log::addListener('errors', $console);
    		Octopus_Log::addListener('dump', $console);
    	}

		if (!empty($options['LIVE']) || self::isLiveEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::WARN, $fileListener);
			}

		} else if (!empty($options['STAGING']) || self::isStagingEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::DEBUG, $fileListener);
			}

		} else if (!empty($options['DEV']) || self::isDevEnvironment()) {

			if ($fileListener) {
				Octopus_Log::addListener(Octopus_Log::DEBUG, $fileListener);
			}

			if (!self::isCommandLineEnvironment()) {
				Octopus_Log::addListener(new Octopus_Log_Listener_Console());
			}

			if (self::shouldUseHtmlLogging()) {
				Octopus_Log::addListener(new Octopus_Log_Listener_Html());
			}
		}

		Octopus_Log::registerExceptionHandler();
		Octopus_Log::registerErrorHandler();
    }

	/**
	 * Writes a DEBUG-level message indicating a function has been deprecated.
	 * Takes the previous frame from the call stack as its reference.
	 */
	public static function deprecated() {

		// TODO: As of 5.4, debug_backtrace supports a second argument which
		// indicates the # of frames to return. We only need the previous frame
		// here.

		if (defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		} else {
			$trace = debug_backtrace(false);
		}

		$line = $trace[1];
		$class = isset($line['class']) ? $line['class'] : '';
		$func = $line['function'];

		$func = $class . ($class ? '::' : '') . $func;

		return Octopus_Log::write('debug', Octopus_Log::DEBUG, "$func is deprecated.");
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

    	if (self::$inDump) {
    		// Avoid infinite recursion when debugging
    		return $x;
    	}

    	self::$inDump = true;

    	// Ensure all log listeners are in place.
    	self::configure();

    	// Send a special Octopus_Debug_DumpedVars object through the logging
    	// infrastructure to the 'dump' log. This gets picked up by the file,
    	// html, and stderr listeners and rendered appropriately.

    	$args = func_get_args();
    	$vars = new Octopus_Debug_DumpedVars($args);
    	Octopus_Log::debug('dump', $vars);

    	self::$inDump = false;

    	// This is kind of a hack. When we are calling dump_r from smarty
    	// templates, we don't want to return the value because it will get
    	// rendered.
    	$lines = self::getMostRelevantTraceLines(1);
    	$line = array_shift($lines);

    	if ($line && preg_match('/\.tpl\.php/', $line['file'])) {
    		return;
    	}

    	return $x;
    }

    /**
     * Calls ::dump() and then exits.
     */
    public static function dumpAndExit($x) {

    	if (!self::$dumpEnabled || !self::isDevEnvironment()) {
    		return $x;
    	}

		$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
    	call_user_func_array(array('Octopus_Debug', 'dump'), $args);
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
     * @param Number $count The number of lines to return (max).
     * @param Array|null $trace The trace from which to work.
     * @param Array $filesToIgnore If a line from the trace originates from
     * any file in here, it is regarded as irrelevant. Note that this ALWAYS
     * includes octopus/includes/functions/debug.php
     * @return Array The top $count most relevant lines from $trace. Lines
     * originating from any files in $filesToIgnore are ignored until a line
     * in another file is found, after which $count lines are returned, whether
     * or not the associated files appear in $filesToIgnore.
     */
    public static function getMostRelevantTraceLines($count, $trace = null, $filesToIgnore = array()) {

    	if ($trace === null) {
    		$trace = self::getNiceBacktrace();
    	}

    	$result = array();

		// Find the first line of the stack trace that is not in this file
		// to display.
		while(count($result) < $count && $traceLine = array_shift($trace)) {

			// Skip closures
			if (empty($traceLine['file'])) {
				continue;
			}

			// Skip stuff in this file
			if ($traceLine['file'] === __FILE__) {
				continue;
			}

			$bannedFilesRx = '#octopus/includes/(functions|classes)/([dD]ebug|Log)#';
			if (preg_match($bannedFilesRx, $traceLine['file'])) {
				continue;
			}

			// The log call from Octopus_App::errorHandler is not relevant
			if (preg_match('#octopus/includes/classes/App\.php#', $traceLine['file']) && isset($traceLine['function']) && $traceLine['function'] === 'errorHandler') {
				continue;
			}

			$result[] = $traceLine;
		}

		return $result;
	}

    /**
     * @param  Mixed $bt The backtrace to format. If not provided, a new
     * backtrace is generated.
     * @return Array A backtrace array with keys normalized and ROOT_DIR
     * stripped off any file names.
     */
    public static function getNiceBacktrace($bt = null) {

        if ($bt === null) {
            $bt = debug_backtrace(false);
            array_shift($bt); // remove this call
        }

        $result = array();
        $rootDir = trim(self::getOption('ROOT_DIR'));

        if (!$rootDir) {
        	$rootDir = dirname(dirname(dirname(dirname(__FILE__))));
        }

        if ($rootDir) {
        	$rootDir = rtrim($rootDir, '/') . '/';
        }

        $rootDirLen = strlen($rootDir);

        $filtered = array();
        foreach($bt as $index => $item) {
        	if (empty($item['class']) && !empty($item['function']) && preg_match('/^call_user_func(_array)?$/', $item['function'])) {
        		// who cares about these?
        		//continue;
        	}
        	$filtered[] = $item;
        }
        $bt = $filtered;

        $base = array(
        	'function' => '',
        	'file' => '',
        	'line' => '',
        	'type' => '',
        	'octopus_type' => '',
        	'class' => '',
        	'scope_function' => '',
        	'scope_class' => '',
        );

        foreach($bt as $index => $item) {

        	$nextItem = ($index < (count($bt) - 1)) ? $bt[$index + 1] : $base;
        	$niceItem = $base;

        	foreach(array('function', 'file', 'line', 'class', 'type') as $key) {
        		if (isset($item[$key])) {
        			$niceItem[$key] = $item[$key];
        		}
        	}

        	if ($nextItem['function']) {
        		if (isset($nextItem['class'])) {
        			$niceItem['scope_function'] = $nextItem['class'] . '::' . $nextItem['function'];
        			$niceItem['scope_class'] = $nextItem['class'];
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

            // Also, remove everything before '/octopus/' or '/PHPUnit/'
            // This helps keep nice_file nice even when octopus dir is outside
            // of root dir (for example, in tests where it is symlinked in).
            // When PHPUnit is installed via homebrew, its path can be long and
            // make output wrap onto multiple lines. It looks bad.
            $niceItem['nice_file'] = preg_replace('#.*/(octopus|PHPUnit/Framework|PHPUnit/TextUI)/#', '$1/', $niceItem['nice_file']);

            if (preg_match('~^octopus/~', $niceItem['nice_file'])) {
            	// This is an octopus system file
            	$niceItem['octopus_type'] = 'octopus';
            } else if (preg_match('~^_?private/smarty/~', $niceItem['nice_file'])) {
            	// This is a smarty temp file
            	$niceItem['octopus_type'] = 'smarty';
            }

            $result[] = $niceItem;
        }

        // Filter out call_user_func items
        $filtered = array();
        foreach($result as $index => $item) {

        	if (!isset($result[$index + 1])) {
        		$filtered[] = $item;
        		break;
        	}

        	$nextItem = $result[$index + 1];
        	if ($item['scope_class'] === '' && ($item['scope_function'] === 'call_user_func' || $item['scope_function'] === 'call_user_func_array')) {
        		// this is a bs call_user_func item, so merge with the next one
        		unset($result[$index + 1]);
        		$item['scope_function'] = '';
        		foreach($item as $key => $value) {
        			if ($value === '') {
        				$item[$key] = $nextItem[$key];
        			}
        		}
        	}

        	$filtered[] = $item;

        }

        return $filtered;

    }

    /**
     * @return boolean Whether we are currently running on the command line.
     */
    public static function isCommandLineEnvironment() {
		return php_sapi_name() === 'cli' &&
   		       empty($_SERVER['HTTP_USER_AGENT']);
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

        $bt = debug_backtrace(false);

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

   			!self::isCommandLineEnvironment() &&

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

	        // NOTE: 5.2 doesn't support getPrevious
	        if (method_exists($ex, 'getPrevious')) {
    			$ex = $ex->getPrevious();
    		} else {
    			$ex = null;
    		}

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

    	if ($x && is_int($x)) {

    		$result .=
    			"\n\t" .
    			sprintf('octal:        0%o', $x) .
    			"\n\t" .
    			sprintf('hex:          0x%X', $x);

    	}

    	if ($x && self::looksLikeTimestamp($x)) {
    		$result .=
    			"\n\t" .
    			        "timestamp:    " . date('r', $x);
    	}

    	if ($x && self::looksLikeFilePermissions($x)) {
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

            				$niceSize = $threshold ? $size / $threshold : $size;
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
