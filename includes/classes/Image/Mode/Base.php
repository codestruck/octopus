<?php

class Octopus_Image_Mode_Base {

    function common() {

        $this->ext = ($this->keep_type) ? $this->src->ext : $this->default_type;
        $this->filename = ($this->src->custom_name != '') ? $this->src->custom_name : $this->src->name;

        if (isset($this->layout->force_type)) {
            $this->ext = $this->layout->force_type;
        }

        // Force to w or h constrain
        if (isset($this->layout->constrain)) {
            if ($this->layout->constrain == '>') {
                $this->layout->constrain = ($this->src->width > $this->src->height) ? 'w' : 'h';
            } elseif ($this->layout->constrain == '<') {
                $this->layout->constrain = ($this->src->width > $this->src->height) ? 'h' : 'w';
            }
        }

        // Check the quality
        $this->quality = $this->quality;
        if (isset($this->layout->quality) && is_numeric($this->layout->quality)) {
            $this->quality = $this->layout->quality;
        }

        if ($this->ext === 'png') {
            $this->quality = round($this->quality / 10 - 1);
        }

        // Set the paths, names, and so forth
        $this->folder = (isset($this->layout->folder) && trim($this->layout->folder) != '') ? $this->layout->folder . '/' : '';

        $this->folder_path = $this->path . $this->folder;

        $this->new_filename = $this->filename;
        if (isset($this->layout->mod) && $this->layout->mod != '') {
            $this->new_filename = $this->filename . $this->layout->mod;
        }

        $this->fullpath = $this->folder_path . $this->new_filename . '.' . $this->ext;
        $this->filenameExt = $this->new_filename . '.' . $this->ext;

        // Check the destination folder path
        if ($this->folder != '') {
            if(!$this->checkPath($this->folder_path)) {
                return;
            }
        }

        $this->x1 = 0;
        $this->y1 = 0;
        $this->src_x1 = 0;
        $this->src_y1 = 0;
        $this->src_width = $this->src->width;
        $this->src_height = $this->src->height;

        $this->direct_copy = false;

    }

}

?>
