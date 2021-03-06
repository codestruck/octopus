<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Image_Mode_Crop_Specific extends Octopus_Image_Mode_Base {

    function __construct($src, $layout) {
        $this->src = $src;
        $this->layout = $layout;
    }

    function prepare() {

        $this->common();

        $this->width = $this->layout->x2 - $this->layout->x1;
        $this->height = $this->layout->y2 - $this->layout->y1;

        $resizeRatio = $this->layout->resizeWidth / $this->src->width;
        $inflateSrc = 1 / $resizeRatio;

        $this->src_x1 = floor($this->layout->x1 * $inflateSrc);
        $this->src_y1 = floor($this->layout->y1 * $inflateSrc);
        $this->src_width = floor($this->width * $inflateSrc);
        $this->src_height = floor($this->height * $inflateSrc);

        $this->canvas_w = $this->width;
        $this->canvas_h = $this->height;

    }

}

