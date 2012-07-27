<?php

class Octopus_Image_Mode_Upload extends Octopus_Image_Mode_Base {

    function __construct($src, $layout) {
        $this->src = $src;
        $this->layout = $layout;
    }

    function prepare() {
        $this->common();

        $this->canvas_w = $this->src->width;
        $this->canvas_h = $this->src->height;
        $this->width = $this->src->width;
        $this->height = $this->src->height;

        if ($this->src->ext == $this->ext && $this->quality == 100) {
            $this->direct_copy = true;
        }

    }

}

