<?php

    setlocale(LC_MONETARY, 'en_US.UTF-8');

    /**
     * Formats a dollar amount.
     */
    function format_money($amount, $format = null, $locale = null) {

        $original = $amount;
        $amount = trim($amount);

        if ($amount === '') {
            return '';
        }

        if ($locale !== null) {
            $oldLocale = setlocale(LC_MONETARY, null);
            setlocale(LC_MONETARY, $locale);
        }

        if (!is_numeric($amount)) {

            $i = localeconv();
            $remove = array($i['currency_symbol'], $i['int_curr_symbol'], $i['mon_thousands_sep']);
            array_walk($remove, 'preg_quote');
            $pattern = '/[' . implode('', $remove) . ']/i';
            $amount = preg_replace($pattern, '', $amount);

            if (!is_numeric($amount)) {
                return $original;
            }

        }

        $amount = floor($amount * 100) / 100;

        if (!is_string($format)) {
            $format = '%(.2n';
        }

        if ($locale !== null) {
            setlocale(LC_MONETARY, $oldLocale);
        }

        return money_format($format, $amount);
    }

    /**
     * Formats a dollar amount, wrapping in a <span class="negative"></span>
     * if the amount is negative.
     */
    function html_format_money($amount, $format = null) {

        $formatted = h(format_money($amount, $format));

        if ($amount < 0) {
            return '<span class="negative">' . $formatted . '</span>';
        }

        return $formatted;
    }

