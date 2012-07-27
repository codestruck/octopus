<?php

interface Octopus_Feed_Item {

    function getTitle();

    function getFullContent();

    function getDescription();

    function getLink();

    function getGuid();

    function getDate();

    /**
     * @return Array key/value store of extra stuff to attach to this item.
     */
    function getExtra();

}

