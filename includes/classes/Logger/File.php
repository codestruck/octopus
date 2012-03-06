<?php

/**
 * @deprecated Use Octopus_Log.
 */
class Octopus_Logger_File {

	private $logger;
	private $name;

    public function __construct($file) {

    	$this->logger = new Octopus_Log_Listener_File(dirname($file));
    	$this->name = basename($file);

    }

    public function log($line) {
    	$this->logger->write($line, $this->name, Octopus_Log::LEVEL_DEBUG);
    }

}
