<?php

class SysController extends Octopus_Controller {

    public function _before($action, $args) {

        /* Restrict access to the system actions when not running in DEV mode */

        if ($action === 'welcome') {
            return true;
        }

        if (!$this->app->isDevEnvironment()) {
            $this->response->forbidden();
            return false;
        }
    }

    public function about() {

        return array(
            'settings' => $this->app->getAllSettings()
        );

    }

    public function index() {

        $this->redirect('/sys/about');

    }

    /**
     * Installs the system.
     */
    public function install($status = '') {

        $result = array(
            'installed' => false
        );

        if (strtolower($status) == 'now') {

            $result['result'] = $this->app->install();
            $this->redirect('/sys/installed');

        }

        return $result;
    }

    /**
     * Action for demoing / testing app logging.
     */
    public function logAction() {

    	$form = new Octopus_Html_Form('log');
    	$form->add('select', 'level')
    		->addOptions(array(
    			Octopus_Log::LEVEL_DEBUG => 'Debug',
    			Octopus_Log::LEVEL_INFO => 'Info',
    			Octopus_Log::LEVEL_WARN => 'Warn',
    			Octopus_Log::LEVEL_ERROR => 'Error',
    			Octopus_Log::LEVEL_FATAL => 'Fatal',
    		));
    	$form->add('log');
    	$form->add('textarea', 'message');
    	$form->addButton('submit', 'Log');

    	if ($form->wasSubmitted()) {

    		$listener = new Octopus_Log_Listener_Html();
    		Octopus_Log::addListener('test', Octopus_Log::LEVEL_DEBUG, $listener);

    		$listener = new Octopus_Log_Listener_File(OCTOPUS_PRIVATE_DIR . 'log');
    		Octopus_Log::addListener($listener);

    		$values = $form->getValues();
    		$values['log'] = preg_replace('/[^a-z0-9_-]/i', '', $values['log']);
    		if (empty($values['log'])) $values['log'] = 'test';

    		Octopus_Log::write($values['log'], $values['level'], $values['message']);

    	} else {
    		$form->setValue('log', 'test');
    	}

    	return compact('form');
    }

    /**
     *
     */
    public function settings() {

        return array(

            'settings' => $this->app->getSettings()

        );

    }

    public function welcome() {
    }

}
