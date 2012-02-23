<?php

    /**
     * Converts an input string to a camelCased string.
     */
    function camel_case($s, $initialCapital = false) {

        $s = trim($s);
        if (!defined('PREG_BAD_UTF8_OFFSET_ERROR')) {
           $s = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $s);
        } else {
            $s = preg_replace('/([\p{Ll}\d])(\p{Lu})/', '$1_$2', $s);
        }

        $parts = preg_split('/[-_\s]+/', $s);

        $result = '';
        foreach($parts as $part) {
            if ($result || $initialCapital) {
                $result .= strtoupper(substr($part, 0, 1)) . strtolower(substr($part, 1));
            } else {
                $result .= strtolower($part);
            }
        }

        return $result;
    }

    /**
     * Alias for camel_case($s, true) for use in e.g.
     * array_map.
     */
    function camel_case_with_initial_capital($s) {
        return camel_case($s, true);
    }

    function _camel_case_helper($matches) {

        $prev = $matches[1];
        $next = $matches[2];

        return $prev . strtoupper($next);
    }

    /**
     * Capitalizes the first word in sentences.
     */
    function capitalize_sentences($str) {

        $str = strtolower($str);
        return preg_replace_callback('/(^|\.\s*)([a-z])/', '_capitalize_sentences_helper');

    }
    function _capitalize_sentences_helper($matches) {
        return $matches[1] . strtoupper($matches[2]);
    }

    /**
     * Converts a StringOfSomeKind to a string-of-some-kind
     */
    function dashed($s) {
        return underscore($s, '-');
    }

    function end_in($end, $str) {

        $len = strlen($str);

        if ($len > 0 && substr($str, $len - strlen($end)) != $end) {
            $str .= $end;
        }

        return $str;

    }

    /**
     * @param $x String Longer string.
     * @param $x String String that $x might end with.
     * @param $ignoreCase bool Whether to ignore case.
     * @param $remainder String Gets set to the portion of $x after $y, if
     * $x ends with $y;
     * @return bool Whether $x ends with $y;
     */
    function ends_with($x, $y, $ignoreCase = false, &$remainder = null) {

        $xLen = strlen($x);
        $yLen = strlen($y);

        if ($yLen > $xLen) {
            return false;
        }

        if ($yLen == 0) {
            $remainder = $x;
            return true;
        }

        $end = substr($x, $xLen - $yLen);

        if ($ignoreCase) {
            $success = strcasecmp($end, $y) == 0;
        } else {
            $success = strcmp($end, $y) == 0;
        }

        if (!$success) {
            $remainder = null;
            return false;
        }

        $remainder = substr($x, 0, $xLen - $yLen);
        return true;
    }

    /**
     * Converts a glob expression to a regular expression.
     */
    function glob_to_regex($glob) {

        $glob = str_replace('.', '\.', $glob);
        $glob = str_replace('+', '\+', $glob);
        $glob = str_replace('*', '.+', $glob);
        $glob = str_replace('?', '.', $glob);

        return $glob ? "^$glob$" : $glob;
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
                return htmlspecialchars($arg, ENT_QUOTES, 'UTF-8');

        }

        $result = '';
        for($i = 0; $i < $count; $i++) {
            $arg = func_get_arg($i);
            $result .= htmlspecialchars($arg, ENT_QUOTES, 'UTF-8');
        }

        return $result;
    }

    /**
     * Converts a 'COMPUTERY_STRING' into a 'Computery String'.
     */
    function humanize($str, $titleCaps = true) {

        $str = strtolower(trim(str_replace('_', ' ', $str)));
        if (!$str) return $str;

        $str = preg_replace('/\[\s*\]$/', '', $str);

        if ($titleCaps) {
            return ucwords($str);
        } else {
            return strtoupper(substr($str, 0, 1)) . substr($str, 1);
        }
    }

    function is_email($input) {
        return !!parse_email($input);
    }

    /**
     * Given some input, tries to turn it into a well-formed http:// url.
     * @todo TEST
     */
    function normalize_url($url) {

        $url = trim($url);
        if (!$url) return $url;

        if (preg_match('~^([a-z0-9_-]+)://~i', $url)) {
            return $url;
        }

        return 'http://' . $url;
    }

    /**
     * Attempts to parse an email address out of some input.
     * @return Mixed false if unsuccessful.
     */
    function parse_email($input) {

        // TODO TEST

        $input = trim($input);
        if (!$input) return false;

        if (preg_match('/^\s*(([A-Z0-9._%+-]+)@([A-Z0-9.-]+\.[A-Z]{2,4}))\s*$/i', $input, $m)) {
            return array(
                'email' => $m[1],
                'user' => $m[2],
                'domain' => $m[3]
            );
        } else {
            return false;
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

        Octopus::loadExternal('simplehtmldom');

        $s = '<p>' . $s . '</p>';
        $dom = str_get_html($s);
        $regex = '/(\s|^)(https?:\/\/[^\s<]+?[^\.])(\.?(\s|$|\)))/ims';

        foreach ($dom->nodes as $el) {
            // dump_r($el->tag, $el->nodetype);

            if ($el->tag == 'text') {
                if (count($el->nodes) == 0 && $el->parent->tag != 'a') {
                    $el->innertext = preg_replace($regex, '$1<a href="$2">$2</a>$3', $el->innertext);
                }
            }

        }

        return substr($dom->save(), 3, -4);

    }

    /**
     * Converts urls to links AND adds rel and target
     */
    function linkify_external($s) {
        $s = linkify($s);

        $s = '<p>' . $s . '</p>';
        $dom = str_get_html($s);

        foreach ($dom->nodes as $el) {
            if ($el->tag == 'a') {
                $el->rel = 'nofollow';
                $el->target = '_blank';
            }
        }

        return substr($dom->save(), 3, -4);

    }

    function add_link_references($s, $domain, $data) {

        Octopus::loadExternal('simplehtmldom');

        $s = '<p>' . $s . '</p>';
        $dom = str_get_html($s);

        foreach ($dom->find('a') as $el) {

            if (preg_match('/https?:\/\/(www.)?' . preg_quote($domain, '/') . '(\/.*|$)/', $el->href)) {
                $el->href = u($el->href, $data, array('html' => true));
            }

        }

        return substr($dom->save(), 3, -4);

    }

    /**
     * Given an arbitrary SQL string, normalizes it (compacts whitespace,
     * removes newlines) so it can be compared, e.g. for testing.
     */
    function normalize_sql($sql, $params = null, $detectErrors = false) {

        // TODO: move to a tests.php file that is only included w/ tests?
        // TODO: actually watch out for whitespace in fields

        $sql = preg_replace('/\s+/m', ' ', trim($sql));
        if (empty($params)) return $sql;

        //TODO: be smarter about ? characters in
        $pos = 0;
        while(count($params) && ($pos = strpos($sql, '?', $pos)) !== false) {

            $p = array_shift($params);
            $p = "'" . str_replace("'", "\\'", $p) . "'";

            $sql = substr($sql,0,$pos) . $p . substr($sql,$pos + 1);
            $pos += strlen($p);
        }

        if ($detectErrors && count($params)) {
            throw new Octopus_Exception(count($params) . " parameter(s) left in params array: " . implode(',', $params));
        }

        return $sql;
    }

    /**
     * Pluralizes a singular noun. Doesn't try too hard.
     */
    function pluralize($x) {

        if (preg_match('/([aieou]?)(s?)s$/i', $x, $m)) {

            if ($m[1] && $m[2]) {
                // class -> classes
                return $x . 'es';
            } else {
                // already plural already
                return $x;
            }
        }

        $x = preg_replace('/([^aeiou])y$/i', '$1ies', $x, 1, $count);
        if ($count) return $x;

        $x = preg_replace('/x$/i', 'xes', $x, 1, $count);
        if ($count) return $x;

        return $x . 's';
    }

    /**
     * @return count then correctly pluralized word
     * @param $format Whether to pass the number through number_format.
     */
    function plural_count($array_or_number, $word, $format = true) {

        if (is_numeric($array_or_number)) {
            $count = $array_or_number;
        } else {
            $count = count($array_or_number);
        }

        $nice_count = $format ? number_format($count) : $count;

        if ($count != 1) {
            return $nice_count . ' ' . pluralize($word);
        } else {
            return $nice_count . ' ' . $word;
        }
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
     * @param $x String Longer string.
     * @param $x String String that $x might start with.
     * @param $ignoreCase bool Whether to ignore case.
     * @param $remainder String Gets set to the portion of $x after $y, if
     * $x starts with $y;
     * @return bool Whether $x starts with $y;
     */
    function starts_with($x, $y, $ignoreCase = false, &$remainder = null) {

        $xLen = strlen($x);
        $yLen = strlen($y);

        if ($yLen > $xLen) {
            return false;
        }

        $start = substr($x, 0, $yLen);

        if ($ignoreCase) {
            $success = strcasecmp($start, $y) == 0;
        } else {
            $success = strcmp($start, $y) == 0;
        }

        if (!$success) {
            $remainder = null;
            return false;
        }

        $remainder = substr($x, $yLen);
        return true;
    }

    /**
     * Converts an arbitrary string into a valid css class;
     */
    function to_css_class($x) {

        $x = trim($x);
        $x = preg_replace('/[^a-z0-9-]/i', '_', $x);
        $x = preg_replace('/-{2,}/', '_', $x);
        $x = preg_replace('/^([^a-z-])/i', '_$1', $x);

        return $x;
    }

    /**
     * Converts a string (plural or singular) into a database_id.
     * <example>
     * to_id('foo') = 'foo_id',
     * to_id('categories') = 'category_id'
     */
    function to_id($s) {
        $s = singularize(trim($s));
        if (!$s) return $s;

        return underscore($s) . '_id';
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
     * Given a singular or plural string, returns a pluralized table name.
     */
    function to_table_name($s) {

        $s = trim($s);
        if (!$s) return $s;

        return pluralize(underscore($s));
    }

    /**
     * Converts a camelCased string to an underscore_separated_string
     */
    function underscore($s, $sep = '_') {

        $s = preg_replace('/([a-z])([A-Z]+)/', '$1' . $sep . '$2', $s);
        $s = preg_replace('/[\s_-]+/', $sep, $s);
        return strtolower($s);

    }

    /**
     *
     */
    function truncate_words($str, $count, $marker = '') {

       $str = trim($str);
       $result = '';

       $words = explode(' ', $str);
       if (count($words) < $count) {
           return $str;
       }

       $i = 0;
       foreach($words as $w) {
           if ($i >= $count) {
               return $result . $marker;
           }
           $result .= ($result ? ' ' : '') . $w;
           $i++;
       }

        return $result . $marker;
    }

    /**
     * Takes an input string and converts wildcard characters ('*', '?') to
     * the valid mysql LIKE equivalents.
     * @param $s String Input string.
     * @param $wrap String If no wildcard characters are found in $s, it will
     * be wrapped in the character specified here. Set to false to disable
     * auto-wrapping.
     */
    function wildcardify($s, $wrap = '%') {

        // Escape wildcard chars
        $s = escape_wildcards($s);

        $starCount = $questionCount = 0;

        $s = str_replace('*', '%', $s, $starCount);
        $s = str_replace('?', '_', $s, $questionCount);

        if ($wrap && !($starCount + $questionCount)) {
            $s = $wrap . $s . $wrap;
        }

        return $s;
    }

    function escape_wildcards($s) {

        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('%', '\\%', $s);
        $s = str_replace('_', '\\_', $s);

        return $s;

    }

    function pretty_json_encode($data) {
        $str = json_encode($data);
        $new = '';
        $prev = null;
        $indent = 0;
        $inQuote = false;

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];

            if ($char === '"' && $prev !== '\\') {
                $inQuote = !$inQuote;
            }

            if (!$inQuote && ($char == '}' || $char == ']')) {
                $new .= "\n";
                $indent -= 4;
                $new .= str_repeat(' ', $indent);
            }

            $new .= $char;

            if (!$inQuote && ($char == '{' || $char == '[')) {
                $new .= "\n";
                $indent += 4;
                $new .= str_repeat(' ', $indent);
            }

            if (!$inQuote && $char == ',') {
                $new .= "\n";
                $new .= str_repeat(' ', $indent);
            }

            if (!$inQuote && $char == ':') {
                $new .= ' ';
            }

            $prev = $char;

        }

        return $new;
    }

?>
