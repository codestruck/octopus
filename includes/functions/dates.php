<?php

define("FUZZY_ONE_DAY", 86400);

if (!function_exists('fuzzy_time')) {

	/**
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
	 */
    function fuzzy_time($time = 0)
    {
        if($time == 0)
        {
            return '-';
        }

        if(!$time)
        {
            return FALSE;
        }

        if (!is_numeric($time)) {
            $time = strtotime($time);
        }

        $now = time();

        // sod = start of day
        $sodTime = mktime(0, 0, 0, date("m", $time), date("d", $time), date("Y", $time));
        $sodNow  = mktime(0, 0, 0, date("m", $now), date("d", $now), date("Y", $now));

        $diff = abs($sodNow - $sodTime);

        // check 'today'
        if ($sodNow == $sodTime)
        {
            return "Today at ".date("g:ia", $time);
        }

        // check 'yesterday'
        if ($diff <= FUZZY_ONE_DAY)
        {
            if ($sodTime > $sodNow) {
                return "Tomorrow at ".date("g:ia", $time);
            } else {
                return "Yesterday at ".date("g:ia", $time);
            }
        }

        // give a day name if within the last 5 days
        if($diff <= (FUZZY_ONE_DAY * 5))
        {
            return date("D \a\\t g:ia", $time);
        }

        // miss off the year if it's this year
        if(date("Y", $now) == date("Y", $time))
        {
            return date("M j \a\\t g:ia", $time);
        }

        // return the date as normal
        return date("M j, Y \a\\t g:ia", $time);
    }
}

if (!function_exists('fuzzy_date')) {

	/**
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
	 */
    function fuzzy_date($time = 0)
    {
        $text = fuzzy_time($time);

        if(!$text)
        {
            return FALSE;
        }

        $pieces = explode(" at ", $text);

        return $pieces[0];
    }
}

/**
 * @return Number the UNIX timestamp for 12:00:00 AM on the given date.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function get_day($date) {

    if (!is_numeric($date)) {
        $date = strtotime($date);
    }

    if (!$date) return 0;

    $date = date('Y-m-d', $date);

    return strtotime($date);
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function add_days($date, $days) {
    $date = get_day($date);
    // NOTE: strtotime handles DST etc internally, so rely on it to do the math
    return strtotime(date("Y-m-d", $date) . ($days >= 0 ? '+' : '-') . abs($days) . ' day');
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function get_time_span_parts($seconds) {
    $counts = array(
        'days' => 86400,
        'hours' => 3600,
        'minutes' => 60,
    );
    $parts = array();

    $microseconds = $seconds - floor($seconds);

    foreach($counts as $name => $count) {
        $parts[$name] = floor($seconds / $count);
        $seconds -= ($parts[$name] * $count);
    }
    $parts['seconds'] = $seconds;
    $parts['microseconds'] = $microseconds * 1000000;

    return $parts;
}

/**
 * Given a length of time in seconds (or microseconds)
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function format_time_span($seconds, $fuzzy = false) {

    $parts = get_time_span_parts($seconds);

    // TODO: fuzzy

    $result = '';
    foreach($parts as $name => $count) {

        $resultLen = strlen($result);

        if (!$resultLen && $name === 'seconds') {
            $result = '00';
            $resultLen = 2;
        } else if (!($resultLen || $count)) {
            continue;
        }

        if ($name === 'microseconds') {

            if ($count) {
                if ($resultLen) $result .= ':';
                $m = round($count / 1000000.0, 3) * 1000;
                $result .= sprintf('%03d', $m);
            }

        } else {
            if ($resultLen) $result .= ':';
            $result .= sprintf('%02d', $count);
        }

    }

    return $result;

}

