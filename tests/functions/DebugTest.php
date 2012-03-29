<?php

class DebugTest extends Octopus_App_TestCase {

	public static $temp = null;

	function setUp() {

		parent::setUp();
		Octopus_Debug::reset();
		self::$temp = null;

	}

	function tearDown() {
		Octopus_Debug::reset();
		parent::tearDown();
	}


	function assertValueDumpedToStdErr($value, $expected) {

		$file = sys_get_temp_dir() . '/assertValueDumpedToStdErr';
		if (is_file($file)) unlink($file);

		if (1) {
			Octopus_Debug::configure();
			Octopus_Log::reset(); // remove default dump_r processor
		}

		$listener = new Octopus_Log_Listener_Console($file);
		Octopus_Log::addListener($listener);

		// If tests are failing, it is probably because the line number in the
		// block of text below does not match that of the line immediately
		// following this comment
		Octopus_Debug::dump($value);

		$boldLine = str_repeat(Octopus_Log_Listener_Console::CHAR_BOLD_LINE, 80);
		$lightLine = str_repeat(Octopus_Log_Listener_Console::CHAR_LIGHT_LINE, 80);

		$this->assertTrue(is_file($file), 'debug output written to file');
		$actual = file_get_contents($file);
		$actual = preg_replace('/^.*?' . Octopus_Log_Listener_Console::CHAR_LIGHT_LINE . '{80}\n/s', '', $actual);
		$actual = preg_replace('/' . Octopus_Log_Listener_Console::CHAR_LIGHT_LINE . '{80}.*$/s', '', $actual);

		$actual = trim($actual);
		$actual = explode("\n", $actual);
		$actual = array_map('trim', $actual);

		$expected = trim($expected);
		$expected = explode("\n", $expected);
		$expected = array_map('trim', $expected);

		$this->assertEquals($expected, $actual);

	}

	function testDumpIntToStdErr() {

		$this->assertValueDumpedToStdErr(
			42,
			<<<END
int(42)
	octal:        052
	hex:          0x2A
END
		);

	}

	function testDumpFloatToStdErr() {

		$this->assertValueDumpedToStdErr(
			3.14,
			'float(3.14)'
		);

	}

	function testDumpTimestampToStdErr() {

		$time = time();
		$niceTime= date('r', $time);

		$octal = sprintf('0%o', $time);
		$hex = sprintf('0x%X', $time);

		$this->assertValueDumpedToStdErr(
			$time,
			<<<END
int({$time})
	octal:        {$octal}
	hex:          {$hex}
	timestamp:    {$niceTime}
END
		);
	}

	function testDumpFilePermsToStdErr() {

		$value = 0666;
		$this->assertValueDumpedToStdErr(
			$value,
			<<<END
int({$value})
	octal:        0666
	hex:          0x1B6
	permissions:  rw-rw-rw-
END
		);
	}

	function testDumpShortStringToStdErr() {

		$value = "Lorem ipsum dolor sit amet";
		$len = strlen($value);

		$this->assertValueDumpedToStdErr(
			$value,
			<<<END
"{$value}" ($len chars)
END
		);

	}

	function testDumpDirectoryNameToStdErr() {

		$dir = sys_get_temp_dir() . '/test-dump-dir';
		if (is_dir($dir)) recursive_delete($dir);

		mkdir($dir);
		touch($dir . '/file1');
		touch($dir . '/file2');

		$len = strlen($dir);

		$this->assertValueDumpedToStdErr(
			$dir,
			<<<END
"$dir" ($len chars)
	Directory: exists, drwxr-xr-x, contains 2 files
END
		);

	}

	function testDumpFileNameToStdErr() {

		$dir = sys_get_temp_dir() . '/test-dump-dir';
		recursive_delete($dir);
		mkdir($dir);

		$file = $dir . '/testdumpfile';
		$len = strlen($file);

		file_put_contents($file, str_repeat('x', 1024 + 500));
		$this->assertValueDumpedToStdErr(
			$file,
			<<<END
"$file"
	Length: $len chars
	File: exists, -rw-r--r--, ~1K (1,524 bytes)
END
		);

		file_put_contents($file, str_repeat('x', 1024 * 1024 + 500));
		$this->assertValueDumpedToStdErr(
			$file,
			<<<END
"$file"
	Length: $len chars
	File: exists, -rw-r--r--, ~1M (1,049,076 bytes)
END
		);

	}

	function testDumpDateStringToStdErr() {

		$date = 'May 15, 2003';
		$len = strlen($date);
		$time = strtotime($date);

		$this->assertValueDumpedToStdErr(
			$date,
			<<<END
"$date" ($len chars)
	Timestamp: $time
END
		);

	}

	function testGetLevelNameBiggerThanDebug() {

		$this->assertEquals('DEBUG', Octopus_Log::getLevelName(Octopus_Log::DEBUG));
		$this->assertEquals('DEBUG1', Octopus_Log::getLevelName(Octopus_Log::DEBUG + 1));
		$this->assertEquals('DEBUG2', Octopus_Log::getLevelName(Octopus_Log::DEBUG + 2));
		$this->assertEquals('DEBUG3', Octopus_Log::getLevelName(Octopus_Log::DEBUG + 3));
		$this->assertEquals('DEBUG50', Octopus_Log::getLevelName(Octopus_Log::DEBUG + 50));

	}


}
