<?php

class Octopus_DB_Schema_Model {

    public static function makeTable($modelClass) {

    	if ($modelClass instanceof Octopus_Model) {
    		$modelClass = get_class($modelClass);
    	}

		$schema = new Octopus_DB_Schema();

    	call_user_func(array($modelClass, 'migrate'), $schema, $modelClass);

    }

}

