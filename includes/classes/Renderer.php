<?php

/**
 * Class responsible for finding views and rendering them.
 */
class Octopus_Renderer {

	protected $app;

	/**
	 * Creates a new renderer for the given app instance.
	 */
	public function __construct(Octopus_App $app) {
		$this->app = $app;
	}

    /**
     * @return Array An array with the following keys:
     *
     *  <dl>
     *      <dt>file</dt>
     *      <dd>Full path to the view file.
     *      <dt>found</dt>
     *      <dd>Whether the requested view was actually found.
     *  </dl>
     */
    public function findView(Octopus_Request $request, $controller, $view = null) {

        $file = $this->findViewFile($request, $controller, $view);
        $found = !!$file;

        if (!$file) {
            $file = $this->getViewNotFoundViewFile($request, $controller);
        }

        return compact('file', 'found');
    }

    /**
     * @return Array An array of all the paths a view for $request could live,
     * in order of priority.
     */
    public function getViewPaths(Octopus_Request $request, Octopus_Controller $controller) {
        $paths = $this->internalGetViewPaths($request, $controller, null, false);
        return array_filter($paths, 'trim');
    }

	/**
     * Renders out the result of an action.
     * @param $controller An Octopus_Controller instance.
     * @param $data Array The result of executing the action on the controller.
     * @param $request Octopus_Request
     * @param $response Octopus_Response
     */
    public function render(Octopus_Controller $controller, Array $data, Octopus_Request $request, Octopus_Response $response) {

        $this->loadTheme($request);

        $viewContent = $this->renderView($controller, $request, $response, $data);
        $templateContent = $this->renderTemplate($controller, $request, $response, $viewContent, $data);
        $response->append($templateContent);

    }

    protected function loadTheme(Octopus_Request $request) {
        
        $app = $this->app;
        $theme = $app->getTheme($request);
        if ($theme) {
            
            foreach(array('SITE_DIR', 'OCTOPUS_DIR') as $dir) {
                $dir = $app->getOption($dir);
                $file = $dir . 'themes/' . $theme . '/theme.php';
                if (is_file($file)) {
                    self::requireOnce($file);
                }
            }

        }

    }

    private static function requireOnce($file) {
        require_once($file);
    }

    /**
     * Renders the full page template and returns the result.
     */
    protected function renderTemplate(Octopus_Controller $controller, Octopus_Request $request, Octopus_Response $response, $viewContent, Array $data) {
        
        $templateFile = $this->findTemplateForRender($controller, $request);

        if (!$templateFile) {
            return $viewContent;
        }

        $this->augmentViewData($data);
        $data['view_content'] = $viewContent;

        $templateRenderer = Octopus_Template_Renderer::createForFile($templateFile);
        $templateContent = $templateRenderer->render($data);

        unset($data['view_content']);

        return $templateContent;
    }

    /**
     * Renders a view and returns the content.
     */
    protected function renderView(Octopus_Controller $controller, Octopus_Request $request, Octopus_Response $response, Array $data, $renderViewNotFound = true) {

        // NOTE: findViewForRender will always return a valid view (by default,
        // it returns the view_not_found view).
        $viewFile = $this->findViewForRender($controller, $request, $response, $data, $renderViewNotFound);

        $this->augmentViewData($data);

        $viewRenderer = Octopus_Template_Renderer::createForFile($viewFile);

        return $viewRenderer->render($data);
    }

