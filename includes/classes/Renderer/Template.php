<?php

/**
 * A renderer that renders an Octopus_Response using the view/layout/theme
 * system.
 */
class Octopus_Renderer_Template extends Octopus_Renderer {

	/**
	 * @param  String|Array One or more view identifiers. The first one found
	 * is used.
	 * @param  Octopus_App $app
	 * @return String|Boolean Full path to the view file to render, or false
	 * if not found.
	 */
	public function getViewFile($view, Octopus_App $app) {
		$finder = $this->createViewFinder($app);
		return $finder->find($view);
	}

	/**
	 * (This method is primarily used for testing)
	 * @return String|Boolean The full path to the view file to render for the
	 * given request, or false if not found.
	 */
	public function getViewFileForRequest(Octopus_Request $request) {

		$app = $request->getApp();
		$view = $this->getViewForRequest($request);

		return $this->getViewFile($view, $app);
	}

	/**
	 * @see Octopus_Renderer::renderContent
	 */
	protected function renderContent(Octopus_Response $response) {

		$request = $response->getRequest();

		// Include the theme.php file for the current theme
		$this->includeTheme($request->app, $response);

		// Build the array of data to pass to whichever templating engine
		// we end up using (smarty, php, mustache)
		$viewData = $response->getValues();
		$this->augmentViewData($viewData, $request, $response);

		// Render the view for whatever action was executed...
		$viewContent = $this->renderView($request, $response, $viewData);

		// ...and then render that into the layout being used
		$layoutContent = $this->renderLayout($request, $response, $viewContent, $viewData);

		return $layoutContent;

	}

	/**
	 * Renders the full page layout and returns the result.
	 */
	protected function renderLayout(Octopus_Request $request, Octopus_Response $response, $viewContent, Array &$data) {

		$dirs = $this->getLayoutSearchDirs($request, $response);
		$finder = new Octopus_Renderer_Template_Finder($dirs);
		$file = $finder->find($response->layout);

		if (!$file) {

			if (Octopus_Log::isDebugEnabled()) {
				$dirs = implode(', ', $dirs);
				Octopus_Log::debug('render', "No layout found for response. \$response->layout is {$response->layout}, \$dirs is [$dirs]");
			}

			return $viewContent;
		}

	    $data['view_content'] = $viewContent;

	    $engine = Octopus_Renderer_Template_Engine::createForFile($file);

	    return $engine->render($data);
	}

	protected function renderView(Octopus_Request $request, Octopus_Response $response, Array &$data) {

		if ($response->hasContent()) {

			// Content has been directly appended to the response, so interpret
			// that as the "view"

    		Octopus_Log::debug('render', "Substituting appended content for view content.");

			return $response->getContent();

		}

		$app = $request->getApp();

		if ($response->view) {
			// Actions can set the view explicitly on their response (the easy way)...
			$view = $response->view;
		} else {
			// ... or we can infer the view from the path they requested (the hard way)
			$view = $this->getViewForRequest($request);
		}

		$finder = $this->createViewFinder($app);
		$file = $finder->find($view);

		if (!$file) {

			// No view found == 404
			if ($app->DEV) {

				// In DEV, the default Octopus 404 view renders a helpful list
				// of the places we looked for the view to aid in debugging.
				// Here we populate that list.

			    $data['path'] = $app->URL_BASE . $request->getPath();
			    $data['resolved_path'] = $app->URL_BASE . $request->getResolvedPath();

			    $paths = $finder->getCandidateFiles($view);
			    $paths = preg_replace('/^' . preg_quote($app->ROOT_DIR, '/') . '/i', '', $paths);
			    $data['view_paths'] = $paths;

			}

			// Make sure we send a 404 status
			if ($response->active) $response->notFound();

			$file = $finder->find('404');

			if (!$file) {
				throw new Octopus_Exception("No '404' view found.");
			}

		}

		$engine = Octopus_Renderer_Template_Engine::createForFile($file);
		return $engine->render($data);
	}

	protected function createViewFinder(Octopus_App $app) {

		// the finder's job is to take all the potential view names, directories,
		// and file extensions and return the best possible match.
		return new Octopus_Renderer_Template_Finder(array(
			// Base directories searched for views
			$app->SITE_DIR . 'views/',
			$app->OCTOPUS_DIR . 'views/',
		));

	}

