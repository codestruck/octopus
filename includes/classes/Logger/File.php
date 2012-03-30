<?php

/**
 * @deprecated Use Octopus_Log.
 */
class Octopus_Logger_File {

	private $logger;
	private $name;

    public function __construct($file) {

    	$this->logger = new Octopus_Log_Listener_File(dirname($file));
    	$this->logger->setExtension(''); // extension is already on $file
    	$this->name = basename($file);

    }

    /**
     * Writes $line to a log file.
     * @throws Octopus_Exception If logging fails.
     */
    public function log($line) {
    	if (!$this->logger->write($line, $this->name, Octopus_Log::DEBUG)) {
    		throw new Octopus_Exception("Failed to write to file log: " . $this->logger->getLogFile($this->name));
    	}
    }

}