    /**
     * @return Array Any extra data to add to the $data array before it is
     * passed to the template renderer. Existing data will NOT be overwritten
     * by the result of this method.
     *
     * The default implementation of this function defines the following
     * extra keys:
     *
     *	_GET -		$_GET
     *  _POST -		$_POST
     *
     *	QS - 		$_GET as a string with no '?' at the beginning.
     *	FULL_QS	-	$_GET as a string with a '?' at the beginning.
     * 	QS_AND -	Character to use to build on FULL_QS (if FULL_QS is
	 *              not '', this is '&'. Otherwise, it is '?').
     *
     *	URL_BASE -	Prefix for the app's public root.
     *
     *	ROOT_DIR
     *	SITE_DIR
     *	OCTOPUS_DIR
     *
     */
    protected function getExtraViewData() {

    	$result = array();

    	// Full querystring
        $qs = $_GET;
        $pathArg = $this->app->getOption('path_querystring_arg');
        unset($qs[$pathArg]);
        $queryString = http_build_query($qs);

        $result['_GET'] = $qs;
        $result['_POST'] =& $_POST;

        // Query string
        $result['QS'] = $queryString;
        $result['FULL_QS'] = ($queryString ? '?' : '') . $queryString;
        $result['QS_AND'] =($queryString ? '&amp;' : '?');

        $result['ROOT_DIR'] = $this->app->getOption('ROOT_DIR');
        $result['SITE_DIR'] = $this->app->getOption('SITE_DIR');
        $result['OCTOPUS_DIR'] = $this->app->getOption('OCTOPUS_DIR');

        if (class_exists('Octopus_Html_Page')) {

            $p = Octopus_Html_Page::singleton();

            // Extra tags and metadata
            $result['HEAD_CONTENT'] = $p->renderHead(true, false);
            $result['HEAD'] = "<head>{$result['HEAD_CONTENT']}</head>";
        }

        return $result;
    }

    /**
     * @param $view Mixed A view string (e.g. 'controller/action') or an array
     * of candidate view strings.
     * @return Mixed Full path to a view file, or false if none is found.
     */
    protected function findViewFile(Octopus_Request $request, $controller, $view) {
        return $this->internalGetViewPaths($request, $controller, $view, true);
    }

    /**
     * @return String Full path to the 'view not found' view to use.
     */
    protected function getViewNotFoundViewFile(Octopus_Request $request, $controller) {
        return $this->findViewFile($request, $controller, 'sys/view_not_found');
    }

    private function augmentViewData(Array &$data) {
    	$extra = $this->getExtraViewData();
    	foreach($extra as $key => $value) {
    		if (!isset($data[$key])) {
    			$data[$key] = $value;
    		}
    	}
    }

