<?php

Octopus::loadClass('Octopus_App_TestCase');
Octopus::loadClass('Octopus_Controller_Api');

class TestApiController extends Octopus_Controller_Api {

    var $protect = array('protectedAddMember');

    public function addMember($name, $password, $favoriteColor = 'blue') {
        return compact('name', 'password', 'favoriteColor');
    }

    public function protectedAddMember($name, $password, $favoriteColor = 'blue') {
        return compact('name', 'password', 'favoriteColor');
    }

}

class ApiControllerTest extends Octopus_App_TestCase {

    function testSuccessfulCall() {

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new TestApiController($app, new Octopus_Request($app,''), $resp);

        $args = array('name' => 'Matt', 'password' => 'test', 'favoriteColor' => 'green');
        $data = $controller->__execute('add-member', $args);

        $this->assertEquals($args, $data);
    }

    function testMissingArgsCall() {

        $app = $this->startApp();

        foreach(array('name', 'password') as $missingArg) {

            $args = array('name' => 'Matt', 'password' => 'test', 'favoriteColor' => 'green');
            unset($args[$missingArg]);

            $resp = new Octopus_Response(true);
            $controller = new TestApiController($app, new Octopus_Request($app,''), $resp);

            $data = $controller->__execute('add-member', $args);

            $this->assertEquals(
                array('success' => false, 'errors' => array($missingArg => "$missingArg is required.")),
                $data
            );

        }

    }

    function testApiKeyRequirement() {

        $app = $this->startApp();
        $resp = new Octopus_Response(true);

        $controller = new TestApiController($app, new Octopus_Request($app,''), $resp);


        $result = $controller->__execute('protected-add-member', array('name' => 'Matt', 'password' => 'test', 'favoriteColor' => 'green'));

        $this->assertEquals(
            array('success' => false, 'errors' => array('Invalid api key.')),
            $result
        );

        $result = $controller->__execute('protected-add-member', array('octopus_api_key' => octopus_api_key(), 'name' => 'Matt', 'password' => 'test', 'favoriteColor' => 'green'));
        $this->assertEquals(array('name' => 'Matt', 'password' => 'test', 'favoriteColor' => 'green'), $result);

    }

}
