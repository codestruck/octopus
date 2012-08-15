<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class LogTest extends Octopus_App_TestCase {

    function setUp() {
        parent::setUp();
        Octopus_Debug::configure();
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

        Octopus_Log::addListener('payments', Octopus_Log::WARN, array($this, 'basicListener'));

        $this->lastLevel = $this->lastMessage = null;

        Octopus_Log::write('payments', Octopus_Log::ERROR, __METHOD__);
        $this->assertEquals(Octopus_Log::ERROR, $this->lastLevel);
        $this->assertEquals(__METHOD__, $this->lastMessage);

        $this->lastLevel = $this->lastMessage = null;
        Octopus_Log::write('some-other-log', Octopus_Log::ERROR, __METHOD__);
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
        $this->assertEquals($level, Octopus_Log::getThreshold(), "Threshold is " . Octopus_Log::getLevelName($level));

        $thresholdName = Octopus_Log::getLevelName($level);

        foreach(Octopus_Log::getLevels() as $testLevel) {

            $name = Octopus_Log::getLevelName($testLevel);
            $convenienceMethod = camel_case($name);

            $this->lastLevel = null;
            $this->lastMessage = null;
            $this->lastLog = null;

            Octopus_Log::write(__METHOD__, $testLevel, 'blerg');

            if ($testLevel <= $level) {
                $this->assertEquals($testLevel, $this->lastLevel, "$name logged for threshold $thresholdName (::write)");
                $this->assertEquals('blerg', $this->lastMessage, "$name logged for threshold $thresholdName (::write)");
                $this->assertEquals(__METHOD__, $this->lastLog, "$name logged for threshold $thresholdName (::write)");
            } else {
                $this->assertNull($this->lastLevel, "$name not logged for threshold $thresholdName (::write)");
                $this->assertNull($this->lastMessage, "$name not logged for threshold $thresholdName (::write)");
                $this->assertNull($this->lastLog, "$name not logged for threshold $thresholdName (::write)");
            }

            $this->lastLevel = null;
            $this->lastMessage = null;
            $this->lastLog = null;

            call_user_func(array('Octopus_Log', $convenienceMethod), __METHOD__, 'blerg');

            if ($testLevel <= $level) {
                $this->assertEquals($testLevel, $this->lastLevel, "$name logged for threshold $thresholdName (::$convenienceMethod)");
                $this->assertEquals('blerg', $this->lastMessage, "$name logged for threshold $thresholdName (::$convenienceMethod)");
                $this->assertEquals(__METHOD__, $this->lastLog, "$name logged for threshold $thresholdName (::$convenienceMethod)");
            } else {
                $this->assertNull($this->lastLevel, "$name not logged for threshold $thresholdName (::$convenienceMethod)");
                $this->assertNull($this->lastMessage, "$name not logged for threshold $thresholdName (::$convenienceMethod)");
                $this->assertNull($this->lastLog, "$name not logged for threshold $thresholdName (::$convenienceMethod)");
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

        $logName = 'test_file_logging';

        Octopus_Log::addListener(Octopus_Log::INFO, $logger);
        for($i = 0; $i < 100; $i++) {

            Octopus_Log::write(
                $logName,
                Octopus_Log::WARN,
                <<<END
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
tempor incididunt ut labore et dolore magna aliqua.
END
            );

        }

        $this->assertEquals(100, Octopus_Log::getWriteCount(), '100x write count');

        $file = $this->getPrivateDir() . 'test-log-dir/' . $logName . '.log';
        $this->assertTrue(is_file($file), 'log file exists');

        $size = filesize($file);
        $this->assertTrue($size > 0 && $size <= 6000, "File size between 0 and 6000 (was $size)");

        for($i = 1; $i <= 10; $i++) {

            $num = "0000{$i}";
            $num = substr($num, -3);

            $dir = $this->getPrivateDir() . 'test-log-dir/';
            $file = $dir . $logName . ".{$num}.log";
            if ($i <= 3) {
                $this->assertTrue(is_file($file), "Exists: $file");
            } else {
                $this->assertFalse(is_file($file), "Does not exist: $file");
            }

        }

    }

    function testFileLoggingIsJson() {

        $dir = $this->getPrivateDir() . 'file-logging-is-json';
        $logger = new Octopus_Log_Listener_File($dir);
        $logger->setMaxFileSize(300 * 1024);

        Octopus_Log::addListener($logger);
        Octopus_Log::warn('Here is my test warning');
        Octopus_Log::debug(array('title' => 'foo', 'num' => 42));

        $file = $dir . '/app.log';
        $this->assertTrue(is_file($file), 'log file found');

        $contents = file_get_contents($file);
        $this->assertTrue(!!$contents, 'log file not empty');
        $contents = '[' . trim(trim($contents), ',') . ']';

        $json = json_decode($contents, true);
        $this->assertTrue(!!$json, 'file contents are json');
        $this->assertTrue(is_array($json), 'file contents are an array');
        $this->assertTrue(count($json) > 0, '> 0 things in array');

        foreach($json as &$entry) {
            $this->assertTrue(array_key_exists('time', $entry), 'time key found on entry');
            $this->assertTrue(time() - $entry['time'] < 5, 'entry is in the last 5 seconds');
            unset($entry['time']);

            $trace = $entry['trace'];
            $this->assertTrue(!empty($trace), 'entry has trace');
            unset($entry['trace']);

            unset($entry['id']);
            unset($entry['index']);
        }

        $this->assertEquals(
            array(
                array(
                    'log' => 'app',
                    'level' => 'WARN',
                    'message' => 'Here is my test warning',
                ),
                array(
                    'log' => 'app',
                    'level' => 'DEBUG',
                    'message' => array('title' => 'foo', 'num' => 42),
                )
            ),
            $json
        );

    }


    var $lastLevel = null;
    var $lastMessage = null;
    var $lastLog = null;

    function basicListener($id, $message, $log, $level) {
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

