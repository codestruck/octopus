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

        // For e.g. 301 redirects we don't need to bother rendering
        if (!$response->shouldContinueProcessing()) {
            return;
        }

        if (!is_array($data)) {
            $data = array('data' => $data);
        }

        $renderer = $this->app->getRenderer();

        $contents = $renderer->render($controller, $data, $request, $response);

        if ($controller->cache) {
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

    	$timestamp = null;

    	// For HTML requests, we can add a timestamp in as an HTML comment to
    	// aid in debugging (like, if this comment is present, you are looking
    	// at a cached version of the page).
    	if ($response->isHtml()) {
    		$timestamp = '<!-- ots' . time() . ' -->';
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
	    			if ($timestamp) fputs($fp, $timestamp);
	    			fclose($fp);
	    		}
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
