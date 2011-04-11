<?php

/**
 * Controller used when none is specified.
 */
class DefaultController extends SG_Controller {

    // This is set by SG_Dispatcher when using the default controller.
    public $requestedController = 'Default';

    public function defaultAction($action, $args) {

        // Reassemble a path and see if we can match it to a view /  or to
        // a content file
        $path = $this->requestedController . '/' . $action;
        if (!empty($args)) {
            $path .= '/' . implode('/', $args);
        }

        $file = $this->getApp()->getFile(
            'content/' . $path,
            null,
            array(
                'extensions' => array('.php', '.tpl', '.html', '.txt')
            )
        );

        if ($file) {
            $this->setView($file);
        }

    }

    /**
     * Called when there's an error.
     */
    public function error($args) {

    }


}
