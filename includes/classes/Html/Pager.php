<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Pager extends Octopus_Html_Element {

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
         * Class added to the link to the first page
         */
        'firstLinkClass' => 'first-page',

        /**
         * Text used for the link to the first page.
         */
        'firstLinkText' => 'First',

        /**
         * Class added to the pager when it is on the first page.
         */
        'firstPageClass' => 'first-page',

        /**
         * Class used for the link to the last page.
         */
        'lastLinkClass' => 'last-page',

        /**
         * Text used for the link to the last page.
         */
        'lastLinkText' => 'Last',

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
         * QueryString arg used to specify the page. Set to false to disable
         * automatic intialization from the querystring.
         */
        'pageArg' => 'page',

        /**
         * # of items to show per page.
         */
        'pageSize' => 10,

        'prevLinkClass' => 'prev',

        'prevLinkText' => 'Previous',

        'renderIrrelevantLinks' => false,

        'singlePageClass' => 'single-page',

        /**
         * Text content for spacers placed between two paging links to indicate
         * there are more pages between them, e.g.:
         *
         *    1 2 3 4 <sep> 9 10
         */
        'separatorText' => '&hellip;',

    );

    private $dataSource = null;
    private $limitedDataSource = null;
    private $options;

    private $page = null;
    private $pageSize = null;

    private $links = null;
    private $renderedLinks = false;

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
        $this->page = null;
        $this->stateChanged();

        return $this;
    }

    /**
     * @return Array The Octopus_Html_Elements that make up the paging links.
     */
    public function getLinks() {

        if ($this->links !== null) {
            return $this->links;
        }

        return ($this->links = $this->createPagingLinks());

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
     * @return The index of the current page being displayed (1-based).
     */
    public function getPage() {

        if (!$this->dataSource) {
            return 1;
        }

        if ($this->page === null) {
            $p = $this->getDefaultPage();
            $this->setPage($p);
        }

        return $this->page;
    }

    /**
     * Sets the index of the current page being displayed.
     * @param Number $page The page index (1-based).
     */
    public function setPage($page) {

        if ($page < 1) $page = 1;

        $pageCount = $this->getPageCount();
        if ($pageCount && $page > $pageCount) $page = $pageCount;

        if ($this->page != $page) {
            $this->page = $page;
            $this->limitedDataSource = null;
            $this->stateChanged();
        }

    }

    public function getPreviousPage() {
        $page = $this->getPage() - 1;
        return max($page, 1);
    }

    public function getNextPage() {
        $page = $this->getPage() + 1;
        return min($page, $this->getPageCount());
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
        $start = ($this->getPage() - 1) * $count;

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
            $this->setPage(1);
            $this->stateChanged();
        }

        return $this;
    }

    /**
     * @return Array Page numbers to be displayed.
     */
    public function getPageNumbers($delta = null) {

        if ($delta === null) $delta = $this->options['delta'];

        $pageCount = $this->getPageCount();

        if ($pageCount <= 0) {
            return array();
        }

        $currentPage = $this->getPage();
        $startIndex = $currentPage - $delta;
        $endIndex = $currentPage + $delta;

        if ($startIndex < 0) {
            $endIndex += abs($startIndex);
            $startIndex = 1;
        }

        if ($endIndex > $pageCount) {
            $startIndex -= ($endIndex - $pageCount);
            $endIndex = $pageCount;
        }

        $startIndex = max(1, $startIndex);
        $endIndex = min($endIndex, $pageCount);

        $result = range($startIndex, $startIndex + ($endIndex - $startIndex));

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

    public function render($return = false, $escape = self::ESCAPE_ATTRIBUTES) {

        if (!$this->renderedLinks) {
            $this->renderInto($this);
            $this->renderedLinks = true;
        }

        return parent::render($return, $escape);
    }

    public function renderInto(Octopus_Html_Element $element, $includeIrrelevantLinks = false) {
        foreach($this->createPagingLinks($includeIrrelevantLinks) as $link) {
            $element->append($link);
        }
    }

    /**
     * @return Array Paging link elements.
     */
    protected function createPagingLinks($includeIrrelevant = null) {

        $o =& $this->options;
        $nums = $this->getPageNumbers();
        $links = array();

        if (empty($nums)) {
            return $links;
        }

        $includeIrrelevant = ($includeIrrelevant === null ? $o['renderIrrelevantLinks'] : $includeIrrelevant);

        $pageNum = $this->getPage();

        if ($pageNum > 1 || $includeIrrelevant) {
            $first = $this->createPagingLink(1, $o['firstLinkText'], $o['firstLinkClass']);
            if ($first) $links[] = $first;

            $prev = $this->createPagingLink($this->getPreviousPage(), $o['prevLinkText'], $o['prevLinkClass']);
            if ($prev) $links[] = $prev;
        }

        if (count($nums) > 1 || $includeIrrelevant) {

            $lastNum = null;
            foreach($nums as $num) {

                if ($lastNum !== null && $lastNum < $num - 1) {
                    $sep = $this->createSeparator($lastNum, $num);
                    if ($sep) $this->append($sep);
                }

                $link = $this->createPagingLink($num);
                if ($link) $links[] = $link;

                $lastNum = $num;

            }
        }

        if ($pageNum < $this->getPageCount() || $includeIrrelevant) {
            $next = $this->createPagingLink($this->getNextPage(), $o['nextLinkText'], $o['nextLinkClass']);
            if ($next) $links[] = $next;

            $last = $this->createPagingLink($this->getPageCount(), $o['lastLinkText'], $o['lastLinkClass']);
            if ($last) $links[] = $last;
        }

        return $links;
    }

    protected function createPagingLink($page, $text = null, $class = '') {

        if (!$class && $page == $this->getPage()) {
            $class = $this->options['currentClass'];
        }

        $l = new Octopus_Html_Element('a');
        if ($class) $l->addClass($class);

        if ($text) {
            $l->html($text);
        } else {
            $l->text($page);
        }

        $l->title = 'Page ' . $page;
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
            return 1;
        }

        $arg = $this->options['pageArg'];

        if (empty($arg) || !isset($_GET[$arg])) {
            return 1;
        }

        $page = preg_replace('/[^\d]/', '', $_GET[$arg]);

        return $page;
    }

    private function stateChanged() {

        $this->clear(); // TODO: only remove link elements
        $this->links = null;
        $this->renderedLinks = false;

        $pageCount = $this->getPageCount();
        $page = $this->getPage();
        $o =& $this->options;

        if ($pageCount) {
            $this->removeClass($o['emptyClass']);
        } else {
            $this->addClass($o['emptyClass']);
        }

        if ($pageCount && $page === 1) {
            $this->addClass($o['firstPageClass']);
        } else {
            $this->removeClass($o['firstPageClass']);
        }

        if ($pageCount && $page === $pageCount) {
            $this->addClass($o['lastPageClass']);
        } else {
            $this->removeClass($o['lastPageClass']);
        }

        if ($pageCount === 1) {
            $this->addClass($o['singlePageClass']);
        } else {
            $this->removeClass($o['singlePageClass']);
        }

    }

}

