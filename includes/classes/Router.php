<?php

    /**
     * Class that handles mapping nice urls to the actual contents of the
     * filesystem. The basic use case is when you use url rewriting to send
     * a nice url to a single page as a querystring arg.
     */
    class SG_Router {
        
        var $_arg;
        var $_routes;
        
        /**
         * Creates a new router that reads path information from the given
         * querystring arg.
         */
        function SG_Router($arg = 'q') {
            $this->_arg = $arg;
            $this->_routes = array();
        }
        
        /**
         * Adds a regex-based route.
         */
        function &add($regex, $dest) {
            $this->_routes[] = array('type' => 'regex', 'regex' => $regex, 'dest' => $dest);
            return $this;
        }
        
        
        /**
         * Makes the contents of a directory routable.
         * @param $dir string Directory whose contents should be routable.
         * @param $prefix string Prefix routes that correspond to this directory should have.
         */
        function &addDirectory($dir, $prefix = '') {
            $this->_routes[] = array('type' => 'dir', 'dir' => $dir, 'prefix' => $prefix);
            return $this;
        }
        
        /**
         * @return string The requested path, normalized.
         */
        function getRequestedPath($path = null) {
            
            if ($path === null) $path = isset($_GET[$this->_arg]) ? $_GET[$this->_arg] : '';
            
            $path = preg_replace('#^[\s/\\\]*#', '', $path);
            $path = preg_replace('#[\s/\\\]*$#', '', $path);
            $path = preg_replace('/\.php\d*$/i', '', $path);
            
            return $path;
        }
        
        /**
         * Figures out the physicial file referred to by the given path.
         * @param $path string Path to resolve. Defaults to the querystring value.
         * @return string File to render or false if no corresponding file was found.
         */
        function resolve($path = null) {
         
            $path = $this->getRequestedPath($path);
            
            
            foreach($this->_routes as $route) {
                
                $type = $route['type'];
                $resolveMethod = '_resolve_' . $type;
                
                $dest = $this->$resolveMethod($route, $path);
                
                if ($dest !== false) {
                    return $dest;
                }
                
            }
            
            return false;
        }
        
        function _resolve_regex(&$route, $path) {
            
            if (!preg_match($route['regex'], $path, $matches)) {
                return false;
            }
            
            $find = array();
            $replaceWith = array();
            
            for($i = 1; $i < count($matches); $i++) {
                $find[] = '$' . $i;
                $replaceWith[] = $matches[$i];
            }
            
            return str_replace($find, $replaceWith, $route['dest']);
        }
        
        function _resolve_dir(&$route, $path) {
            
            $dir = rtrim($route['dir'], '\\/');
            
            if (strncasecmp($route['prefix'], $path, strlen($route['prefix'])) == 0) {
                $path = trim(substr($path, strlen($route['prefix'])), '/\\');
            } else {
                // Doesn't have the prefix for this route.
                return false;
            }
            
            if (is_dir($dir . '/' . $path)) {
                $file = $dir . '/' . $path . '/index.php';
                if (is_file($file)) return $file;
            }
            
            $file = $dir . '/' . $path . '.php';
            
            if (is_file($file)) {
                return $file;
            } else {
                return false;
            }
            
        }
        
    }

?>
