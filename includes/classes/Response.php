<?php

/**
 * Class that encapsulates an Octopus response. Tracks headers and key/value
 * data.
 *
 * @property String $contentType The HTTP content-type header for this response.
 * Defaults to text/html.
 * @property String $charset The encoding of the response (defaults to UTF-8)
 * @property Octopus_Request $request
 * @property Number $status The HTTP status code for this response.
 * @property String $theme
 * @property String $view
 */
class Octopus_Response implements ArrayAccess {

	private static $httpResponseCodes = array(
		200 => 'OK',
		301 => 'Moved Permanently',
		302 => 'Found',
		404 => 'Not Found',
		420 => 'Enhance Your Calm',
		500 => 'Internal Server Error',
	);

	private static $defaultHeaders = array(
		'content-type' => array('name' => 'Content-type', 'value' => 'text/html; charset=UTF-8'),
	);

    private $values;
    private $headers;
    private $content = array();

    // These are all available through getters (and/or setters) as well as magic
    // properties on this class
    private $_active = true;
    private $_layout = 'page';
    private $_renderer = null;
    private $_request = null;
    private $_status = 200;
    private $_theme = '';
    private $_view = '';

    /**
     * Creates a new Octopus_Response instance.
     * @param Octopus_Request $request The request this response is for.
     */
    public function __construct(Octopus_Request $request) {

    	if (!$request instanceof Octopus_Request) {
    		throw new Octopus_Exception('$request must be an Octopus_Request');
    	}

    	$this->_request = $request;
    	$this->reset();
    }

    public function __get($name) {

    	switch($name) {

    		case 'charset':
    		case 'contentType':
    		case 'layout':
    		case 'renderer':
    		case 'request':
    		case 'status':
    		case 'theme':
    		case 'values':
    		case 'view':
    			$getter = 'get' . ucwords($name);
    			return $this->$getter();

    		case 'active':
    			return $this->_active;

    		case 'status':
    			return $this->_status;
    	}

    }

    public function __set($name, $value) {

    	switch($name) {

    		case 'charset':
    		case 'contentType':
    		case 'layout':
    		case 'renderer':
    		case 'status':
    		case 'theme':
    		case 'view':
    			$setter = 'set' . ucwords($name);
    			return $this->$setter($value);

    	}

    }

    public function __toString() {

    	$result = array($this->getStatusString());
    	foreach($this->headers as $h) {
    		$result[] = "{$h['name']}: {$h['value']}";
    	}

    	return implode("\n", $result);

    }

    /**
     * @deprecated Use ::setHeader()
     * @uses ::setHeader()
     */
    public function addHeader($header, $value) {
    	// Octopus_Debug::deprecated();
    	return $this->setHeader($header, $value);
    }

    /**
     * Adds one or more chunk(s) of content to be rendered for this response.
     * Support for this depends on the renderer being used (e.g.,
     * Octopus_Renderer_Json does not render any content appended to a
     * response).
     * @param  String|Array $content..
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function append($content) {

    	$this->checkNotStopped();

    	$args = func_get_args();
    	foreach($args as $arg) {

    		if (is_array($arg)) {
    			call_user_func_array(__METHOD__, $arg);
    		} else {
    			$this->content[] = $arg;
    		}

    	}

    	return $this;

    }

    /**
     * Unsets one or more keys on this response.
     * @param String|Array $key.. Key(s) to unset.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function clear($key) {

    	$this->checkNotStopped();

    	$args = func_get_args();
    	foreach($args as $arg) {

    		if (is_array($arg)) {
    			call_user_func_array(__METHOD__, $arg);
    		} else {
    			unset($this->values[$arg]);
    		}

    	}

    	return $this;

    }

    /**
     * Clears a header set using ::setHeader()
     * @param  String $name Name of header, e.g. 'Content-type'.
     * Case-insensitive.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
	 */
    public function clearHeader($name) {
    	$this->checkNotStopped();
    	$normalized = strtolower($name);
    	unset($this->headers[$normalized]);
    	return $this;
    }

