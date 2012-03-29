<?php

/**
 * Class encapsulating a way of minifying content.
 */
abstract class Octopus_Minify_Strategy {

    /**
     * @param $files Mixed
     *        Either:
     *            An array of *absolute physical file paths* (e.g., /var/www/whatever.js) or
     *          full http urls (http://jquery.com/jquery.js)
     *
     *        Or:
     *            A string that is either of the above.
     *
     * @param $options Array Environment options etc.
     * @return An array where keys are the new paths, and the values are arrays of
     * files the key minifies.
     */
    abstract public function minify($files, $options = array());

    protected function getCacheDir($options = array()) {

        $dir = get_option('OCTOPUS_CACHE_DIR', null, $options);

        if (!$dir) {
            throw new Octopus_Exception('OCTOPUS_CACHE_DIR not set.');
        }

        return rtrim($dir, '/') . '/';
    }

    protected function getCacheFile($function, $uniqueHash, $deleteHash, $extension, $options = array()) {

        $cacheDir = $this->getCacheDir($options);
        if ($function) $cacheDir .= rtrim($function, '/') . '/';

        $file = $cacheDir . $deleteHash . '-' . $uniqueHash . $extension;
        //dump_r("EXISTS? $file", is_file($file));

        return is_file($file) ? $file : false;
    }

    /**
     * @return Whether $file looks like a local file.
     */
    protected function looksLikeLocalFile($file) {
        return !preg_match('#^([a-z0-9_-]*:)?//#i', $file);
    }

    /**
     * Saves a cache file with the given content
     * @param $uniqueHash String Hash uniquely identifying the content / mtime
     * @param $deleteHash String Hash used to clear out old cached versions of this content.
     * @param $content String Data to put in the cache file.
     * @param $options Array helper options.
     * @return String Physical path to the file.
     */
    protected function saveCacheFile($function, $uniqueHash, $deleteHash, $extension, $content, $options = array()) {

        $cacheDir = get_option('OCTOPUS_CACHE_DIR', null, $options);
        if ($function) $cacheDir .= rtrim($function, '/') . '/';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }

        if ($deleteHash) {

            // Remove old cache files for this content
            $oldFilesGlob = $cacheDir . $deleteHash . '-*' . $extension;
            $oldFiles = glob($oldFilesGlob);

            if ($oldFiles) {
                foreach($oldFiles as $f) {
                    unlink($f);
                }
            }
        }

        $cacheFile = $cacheDir . $deleteHash . '-' . $uniqueHash . $extension;

        file_put_contents($cacheFile, $content);
        //dump_r("WRITE: $cacheFile");

        return is_file($cacheFile) ? $cacheFile : false;
    }
}

?>