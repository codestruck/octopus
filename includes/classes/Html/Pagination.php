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
    	 * CSS class used to mark the link to the current page.
    	 */
    	'currentClass' => 'current',

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
    	 * Class added to the pager when it is on the first page.
    	 */
    	'firstPageClass' => 'first-page',

    	/**
    	 * Class added to the pager when it is on the last page.
    	 */
    	'lastPageClass' => 'last-page',

    	/**
    	 * Largest allowed value for the 'pageSize' option.
    	 */
    	'maxPageSize' => 50,

    	'nextLinkClass' => 'next',

    	'nextLinkText' => 'Next',

        /**
         * QueryString arg used to specify the page.
         */
        'pageArg' => 'page',

        /**
         * # of items to show per page.
         */
        'pageSize' => 10,

        'prevLinkClass' => 'prev',

        'prevLinkText' => 'Previous',

        /**
         * Text content for spacers placed between two paging links to indicate
         * there are more pages between them, e.g.:
         *
         *	1 2 3 4 <sep> 9 10
         */
        'separatorText' => '&hellip;'

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

    	$this->updateCss();

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
    		$this->updateCss();
    	}

    }

    public function getPreviousPage() {
    	$page = $this->getCurrentPage() - 1;
		return max($page, 0);
    }

    public function getNextPage() {
    	$page = $this->getCurrentPage() + 1;
    	return min($page, $this->getPageCount() - 1);
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
    		$this->updateCss();
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
     * @param Number $page Page index to link to (1-based).
     * @param mixed $curretUrl URL to modify to generate the paging link.
     * @return String The URL to the given page.
     */
    public function getPageUrl($page, $currentUrl = null) {

        if ($currentUrl === null) $currentUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        $arg = $this->options['pageArg'];

        return u($currentUrl, array($arg => $page));
    }

    /**
     * @return Number the number of items being paged (disregarding any limits).
     */
    public function getTotalItemCount() {

    	$ds = $this->getDataSource();
    	return $ds ? count($ds) : 0;

    }

    public function render($return = false) {

    	$this->createPagingLinks();

    	return parent::render($return);

    }

    /**
     * Generates the list of paging links and appends them to the pager.
     */
    protected function createPagingLinks() {

    	$o =& $this->options;

    	$this->clear();

    	$nums = $this->getPageNumbers();
    	if (empty($nums)) {
    		return;
    	}

    	$prev = $this->createPagingLink($this->getPreviousPage() + 1, $o['prevLinkText'], $o['prevLinkClass']);
    	$this->append($prev);

    	$lastNum = null;
    	foreach($nums as $num) {

    		if ($lastNum !== null && $lastNum < $num - 1) {
    			$sep = $this->createSeparator($lastNum, $num);
    			if ($sep) $this->append($sep);
    		}

    		$link = $this->createPagingLink($num);
    		$this->append($link);

    		$lastNum = $num;

    	}

    	$next = $this->createPagingLink($this->getNextPage() + 1, $o['nextLinkText'], $o['nextLinkClass']);
    	$this->append($next);

    }

    protected function createPagingLink($page, $text = null, $class = '') {

		if (!$class && $page == $this->getCurrentPage() + 1) {
    		$class = $this->options['currentClass'];
    	}

    	$l = new Octopus_Html_Element('a');
    	if ($class) $l->addClass($class);
    	$l->text($text === null ? $page : $text);
    	$l->href = $this->getPageUrl($page);

    	return $l;

    }

    /**
     * Creates a an element to put between two paging links to indicate there
     * are more pages between them.
     */
    protected function createSeparator($prevPage, $nextPage) {
    	$el = new Octopus_Html_Element('span');
    	$el->addClass('sep');
    	$el->html('&hellip;');
    	return $el;
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

    private function updateCss() {

    	$pageCount = $this->getPageCount();
    	$page = $this->getCurrentPage();
    	$o =& $this->options;

    	if ($pageCount) {
    		$this->removeClass($o['emptyClass']);
    	} else {
    		$this->addClass($o['emptyClass']);
    	}

    	if ($pageCount && $page == 0) {
    		$this->addClass($o['firstPageClass']);
    	} else {
    		$this->removeClass($o['firstPageClass']);
    	}

    	if ($pageCount && $page == $pageCount - 1) {
    		$this->addClass($o['lastPageClass']);
    	} else {
    		$this->removeClass($o['lastPageClass']);
    	}

    }

}

