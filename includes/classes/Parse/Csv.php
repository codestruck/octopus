<?php

ini_set('auto_detect_line_endings', true);

class Octopus_Parse_Csv {

    function Octopus_Parse_Csv($filename, $skipRows = 2) {

        $this->analyzeFile($filename);

        $this->log = array();
        $this->current_row = 1;

        $this->_fp = fopen($filename, 'r');

        $line = fgetcsv($this->_fp, 1000, $this->delimiter);
        ++$this->current_row;
        $this->headerRow = array();

        foreach ($line as $col) {
            $this->headerRow[] = strtolower(trim(str_replace('v_', '', $col), " #:\n\r"));
        }

        $this->headerRowCount = count($this->headerRow);
        $this->log[] = "# of columns: $this->headerRowCount";

        // skip lines
        for ($i = 0; $i < $skipRows; ++$i) {
            $line = fgetcsv($this->_fp, 1000, $this->delimiter);
            ++$this->current_row;
        }
    }

    function analyzeFile($filename) {

        $fp = fopen($filename, 'r');
        $str = fread($fp, 5000);

        $tabs = substr_count($str, "\t");
        $commas = substr_count($str, ",");
        $semicolons = substr_count($str, ";");

        if ($tabs > $commas && $tabs > $semicolons) {
            $this->delimiter = "\t";
        } else if ($semicolons > $commas && $semicolons > $tabs) {
            $this->delimiter = ';';
        } else {
            $this->delimiter = ',';
        }

        fclose($fp);
    }

    function next() {

        $line = fgetcsv($this->_fp, 0 /* unlimited line length */, $this->delimiter);

        if ($line == false || (count($line) == 1 && $line[0] == '')) {
            $this->log[] = "# of rows: $this->current_row";
            fclose($this->_fp);
            return false;
        }

        $data = array();
        $i = 0;

        while ($i < $this->headerRowCount) {
            $k = $this->headerRow[$i];
            $v = $line[$i];
            $v = trim($v);
            $data[$k] = $v;
            ++$i;
        }

        $data['_current_row'] = $this->current_row;
        ++$this->current_row;
        return $data;
    }

}

?>
