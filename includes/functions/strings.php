<?php

    /**
     * Converts an input string to a camelCased string.
     */
    function camel_case($s, $initialCap = false) {

        $s = trim($s);
        $s = preg_replace('/([\p{Ll}\d])(\p{Lu})/', '$1_$2', $s);

        $parts = preg_split('/[-_\s]+/', $s);

        $result = '';
        foreach($parts as $part) {
            if ($result || $initialCap) {
                $result .= strtoupper(substr($part, 0, 1)) . strtolower(substr($part, 1));
            } else {
                $result .= strtolower($part);
            }
        }

        return $result;
    }
    function _camel_case_helper($matches) {

        $prev = $matches[1];
        $next = $matches[2];

        return $prev . strtoupper($next);
    }

    /**
     * Cleans up text from unknown sources (like ie from word).
     */
    function decruftify($s) {

        /*
         * Things this could do:
         *  - normalize whitespace characters
         *  - Clean up curly quotes
         *  - Return nice UTF8
         */

    }

    function end_in($end, $str) {

        $len = strlen($str);

        if ($len > 0 && substr($str, $len - strlen($end)) != $end) {
            $str .= $end;
        }

        return $str;

    }

    /**
     * Alias for htmlspecialchars.
     * @param mixed Individual strings to escape and concatenate.
     * @return string All arguments, escaped and concatenated.
     */
    function h(/* as many as you want! */) {

        $count = func_num_args();

        switch($count) {

            case 0: return '';

            case 1:
                $arg = func_get_arg(0);
                return htmlspecialchars($arg);

        }

        $result = '';
        for($i = 0; $i < $count; $i++) {
            $arg = func_get_arg($i);
            $result .= htmlspecialchars($arg);
        }

        return $result;
    }

    /**
     * Converts a 'COMPUTERY_STRING' into a 'Computery String'.
     */
    function humanize($str, $titleCaps = true) {

        $str = strtolower(trim(str_replace('_', ' ', $str)));
        if (!$str) return $str;

        if ($titleCaps) {
            return ucwords($str);
        } else {
            return strtoupper(substr($str, 0, 1)) . substr($str, 1);
        }
    }

    /**
     * Examines a string and tells you if it looks like a regular expression.
     * For our purposes, regexes look like this:
     *
     * /whatever/i
     *
     * @param $s String You know, the one to check whether it's a regex
     * @return Mixed if $s is a valid regex, returns an array with keys 'pattern' and 'flag'.
     * Otherwise returns false.
     */
    function parse_regex($s, $boundaryChars = '\/#-') {

        $pattern = '/^([' . $boundaryChars . '])(.*)\1([i]*)/i';

        if (preg_match($pattern, $s, $m)) {
            $result = array('pattern' => $m[2], 'flags' => strtolower($m[3]));
            return $result;
        }

        return false;
    }

    /**
     * Turns URLs in $s into hyperlinks.
     */
    function linkify($s) {
        // TODO implement
    }

    /**
     * Given an arbitrary SQL string, normalizes it (compacts whitespace,
     * removes newlines) so it can be compared, e.g. for testing.
     */
    function normalize_sql($sql, $params = null) {

        // TODO: move to a tests.php file that is only included w/ tests?
        // TODO: actually watch out for whitespace in fields

        $sql = preg_replace('/\s+/m', ' ', trim($sql));
        if (empty($params)) return $sql;

        //TODO: be smarter about ? characters in
        $pos = 0;
        while(count($params) && ($pos = strpos($sql, '?', $pos)) !== false) {

            $p = array_shift($params);
            if (!is_numeric($p)) {
                $p = "'" . str_replace("'", "\\'", $p) . "'";
            }

            $sql = substr($sql,0,$pos) . $p . substr($sql,$pos + 1);
            $pos += strlen($p);
        }


        return $sql;
    }

    /**
     * Pluralizes a singular noun. Doesn't try too hard.
     */
    function pluralize($x) {

        if (substr($x, -1) == 's') {
            return $x;
        }

        $x = preg_replace('/y$/i', 'ies', $x, 1, $count);
        if ($count) return $x;

        return $x . 's';
    }

    /**
     * Converts a plural back into a singular noun.
     */
    function singularize($x) {

        if (substr($x, -1) != 's') {
            return $x;
        }

        $x = preg_replace('/ies$/i', 'y', $x, 1, $count);
        if ($count) return $x;

        return substr($x, 0, strlen($x) - 1);
    }

    function start_in($start, $str) {

        if (strncmp($start, $str, strlen($start)) != 0) {
            return $start . $str;
        } else {
            return $str;
        }
    }

    /**
     * Converts an arbitrary string into a valid css class;
     */
    function to_css_class($x) {

        $x = trim($x);
        $x = preg_replace('/[^a-z0-9-]/i', '-', $x);
        $x = preg_replace('/-{2,}/', '-', $x);
        $x = preg_replace('/^([^a-z-])/i', '-$1', $x);

        return $x;
    }

    /**
     * Takes a PHP regex pattern and converts it for use in mysql queries.
     */
    function to_mysql_regex($pattern) {
        $pattern = str_replace('\\(', '[(]', $pattern);
        $pattern = str_replace('\\)', '[)]', $pattern);
        return $pattern;
    }

    /**
     * Converts an arbitrary string into a valid slug.
     * @param $x String Text you want to slugify.
     * @param $counter Number A numeric index to append to the end (for when
     * you are trying to find a unique slug. If $counter is greater than 1,
     * it will be appended to the resulting slug.
     */
    function to_slug($x, $counter = null) {

        $x = strtolower(trim($x));
        $x = str_replace('&', ' and ', $x);
        $x = preg_replace('/[\'"\(\)]/', '', $x);
        $x = preg_replace('/[^a-z0-9-]/i', '-', $x);
        $x = preg_replace('/-{2,}/', '-', $x);
        $x = trim($x, '-');

        if ($counter > 1) {
            $x .= '-' . $counter;
        }

        return $x;
    }

    /**
     * Converts a camelCased string to an underscore_separated_string
     */
    function underscore($s) {

        $s = preg_replace('/([a-z])([A-Z]+)/', '$1_$2', $s);
        $s = preg_replace('/\s+/', '_', $s);
        return strtolower($s);

    }


?>
