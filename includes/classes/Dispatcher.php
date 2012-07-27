<?php

/**
 * Class responsible for locating controllers and rendering views.
 */
class Octopus_Dispatcher {

    private $app;

    public function __construct(Octopus_App $app) {
        $this->app = $app;
    }

    /**
     * Executes the action(s) for a request and renders the result.
     */
    public function handleRequest(Octopus_Request $request, Octopus_Response $response) {

        $path = $request->getResolvedPath();

        $controller = $request->getController();

        $this->prepareController($controller, $request, $response);

        $data = $controller->__execute(
            $request->getAction(),
            $request->getActionArgs()
        );

        if ($data !== null) {

	        if (!is_array($data)) {
	            $data = array('data' => $data);
	        }

	        if ($response->active) $response->set($data);

	    }

        // For e.g. 301 redirects we don't need to bother rendering
        if (!$response->active) {
            return;
        }

        $contents = $response->render(true);

        if ($controller->cache && $response->isHtml()) {
        	$this->saveFullCacheFile($request, $response, $contents);
        }

    }

    /**
     * Writes a cache file out for the given request, WP Super Cache style.
     * Octopus's .htaccess picks this up and serves it out if found.
     * @return Boolean True if a cache file was saved, false otherwise.
     */
    protected function saveFullCacheFile(Octopus_Request $request, Octopus_Response $response, $content) {

    	if (!$this->app->isFullCacheEnabled()) {
    		return false;
    	}

    	// Only GET requests can be cached
    	if (!$request->isGet()) {
    		return false;
    	}

    	// Only GET requests w/o querystring can be cached
    	if (!empty($_GET)) {
    		return false;
    	}

    	// The cache dir must exist
    	$cacheDir = $this->app->OCTOPUS_CACHE_DIR;

    	if (!is_dir($cacheDir)) {
    		Octopus_Log::warn('OCTOPUS_CACHE_DIR does not exist: ' . $cacheDir);
    		return false;
    	}

    	$dir = $cacheDir . 'full/' . $request->getPath();
    	$dir = rtrim($dir, '/') . '/';

    	// We must be able to create the subdir in the cache dir
    	if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
    		Octopus_Log::warn("Could not create full cache dir: $dir");
    		return false;
    	}

    	// Since full cache is only supported for text/html, we can append
    	// an HTML comment with a timestamp to aid in debugging.
		$timestamp = '<!-- ots' . time() . ' -->';
		$DEV = $this->app->DEV;

		if ($DEV) {

			// NOTE: By default, Octopus_App::isFullCacheEnabled() returns false
			// for DEV. So in practice, this notice won't end up getting written
			// out. But it is left here to maybe help debugging.

			$timestamp .= <<<END

<div style="background: #800; color: #fff; padding: 10px; text-align: center; position: fixed; top: 0; left: 0; width: 100%; opacity: 0.8;">
	<div style="font-size: 14px; font-weight: bold; margin-bottom:10px;">THIS IS A CACHED PAGE</div>
	If not running in DEV, <a style="color: #fff; text-decoration: underline;" href="?clear-full-cache">go here to clear the cache</a>.
</div>
END;
		}

    	$fp = fopen($dir . 'index.html', 'w');
    	if ($fp) {
    		fputs($fp, $content);
    		if ($timestamp) fputs($fp, $timestamp);
    		fclose($fp);
    	}

    	// Write out a gzipped version that Apache can serve up directly to
    	// clients that support it. Like, all clients everywhere.
    	if (function_exists('gzencode')) {

    		$content = gzencode($content);

    		if ($content !== false) {

    			$fp = fopen($dir . 'index.html.gz', 'w');

	    		if ($fp) {
	    			fputs($fp, $content);

	    			if ($timestamp) {

	    				if ($DEV) {
	    					$timestamp = str_replace('CACHED', 'GZIPPED, CACHED', $timestamp);
	    				}

	    				fputs($fp, $timestamp);
	    			}

	    			fclose($fp);
	    		}
	    	}

	    }

	    if ($DEV) {

	    	// Leave a marker on the filesystem indicating that there are
	    	// full-cache pages generated in DEV. Octopus_App, when running
	    	// in LIVE or STAGING, will check for the presence of this file
	    	// and clear the cache if it exists (in case you switch from
	    	// DEV -> STAGING or DEV -> LIVE on a production server).
	    	$file = $this->app->OCTOPUS_CACHE_DIR . 'full/.generated_in_dev';

	    	if (!@file_put_contents($file, '1')) {
	    		Octopus_Log::warn("Could not save the .generated_in_dev file in the full cache dir. Disabling full cache writing.");
	    		$this->app->clearFullCache();
	    	}

	    }

	    return true;

    }

    private function prepareController($controller, $request, $response) {
        $controller->app = $this->app;
        $controller->request = $request;
        $controller->response = $response;
        $controller->cache = false;
        $controller->view = null;
        $controller->template = null;
        $controller->theme = null;
    }

}

?>
