<?php

define("FUZZY_ONE_DAY", 86400);

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

?>
