<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class DirectoryMonitorTest extends Octopus_App_TestCase {

	function testMonitorDirectoryCatchNewFile() {

		$dir = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9-]/i', '-', __METHOD__) . '-' . time() . '/';
		mkdir($dir);

		$monitor = new Octopus_Directory_Monitor($dir);

		$this->assertEquals(
			array(),
			$monitor->getChangedFiles()
		);

		touch($dir . 'test.txt');
		$this->assertEquals(array($dir . 'test.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

		$monitor->reset();
		$this->assertEquals(array($dir . 'test.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

	}

	function testMonitorDirectoryCatchNewFileDeep() {

		$dir = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9-]/i', '-', __METHOD__) . '-' . time() . '/';
		mkdir($dir);

		$subdir = $dir . 'subdir/';
		mkdir($subdir);

		$monitor = new Octopus_Directory_Monitor($dir);

		$this->assertEquals(
			array(),
			$monitor->getChangedFiles()
		);

		touch($subdir . 'test.txt');
		$this->assertEquals(array($subdir . 'test.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

		$monitor->reset();
		$this->assertEquals(array($subdir . 'test.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

		touch($dir . 'test.txt');
		$this->assertEquals(array($dir . 'test.txt'), $monitor->getChangedFiles());

		$monitor->reset();

		$expected = array($subdir . 'test.txt', $dir . 'test.txt');
		$actual = $monitor->getChangedFiles();

		sort($expected);
		sort($actual);

		$this->assertEquals($expected, $actual);

	}

	/**
	 * @group slow
	 */
	function testMonitorDirectoryCatchChangedFile() {

		$dir = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9-]/i', '-', __METHOD__) . '-' . time() . '/';
		mkdir($dir);

		$monitor = new Octopus_Directory_Monitor($dir);

		$this->assertEquals(array(), $monitor->getChangedFiles());

		touch($dir . 'test.txt');
		$this->assertEquals(array($dir . 'test.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

		sleep(2);


		touch($dir . 'test.txt');
		$this->assertEquals(array($dir . 'test.txt'), $monitor->getChangedFiles());



	}

	/**
	 * @group slow
	 */
	function testMonitorDirectoryCatchChangedFileDeep() {

		$dir = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9-]/i', '-', __METHOD__) . '-' . time() . '/';
		mkdir($dir);

		$subdir = $dir . 'subdir/';
		mkdir($subdir);

		$monitor = new Octopus_Directory_Monitor($dir);

		$this->assertEquals(array(), $monitor->getChangedFiles());

		touch($subdir . 'test.txt');
		$this->assertEquals(array($subdir . 'test.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

		sleep(2);

		touch($subdir . 'test.txt');
		$this->assertEquals(array($subdir . 'test.txt'), $monitor->getChangedFiles());



	}

	function testMonitorDirectoryNotDeep() {

		$dir = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9-]/i', '-', __METHOD__) . '-' . time() . '/';
		mkdir($dir);

		$subdir = $dir . 'subdir/';
		mkdir($subdir);

		$monitor = new Octopus_Directory_Monitor();
		$monitor->addDirectory($dir, false);

		touch($subdir . 'test.txt');
		$this->assertEquals(array(), $monitor->getChangedFiles());

		touch($dir . 'test.txt');
		$this->assertEquals(array($dir . 'test.txt'), $monitor->getChangedFiles());

	}

	function testRegexFilter() {

		$dir = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9-]/i', '-', __METHOD__) . '-' . time() . '/';
		mkdir($dir);

		$monitor = new Octopus_Directory_Monitor($dir);
		$monitor->addFilter('/foo/');
		$this->assertEquals(array('/foo/'), $monitor->getFilters());
		$monitor->removeFilter('blerg');
		$this->assertEquals(array('/foo/'), $monitor->getFilters());
		$monitor->removeFilter('/foo/');
		$this->assertEquals(array(), $monitor->getFilters());
		$monitor->addFilter('/foo/');

		$this->assertEquals(array(), $monitor->getChangedFiles());

		touch($dir . 'test.txt');
		touch($dir . 'foo.txt');

		$this->assertEquals(array($dir . 'foo.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

		$monitor->reset();
		$this->assertEquals(array($dir . 'foo.txt'), $monitor->getChangedFiles());
		$this->assertEquals(array(), $monitor->getChangedFiles());

		$monitor->clearFilters();

		$expected = array($dir . 'foo.txt', $dir . 'test.txt');
		$actual = $monitor->getChangedFiles();

		sort($expected);
		sort($actual);

		$this->assertEquals($expected, $actual);

	}

}