    /**
     * Given some controller/action parameters, builds a list of potential
     * view names.
     */
    private function &buildCandidateViewList(Octopus_Request $request, $action = null) {

        if ($action === null) {
            $action = $request->getAction();
        } else if (is_array($action)) {
            $result = array();
            foreach($action as $a) {
                foreach($this->buildCandidateViewList($request, $a) as $v) {
                    $result[] = $v;
                }
            }
            return $result;
        }

        $app = $request->getApp();

        $octopusDir = $app->getSetting('OCTOPUS_DIR');
        $siteDir = $app->getSetting('SITE_DIR');
        $controllerFile = $request->getControllerFile();

        $r = null;

        // slight HACK: DefaultController should not be used to locate views.
        if ($controllerFile === $octopusDir . 'controllers/Default.php') {
            $controller = '';
        } else if ($controllerFile && starts_with($controllerFile, $siteDir . 'controllers/', false, $r)) {
            $controller = preg_replace('/\.php$/i', '', $r);
        } else if ($controllerFile && starts_with($controllerFile, $octopusDir . 'controllers/', false, $r)) {
            $controller = preg_replace('/\.php$/i', '', $r);
        } else {
            $class = $request->getControllerClass();
            $controller = preg_replace('/(.*)(Controller)?$/', '$1', $class);
        }

        if (strpos($controller, '/') === false) {
            $controller = explode('_', $controller);
        } else {
            $controller = explode('/', $controller);
        }

        $controller = array_map('underscore', $controller);
        $controller = implode('/', $controller);

        $action = underscore($action);

        $parts = explode('/', $controller);
        $count = count($parts);
        if ($action) $count++;

        $names = array();

        while($count) {

            $path = $parts ? implode('/', $parts) : '';

            if ($action) {
                $path .= ($path ? '/' : '') . $action;
            }

            $names[] = $path;

            array_pop($parts);

            $count--;
        }

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

    /**
     * @return Mixed A set of paths to check for a view file, or if $return
     * is true, the path to the first view found, or false if none is found.
     */
    private function internalGetViewPaths(Octopus_Request $request, $controller, $view, $returnFirstValid) {

        $action = '';

        if ($controller) {
            $action = $controller->__getExecutedActions();
        }

        if (empty($action)) {
            $action = $request->getAction();
        }

        if (empty($view)) {

            if (!empty($controller->view)) {
                $view = $controller->view;
            } else {
                $view = $this->buildCandidateViewList($request, $action);
            }

        }

        $views = is_array($view) ? $view : array($view);
        $app = $request->getApp();

        $searchDirs = array(
            $app->getSetting('SITE_DIR') . 'views/',
            $app->getSetting('OCTOPUS_DIR') . 'views/'
        );

        $extensions = $app->getSetting('view_extensions');

        $result = array();

        foreach($searchDirs as $d) {

            foreach($views as $view) {

                if (!$view) {
                    continue;
                }

                if (strncmp($view, '/', 1) == 0) {

                    // This is an absolute path
                    if ($returnFirstValid && is_file($view)) {
                        return $view;
                    } else {
                        $result[$f] = true;
                        continue;
                    }

                }

                $view = trim($view, '/');

                foreach($extensions as $ext) {

                    $file = $d . $view . $ext;

                    if ($returnFirstValid && is_file($file)) {
                        return $file;
                    } else {
                        $result[$file] = true;
                        continue;
                    }

                }
            }
        }

        if ($returnFirstValid) {
            return false;
        }

        return array_keys($result);
    }

    private function findViewForRender(Octopus_Controller $controller, Octopus_Request $request, Octopus_Response $response, Array &$data, $useViewNotFound = true) {

        if ($response->isForbidden()) {
            $info = $this->findView($request, $controller, 'sys/forbidden');
        } else {
            $info = $this->findView($request, $controller);
        }

        if ($info && !empty($info['file'])) {

            if (!$info['found'] && $useViewNotFound) {

                // View wasn't found, so provide some extra data for the 'view not found' view.
                $data = array(
                    'controller_data' => array(),
                    'path' => $request->getPath(),
                    'resolved_path' => '',
                    'view_paths' => array()
                );

                $response->setStatus(404);

                if ($this->app->isDevEnvironment()) {
                    $data['controller_data'] = $data;
                    $data['resolved_path'] = $request->getResolvedPath();

                    $paths = $this->getViewPaths($request, $controller);
                    $paths = preg_replace('/^' . preg_quote(ROOT_DIR, '/') . '/i', '', $paths);
                    $data['view_paths'] = $paths;
                }
            }

            return $info['file'];
        }

        return false;
    }

    private function findTemplateForRender(Octopus_Controller $controller, Octopus_Request $request) {

        $siteDir = $this->app->getOption('SITE_DIR');
        $octopusDir = $this->app->getOption('OCTOPUS_DIR');

        $extensions = array('', '.php', '.tpl');
        $theme = $this->app->getTheme($request);

        $template = $controller->template;

        if ($template === null) {
            $template = $this->app->getOption('default_template');
        }

        if (strncmp($template, '/', 1) != 0) {
            $template = $this->app->getFile(
                $template,
                array($siteDir . 'themes/' . $theme . '/templates/', $octopusDir . 'themes/' . $theme . '/templates/'),
                array(
                    'extensions' => $extensions
                )
            );
        }

        if (!$template) return $template;

        foreach($extensions as $ext) {
            $f = $template . $ext;
            if (is_file($f)) {
                return $f;
            }
        }

        return $template;
    }
}

?>