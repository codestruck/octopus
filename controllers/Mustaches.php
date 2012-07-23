<?php

class Mustaches_Controller extends Octopus_Controller_Api {

    function _default($action = '') {
        if (get('production')) {
            $js = $this->getProduction($action);
        } else {
            $js = $this->getDev($action);
        }

        $this->response->addHeader('Content-Type', 'application/javascript');
        $this->response->append($js);
        $this->response->stop();
    }

    function getProduction($action) {

        $compiled = `node octopus/build/mustache_compile.js site/views/$action`;
        if (!$compiled) {
            return $this->getDev($action);
        }

        $js = <<<END
(function() {

if (!window.Hogan) {
    throw 'Please include Hogan.js before /mustaches/';
}

var compiled = {
$compiled
};

window.MUSTACHES = {
    compiled: function(key) {
        return compiled[key];
    }
};

})();

END;

        return $js;
    }

    function getDev($action) {

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

        return $js;

    }

}
