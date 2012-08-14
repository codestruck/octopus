<?php

if (!function_exists('dump_r')) {

    /**
     * @deprecated Is this in use anywhere?
     * @return String The results of var_dump for $var.
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
     */
    function debug_var($var) {
        return Octopus_Debug::dumpToString($var, 'text');
    }

    /**
     * @see Octopus_Debug::enableDumping
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
     */
    function enable_dump_r($enable = true) {
    	Octopus_Debug::enableDumping($enable);
    }

    /**
     * @see Octopus_Debug::disableDumping
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
     */
    function disable_dump_r() {
    	Octopus_Debug::disableDumping();
    }

    /**
     * Outputs the arguments passed to it along w/ debugging info.
     * @param mixed Any arguments you want dumped.
     * @return Mixed The first argument passed in.
     * @see Octopus_Debug::dump
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
     */
    function dump_r() {
    	$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
    	return call_user_func_array(array('Octopus_Debug', 'dump'), $args);
    }

    /**
     * Calls dump_r and then exit().
     * @param mixed Any values you want displayed.
     * @return If dumping is disabled, returns the first argument passed in.
     * @see Octopus_Debug::dumpAndExit()
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
     */
    function dump_x() {
    	$args = func_get_args(); // PHP 5.2 craps out if you try to pass func_get_args() directly
    	return call_user_func_array(array('Octopus_Debug', 'dumpAndExit'), $args);
    }

    /**
     * Prints out a slightly saner backtrace to stderr.
     * @see Octopus_Debug::printBacktrace
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
     */
    function print_backtrace($limit = 0) {
    	Octopus_Debug::printBacktrace($limit);
    }

} // if (!function_exists('dump_r))