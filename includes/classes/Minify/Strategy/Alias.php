<?php

/**
 * A minification strategy that watches for one or more files it's been told
 * can be aliased using another file. E.g., in production you might want
 * each instance of both "public.js" and "admin.js" on a page to be
 * replaced with "public_and_admin.js" on a CDN somewhere.
 *
 */
class Octopus_Minify_Strategy_Alias extends Octopus_Minify_Strategy {

    private $finder;
    private $aliases = array();

    /**
     * Creates a new aliaser that uses $findCallback to locate files physically
     * on the filesystem.
     */
    public function __construct($findCallback = null) {
        $this->finder = $findCallback ? $findCallback : array($this, 'defaultFindCallback');
    }

    public function defaultFindCallback($file) {
        return $file;
    }

    /**
     * Adds an alias for $files.
     */
    public function addAlias(Array $files, $alias) {
        $this->aliases[] = compact('files', 'alias');
    }

    public function minify($files, $options = array()) {

        $result = array();

        $aliases = $this->aliases;

        // Locate all the files in question

        foreach($aliases as $index => &$a) {

            foreach($a['files'] as $fileIndex => $file) {
                $file = call_user_func($this->finder, $file);
                if (!$file) {
                    // One of the files needed to match the alias
                    // could not be found, so the alias is
                    // invalid
                    return $result;
                }
                $a['files'][$fileIndex] = $file;
            }

            // If the file being aliased in does not exist, does that matter?
            $alias = call_user_func($this->finder, $a['alias']);
            if ($alias) {
                $a['alias'] = $alias;
            }

        }
        unset($a);

        foreach($files as $index => $file) {
            $file = call_user_func($this->finder, $file);
            if (!$file) {
                unset($files[$index]);
            }
        }

        // Cull out any aliases that require files that were not provided

        while($aliases && $files) {

            foreach($aliases as $index => $a) {

                foreach($a['files'] as $f) {
                    if (!in_array($f, $files)) {
                        unset($aliases[$index]);
                        break;
                    }
                }

            }

            $bestAliasIndex = null;
            $bestFileCount = 0;

            foreach($aliases as $index => $a) {

                $count = count($a['files']);
                if ($count > $bestFileCount) {
                    $bestAliasIndex = $index;
                    $bestFileCount = $count;
                }

            }

            if ($bestAliasIndex === null) {
                return $result;
            }

            $bestAlias = $aliases[$bestAliasIndex];
            unset($aliases[$bestAliasIndex]);

            $result[$bestAlias['alias']] = $bestAlias['files'];
            $files = array_diff($files, $bestAlias['files']);
        }

        return $result;

    }

    /**
     * @return Array The result of injecting any aliases present in $aliases into
     * $files.
     */
    protected function processAliases($items, $aliases) {

        if (empty($aliases)) {
            return $items;
        }

        // Sort aliases in the order they should be attempted
        usort($aliases, array('Octopus_Html_Page', 'compareAliases'));

        // NOTE: At this point, URL_BASE has already been prepended to everything that needs it

        $result = array();

        foreach($aliases as $a) {

            if (self::checkIfAlias($items, $a['urls'], $weight, $index)) {

                // We can use this in place
                $a['weight'] = $weight;
                $a['index'] = $index;
                $a['section'] = '';

                // TODO: Actually compare attributes and section as well when checking if files can be
                // aliased

                $result[] = $a;

                foreach($a['urls'] as $url) {
                    $key = self::findByUrl($items, $url);
                    if ($key !== false) unset($items[$key]);
                }

            }

            if (empty($items)) {
                break;
            }
        }

        foreach($items as $item) {
            $result[] = $item;
        }

        usort($result, array('Octopus_Html_Page', 'compareWeights'));

        return $result;
    }

    private static function checkIfAlias($items, $aliasCandidateUrls, &$weight, &$index) {

        if (empty($items)) {
            return empty($aliasCandidateUrls);
        }

        $prev = null;
        $weight = 0;
        $index = null;

        foreach($aliasCandidateUrls as $url) {

            $key = self::findByUrl($items, $url, $itemIndex);

            if ($key === false) {
                return false;
            }

            // Urls must appear in the same order specified in the alias
            if ($prev !== null && $itemIndex < $prev) {
                return false;
            }

            $weight += $items[$key]['weight'];

            if ($index === null || $items[$key]['index'] < $index) {
                $index = $items[$key]['index'];
            }

            $prev = $itemIndex;
        }

        return true;
    }

    private static function findByUrl(&$ar, $url, &$index = 0) {
        $index = 0;
        foreach($ar as $key => $item) {
            if (strcasecmp($item['url'], $url) === 0) {
                return $key;
            }
            $index++;
        }
        return false;
    }


}

