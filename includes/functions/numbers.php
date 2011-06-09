<?php

    setlocale(LC_MONETARY, 'en_US');

    /**
     * Formats a dollar amount.
     */
    function format_money($amount, $format = null) {
        return money_format('%(.2n', $amount);
    }

?>
