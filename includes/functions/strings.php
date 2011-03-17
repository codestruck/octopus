<?php

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

        $args = func_get_args();
        $result = '';
        foreach($args as $arg) {
            $result .= htmlspecialchars($arg);
        }

        return $result;
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
    function normalize_sql($sql) {

        // TODO: move to a tests.php file that is only included w/ tests?
        // TODO: actually watch out for whitespace in fields

        $sql = preg_replace('/\s+/m', ' ', trim($sql));

        return $sql;
    }

    /**
     * Pluralizes a singular noun. Doesn't try too hard.
     */
    function pluralize($x) {

        $x = preg_replace('/y$/i', 'ies', $x, 1, $count);
        if ($count) return $x;

        return $x . 's';
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
        $x = preg_replace('/-{2,}/', '-');
        $x = preg_replace('/^([^a-z-])/i', '-$1', $x);

        return $x;
    }

    /**
     * Converts an arbitrary string into a valid slug.
     */
    function to_slug($x) {

        $x = strtolower(trim($x));
        $x = str_replace('&', ' and ', $x);
        $x = preg_replace('/[\'"\(\)]/', '', $x);
        $x = preg_replace('/[^a-z0-9-]/i', '-', $x);
        $x = preg_replace('/-{2,}/', '-', $x);
        return trim($x, '-');
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
