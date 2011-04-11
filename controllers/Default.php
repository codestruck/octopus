<?php

/**
 * Controller used when none is specified.
 */
class DefaultController extends SG_Controller {

    public function defaultAction($action, $args) {
        return $args;
    }

    /**
     * Called when there's an error.
     */
    public function error($args) {

    }


}
