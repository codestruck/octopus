<?php

class SysController extends SG_Controller {

    public function _before($action, $args) {

        /* Restrict access to the system actions when not running in DEV mode */

        if (!$this->getApp()->isDevEnvironment()) {
            $this->getResponse()->forbidden();
            return false;
        }
    }

    public function about() {

        return array(
            'options' => $this->getApp()->getOptions()
        );

    }

}

?>