	/**
	 * @return Array Directories to search for layouts for $response. If
	 * $response has a theme, this will be $THEME_DIR/layouts/ (also,
	 * $THEME_DIR/templates/html/ for legacy purposes).
	 */
	protected function getLayoutSearchDirs(Octopus_Request $request, Octopus_Response $response) {

		$result = array();
		$app = $request->getApp();

		$siteDir = $app->SITE_DIR;
		$octopusDir = $app->OCTOPUS_DIR;
		$theme = $response->getTheme();

		$result = array();

		// First, look in the site's theme dir
		if ($theme) {
			$result[] = $siteDir . "themes/{$theme}/layouts/";
			$result[] = $siteDir . "themes/{$theme}/templates/html/";
		}

		// Then site's root
		$result[] = $siteDir . 	'layouts/';
		$result[] = $siteDir . 	'templates/html/';

		// Then octopus's theme dir
		if ($theme) {
			$result[] = $octopusDir . "themes/{$theme}/layouts/";
			$result[] = $octopusDir . "themes/{$theme}/templates/html/";
		}

		// Then octopus's root
		$result[] = $octopusDir . 'layouts/';
		$result[] = $octopusDir . 'templates/html/';

		return $result;
	}

	/**
	 * @return Array An array of potential view identifiers for $request. These
	 * are generated based on the requested path, arguments, etc. The logic
	 * here is fairly convoluted and makes my brain hurt.
	 */
	protected function getViewForRequest(Octopus_Request $request) {

		/*
		 * Search order:
		 *
		 * ControllerNameController->action($arg2, $arg2)
		 * /controller/name/action/arg1/arg2
		 *
		 * If controller + action exist:
		 *
		 * 		views/controller/name/action/arg1/arg2
		 * 		views/controller/name/action/arg1
		 * 		views/controller/name/action
		 * 		views/controller/action
		 * 		views/action (allow controllers w/ similar actions to reuse views)
		 *
		 * If controller + action don't exist:
		 *
		 * 		views/controller/name/action/arg1/arg2
		 *   	(no fallback)
		 */

		self::getViewPathComponents($request, $controllerPathComponents, $actionPathComponents);

		foreach($controllerPathComponents as $controllerPath) {

			$working = $actionPathComponents;
			while($working) {
				$names[] = trim($controllerPath . '/' . implode('/', $working), '/');
				array_pop($working);
			}

		}

		// Now take all those possible paths and combine them into a big list
		// of uniques, with variations based on underscoring vs. camel case
		// etc.

		$result = array();

		foreach($names as $name) {

		    $underscore = $name;
		    $camel = camel_case($name, true);
		    $dash = dashed($name);
		    $smooshed = strtolower($camel);

		    $result[$underscore] = true;
		    $result[$camel] = true;
		    $result[$dash] = true;
		    $result[$smooshed] = true;
		}

		$result = array_keys($result);

		return $result;

	}

	protected function includeTheme(Octopus_App $app, Octopus_Response $response) {

		$theme = $response->theme;
		if (!$theme) return;

		$themeDir = $app->SITE_DIR . 'themes/' . $theme . '/';

		$themeDotPHP = $themeDir . 'theme.php';

		if (is_file($themeDotPHP)) {
			Octopus::requireOnce($themeDotPHP);
		}

	}

