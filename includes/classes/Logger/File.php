<?php

/**
 * @deprecated Use Octopus_Log.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
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
     */
    public function log($line) {
    	return $this->logger->write(md5($line . microtime()), $line, $this->name, Octopus_Log::DEBUG, 0);
    }

}
