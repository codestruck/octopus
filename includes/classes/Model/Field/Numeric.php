<?php

class Octopus_Model_Field_Numeric extends Octopus_Model_Field {

    public function accessValue($model, $saving = false) {

        $value = parent::accessValue($model, $saving);

        // regexs to truncate decimal fields
        if (!$saving && $decimal_places = $this->getOption('decimal_places')) {
            $value = preg_replace('/^(.*\.\d{' . $decimal_places . '})(\d*)/', '$1', $value);

            // overflow the precision
            if ($precision = $this->getOption('precision')) {
                $value = min($value * pow(10, $decimal_places), doubleval(str_repeat('9', $precision))) / pow(10, $decimal_places);
            }

        }

        return $value;

    }

    public function migrate($schema, $table) {

        if ($decimalPlaces = $this->getOption('decimal_places')) {
            $precision = $this->getOption('precision', 60);
            $table->newDecimal($this->getFieldName(), $precision, $decimalPlaces);
        } else {
            $table->newBigInt($this->getFieldName());
        }

    }

    public function save($model, $sqlQuery) {
        $value = $this->accessValue($model, true);
        $value = preg_replace('/[^\d\.]/', '', $value);
        $sqlQuery->set($this->getFieldName(), $value);
    }

}

?>
