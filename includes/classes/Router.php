<?php

/**
 * Class responsible for manging path aliases and friendly URLs.
 */
class Octopus_Router {

	private $routes = array();

	/**
	 * Registers a route alias.
	 * @param String $from String describing the pattern to be matched. Some examples
	 * include:
	 * <example>
	 *	The following are equivalent:
	 *	'/products/{$id}' -> $id refers to a pattern described in $options
	 *  '/products/{(?<id>\d+)}'
	 * </example>
	 * @param String $to Destination string. Groups matched in $from can be refered to
	 * as {$groupname}, like this:
	 * <example>
	 *	'/products/view/{$id}' -> $id refers to the named 'id' group matched in from
	 * </example>
	 * @param Array $options Any patterns you refer to by name in $from must be
	 * defined here.
	 *
	 * So, putting it all together:
	 * <example>
	 *
	 *	$r = new Octopus_Router();
	 * 	$r->alias('/products/{$id}', '/products/view/{$id}', array('id' => '\d+'));
	 *
	 *	Will map, for instance, the path '/products/50' to '/products/view/50'.
	 *  Note that this will ALSO map the path '/products/50/detailed' to
	 *  '/products/view/50/detailed' (anything after the last pattern in $from)
	 *  is appended to the end of the resulting path.
	 *
	 * </example>
	 */
	public function alias($from, $to, $options = array()) {
		array_unshift($this->routes, compact('from', 'to', 'options'));
		return $this;
	}

	/**
	 * Attempts to resolve $path.
	 * @return Mixed If the path can be mapped to something by the router, the
	 * new path is returned. Otherwise false is returned.
	 */
	public function resolve($path) {

		$path = start_in('/', $path);

		foreach($this->routes as &$route) {

			$result = $this->resolveRoute($path, $route);
			if ($result) return $result;

		}

		return $path;

	}

	/**
	 * Given a candidate routing pattern, generates a regex that can be used
	 * to match it against an incoming path.
	 */
	protected function createRegexPattern(Array $route) {

		// /foo/bar/{$id}
		// /foo/bar/{\d+}

		$patterns = array();
		$currentPattern = '';
		$escape = false;
		$inSubpattern = false;

		$from = $route['from'];
		$len = strlen($from);

		for($i = 0; $i < $len; $i++) {

			$c = $from[$i];

			if ($escape) {
				$currentPattern .= $c;
				$escape = false;
				continue;
			}

			// NOTE: escaping is disabled in subpatterns to make inline
			//       regexes easier
			if ($c === '\\' && !$inSubpattern) {

				$escape = true;

			} else if ($inSubpattern) {

				if ($c === '}') {
					$patterns[] = $currentPattern;
					$currentPattern = '';
					$inSubpattern = false;
				} else {
					$currentPattern .= $c;
				}

			} else {

				if ($c === '{') {
					$patterns[] = preg_quote($currentPattern, '/');
					$currentPattern = '';
					$inSubpattern = true;
				} else {
					$currentPattern .= $c;
				}

			}
		}

		$patterns[] = $inSubpattern ? $currentPattern : preg_quote($currentPattern, '/');
		$patterns = array_filter($patterns);

		// $patterns is now an array of either
		//
		// quoted regex literals, e.g. '\/foo\/bar\/'
		// subpattern referring to a named pattern in $options, e.g. '$id'
		// regex subpattern, e.g. [\d+]
		//
		// it needs to be re-assembled into a single regular expression pattern
		// for matching.

		$pattern = '/^';

		foreach($patterns as $p) {

			if ($p[0] === '$') {

				// this refers to a named pattern in $options
				$name = substr($p, 1);
				if (!isset($route['options'][$name])) {
					throw new Octopus_Exception("Subpattern \$$name not provided in options.");
				}
				$pattern .= '(?<' . $name . '>' . $route['options'][$name] . ')';
			} else {
				$pattern .= $p;
			}

		}

		// Capturing the remainder here allows passing on action arguments
		// in assemblePath (below)
		$pattern .= '(?<__octopus_remainder__>(\/.*|$))/i';

		return $pattern;
	}

	protected function resolveRoute($path, &$route) {

		if (!isset($route['pattern'])) {
			$route['pattern'] = $this->createRegexPattern($route);
		}

		if (!preg_match($route['pattern'], $path, $m)) {
			return false;
		}

		return $this->assemblePath($path, $route['to'], $m);
	}

	protected function assemblePath($path, $to, $values) {

		$parts = array();
		$len = strlen($to);
		$inRef = false;
		$part = '';

		$remainder = isset($values['__octopus_remainder__']) ? $values['__octopus_remainder__'] : '';
		unset($values['__octopus_remainder__']);

		for($i = 0; $i < $len; $i++) {

			$c = $to[$i];

			if ($inRef) {

				if ($c === '}') {
					$parts[] = $this->getReferencedValue($part, $values);
					$part = '';
					$inRef = false;
				} else {
					$part .= $c;
				}

			} else {

				if ($c === '{') {
					$parts[] = $part;
					$part = '';
					$inRef = true;
				} else {
					$part .= $c;
				}

			}

		}
		$parts[] = $part;

		$parts = array_filter($parts);

		return implode('', $parts) . $remainder;
	}

	private function getReferencedValue($name, &$values) {

		if ($name[0] === '$') $name = substr($name, 1);

		if (!isset($values[$name])) {
			throw new Octopus_Exception("No pattern defined for \$$name");
		}

		return $values[$name];

	}

	private function assemblePathCallback($m) {
		$name = $m['name'];
		return isset($this->_matches[$name]) ? $this->_matches[$name] : '';

	}

}

?>