<?php

class SysController extends SG_Controller {

    public function _before($action, $args) {

        /* Restrict access to the system actions when not running in DEV mode */

        if (!$this->app->isDevEnvironment()) {
            $this->response->forbidden();
            return false;
        }
    }

    public function about() {

        return array(
            'options' => $this->app->getOptions(),
            'settings' => $this->app->getSettings()->toArray()
        );

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
     *
     */
    public function settings() {


        return array(

            'settings' => $this->app->getSettings()

        );

    }

}

?>