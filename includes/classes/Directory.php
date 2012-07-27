<?php

class Octopus_Directory {

    function createWritable($dir, $base) {

        if (!preg_match('|^' . $base . '|', $dir) || preg_match('|\.\.|', $dir)) {
            return false;
        }

        $path = str_replace($base, '', $dir);
        $parts = explode('/', $path);

        $trail = '';

        foreach ($parts as $part) {

            $trail .= $part . '/';
            $fullDir = $base . $trail;

            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0777);
            }

            @chmod($fullDir, 0777);
        }

        return true;

    }

    function remove($dir, $base) {

        if (!preg_match('|^' . $base . '|', $dir) || preg_match('|\.\.|', $dir)) {
            return false;
        }

        if (is_dir($dir)) {
            $handle = opendir($dir);

            while (false !== ($file = readDir($handle))) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $this->remove($dir . '/' . $file, $base);
            }

            closedir($handle);

            @rmdir($dir);
        } else {
            if (is_file($dir)) {
                @unlink($dir);
            }
        }

    }

}

