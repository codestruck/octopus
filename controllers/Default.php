<?php

/**
 * Controller used when none is specified.
 */
class DefaultController extends SG_Controller {

    public function defaultAction($args) {
        return $this->render('about-octopus', array('args' => $args));
    }



}
