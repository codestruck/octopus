<?php

class Mustaches_Controller extends Octopus_Controller_Api {

    function _default($action = '') {

        $mustacheDir = SITE_DIR . 'views/' . $action;
        $templates = array();

        foreach(safe_glob($mustacheDir . '/*.mustache') as $file) {
            $base = basename($file);
            $base = str_replace('.mustache', '', $base);
            $templates[] = "$base:" . json_encode(file_get_contents($file));
        }

        $templates = join(",\n", $templates);

        $js = <<<END
(function() {

if (!window.Hogan) {
    throw 'Please include Hogan.js before /mustaches/';
}

var templates = {
$templates
};

var compiled = {
};

window.MUSTACHES = {
    compiled: function(key) {

        if (!compiled[key]) {
            compiled[key] = Hogan.compile(templates[key]);
        }

        return compiled[key];
    }
};

})();

END;

        $this->response->addHeader('Content-Type', 'application/javascript');
        $this->response->append($js);
        $this->response->stop();

    }

}
