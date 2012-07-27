<?php

class Octopus_Image_Thumb {

    function getThumbPath($file, $width = null, $height = null, $action = 'r') {

        $hash = $this->getFileHash($file, $width, $height, $action);
        $filename = sprintf("%simage_cache/%s.jpg", PRIVATE_DIR, $hash);

        $imageDir = SITE_DIR;

        if (is_file($filename) && filemtime($filename) >= filemtime($imageDir . $file)) {
            return $filename;
        }

        $layout = array(
            array(
                       'action' => $action,
                       'width' => $width,
                       'height' => $height,
                       'constrain' => '>',
                       'mod' => '',
                       'quality' => 90,
                       ),
        );


        $src = $imageDir . $file;
        $path = PRIVATE_DIR . 'image_cache/';
        $dir = substr($hash, 0, 1);

        $makeDir = new Octopus_Directory();
        $makeDir->createWritable($path, $path);
        $makeDir->createWritable($path . $dir, $path);

        $sg_image = new Octopus_Image($layout);
        $sg_image->processImages($path, $hash, $src);

        return $filename;

    }

    function getFileHash($file, $width = null, $height = null, $action = 'r') {

        $hash = sha1(sprintf("%s_%d_%d_%s", $file, $width, $height, $action));
        $hashDir = substr($hash, 0, 1) . '/' . substr($hash, 1);
        return $hashDir;

    }

}