	/**
	 * Adds standard metadata to $data (to be passed to the rendering engine).
	 * @uses ::getExtraViewData()
	 */
    private function augmentViewData(Array &$data, Octopus_Request $request, Octopus_Response $response) {

        $extra = $this->getExtraViewData($request, $response);
        foreach($extra as $key => $value) {

        	if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }

        }

    }

    /**
     * Figures out different combinations of path elements to combine when
     * searching for views.
     */
    private static function getViewPathComponents(Octopus_Request $req, &$controllerPathComponents, &$actionPathComponents) {

    	$app = $req->getApp();

		// Because there are different supported variations for controller class
		// naming (ControllerNameSomethingController,
		// Controller_Name_Something_Controller, etc.), base the view search
		// path on the controller's file name because it is safe to break on
		// underscores

		if ($req->isDefaultController()) {
			$controllerFile = '';
		} else {

			$app = $req->getApp();
			$controllerFile = $req->getControllerFile();
			$controllerFile = preg_replace('/^(' . preg_quote($app->SITE_DIR, '/') . '|' . preg_quote($app->OCTOPUS_DIR, '/') . ')controllers\//', '', $controllerFile);
			$controllerFile = preg_replace('/\.php$/i', '', $controllerFile);

		}

        if (strpos($controllerFile, '/') === false) {
            $controllerPathComponents = explode('_', $controllerFile);
        } else {
            $controllerPathComponents = explode('/', $controllerFile);
        }

        $controllerPathComponents = array_map('underscore',   $controllerPathComponents);
        // $controllerPathComponents is now something like array('controller', 'name')

        $controller = $req->getController();

		if ($controller->__actionExists($req->getAction())) {

			// The action exists on the controller, so be slightly more liberal
			// about what paths we will search for view files

			$components = $controllerPathComponents;
			$controllerPathComponents = array();

			// Build a list of potential search paths, including '' as the last
			// element to allow falling back at the root level

			for($i = count($components); $i >= 0; $i--) {
				$controllerPathComponents[] =
					implode('/', array_slice($components, 0, $i));
			}

			// go from most specific (action + all args) to least (just action)
	    	$actionPathComponents = array($req->getAction());
			foreach($req->getActionArgs() as $arg) {
				$actionPathComponents[] = $arg;
			}

			if (count($actionPathComponents) > 10) $actionPathComponents = array_slice($actionPathComponents, 0, 10);
			$actionPathComponents = array_map('underscore', $actionPathComponents);


		} else  {

			$controllerPathComponents = array(implode('/', $controllerPathComponents));

			// require full action + args to locate view file
			$parts = $req->getActionArgs();
			array_unshift($parts, $req->getAction());

			$parts = array_map('underscore', $parts);

			$actionPathComponents = array(implode('/', $parts));

		}

     }

    /**
     * @return Array Any extra data to add to the $data array before it is
     * passed to the template renderer. Existing data will NOT be overwritten
     * by the result of this method.
     *
     * The default implementation of this function defines the following
     * extra keys:
     *
     *    _GET -   $_GET
     *    _POST -  $_POST
     *
     *    HOST - Current hostname, normalized to remove 'www.' and in all
     *           lowercase.
     *
     *    QS -         $_GET as a string with no '?' at the beginning.
     *    FULL_QS -    $_GET as a string with a '?' at the beginning.
     *    QS_AND -    Character to use to build on FULL_QS (if FULL_QS is
     *                not '', this is '&'. Otherwise, it is '?').
     *
     *    URL_BASE -    Prefix for the app's public root.
     *
     *    URI -            The full requested URI
     *    URI_AS_CLASS -    The URI escaped for use as a css class
     *
     *    ROOT_DIR
     *    SITE_DIR
     *    OCTOPUS_DIR
     *
     */
    protected function getExtraViewData(Octopus_Request $request, Octopus_Response $response) {

    	// TODO: This is a gd mess. Do views really need $_GET and $_POST? Isn't
    	// the idea that we keep direct access to that stuff out of views?

    	$app = $request->getApp();
        $result = array();

        // Full querystring
        $qs = $_GET;
        $pathArg = $app->getOption('path_querystring_arg');
        unset($qs[$pathArg]);
        $queryString = http_build_query($qs);

        $result['_GET'] = $qs;
        $result['_POST'] =& $_POST;

        // Host
        if (isset($_SERVER['HTTP_HOST'])) {
            $result['HOST'] = strtolower(preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST']));
        }

        // Query string
        $result['QS'] = $queryString;
        $result['FULL_QS'] = ($queryString ? '?' : '') . $queryString;
        $result['QS_AND'] =($queryString ? '&amp;' : '?');

        $result['ROOT_DIR'] = $app->getOption('ROOT_DIR');
        $result['SITE_DIR'] = $app->getOption('SITE_DIR');
        $result['OCTOPUS_DIR'] = $app->getOption('OCTOPUS_DIR');

        $result['URL_BASE'] = $app->getOption('URL_BASE');

        $result['URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $request->getPath();
        $result['URI_AS_CLASS'] = to_css_class(str_replace('/', '', $request->getPath()));

        $controller = preg_replace('/-?Controller$/', '', $request->getControllerClass());
        $controller = strtolower(dashed($controller));

        $action = $request->getAction();
        $action = strtolower(dashed($action));

        $result['CONTROLLER'] = $controller;
        $result['ACTION'] = $action;
        $result['ACTION_AS_CLASS'] = $controller . '-' . $action;

        $result['SETTINGS'] = $app->getSettings();

        if (class_exists('Octopus_Html_Page')) {

            $p = Octopus_Html_Page::singleton();

            // Extra tags and metadata
            $result['HEAD_CONTENT'] = $p->head->content;
            $result['HEAD'] = $p->head;

            $result['TITLE'] = $p->getTitle();
            $result['FULL_TITLE'] = $p->getFullTitle();

            $result['PAGE'] = $p;
        }

        $result['DEV'] = $app->DEV;
        $result['LIVE'] = $app->LIVE;
        $result['STAGING'] = $app->STAGING;

        return $result;
    }

}
