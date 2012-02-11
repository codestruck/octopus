<?php

class Octopus_Model_Field_Numeric extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options);
        $this->defaultOptions['form'] = 'true';
    }

    public function accessValue($model, $saving = false) {

        $value = parent::accessValue($model, $saving);

        return $this->normalizeValue($value, 0);
    }

    public function setValue($model, $value) {

        $value = $this->normalizeValue($value, false);

        if ($value !== false) {
            return parent::setValue($model, $value);
        }
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
        $sqlQuery->set($this->getFieldName(), $value);
    }

    public function addToTable(Octopus_Html_Table $table) {

        if (!$this->getOption('table', true)) {
            return;
        }

        $formatFunc = 'number_format';

        $type = $this->getOption('original_type');
        if ($type === 'money' || $type === 'currency') {
            $formatFunc = 'html_format_money';
        }

        $table->addColumns(array(
            $this->getFieldName() => array(
                'function' => $formatFunc
            )
        ));
    }

    private function normalizeValue($value, $valueIfInvalid = 0) {

        $currencySymbol = $this->getOption('currency_symbol', '$');
        $thousandsSep = $this->getOption('thousands_separator', ',');
        $decimalPlaces = $this->getOption('decimal_places');

        if (is_string($value) && strlen($value)) {
            $value = str_replace($currencySymbol, '', $value);
            $value = str_replace($thousandsSep, '', $value);
        }

        if (!is_numeric($value)) {
            if (is_bool($valueIfInvalid)) {
                return $valueIfInvalid;
            } else {
                return $decimalPlaces ? doubleval($valueIfInvalid) : intval($valueIfInvalid);
            }
        }

        if ($decimalPlaces) {

            $value = doubleval($value);
            $value = round($value, $decimalPlaces); // mysql rounds rather than truncating

            if ($precision = $this->getOption('precision')) {
                $value = min($value * pow(10, $decimalPlaces), doubleval(str_repeat('9', $precision))) / pow(10, $decimalPlaces);
            }

        } else {
            $value = bctrunc($value);
        }

        return $value;

    }

}

?>
