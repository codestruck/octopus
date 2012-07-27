<?php

/**
 * Minification strategy that uses '_src' at the end of a filename to indicate
 * the unminified version of a file, and returns either the 'src' version or
 * the non-'src' version depending on which mod time is more recent.
 */
class Octopus_Minify_Strategy_Src extends Octopus_Minify_Strategy {


    public function minify($files, $options = array()) {

        $result = array();

        foreach($files as $file) {

            if (!$this->looksLikeLocalFile($file)) {
                continue;
            }

            $minifiedFile = $this->getMinifiedFile($file);

            if ($minifiedFile) {
                $result[$minifiedFile] = array($file);
            }
        }

        return $result;
    }

    private function getMinifiedFile($file) {

        $info = pathinfo($file);
        $info['filename'] = preg_replace('/_src$/i', '', $info['filename']);
        $info['extension'] = empty($info['extension']) ? '' : '.' . $info['extension'];

        $file = "{$info['dirname']}/{$info['filename']}{$info['extension']}";
        $src =  "{$info['dirname']}/{$info['filename']}_src{$info['extension']}";

        $fileExists = is_file($file);
        $srcExists = is_file($src);

        if ($fileExists && $srcExists) {

            $fileTime = filemtime($file);
            $srcTime = filemtime($src);

            return ($srcTime >= $fileTime ? $src : $file);

        } else if ($fileExists) {
            return $file;
        } else if ($srcExists) {
            return $src;
        } else {
            return false;
        }
    }

}

