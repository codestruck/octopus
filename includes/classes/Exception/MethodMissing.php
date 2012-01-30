<?php

class Octopus_Exception_MethodMissing extends Octopus_Exception {

    public function __construct($obj, $method, $args = array(), $description = '') {

        $message = "Method not found";

        if ($obj && is_object($obj)) {
            $message .= ' on class ' . get_class($obj);
        }

        $message .= ": $method";

        if ($description) {
            $message .= " ($description)";
        }

        parent::__construct($message);
    }

}

?>