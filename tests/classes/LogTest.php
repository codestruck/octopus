<?php

class LogTest extends Octopus_App_TestCase {

	function setUp() {
		parent::setUp();
		Octopus_Log::reset();
	}

	function testNoLoggingByDefault() {

		foreach(Octopus_Log::getLevels() as $level) {

			Octopus_Log::write('nologging', $level, 'blerg');

		}

		$this->assertEquals(count(Octopus_Log::getLevels()), Octopus_Log::getCallCount());
		$this->assertEquals(0, Octopus_Log::getWriteCount(), 'no writes');

	}

	function testListenerLogFiltering() {

		Octopus_Log::addListener('payments', Octopus_Log::LEVEL_WARN, array($this, 'basicListener'));

		$this->lastLevel = $this->lastMessage = null;

		Octopus_Log::write('payments', Octopus_Log::LEVEL_ERROR, __METHOD__);
		$this->assertEquals(Octopus_Log::LEVEL_ERROR, $this->lastLevel);
		$this->assertEquals(__METHOD__, $this->lastMessage);

		$this->lastLevel = $this->lastMessage = null;
		Octopus_Log::write('some-other-log', Octopus_Log::LEVEL_ERROR, __METHOD__);
		$this->assertNull($this->lastLevel);
		$this->assertNull($this->lastMessage);

	}

	/**
	 * @dataProvider getLogLevels
	 */
	function testLoggingLevelEnabled($level) {

		Octopus_Log::addListener($level, array($this, 'basicListener'));

		foreach(Octopus_Log::getLevels() as $testLevel) {

			$name = Octopus_Log::getLevelName($testLevel);
			$method = 'is' . camel_case($name, true) . 'Enabled';

			if ($testLevel < $level) {
				$this->assertFalse(Octopus_Log::isEnabled($testLevel), "$name not enabled");
				$this->assertFalse(call_user_func(array('Octopus_Log', $method), "$name not enabled (helper method)"));
			} else {
				$this->assertTrue(Octopus_Log::isEnabled($testLevel), "$name enabled");
				$this->assertTrue(call_user_func(array('Octopus_Log', $method), "$name enabled (helper method)"));
			}

		}

	}

	/**
	 * @dataProvider getLogLevels
	 */
	function testLoggingThreshold($level) {

		Octopus_Log::addListener($level, array($this, 'basicListener'));

		$thresholdName = Octopus_Log::getLevelName($level);

		foreach(Octopus_Log::getLevels() as $testLevel) {

			$name = Octopus_Log::getLevelName($testLevel);
			$convenienceMethod = camel_case($name);

			$this->lastLevel = null;
			$this->lastMessage = null;
			$this->lastLog = null;

			Octopus_Log::write(__METHOD__, $testLevel, 'blerg');

			if ($testLevel >= $level) {
				$this->assertEquals($testLevel, $this->lastLevel);
				$this->assertEquals('blerg', $this->lastMessage);
				$this->assertEquals(__METHOD__, $this->lastLog);
			} else {
				$this->assertNull($this->lastLevel);
				$this->assertNull($this->lastMessage);
				$this->assertNull($this->lastLog);
			}

			$this->lastLevel = null;
			$this->lastMessage = null;
			$this->lastLog = null;

			call_user_func(array('Octopus_Log', $convenienceMethod), __METHOD__, 'blerg');

			if ($testLevel >= $level) {
				$this->assertEquals($testLevel, $this->lastLevel, "$name logged for threshold $thresholdName (convenience method)");
				$this->assertEquals('blerg', $this->lastMessage, "$name logged for threshold $thresholdName (convenience method)");
				$this->assertEquals(__METHOD__, $this->lastLog, "$name logged for threshold $thresholdName (convenience method)");
			} else {
				$this->assertNull($this->lastLevel, "$name not logged for threshold $thresholdName (convenience method)");
				$this->assertNull($this->lastMessage, "$name not logged for threshold $thresholdName (convenience method)");
				$this->assertNull($this->lastLog, "$name not logged for threshold $thresholdName (convenience method)");
			}


		}

	}

	/**
	 * @dataProvider getLogLevels
	 */
	function testConvenienceMethod($level) {

		Octopus_Log::addListener($level, array($this, 'basicListener'));

		$method = camel_case(Octopus_Log::getLevelName($level));

		$this->lastLevel = null;
		$this->lastMessage = null;
		$this->lastLog = null;

		call_user_func(array('Octopus_Log', $method), __METHOD__, "Test $method");

		$this->assertEquals(__METHOD__, $this->lastLog);
		$this->assertEquals($level, $this->lastLevel);
		$this->assertEquals("Test $method", $this->lastMessage);

	}


	function testFileLogging() {

		$logger = new Octopus_Log_Listener_File($this->getPrivateDir() . 'test-log-dir');
		$logger->setMaxFileSize(1024);
		$logger->setRotationDepth(3);

		Octopus_Log::addListener(Octopus_Log::LEVEL_INFO, $logger);
		for($i = 0; $i < 100; $i++) {

			Octopus_Log::write(
				__METHOD__,
				Octopus_Log::LEVEL_WARN,
				<<<END
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
tempor incididunt ut labore et dolore magna aliqua.
END
			);

		}

		$this->assertEquals(100, Octopus_Log::getWriteCount(), '100x write count');

		$file = $this->getPrivateDir() . 'test-log-dir/' . to_slug(__METHOD__) . '.log';
		$this->assertTrue(is_file($file), 'log file exists');

		$size = filesize($file);
		$this->assertTrue($size > 0 && $size <= 1024, "File size between 0 and 1024 (was $size)");

		for($i = 1; $i <= 10; $i++) {

			$num = "0000{$i}";
			$num = substr($num, -3);

			$dir = $this->getPrivateDir() . 'test-log-dir/';
			$file = $dir . to_slug(__METHOD__) . ".{$num}.log";
			if ($i <= 3) {
				$this->assertTrue(is_file($file), "Exists: $file");
			} else {
				$this->assertFalse(is_file($file), "Does not exist: $file");
			}

		}

	}


	var $lastLevel = null;
	var $lastMessage = null;
	var $lastLog = null;

	function basicListener($message, $log, $level) {
		$this->lastLevel = $level;
		$this->lastLog = $log;
		$this->lastMessage = $message;
	}

	function getLogLevels() {

		$result = array();

		foreach(Octopus_Log::getLevels() as $level) {
			$result[] = array($level, Octopus_Log::getLevelName($level));
		}

		return $result;

	}

}

?>