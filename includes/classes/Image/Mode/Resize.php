<?php

class Octopus_Image_Mode_Resize extends Octopus_Image_Mode_Base {

    function __construct($src, $layout) {
        $this->src = $src;
        $this->layout = $layout;
    }

    function prepare() {

        $this->common();

        if ($this->layout->width > $this->src->width) {
            $this->layout->width = $this->src->width;
        }

        if ($this->layout->height > $this->src->height) {
            $this->layout->height = $this->src->height;
        }

        switch($this->layout->constrain) {

            case "w":
                $this->width = $this->canvas_w =  $this->layout->width;
                $this->height = $this->canvas_h = ($this->width / $this->src->width) * $this->src->height;
                //$this->w / ($this->src->width / $this->src->height);
            break;

            case "h":
            default:
                $this->height = $this->canvas_h = $this->layout->height;
                $this->width = $this->canvas_w = ($this->height / $this->src->height) * $this->src->width;
            break;

        }
    }

}

