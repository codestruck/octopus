<?php

    setlocale(LC_MONETARY, 'en_US');

    /**
     * Formats a dollar amount.
     */
    function format_money($amount, $format = null) {

        if (!is_string($format)) {
            $format = '%(.2n';
        }

        return money_format($format, $amount);
    }

    /**
     * Formats a dollar amount, wrapping in a <span class="negative"></span>
     * if the amount is negative.
     */
    function html_format_money($amount, $format = null) {

        $formatted = htmlspecialchars(format_money($amount, $format));

        if ($amount < 0) {
            return '<span class="negative">' . $formatted . '</span>';
        }

        return $formatted;
    }

?>
