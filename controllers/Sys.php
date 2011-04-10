<?php

class SysController extends SG_Controller {

    public function defaultAction($args) {

        if (!$this->getApp()->isDevEnvironment()) {
            $this->getResponse()->forbidden();
        }

    }

}

?>