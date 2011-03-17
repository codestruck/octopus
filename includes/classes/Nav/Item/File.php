<?php

    /**
     * An SG_Nav_Item based on a physical file.
     */
    class SG_Nav_Item_File extends SG_Nav_Item {

        var $_file;

        function getText() {

            if (isset($this->options['text'])) {
                return $this->options['text'];
            }

            $text = basename($this->_file);
            $text = preg_replace('/\..*?$/', '', $text);
            $text = preg_replace('/[_-]/', ' ', $text);
            $text = preg_replace('/\s{2,}/', ' ', $text);
            $text = ucwords($text);

            return $this->options['text'] = $text;
        }

    }

?>
