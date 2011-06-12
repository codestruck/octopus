<?php

/**
 * Class responsible for locating views based on a request.
 */
class Octopus_View_Finder {

    /**
     * @return Array An array of all the paths a view for $path could live,
     * in order of priority.
     */
    public function getViewPaths($path, $app) {
        return $this->internalGetViewPaths($path, $app, false);
    }

    /**
     * @return Array view info for the given path.
     */
    public function getViewForPath($path, $app) {

        // TODO implement.

        return array(
            'file' => $this->getViewNotFoundViewFile($app),
            'found' => false
        );
    }

    /**
     * @return String Full path to the 'view not found' view to use.
     */
    protected function getViewNotFoundViewFile($app) {
        return $this->findViewFile('sys/view_not_found', $app);
    }

    private function internalGetViewPaths($view, &$app, $returnFirstValid) {

        if ($view instanceof Octopus_Request) {

            $request = $view;
            $app = $request->getApp();

            $c = $request->getControllerInfo();

            if (!$c || empty($c['file'])) {
                // TODO: Does this make sense?
                $view = $request->getPath();
            } else {
                $controllerFile = $c['file'];
                $controllerClass = $request->getControllerClass();

                $view = $this->buildCandidateViewList($request);
            }
        }

        $views = is_array($view) ? $view : array($view);

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

    /**
     * @param $view Mixed A view string (e.g. 'controller/action') or an array
     * of candidate view strings.
     * @return Mixed Full path to a view file, or false if none is found.
     */
    protected function findViewFile($view, &$app) {
        return $this->internalGetViewPaths($view, $app, true);
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
    public function &findView($view, $app) {

        $file = $this->findViewFile($view, $app);
        $found = !!$file;

        if (!$file) {
            $file = $this->getViewNotFoundViewFile($app);
        }

        $result = compact('file', 'found');
        return $result;
    }

    /**
     * Given some controller/action parameters, builds a list of potential
     * view names.
     */
    protected function &buildCandidateViewList($request) {

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

        $controller = str_replace('_', '/', $controller);
        $controller = explode('/', $controller);
        $controller = array_map('underscore', $controller);
        $controller = implode('/', $controller);

        $action = $request->getAction();

        $parts = explode('_', $controller);
        $count = count($parts);
        if ($action) $count++;

        $names = array();

        while($count) {

            $path = $parts ? implode('_', $parts) : '';

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

}

?>
