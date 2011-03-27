<?php

    /*
     * Default header
     *
     */

     extract($controller_data);

     $header->addJavascript(...)

?>
<! doctype html >
<html>
<head>
<title><?php echo $PAGE->getTitle(); ?></title>
</head>
<div id="header">
    <h1><?php echo a('/', $SETTINGS->get('app_name')); ?></h1>
</div>
