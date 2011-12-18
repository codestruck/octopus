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

        $this->applyTheme($request, $data);

        $viewContent = $this->renderView($controller, $request, $response, $data);
        $templateContent = $this->renderTemplate($controller, $request, $response, $viewContent, $data);
        $response->append($templateContent);

    }

    /**
     * Gives themes a chance to hook into the rendering process. Looks for and
     * loads the 'theme.php' file in the current theme's directory, and adds
     * the theme's directory as potential paths for js and css files.
     */
    protected function applyTheme(Octopus_Request $request, &$data) {

        $app = $this->app;
        $theme = $app->getTheme($request);

        if (!$theme) {
        	return;
        }

        $dirs = array($app->getOption('SITE_DIR'), $app->getOption('OCTOPUS_DIR'));

        $page = Octopus_Html_Page::singleton();

        // In case theme was changed, remove any existing theme directories
        // being searched for js / css files and re-add
        foreach($dirs as $dir) {

        	$themesDir = $dir . 'themes/';

        	foreach($page->getJavascriptDirs() as $jsDir) {
        		if (starts_with($jsDir, $themesDir)) {
        			$page->removeJavascriptDir($jsDir);
        		}
        	}

			foreach($page->getCssDirs() as $cssDir) {
        		if (starts_with($cssDir, $themesDir)) {
        			$page->removeCssDir($cssDir);
        		}
        	}

        	$themeDir = $themesDir . $theme;
        	$page->addJavascriptDir($themeDir);
        	$page->addCssDir($themeDir);
        }

        foreach($dirs as $dir) {
        	$themeDotPHP = $dir . 'themes/' . $theme . '/theme.php';
        	if (is_file($themeDotPHP)) {
        		Octopus::requireOnce($themeDotPHP);
        	}
        }

    }

    /**
     * Renders the full page template and returns the result.
     */
    protected function renderTemplate(Octopus_Controller $controller, Octopus_Request $request, Octopus_Response $response, $viewContent, Array $data) {

        $templateFile = $this->findTemplateForRender($controller, $request);

        if (!$templateFile) {
            return $viewContent;
        }

        $this->augmentViewData($data, $request, $response);
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

        $this->augmentViewData($data, $request, $response);

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
     *    _GET -		$_GET
     *    _POST -    	$_POST
     *
     *    QS - 		$_GET as a string with no '?' at the beginning.
     *    FULL_QS	-	$_GET as a string with a '?' at the beginning.
     *    QS_AND -	Character to use to build on FULL_QS (if FULL_QS is
     *              not '', this is '&'. Otherwise, it is '?').
     *
     *    URL_BASE -	Prefix for the app's public root.
     *
     *    URI -			The full requested URI
     *    URI_AS_CLASS -	The URI escaped for use as a css class
     *
     *    ROOT_DIR
     *    SITE_DIR
     *    OCTOPUS_DIR
     *
     */
    protected function getExtraViewData(Octopus_Request $request, Octopus_Response $response) {

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

        $result['URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $request->getPath();
        $result['URI_AS_CLASS'] = to_css_class(str_replace('/', '', $request->getPath()));

        $controller = preg_replace('/-?Controller$/', '', $request->getControllerClass());
        $controller = strtolower(dashed($controller));

        $action = $request->getAction();
        $action = strtolower(dashed($action));

        $result['CONTROLLER'] = $controller;
		$result['ACTION'] = $action;
        $result['ACTION_AS_CLASS'] = $controller . '-' . $action;

        $result['SETTINGS'] = $this->app->getSettings();

        if (class_exists('Octopus_Html_Page')) {

            $p = Octopus_Html_Page::singleton();

            // Extra tags and metadata
            $result['HEAD_CONTENT'] = $p->renderHead(true, false);
            $result['HEAD'] = "<head>{$result['HEAD_CONTENT']}</head>";

            $result['TITLE'] = $p->getTitle();
            $result['FULL_TITLE'] = $p->getFullTitle();

            $result['PAGE'] = $p;
        }

        $result['DEV'] = $this->app->DEV;
        $result['LIVE'] = $this->app->LIVE;
        $result['STAGING'] = $this->app->STAGING;

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

    private function augmentViewData(Array &$data, Octopus_Request $request, Octopus_Response $response) {

        $extra = $this->getExtraViewData($request, $response);
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
            $app->SITE_DIR . 'views/',
            $app->OCTOPUS_DIR . 'views/'
        );

        $extensions = $app->view_extensions;

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