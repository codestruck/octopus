<?php

    $installUrl = u('install/now');

    if ($installed) {

        $output = <<<END
<h1>Installed</h1>
<p>
    The DB has been set up.
</p>
END;

        echo $output;

    } else {

        echo <<<END
<h1>Install</h1>
<p>
    <a href="$installUrl">Click here to install</a>
</p>
END;


    }

