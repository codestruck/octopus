<?php

class AutoloadingTest extends Octopus_App_TestCase {

    /**
     * @dataProvider getClassTestDirs
     */
    function testAutoLoadClass($prefix, $classDir) {

    	$dir = 'AutoloadingTest/X/';
        $class = str_replace('/', '_', $dir) . md5(uniqid());
        $file = $classDir . str_replace('_', '/', $class) . '.php';
        $class = $prefix . $class;

        recursive_touch($file);
        file_put_contents(
            $file,
            <<<END
<?php

    class $class {

        public function doSomething() { return true; }

    }

?>
END
        );

        $this->assertTrue(class_exists($class), "$class exists");

        $instance = new $class();
        $this->assertTrue($instance->doSomething());

        recursive_delete($dir);

    }

    /**
     * @dataProvider getControllerTestData
     */
    function testAutoLoadController($class, $file) {

        $id = md5(uniqid());
        $class = preg_replace('/_*Controller$/i', $id . 'Controller', $class);
        $file = preg_replace('#\.php$#', $id . '.php', $file);

        recursive_touch($file);
        file_put_contents(
            $file,
            <<<END
<?php
class $class extends Octopus_Controller {

    public function testAction() {
        return true;
    }

}
?>
END
        );

        $this->assertTrue(class_exists($class), "$class exists");

        $instance = new $class();
        $this->assertTrue($instance->testAction());

        unlink($file);
    }

    function getControllerTestData() {

        $o = $this->getOctopusDir() . 'controllers/';
        $s = $this->getSiteDir() . 'controllers/';

        return array(

            array('TestController',         $o . 'Test.php'),
            array('Subdir_TestController',     $o . 'Subdir_Test.php'),
            array('Subdir_Test_Controller', $o . 'Subdir_Test.php'),
            //array('Subdir_Test_Controller', $o . 'Subdir/Test.php'),

        );

    }

    function getClassTestDirs() {

        $o = $this->getOctopusDir();
        $s = $this->getSiteDir();

        return array(
            array('Octopus_',     $o . 'includes/classes/'),
            array('',             $o . 'includes/classes/'),
            array('Octopus_',     $s . 'classes/'),
            array('',             $s . 'classes/')
        );
    }
}


?>