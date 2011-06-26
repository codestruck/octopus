<?php

/**
 * Class responsible for locating views based on a request.
 */
class Octopus_View_Finder {

    /**
     * @return Array An array of all the paths a view for $path could live,
     * in order of priority.
     */
    public function getViewPaths(Octopus_Request $request, Octopus_Controller $controller) {
        return $this->internalGetViewPaths($request, $controller, null, false);
    }

    /**
     * @param $request Octopus_Request
     * @param
     *
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
     * @return String Full path to the 'view not found' view to use.
     */
    protected function getViewNotFoundViewFile(Octopus_Request $request, $controller) {
        return $this->findViewFile($request, $controller, 'sys/view_not_found');
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
     * Given some controller/action parameters, builds a list of potential
     * view names.
     */
    protected function &buildCandidateViewList(Octopus_Request $request, $action = null) {

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

        if ($controllerFile && starts_with($controllerFile, $siteDir . 'controllers/', false, $r)) {
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

                $c = $request->getControllerInfo();

                if (!$c || empty($c['file'])) {
                    // TODO: Does this make sense?
                    $view = $request->getPath();
                } else {
                    $controllerFile = $c['file'];
                    $controllerClass = $request->getControllerClass();

                    $view = $this->buildCandidateViewList($request, $action);
                }
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
                    if ($returnFirstValid && ($f = get_true_filename($view))) {
                        return $f;
                    } else {
                        $result[$f] = true;
                        continue;
                    }

                }

                $view = trim($view, '/');

                foreach($extensions as $ext) {

                    $file = $d . $view . $ext;

                    if ($returnFirstValid && ($f = get_true_filename($file))) {
                        return $f;
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

}

?>
