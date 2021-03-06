<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_Numeric extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options) {

        parent::__construct($field, $modelClass, $options);

        $this->defaultOptions['form'] = true;
        $this->defaultOptions['auto_increment'] = false;
    }

    public function accessValue($model, $saving = false) {

        $value = parent::accessValue($model, $saving);
        $value = $this->normalizeValue($value, 0);

        if ($value) {
            return $value;
        }

        return $this->getOption('auto_increment') ? null : $value;
    }

    public function afterDelete(Octopus_Model $model) {

        if ($this->getOption('auto_increment')) {
            $model->setInternalValue($this->getFieldName(), null);
        }

    }

    public function setValue($model, $value) {

        $value = $this->normalizeValue($value, false);

        if ($value !== false) {
            return parent::setValue($model, $value);
        }
    }

    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {

        if (!$name) $name = $this->getFieldName();
        if ($autoIncrement === null) $autoIncrement = $this->getOption('auto_increment');

        if ($decimalPlaces = $this->getOption('decimal_places')) {
            $precision = $this->getOption('precision', 60);
            $table->newDecimal($name, $precision, $decimalPlaces);
        } else if ($this->getOption('auto_increment')) {

            // Auto increment == field is being used as ID
            $table->newKey($name, $autoIncrement);

        } else {
            $table->newBigInt($name);
        }

    }

    public function recordDisappeared(Octopus_Model $model) {

        if ($this->getOption('auto_increment')) {
            // reset auto increment values
            $model->setInternalValue($this->getFieldName(), null);
        }
    }

    public function save($model, $sqlQuery) {

        if ($this->getOption('auto_increment')) {
            return;
        }

        $value = $this->accessValue($model, true);
        $sqlQuery->set($this->getFieldName(), $value);
    }

    public function addToForm(Octopus_Html_Form $form) {

        if ($this->getOption('auto_increment')) {
            return;
        }

        return parent::addToForm($form);

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

