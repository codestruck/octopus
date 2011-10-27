<?php

class Octopus_Image_Mode_Crop_Thumb extends Octopus_Image_Mode_Base {

    function Octopus_Image_Mode_Crop_Thumb($src, $layout) {
        $this->src = $src;
        $this->layout = $layout;
    }

    function prepare() {

        $this->common();

        // Make the canvas our target size. Even if larger than the image
        $this->canvas_w = $this->layout->width;
        $this->canvas_h = $this->layout->height;


        //Make sure the resample sizes aren't bigger than the original
        if ($this->layout->width > $this->src->width) {
            $this->layout->width = $this->src->width;
        }

        if ($this->layout->height > $this->src->height) {
            $this->layout->height = $this->src->height;
        }

        // Do the ratio work
        $ratio = min($this->layout->width/$this->src->width, $this->layout->height/$this->src->height);

        $this->width = floor($ratio * $this->src->width);
        $this->height = floor($ratio * $this->src->height);


        // Calculate offsets so the final crop is the center of the image
        $this->x1 = floor(($this->canvas_w - $this->width) / 2);
        $this->y1 = floor(($this->canvas_h - $this->height) / 2);

    }

}

?>