    /**
     * Clears all values set on this response using ::set().
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function clearValues() {
    	$this->checkNotStopped();
    	$this->values = array();
    	return $this;
    }

    /**
     * Sets the status header to 403 Forbidden and optionally clears the
     * data associated with this response.
     */
    public function forbidden() {
        return $this->setStatus(403);
    }

    /**
     * Gets the values of one or more keys. Keys can also be read/written
     * via PHP array syntax.
     * @param String $key Key to get.
     * @param Mixed $default Value to return if $key is not set.
     * @return Mixed The value of $key, or $default.
     */
    public function get($key, $default = null) {
    	return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    /**
     * @return String The charset of this response. Defaults to "UTF-8".
     */
    public function getCharset() {

    	$h = $this->getHeader('Content-type');

    	if (preg_match('/;\s*charset\s*=\s*(.+)/i', $h, $m)) {
    		return $m[1];
    	}

    	return '';

    }

    /**
     * @return String Any content appended via ::append().
     */
    public function getContent() {
    	return implode("\n", $this->content);
    }

    /**
     * @return String The value of the content-type header, excluding Charset.
     */
    public function getContentType() {

    	$result = $this->getHeader('Content-type');
    	if (!$result) return '';

    	$pos = strpos($result, ';');

    	if ($pos !== false) {
    		$result = substr($result, 0, $pos);
    	}

    	return trim($result);
    }

    /**
     * Gets the value of a header.
     * @param String $header The header value to retrieve. Case-insensitive.
     * @return String The value of the header or an empty string if it is
     * not set.
     */
    public function getHeader($header) {

    	$normalized = strtolower($header);

    	if (isset($this->headers[$normalized])) {
    		return $this->headers[$normalized]['value'];
    	}

    	return '';

    }

    /**
     * @return Array The headers set for this response.
     */
    public function getHeaders() {

    	$result = array();
    	foreach($this->headers as $h) {
    		$result[$h['name']] = $h['value'];
    	}

    	return $result;
    }

    /**
     * Gets the layout to use within the theme when rendering.
     * @return String
     */
    public function getLayout() {
    	return $this->_layout;
    }

    /**
     * @return Octopus_Renderer The renderer to be used to render this response.
     * If not specified using ::setRenderer(), it is inferred from the content
     * type of the response. Octopus_Renderer_Template is the default.
     * @see Octopus_Renderer
     * @see Octopus_Renderer_Template
     * @see Octopus_Renderer_Json
     * Also available via the ::renderer property.
     */
    public function getRenderer() {

    	if ($this->_renderer) {
    		return $this->_renderer;
    	} else {
    		return Octopus_Renderer::getForContentType($this->contentType);
    	}

    }

    /**
     * @return Octopus_Request The request this response is for. Also
     * accessible via the 'request' property.
     */
    public function getRequest() {
    	return $this->_request;
    }

    /**
     * @return Number The HTTP status code for this response.
     */
    public function getStatus() {
    	return $this->_status;
    }

    /**
     * @return String The full status string, e.g. "HTTP/1.1 200 OK".
     */
    public function getStatusString() {

    	$code = $this->status;
    	$desc = isset(self::$httpResponseCodes[$code]) ? self::$httpResponseCodes[$code] : '';
    	return trim("HTTP/1.1 {$code} {$desc}");

    }

    /**
     * @return String The theme to be used when rendering this response
     * (assuming the renderer supports themes). If not set via ::setTheme,
     * this will be determined based on the theme inferred from the request
     * for this response.
     */
    public function getTheme() {

    	if ($this->_theme) {
    		return $this->_theme;
    	}

    	$req = $this->getRequest();
    	$app = $req->getApp();

    	if (!$app->getOption('use_themes')) {
    	    return '';
    	}

    	$path = $req->getPath();

    	$key = 'site.theme';
    	$parts = array_filter(explode('/', $path), 'trim');
    	if (!empty($parts)) {
    	    $key .= '.' . implode('.', $parts);
    	}

    	return $app->getSetting($key);

    }

    /**
     * @return Array All values set via ::set()
     */
    public function getValues() {
    	return $this->values;
    }

	/**
     * @return String The view to be used when rendering this response
     * (assuming the renderer supports views).
     */
    public function getView() {
    	return $this->_view;
    }

    /**
     * @return boolean Whether any content has been appended using ::append().
     */
    public function hasContent() {
    	return count($this->content) > 0;
    }

    /**
     * @return bool Whether this response is 403 forbidden.
     */
    public function isForbidden() {
        return $this->status === 403;
    }

    /**
     * @return boolean Whether the content type for this response is text/html.
     */
    public function isHtml() {
    	return !!preg_match('~text/html~i', $this->getHeader('Content-type'));
    }

    /**
     * @return bool Whether this response is 404 Not Found
     */
    public function isNotFound() {
        return $this->status === 404;
    }

    /**
     * Sets the status on this response to 404 (not found).
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function notFound() {
    	return $this->setStatus(404);
    }

    public function offsetExists($offset) {
    	return array_key_exists($offset, $this->values);
    }

    public function offsetGet($offset) {
    	return array_key_exists($offset, $this->values) ? $this->values[$offset] : null;
    }

    public function offsetSet($offset, $value) {
    	$this->set($offset, $value);
    }

    public function offsetUnset($offset) {
    	$this->clear($offset);
    }

    /**
     * Clears headers and data on this response and reconfigures it to
     * redirect to $to.
     * @param $to string URL to redirect to.
     * @param $permanent bool Whether this is a permanent redirect.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function redirect($to, $permanent = false) {

    	$this->checkNotStopped();

		$this->setRenderer(new Octopus_Renderer_HeadersOnly());
    	$this->_status = ($permanent ? 301 : 302);
    	$this->headers = array(
    		'location' => array('name' => 'Location', 'value' => $to)
    	);
    	$this->_active = false;

    	return $this;

    }

    /**
     * Uses the renderer configured for this response to render it.
     * @param  boolean $return Whether to return the rendered content or
     * echo it directly. Headers will only be outputted if $return is false.
     * @return String|Octopus_Response If $return is true, the rendered content
     * (minus headers). Otherwise, $this.
     * @see Octopus_Renderer
     * @see ::getRenderer
     * @see ::setRenderer
     * @see ::renderer
     */
    public function render($return = false) {

    	$renderer = $this->getRenderer();

    	if ($return) {
    		return $renderer->render($this, true);
    	} else {
    		$renderer->render($this, false);
    		return $this;
    	}

    }

    /**
     * Clears all values in this response and clears all headers such that this
     * becomes a text/html, charset=UTF-8 response with status 200.
     * @uses ::resetHeaders()
     * @uses ::clearValues()
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function reset() {
    	$this->resetHeaders();
    	$this->clearValues();
        return $this;
    }

    /**
     * Clears all headers on this response, then resets the status and content
     * type headers to 200 and 'text/html; charset=UTF-8'.
     * @return [type] [description]
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function resetHeaders() {
    	$this->checkNotStopped();
    	$this->headers = self::$defaultHeaders;
    }

    /**
     * Sets the value of a key for this response.
     * @param String|Array $key   A key to set or an array of key/value pairs
     * @param Mixed $value Value to set $key to.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function set($key, $value = null) {

    	$this->checkNotStopped();

    	if (func_num_args() === 1) {

    		// Support set(array())
    		if (is_array($key)) {
    			foreach($key as $k => $v) {
    				$this->values[$k] = $v;
    			}
    			return $this;
    		}

    	}

    	$this->values[$key] = $value;

    	return $this;
    }

    /**
     * Sets the charset portion of the Content-type header.
     * @param String $type Content type, e.g. 'text/html'.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function setCharset($charset) {

    	$this->checkNotStopped();

    	$h = $this->getHeader('Content-type');
    	if (!$h) {
    		$this->headers['content-type'] = array(
    			'name' => 'Content-type',
    			'value' => ';charset=' . $charset
    		);
    		return $this;
    	}

    	$pos = strpos($h, ';');

    	if ($pos === false) {
    		$h .= '; charset=' . $charset;
    	} else {
    		$h = substr($h, 0, $pos) . '; charset=' . $charset;
    	}

    	$this->headers['content-type'] = array(
    		'name' => 'Content-type',
    		'value' => $h
    	);

    	return $this;

    }

    /**
     * Sets the Content-type header.
     * @param String $type Content type, e.g. 'text/html'.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function setContentType($type) {

    	if (isset($this->headers['content-type'])) {

	    	// Preserve the charset in the content type
	    	$h = $this->headers['content-type'];
	    	$pos = strpos($h['value'], ';');

	    	if ($pos !== false) {
	    		$type = $type . substr($h['value'], $pos);
	    	}
	    }

    	$this->headers['content-type'] = array(
    		'name' => 'Content-type',
    		'value' => $type
    	);

    	return $this;
    }

    /**
     * Sets a header on this response.
     * @param String $header Header to set.
     * @param String $value  Value to set it to.
     * @return Octopus_Response $this
	 * @throws Octopus_Exception If this response is currently inactive (
	 * ::stop() has been called).
     */
    public function setHeader($header, $value) {

    	$this->checkNotStopped();

    	$normalized = strtolower($header);
    	$this->headers[$normalized] = array(
    		'name' => $header,
    		'value' => $value,
    	);

    	return $this;
    }

    /**
     * Sets the layout to use when rendering this response (assuming the template
     * renderer is being used.)
     * @param String $layout Layout identifier
     * @return Octopus_Response $this
 	 * @throws Octopus_Exception If this response is currently inactive (
 	 * ::stop() has been called).
     */
    public function setLayout($layout) {
    	$this->_layout = $layout;
    	return $this;
    }

    /**
     * Sets the renderer to be used to render this response.
     * @see ::render()
     * @param Octopus_Renderer $renderer
     * @return Octopus_Response $this
     * Also available via the ::renderer property.
     */
    public function setRenderer(Octopus_Renderer $renderer) {
    	$this->_renderer = $renderer;
    	return $this;
    }

    /**
     * Sets the status code for this response.
     * @param Number $code The HTTP status code.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function setStatus($code) {
    	$this->checkNotStopped();
    	$this->_status = intval($code);
    	return $this;
    }

    /**
     * Sets the theme to be used to render this response (assuming the renderer
     * supports themes).
     * @param String $theme Theme to use.
     */
    public function setTheme($theme) {
    	$this->checkNotStopped();
    	$this->_theme = $theme;
    	return $this;
    }

    /**
     * Sets the view to be used to render this response (assuming the renderer
     * supports views).
     * @param String $view View to use.
     * @return Octopus_Response $this
     * @throws Octopus_Exception If this response is currently inactive (
     * ::stop() has been called).
     */
    public function setView($view) {
    	$this->checkNotStopped();
    	$this->_view = $view;
    	return $this;
    }

    /**
     * Prevents any further header/value changes from being made to this
     * response. After this is called, ::active will return false.
     * @return Octopus_Response $this
     */
    public function stop() {
    	$this->_active = false;
    	return $this;
    }

    /**
     * @return A reference to the current response, if any is in progress.
     */
    public static function current() {

        if (!Octopus_App::isStarted()) {
            return null;
        }

        $app = Octopus_App::singleton();
        return $app->getCurrentResponse();

    }

    private function checkNotStopped() {
    	if (!$this->_active) {
    		throw new Octopus_Exception("Values and headers cannot be set on an Octopus_Response after ::stop() has been called.");
    	}
    }

}
