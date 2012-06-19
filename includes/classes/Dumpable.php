<?php

/**
 * Implement this interface to have greater control over how your class is
 * displayed in log output.
 */
interface Dumpable {

    /**
     * @return String debugging info for this object, formatted as HTML.
     */
    function __dumpHtml();

    /**
     * @return String debugging info for this object, formatted as plain text.
     */
    function __dumpText();

}