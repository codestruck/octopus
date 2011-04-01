<?php

/**
 * Base class for implementing an Octopus controller.
 */
abstract class SG_Controller {

    /**
     * If the action specified does not exist on this class, defaultAction()
     * gets called.
     */
    public function defaultAction() {
        return $this->render();
    }

    /**
     * Called at the end of each action to render the view HTML.
     * @return String View html.
     */
    public function render($data = array(), $options = null) {
    }

    /**
     * Called at the end of each action to render the controller data as
     * JSON.
     */
    public function renderJson($data = array(), $options = null) {

        header('Content-type: application/json');

        echo json_encode($data);

        exit();

    }


}

?>
