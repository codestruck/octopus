<?php


    echo "Congrats! You've got Project Octopus Running!";

    echo "<p>";
    echo a('?dump_r=1', "Test dump_r redirect canceling");
    echo "</p>";

    if (get_numeric('dump_r')) {

        $myVar = array('test variable' => true);
        dump_r($myVar);

        redirect('should_be_canceled');
    }


?>
