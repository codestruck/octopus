<?php

/**
 *
 */
class Octopus_Html_Pagination extends Octopus_Html_Element {

    public static $defaults = array(

    	/**
    	 * CSS class applied to wrapping <div>
    	 */
    	'class' => 'pager',

    	/**
    	 * URL of the current page. If null, REQUEST_URI is used.
    	 */
    	'currentUrl' => null,

    	/**
    	 * # of pages to show around the current page (on either side)
    	 */
    	'delta' => 2,

    	/**
    	 * Class added to the pager when it is empty (no pages).
    	 */
    	'emptyClass' => 'empty',

    	/**
    	 * Largest allowed value for the 'pageSize' option.
    	 */
    	'maxPageSize' => 50,

        /**
         * QueryString arg used to specify the page.
         */
        'pageArg' => 'page',

        /**
         * # of items to show per page.
         */
        'pageSize' => 10,

    );

    private $dataSource = null;
    private $limitedDataSource = null;
    private $options;

    private $page = null;
    private $pageSize = null;
    private $readQueryString = false;

    protected $requireCloseTag = true;

    public function __construct($options = array(), $tag = null, $attrs = null) {

    	if (is_array($tag)) {
			$attrs = $tag;
			$tag = null;
    	}

    	if (is_string($options)) {
    		$tag = $options;
    		$options = array();
    	}

    	if ($attrs === null) {
    		$attrs = array();
    	}

    	if ($tag === null) {
    		$tag = 'div';
    	}

        $this->options = array_merge(self::$defaults, $options);

    	foreach(array('class') as $key) {
    		if (isset($this->options[$key])) {
    			$attrs[$key] = $this->options[$key];
    			unset($this->options[$key]);
    		}
    	}

    	$this->setPageSize($this->options['pageSize']);

    	parent::__construct($tag, $attrs);
    }

    public function getDataSource() {
    	return $this->dataSource;
    }

    public function setDataSource($ds) {

    	if (is_array($ds)) {
    		$ds = new Octopus_DataSource_Array($ds);
    	}

    	$this->dataSource = $ds;
    	$this->limitedDataSource = null;

    	if ($this->getPageCount()) {
    		$this->removeClass($this->options['emptyClass']);
    	} else {
    		$this->addClass($this->options['emptyClass']);
    	}
    }

    /**
     * @return Number The # of pages.
     */
    public function getPageCount() {

        $count = count($this->dataSource);
        $size = $this->getPageSize();
        return $size ? (int)ceil($count / $this->getPageSize()) : 0;

    }

    /**
     * @return The index of the current page being displayed (zero-based).
     */
    public function getCurrentPage() {

    	if ($this->page === null) {
    		$p = $this->getDefaultPage();
    		$this->setCurrentPage($p);
    	}

    	return $this->page ? $this->page : 0;
    }

    /**
     * Sets the index of the current page being displayed.
     * @param Number $page The page index (zero-based).
     */
    public function setCurrentPage($page) {

    	if ($page < 0) $page = 0;

    	$pageCount = $this->getPageCount();
    	if ($pageCount && $page >= $pageCount) $page = $pageCount - 1;

    	if ($this->page != $page) {
    		$this->page = $page;
    		$this->limitedDataSource = null;
    	}

    }

    /**
     * @return An iteratable set of the items to display for the current
     * page.
     */
    public function getItems() {

    	if (!$this->dataSource) {
    		return array();
    	}

    	$count = $this->getPageSize();
    	$start = $this->getCurrentPage() * $count;

    	if (!$this->limitedDataSource) {

    		$ds = $this->dataSource->unlimit();
    		$this->limitedDataSource = $ds->limit($start, $count);

    	}

    	return $this->limitedDataSource;
    }

    public function getPageSize() {
    	return $this->pageSize;
    }

    public function setPageSize($size) {

    	$size = max(1, $size);
    	$size = min($this->options['maxPageSize'], $size);

    	if ($this->pageSize != $size) {
    		$this->pageSize = $size;
    		$this->limitedDataSource = null;
    		$this->setCurrentPage(0);
    	}

    }

    /**
     * @return Array Page numbers to be displayed.
     */
    public function getPageNumbers($delta = null) {

    	if ($delta === null) $delta = $this->options['delta'];

    	$pageCount = $this->getPageCount();
    	$currentPage = $this->getCurrentPage();

    	if ($pageCount <= 0) {
    		return array();
    	}

    	$startIndex = $currentPage - $delta;
    	$endIndex = $currentPage + $delta;

    	if ($startIndex < 0) {
    		$endIndex += abs($startIndex);
    		$startIndex = 0;
    	}

    	if ($endIndex >= $pageCount) {
    		$startIndex -= (($endIndex + 1) - $pageCount);
    		$endIndex = $pageCount - 1;
    	}

    	$startIndex = max(0, $startIndex);
    	$endIndex = min($endIndex, $pageCount - 1);

    	$result = range($startIndex + 1, $startIndex + ($endIndex - $startIndex) + 1);

    	// Add pages at the end
    	for ($i = 1; $i <= $delta; $i++) {

    		$p = ($pageCount - 1) - ($delta - $i);

    		if ($p <= $endIndex) {
    			continue;
    		}

    		$result[] = $p + 1;

    	}

    	return $result;
    }

    /**
     * @return String The URL to the given page.
     */
    public function getPageUrl($page, $currentUrl = null) {

        if (!$currentUrl) $currentUrl = $_SERVER['REQUEST_URI'];

        $qPos = strpos($currentUrl, '?');

        if ($qPos === false) {
            return $url . "?$arg=" . rawurlencode($page);
        }

        $qs = substr($currentUrl, $qPos + 1);
        $args = array();
        parse_str($qs, $args);

        $args[$arg] = $page;

        return substr($currentUrl, $qPos) . '?' . http_build_query($args);
    }

    /**
     * @return Number the number of items being paged (disregarding any limits).
     */
    public function getTotalItemCount() {

    	$ds = $this->getDataSource();
    	return $ds ? count($ds) : 0;

    }

    /**
     * Reads the query string to figure out what the default page is.
     */
    protected function getDefaultPage() {

    	if (empty($this->dataSource)) {
    		return 0;
    	}

    	$arg = $this->options['pageArg'];

    	if ($this->readQueryString || empty($arg) || !isset($_GET[$arg])) {
    		return 0;
    	}

    	$page = preg_replace('/[^\d]/', '', $_GET[$arg]);
    	$this->readQueryString = true;

    	return $page ? $page : 0;
    }

}

