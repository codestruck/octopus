<?php

class DebugTest extends Octopus_App_TestCase {

	public static $temp = null;

	function setUp() {

		parent::setUp();
		Octopus_Debug::reset();
		self::$temp = null;

	}

	function testDumpNumber() {

		Octopus_Log::addListener(function($message) {
			DebugTest::$temp = $message;
		});

		Octopus_Debug::dump(42);

		$this->assertTrue(self::$temp instanceof Octopus_Debug_Dumped_Vars, 'Octopus_Debug_Dumped_Vars logged');
		$this->assertEquals(1, count(self::$temp), '1 var dumped');
		$this->assertEquals(42, self::$temp[0], 'correct number logged');

	}

	function testDumpNumberToStdErrFormatting() {

		$file = sys_get_temp_dir() . '/testDumpNumberToStdErrFormatting';
		if (is_file($file)) unlink($file);

		$listener = new Octopus_Log_Listener_Console($file);
		Octopus_Log::addListener($listener);

		Octopus_Debug::dump(42);

		$this->assertTrue(is_file($file), 'debug output written to file');
		$this->assertEquals(
			<<<END

********************************************************************************
 dump - DEBUG                     DebugTest::testDumpNumberToStdErrFormatting()
                                 octopus/tests/functions/DebugTest.php, line 37
--------------------------------------------------------------------------------
int(42)
********************************************************************************


END
			,
			file_get_contents($file)
		);

	}

}

?>
