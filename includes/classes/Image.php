<?php
/**
 * TODO:
 *
 * Pre Processing:
 * Recursive "folder" layout paths?
 * Determine what Specific Crop does
 * Should cropping to larger than image keep image og size, but write output to crop target canvas size?
 *
 * Post Processing:
 * - Sharpening
 * - Watermarking / Overlay
 *
 * Output:
 * Output image instead of file. Double check mime header type output
 * Class returning a handle to the image(s)
 * Ability to return IPICT or EXIF data from the original
 *
 * Testing:
 * BMP generation / conversion
 * PNG Quality as factor of 10 on lesser php / gd versions
 * CMYK color conversion handling
 *
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Image {



    public function __construct($file_layout = array()) {

        $this->allowed_types = array("gif","jpg","jpeg","png","bmp");
        $this->success = true;
        $this->processed = true;
        $this->error = array();

        $this->quality = 90;
        $this->default_type = 'jpg';

        $this->file_layout = $file_layout;
        $this->keep_type = false;

        $this->src = new StdClass();

        if (count($this->file_layout) == 0) {
            $this->error[] = "No file layout specified";
            $this->processed = false;
            return;
        }

        if (!extension_loaded('gd') && !function_exists('gd_info')) {
            $this->error[] = 'You must have the GD Library installed';
            $this->processed = false;
            return;
        }

    }

    // Map a local file to appear as an http file upload
    function convert_file($filepath) {

        $size = getimagesize($filepath);

        $args = array(
                    'name' => basename($filepath),
                    'type' => $size['mime'],
                    'tmp_name' => $filepath,
                    'error' => 0,
                    'size' => filesize($filepath)
                    );

        $this->src_data = $args;
    }

    function setup_file($image_data) {

        // Determine the file source and normalize
        if (is_array($image_data) && is_file($image_data['tmp_name'])) {
            $this->src_data = $image_data;
        } elseif (is_string($image_data) && is_file($image_data)) {
            $this->convert_file($image_data);
        } else {
            $this->error[] = "Cannot seem to locate the file";
        }

        // Setup some vars & make sure it's a valid type for uploading
        $this->src->name = substr($this->src_data['name'], 0,strrpos($this->src_data['name'],'.'));
        $this->src->file = $this->src_data['tmp_name'];

        $info = getimagesize($this->src->file);
        $this->src->type = $info['mime'];

        $this->src->ext = substr($this->src->type,strrpos($this->src->type,"/")+1);
        $this->src->ext = strtolower($this->src->ext);
        if ($this->src->ext == 'jpeg') {
            $this->src->ext = 'jpg';
        }

        if (!in_array($this->src->ext, $this->allowed_types)) {
            if ($this->src->ext) {
                $this->error[] = "This type of file is not allowed: ." . strtoupper($this->src->ext);
            } else {
                $this->error[] = "File type not allowed";
            }
            return false;
        }

        // Apply sizing and other variables for processing
        $this->src->width = $info[0];
        $this->src->height = $info[1];

        if ($this->src->width > $this->src->height)
            $this->src->orientation = 'landscape';
        elseif ($this->src->height > $this->src->width)
            $this->src->orientation = 'portrait';
        else
            $this->src->orientation = 'square';

        return true;

    }

    function getMode($layout) {

        $modes = array(
           'sc' => 'Octopus_Image_Mode_Crop_Specific',
           'c' => 'Octopus_Image_Mode_Crop',
           'ct' => 'Octopus_Image_Mode_Crop_Thumb',
           'u' => 'Octopus_Image_Mode_Upload',
           'r' => 'Octopus_Image_Mode_Resize',
        );

        $class = $modes[ $layout['action'] ];

        $layoutObj = new StdClass();
        $layoutObj->constrain = '';
        foreach ($layout as $k => $v) {
            $layoutObj->$k = $v;
        }

        $obj = new $class($this->src, $layoutObj);
        $obj->keep_type = $this->keep_type;
        $obj->default_type = $this->default_type;
        $obj->quality = $this->quality;
        $obj->path = $this->path;

        return $obj;

    }

    function processImages($path, $custom_name, $image_data) {

        $this->src->custom_name = $custom_name;
        $this->path = end_in('/', $path);

        if (!$this->checkPath($this->path)) {
            return;
        }

        if (!$this->setup_file($image_data)) {
            return;
        }

        // We're good to start processing the image
        foreach($this->file_layout as $layout) {

            $this->dest = $this->getMode($layout);
            $this->dest->prepare();

            $this->load();

        }

    }

    function getCreateFunction($ext) {

        $types = array(
           'gif' => 'imagecreatefromgif',
           'png' => 'imagecreatefrompng',
           'bmp' => 'imagecreatefrombmp',
           'jpg' => 'imagecreatefromjpeg',
        );

        if (isset($types[$ext])) {
            return $types[$ext];
        } else {
            return 'imagecreatefromjpeg';
        }

    }

    function getSaveFunction($ext) {

        $types = array(
           'gif' => 'imagegif',
           'png' => 'imagepng',
           'bmp' => 'imagebmp',
           'jpg' => 'imagejpeg',
        );

        if (isset($types[$ext])) {
            return $types[$ext];
        } else {
            return 'imagejpeg';
        }

    }

    function load() {

        if ($this->dest->direct_copy) {

            copy($this->src->file, $this->dest->fullpath);

        } else {

            if (empty($this->output)) $this->output = new StdClass();
            $this->output->temp = imagecreatetruecolor($this->dest->canvas_w, $this->dest->canvas_h);

            $function = $this->getCreateFunction($this->src->ext);
            $this->output->source = $function($this->src->file);

            $this->create();
        }

    }

    function create() {

        if ($this->dest->ext == 'png') {
            imagealphablending($this->output->temp, false);
            imagesavealpha($this->output->temp, true);
            $bgcolor = imagecolorallocatealpha($this->output->temp, 0, 0, 0, 127);
        } else {
            $bgcolor = imagecolorallocate($this->output->temp, 255, 255, 255);
        }

        imagefill($this->output->temp, 0, 0, $bgcolor);

        $res = imagecopyresampled($this->output->temp, $this->output->source, $this->dest->x1, $this->dest->y1, $this->dest->src_x1, $this->dest->src_y1, $this->dest->width, $this->dest->height, $this->dest->src_width, $this->dest->src_height);

        $this->output();
    }

    function output() {

        $destination = $this->dest->fullpath;

        $function = $this->getSaveFunction($this->dest->ext);
        $this->output->image = $function($this->output->temp, $destination, $this->dest->quality);

        if (!$this->output->image) {
            $this->error[] = "Image could not be created";
        }

        // Free up some resources
        imagedestroy($this->output->temp);
        imagedestroy($this->output->source);

    }

    function checkPath($path) {

        if (!is_dir($path)) {
            if (!@mkdir($path)) {
                $this->error[] = "New folder \"$path\" could not be created";
                $this->processed = false;
                return false;
            }
        } else {
            if (!is_writable($path)) {
                if (!@chmod($path, 0777)) {
                    $this->error[] = "Destination folder \"$path\" is not writeable";
                    $this->processed = false;
                    return false;
                }
            }
        }

        return true;
    }

}
