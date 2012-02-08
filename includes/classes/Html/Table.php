<?php

/**
 *
 */
class Octopus_Html_Table extends Octopus_Html_Element {

    public static $defaultAttrs = array(

        'cellpadding' => 0,

        'cellspacing' => 0,

        'border' => 0,

    );

    public static $defaultOptions = array(

        /**
         * Text in the 'clear filters' link. To render your own link, set this
         * to false and use the getClearFiltersUrl() method to get the proper
         * url.
         */
        'clearFiltersLinkText' => 'Clear Filters',

        /**
         * Arg used to clear filters.
         */
        'clearFiltersArg' => 'clearfilters',

        /**
         * Whether or not to print debug messages while rendering.
         */
        'debug' => false,

        /**
         * Class applied to the table when there is nothing in it.
         */
        'emptyClass' => 'empty',

        /**
         * Content to display when there is nothing in the table.
         */
        'emptyContent' => '<p>Sorry, there is nothing to display. Try another search or filter.</p>',

        /**
         * Whether or not to render a <form> around the filters.
         */
        'filterForm' => true,

        /**
         * CSS class added to the first cell in a row.
         */
        'firstCellClass' => 'firstCell',

        /**
         * CSS class added to the last cell in a row.
         */
        'lastCellClass' => 'lastCell',

        /**
         * Max # of columns to allow sorting on.
         */
        'maxSortColumns' => 2,

        /**
         * Function to call to prepare a row for render. Will be called like:
         * prepareRow($tr, $data, $index)
         */
        'prepareRow' => false,

        /**
         * Argument used on the querystring to specify the col to sort on.
         */
        'sortArg' => 'sort',

        /**
         * Argument used in the querystring to indicate the current page.
         */
        'pageArg' => 'page',

        /**
         * Pager to use, or false not to use one.
         */
        'pager' => 'default',

        /**
         * A function to generate the text for the pager location block.
         * Receives 3 arguments: current page, page count, and the table.
         */
        'pagerLocationTextCallback' => null,

        /**
         * # of records per page.
         */
        'pageSize' => 20,

        /**
         * Pages to show around the current one.
         */
        'pagerDelta' => 10,

        /**
         * Current page we are on. Used to generate links for sorting/paging/etc.
         */
        'requestURI' => null,

        /**
         * $_GET values to use.
         */
        'queryString' => null,

        /**
         * Whether sorting should put the user back on the 1st page.
         */
        'resetPageOnSort' => true,

        'prevPageLinkText' => '&laquo; Previous',
        'nextPageLinkText' => 'Next &raquo;',

        'firstPageLinkText' => '&laquo; First Page',
        'lastPageLinkText' => 'Last Page &raquo;',

        /**
         * Default sorting to use when the user has not clicked any column headers.
         * format is the same as the args passed to sort()
         */
        'defaultSorting' => array(),

        /**
         * Whether or not to store sorting / filters in the session.
         */
        'useSession' => true,

        /**
         * Function to call to redirect the user. Gets passed a single arg:
         * the new redirect path. To prevent any autoredirection, set this
         * to false.
         */
        'redirectCallback' => 'redirect',

        /**
         * Whether, when the user clicks 'clear filters', to also reset the
         * sorting to the default.
         */
        'resetSortingOnClearFilters' => true
    );

    private $_options;

    private $_dataSource = null;
    private $_originalDataSource = null;
    private $_pagerData = null;
    private $_currentPage = 1;

    private $_shouldInitFromEnvironment = true;

    private $_columns = array();
    private $_sortColumns = array();
    private $_filters = array();
    private $_pagerOptions = null;

    private $_queryString = null;

    public function __construct($id, $options = array()) {

        $attrs = array('id' => $id);
        foreach(self::$defaultAttrs as $attr => $value) {
            if (isset($options[$attr])) {
                $attrs[$attr] = $options[$attr];
                unset($options[$attr]);
            }
        }
        $attrs = array_merge(self::$defaultAttrs, $attrs);

        parent::__construct('table', $attrs);

        $this->_options = array_merge(self::$defaultOptions, $options);
        $o =& $this->_options;

        $this->_pagerOptions = array(
            'perPage' => $o['pageSize'],
            'urlVar' => $o['pageArg'],
            'delta' => $o['pagerDelta'],
            'firstPageText' => $o['firstPageLinkText'],
            'lastPageText' => $o['lastPageLinkText'],
            'firstPagePre' => '',
            'firstPagePost' => '',
            'lastPagePre' => '',
            'lastPagePost' => '',
            'nextImg' => $o['nextPageLinkText'],
            'prevImg' => $o['prevPageLinkText'],
            'curPageLinkClassName' => 'current',
        );
    }

    /**
     * Add a column to this table.
     * @param $id string Unique ID for this column.
     * @param $title string Header title for this column.
     * @param $function Mixed Function to use to generate the content for this
     * column.
     * @param $options Any extra options.
     */
    function addColumn($id, $title = null, $function = null, $options = null) {

        $column = null;

        if ($id instanceof Octopus_Html_Table_Column) {
            $column = $id;
        } else {
            $column = $this->createColumn($id, $title, $function, $options);
        }

        $this->_columns[$column->id] = $column;

        return $column;

    }

    protected function createColumn($id, $title, $function, $options) {

        if ($options === null) {
            if ($function === null && is_array($title)) {
                // support addColumn('id', array('opt' => 'value'))
                $options = $title;
                $title = null;
            } else {
                $options = array();
            }
        }

        if ($title !== null) {
            $options['title'] = $title;
        }

        if ($function !== null) {
            $options['function'] = $function;
        }

        $column = new Octopus_Html_Table_Column($id, $options, $this);

        $actions = array();

        if ($id == 'actions' || $id == 'toggles') {

            // Allow passing 'actions' or 'toggles' directly to addColumn()

            foreach(Octopus_Html_Table_Column::$defaults as $key => $value) {
                unset($options[$key]);
            }

            foreach($options as $actionID => $actionOptions) {

                if (is_numeric($actionID)) {
                    $actionID = $actionOptions;
                    $actionOptions = array();
                }
                $actionOptions['id'] = $actionID;
                if ($id == 'toggles') $actionOptions['type'] = 'toggle';
                $actions[] = $actionOptions;
            }
        } else if (isset($options['type'])) {
            $options['id'] = $id;
            $actions[] = $options;
        }

        if (isset($options['actions'])) {
            foreach($options['actions'] as $a) {
                $actions[] = $a;
            }
        }

        if (isset($options['toggles'])) {
            foreach($options['toggles'] as $t) {
                if (!isset($t['type'])) $t['type'] = 'toggle';
                $actions[] = $t;
            }
        }

        if (empty($actions)) {
            return $column;
        }

        foreach($actions as $action) {
            $column->addAction($action);
        }

        return $column;

    }

    /**
     * Adds one or more columns to this table.
     */
    function addColumns(/* polymorphic */) {

        $args = func_get_args();
        foreach($args as $arg) {

            if (is_string($arg)) {
                $this->addColumn($arg);
            } else if (is_array($arg)) {
                foreach($arg as $id => $options) {

                    if (is_numeric($id)) {
                        // assume this is an array index

                        if (is_string($options)) {
                            $id = $options;
                            $options = array();
                        } else {
                            $id = $options['id'];
                        }
                    }

                    $this->addColumn($id, $options);
                }
            }

        }

        return $this;
    }

    /**
     * Adds a filter control to this table.
     */
    public function addFilter($type, $id = null, $label = null, $options = null) {

        if ($type instanceof Octopus_Html_Table_Filter) {
            $filter = $type;
        } else {
            $filter = Octopus_Html_Table_Filter::create($type, $id, $label, $options);
        }

        if ($filter) {
            $this->_filters[$filter->id] = $filter;
        }

        return $filter;
    }

    public function getFilter($id) {
        $this->initFromEnvironment();
        return isset($this->_filters[$id]) ? $this->_filters[$id] : null;
    }

    /**
     * @return Mixed A column by ID or false if it is not found.
     */
    public function getColumn($id) {
        return isset($this->_columns[$id]) ? $this->_columns[$id] : false;
    }

    /**
     * @return Array The columns in this table.
     */
    public function getColumns() {
        return $this->_columns;
    }

    public function getPageSize() {
        return $this->_options['pageSize'];
    }

    public function setPageSize($size) {
        $this->_options['pageSize'] = $this->_pagerOptions['perPage'] = $size;
        return $this->resetData();
    }

    public function getPage() {
        $this->initFromEnvironment();
        return $this->_currentPage;
    }

    public function getPageCount() {
        return $this->getPagerData('pages');
    }

    public function setPage($page) {

        $this->_currentPage = $page;

        $this->rememberState();
        $this->resetData();
        $this->dontInitFromEnvironment();

        return $this;
    }

    public function nextPage() {
        $page = $this->getPage();
        $page++;
        if ($page <= $this->getPageCount()) {
            $this->setPage($page);
        }
        return $this;
    }

    public function prevPage() {
        $page = $this->getPage();
        $page--;
        if ($page >= 1) {
            $this->setPage($page);
        }
        return $this;
    }

    /**
     * Applies a filter to the data in this table. This will modify the actual
     * data source, so you can use getDataSource() after calling this to get
     * the filtered data.
     */
    public function filter() {

        $this->initFromEnvironment();

        $args = func_get_args();

        // let $table->filter(false) == $table->unfilter()
        if (count($args) == 1 && $args[0] === false) {
            return $this->unfilter();
        }

        $filterID = null;
        $toApply = array();

        foreach($args as $arg) {

            if (!is_array($arg)) {
                if ($filterID === null) {
                    $filterID = $arg;
                } else {
                    $toApply[$filterID] = $arg;
                    $filterID = null;
                }
                continue;
            }

            foreach($arg as $id => $value) {
                $toApply[$id] = $value;
            }
        }

        foreach($toApply as $id => $value) {
            if ($value === '') {
                unset($toApply[$id]);
                continue;
            }
            $filter = $this->getFilter($id);
            if (!$filter) continue;
            $filter->val($value);
        }

        $ds = $this->_originalDataSource;
        foreach($this->_filters as $key => $filter) {
            if (isset($toApply[$key])) {
                $filteredDS = $filter->apply($ds, $this);
                if ($filteredDS !== null) $ds = $filteredDS;
            }
        }

        $this->internalSetDataSource($ds, false);
        $this->rememberState();

        return $this;
    }

    /**
     * Restores the datasource to its state before filter() was called.
     */
    public function unfilter() {

        foreach($this->_filters as $filter) {
            $filter->clear();
        }

        $this->internalSetDataSource($this->_originalDataSource, true);

        $this->rememberState();

        return $this;
    }

    /**
     * Removes any filters on this table deletes them from session storage.
     */
    public function clearFilters() {
        $this->unfilter();
        $this->forgetState('filter');
    }

    /**
     * @return String The URL to use to clear the filters on this table.
     */
    public function getClearFiltersUrl() {

        $uri = $this->getRequestURI(false);
        $qs = $this->getQueryString();

        foreach($this->_filters as $f) {
            unset($qs[$f->id]);
        }

        $qs[$this->_options['clearFiltersArg']] = 1;

        return $uri . '?' . http_build_query($qs);
    }

    public function render($return = false, $escape = self::ESCAPE_ATTRIBUTES) {

        $this->initFromEnvironment();

        if ($this->isEmpty()) {
            $this->addClass($this->_options['emptyClass']);
        } else {
            $this->removeClass($this->_options['emptyClass']);
        }

        // Do our own custom rendering
        $html =
            $this->renderHeader() .
            $this->renderBody() .
            $this->renderFooter();

        if ($return) {
            return $html;
        } else {
            echo $html;
        }
    }

    /**
     * Resets sorting, paging, and filter state. Keeps columns and filters
     * that have been added. Clears the data source.
     */
    public function reset() {
        parent::reset();

        $this->resetData();

        foreach($this->_columns as $c) {
            $c->sort(false);
        }
        $this->_sortColumns = array();

        foreach($this->_filters as $f) {
            $f->clear();
        }

        $this->forgetState();

        $this->_shouldInitFromEnvironment = true;
    }

    /**
     * @return Array An array of arrays where each member is a row in the table.
     * Items in each row array will contain the rendered content for that cell.
     */
    public function &toArray() {

        $result = array();

        $header = array();
        foreach($this->getColumns() as $col) {
            $header[] = $col->title();
        }
        $result[] = $header;

        $this->renderBody($result);

        return $result;
    }

    public function isEmpty() {
        return $this->count() == 0;
    }

    public function hasRows() {
        return !!$this->count();
    }

    /**
     * @return Number The total # of records.
     */
    public function count() {
        return $this->getPagerData('totalItems');
    }

    public function isSorted() {
        $this->initFromEnvironment();
        return !!count($this->_sortColumns);
    }

    /**
     * @return Mixed The data being shown in the table.
     */
    public function &getData() {
        $pd = $this->getPagerData();
        $data =& $pd['data'];
        return $data;
    }

    public function &getDataSource() {

        // Make sure filters are applied before returning data source
        $this->initFromEnvironment();

        return $this->_dataSource;
    }

    public function setDataSource($dataSource) {
        return $this->internalSetDataSource($dataSource, true);
    }

    private function internalSetDataSource($dataSource, $isOriginal) {

        $this->_dataSource = $dataSource;

        if ($isOriginal) {
            $this->_originalDataSource = $dataSource;
        }

        return $this->resetData();
    }

    protected function resetData() {
        $this->_pagerData = null;
        return $this;
    }

    public function getDefaultSorting() {
        return $this->_options['defaultSorting'];
    }

    public function setDefaultSorting(/* variable */) {

        $args = func_get_args();
        $this->resolveSortColumnArgs($args, $defaultSorting);
        $this->_options['defaultSorting'] = $defaultSorting;

        return $this;
    }

    private function sort(/* lots of different ways */) {

        $this->initFromEnvironment();

        $args = func_get_args();
        $this->resolveSortColumnArgs($args, $newSortingArgs);

        // $newSortingArgs is now an array in the form
        // array( 'column id' => 'ASC' or 'DESC')

        $this->_sortColumns = array();

        foreach($newSortingArgs as $id => $dir) {

            $col = $this->getColumn($id);

            if ($col) {
                $col->sort($dir);
                $this->_sortColumns[] = $col;
            }
        }

        $this->rememberState();

        if ($this->_options['resetPageOnSort']) {
            $this->setPage(1);
        }

        return $this;
    }

    private function resolveSortColumnArgs($args, &$cols = null) {

        if ($cols === null) {
            $cols = array();
        }

        foreach($args as $key => $col) {

            if (is_array($col)) {
                $this->resolveSortColumnArgs($col, $cols);
                continue;
            }

            if (is_numeric($key) && is_bool($col)) {
                // e.g., $table->sort(false);
                continue;
            }

            $asc = true;

            if (!is_numeric($key)) {
                $asc = self::parseSortDirection($col);
                $col = $key;
            }

            while(substr($col,0,1) == '!') {
                $asc = !$asc;
                $col = substr($col,1);
            }

            $cols[$col] = $asc ? OCTOPUS_SORT_ASC : OCTOPUS_SORT_DESC;
        }

    }

    /**
     * @return Array Full pager data for this table.
     */
    protected function getPagerData($key = null) {

        $this->initFromEnvironment();

        if ($this->_pagerData) {

            if ($this->_pagerData['currentPage'] !== $this->getPage()) {
                $this->_pagerData = null;
            }

        }


        if (!$this->_pagerData) {

            $ds = ($this->_dataSource ? $this->_dataSource : array());

            $options = array_merge($this->_pagerOptions, array('currentPage' => $this->_currentPage));

            $pager = new Octopus_Html_Table_Paginate($ds, $options, $this->_sortColumns);
            $this->_pagerData = $pager->getData();

            $this->rememberState();
        }

        if ($key === null) {
            return $this->_pagerData;
        }

        $keys = explode('.', $key);
        $v = $this->_pagerData;
        while($key = array_shift($keys)) {
            $v = $v[$key];
        }
        return $v;
    }

    private function debugging() {
        return !empty($this->_options['debug']);
    }

    /**
     * Returns HTML for a div describing the current position in the table.
     */
    protected function renderLocationDiv() {

        $o =& $this->_options;
        $p = $this->getPagerData();

        $func = $o['pagerLocationTextCallback'];
        if ($func && is_callable($func)) {
            $text = call_user_func($func, $p['currentPage'], $p['totalPages'], $this);
        } else {
            $text = "Showing {$p['from']} to {$p['to']} of {$p['totalItems']}";
        }

        return <<<END
            <div class="pagerLoc">
                $text
            </div>
END;
    }

    /**
     * @return Array $_GET values.
     */
    protected function getQueryString() {

        if ($this->_queryString !== null) {
            return $this->_queryString;
        }

        if ($this->_options['queryString'] !== null) {
            $qs = $this->_options['queryString'];
        } else {
            $uri = $this->getRequestURI();
            $pos = strpos($uri, '?');
            if ($pos === false) {
                $qs = $_GET;
            } else {
                $qs = substr($uri, $pos + 1);
            }
        }

        if (!is_array($qs)) {
            parse_str($qs, $qs);
        }

        $this->_queryString = $qs;
        return $qs;
    }

    /**
     * Writes the current state of the table to the session.
     */
    protected function rememberState() {

        if (!$this->_options['useSession']) {
            return false;
        }

        $this->getSessionKeys($this->getRequestURI(false), $sort, $page, $filter);

        $_SESSION[$sort] = self::buildSortString($this->_sortColumns);
        $_SESSION[$page] = $this->getPage();
        $_SESSION[$filter] = $this->internalGetFilterValues();
    }

    /**
     * Clears out any session storage.
     */
    protected function forgetState($what = null) {

        $this->_queryString = null;
        $this->getSessionKeys($this->getRequestURI(false), $sort, $page, $filter);

        if ($what === null || $what == 'sort') unset($_SESSION[$sort]);
        if ($what === null || $what == 'page') unset($_SESSION[$page]);
        if ($what === null || $what == 'filter') unset($_SESSION[$filter]);
    }

    /**
     * @return String Comma-delimited string describing current sorting.
     */
    private function buildSortString($sorting) {

        $result = '';

        $ds = $this->getDataSource();

        foreach($sorting as $key => $value) {

            if (is_string($key)) {
                $col = $this->getColumn($key);
                $asc = $value;
            } else {
                $col = $value;
                $asc = $col->isSortedAsc($ds);
            }

            if (!$col->isSortable($ds)) {
                continue;
            }

            if ($result) $result .= ',';

            $result .= ($asc ? '' : '!') . $col->id;
        }

        return $result;
    }

    protected function &internalGetFilterValues($source = null) {

        $values = array();
        foreach($this->_filters as $f) {
            if ($source !== null) {
                if (isset($source[$f->id])) {
                    $values[$f->id] = $source[$f->id];
                }
            } else {
                $val = $f->val();

                if (trim($val) !== '') {
                    $values[$f->id] = $val;
                }
            }
        }

        return $values;
    }

    public function &getFilterValues() {
        $this->initFromEnvironment();
        $values = $this->internalGetFilterValues();
        return $values;
    }

    /**
     * @return String The URL to use to sort on the given column.
     */
    protected function getSortingUrl($column) {

        $ds = $this->getDataSource();
        if (!$column->isSortable($ds)) {
            return '';
        }

        $newSorting = array($column->id => true);
        $first = true;


        foreach($this->_sortColumns as $col) {

            if (count($newSorting) >= $this->_options['maxSortColumns']) {
                break;
            }

            if ($column == $col) {

                if ($first) {

                    // Clicking on the thing that is already primarily sorted
                    // inverts the sort order of that column.
                    $newSorting[$col->id] = !$col->isSortedAsc($ds);

                }

            } else {
                $newSorting[$col->id] = $col->isSortedAsc($ds);
            }

            $first = false;
        }

        $qs = $this->getQueryString();
        $arg = $this->_options['sortArg'];

        if (empty($newSorting)) {
            unset($qs[$arg]);
        } else {
            $qs[$arg] = self::buildSortString($newSorting);
        }

        if ($this->_options['resetPageOnSort']) {
            unset($qs[$this->_options['pageArg']]);
        }

        $qs = http_build_query($qs);

        $url = $this->getRequestURI(false);
        if ($qs) $url .= '?' . $qs;

        return $url;
    }


    /**
     * Puts content in a <th>
     */
    protected function fillHeaderCell($th, $column) {

        $html = '';
        $close = '';

        if ($column->isSortable($this->getDataSource())) {
            $html .= '<a href="' . $this->getSortingUrl($column) . '"><span class="sortMarker">';
            $close .= '</span></a>';
        }

        $html .= h($column->title());

        $th->append($html . $close);
    }

    /**
     * @return String The current requested URI.
     * @param $includeQueryString bool Whether or not to strip the querystring.
     */
    protected function getRequestURI($includeQueryString = true) {

        $uri = '';

        if (isset($this->_options['requestURI'])) {
            $uri = $this->_options['requestURI'];
        } else if (isset($this->_options['REQUEST_URI'])) {
            $uri = $this->_options['REQUEST_URI'];
        } else if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
        }

        if (!$includeQueryString) {
            $pos = strpos($uri, '?');
            if ($pos !== false) {
                $uri = substr($uri, 0, $pos);
            }
        }

        return $uri;
    }

    public function getSessionKeys($uri, &$sort, &$page, &$filter) {

        $base = 'octopus-table-' . to_slug($uri) . '-' . $this->id . '-';

        $sort = $base . 'sort';
        $page = $base . 'page';
        $filter = $base . 'filter';
    }

    protected function renderPagingLinks() {

        return Octopus_Html_Table_Paginate::makeLinks($this->getPagerData(), $this->_options);

    }

    private function redirect($uri) {

        $callback = $this->_options['redirectCallback'];
        if (!$callback) return false;

        call_user_func($callback, u($uri));
        return true;
    }

    private function dontInitFromEnvironment() {
        $this->_shouldInitFromEnvironment = false;
    }

    /**
     * Looks at external factors, like querystring args and session data,
     * and restores the table's state.
     */
    private function initFromEnvironment() {

        if (!$this->_shouldInitFromEnvironment) {
            return;
        }
        $this->dontInitFromEnvironment();

        $useSession = $this->_options['useSession'];
        $sortArg = $this->_options['sortArg'];
        $pageArg = $this->_options['pageArg'];

        $uri = $this->getRequestURI(false);
        $qs = $this->getQueryString();
        $this->getSessionKeys($uri, $sessionSortKey, $sessionPageKey, $sessionFilterKey);

        $clearFiltersArg = $this->_options['clearFiltersArg'];
        if (isset($qs[$clearFiltersArg]) && $qs[$clearFiltersArg]) {

            $this->clearFilters();
            unset($qs[$clearFiltersArg]);

            if ($this->_options['resetSortingOnClearFilters']) {
                $this->sort(false);
                unset($qs[$sortArg]);
            }

            $qs = http_build_query($qs);
            if ($qs) $uri .= '?' . $qs;
            $this->redirect($uri);
            return;
        }

        $sort = null;
        $page = null;

        $filterValues = $this->internalGetFilterValues($qs);
        if (empty($filterValues) && isset($_SESSION[$sessionFilterKey])) {
            $filterValues = $this->internalGetFilterValues($_SESSION[$sessionFilterKey]);
        }

        $this->unfilter()->filter($filterValues);

        if (isset($qs[$sortArg])) {
            $sort = rawurldecode($qs[$sortArg]);
        } else if ($useSession && isset($_SESSION[$sessionSortKey])) {
            $sort = $_SESSION[$sessionSortKey];
        }

        if (isset($qs[$pageArg])) {
            $page = $qs[$pageArg];
        } else if ($useSession && isset($_SESSION[$sessionPageKey])) {

            // Ensure that changing the sorting resets the page.
            if (!isset($qs[$sortArg])) {
                $page = $_SESSION[$sessionPageKey];
            }
        }

        // Ensure the current page's URL reflects the actual state
        if ($this->_options['redirectCallback']) {
            $actual = $expected = $qs;

            if ($sort) {
                $expected[$sortArg] = $sort;
            } else {
                unset($expected[$sortArg]);
            }

            if ($page && $page != 1) { // don't redirect to page=1
                $expected[$pageArg] = $page;
            } else {
                unset($expected[$pageArg]);
            }

            foreach($filterValues as $key => $val) {
                $expected[$key] = $val;
            }

            ksort($expected);
            ksort($actual);

            if (array_diff_key($expected, $actual) || array_diff($expected, $actual)) {
                $newUri = $uri;
                $expected = http_build_query($expected);
                if ($expected) $newUri .= '?' . $expected;
                $this->redirect($newUri);
            }
        }

        if ($sort) {
            $this->sort(explode(',', $sort));
        } else {
            // Use default sorting
            $this->sort($this->getDefaultSorting());
        }

        // This needs to be called last or it won't be applied
        $this->setPage($page);
    }

    /**
     * Hook to tweak a row before it is loaded up with junk.
     */
    protected function prepareBodyRow($tr, &$data, $rowIndex) {

        $tr->class = ($rowIndex % 2 ? 'odd' : 'even');

        if (is_callable($this->_options['prepareRow'])) {
            call_user_func($this->_options['prepareRow'], $tr, $data, $rowIndex);
        }

    }

    /**
     * Hook to tweak a cell in the header before it has content added to it.
     */
    protected function prepareHeaderCell($th, $column, $columnIndex, $columnCount) {
        $this->prepareCell($th, $column, $columnIndex, $columnCount);

        $ds = $this->getDataSource();
        $sortable = $column->isSortable($ds);

        if (!$sortable) {
            return;
        }

        $asc = $column->isSortedAsc($ds);
        $desc = $column->isSortedDesc($ds);
        $sortClass = '';

        if ($asc || $desc) {
            $first = array_shift($this->_sortColumns);
            if ($first == $column) $sortClass = ($desc ? 'sortDesc' : 'sortAsc');
            array_unshift($this->_sortColumns, $first);
        }

        $th->addClass('sortable', $sortClass);
    }

    /**
     * Hook to tweak a cell before it is loaded w/ content.
     */
    protected function prepareCell($td, $column, $columnIndex, $columnCount) {

        $attrs = $column->getAttributes();

        $class = isset($attrs['class']) ? $attrs['class'] : '';

        if ($columnIndex == 1) {
            $class .= ' ' . $this->_options['firstCellClass'];
        } else if ($columnIndex == $columnCount) {
            $class .= ' ' . $this->_options['lastCellClass'];
        }

        $attrs['class'] = trim($class);

        $td->attr($attrs);
    }

    protected function renderBody(&$array = null) {

        $rows = $this->getData();
        $pagerData = $this->getPagerData();

        if ($pagerData['totalItems'] == 0) {
            $td = new Octopus_Html_Element('td', array('class' => 'emptyNotice'));
            $td->html($this->_options['emptyContent']);
            return '<tbody class="emptyNotice"><tr>' . $td . '</tr></tbody>';
        }

        $html = '<tbody>';

        $columnCount = count($this->_columns);

        $tr = new Octopus_Html_Element('tr');
        $td = new Octopus_Html_Element('td');

        $rowIndex = 1;
        foreach($rows as $row) {

            $tr->reset();
            $this->prepareBodyRow($tr, $row, $rowIndex);

            if ($array) $rowArray = array();

            $columnIndex = 1;
            foreach($this->_columns as &$column) {

                $td->reset();
                $this->prepareCell($td, $column, $columnIndex, $columnCount);

                $column->fillCell($td, $row);

                if ($array) {
                    $rowArray[] = $td->renderContent();
                }

                $tr->append($td->render(true));

                $columnIndex++;
            }

            $html .= $tr->render(true);
            $rowIndex++;

            if ($array) {
                $array[] = $rowArray;
            }
        }

        $html .= '</tbody>';

        return $html;

    }

    protected function renderFooter() {

        $pager = $this->renderPager();
        if (!$pager) return '</table>';

        $html = '<tfoot><tr><td class="pager" colspan="' . count($this->_columns) . '">';
        $html .= $pager;
        $html .= '</td></tr></tfoot></table>';

        return $html;
    }

    protected function renderFilters() {

        $td = new Octopus_Html_Element('td');
        $td->attr('class', 'filters')
            ->attr('colspan', count($this->getColumns()));

        $parent = $td;

        if ($this->_options['filterForm']) {
            $parent = new Octopus_Html_Element('form', array('class' => 'filterForm', 'method' => 'get', 'action' => ''));
            $td->append($parent);
        }

        $this->appendFilterElements($parent);

        if (count($parent->children()) == 0) {
            // no filter controls
            return '';
        }

        if ($this->_options['clearFiltersLinkText']) {
            $clear = new Octopus_Html_Element('a', array('class' => 'clearFilters', 'href' => $this->getClearFiltersUrl()), $this->_options['clearFiltersLinkText']);
            $parent->append($clear);
        }

        return '<thead class="filters"><tr>' . $td . '</tr></thead>';
    }

    /**
     * Appends all filter elements to the given container.
     */
    protected function appendFilterElements(Octopus_Html_Element $parent) {

        $index = 0;
        $count = count($this->_filters);
        foreach($this->_filters as $filter) {

            $wrap = new Octopus_Html_Element('div', array('class' => 'filter'));

            $wrap->addClass($filter->id, $filter->getType());
            if ($index == 0) $wrap->addClass('firstFilter');
            if ($index == $count - 1) $wrap->addClass('lastFilter');

            $label = $filter->createLabelElement();
            if ($label) {
                $wrap->append($label);
            }

            $wrap->append($filter->render(true));
            $parent->append($wrap);

            $index++;
        }

    }

    protected function renderHeader() {

        $html = $this->renderOpenTag();
        if (substr($html,-1) != '>') $html .= '>';

        $html .= $this->renderFilters();

        $html .= '<thead class="columns"><tr>';

        $th = new Octopus_Html_Element('th');

        $columnCount = count($this->_columns);
        $columnIndex = 1;

        foreach($this->_columns as $column) {

            $th->reset();
            $this->prepareHeaderCell($th, $column, $columnIndex, $columnCount);
            $this->fillHeaderCell($th, $column);

            $html .= $th->render(true);
            $columnIndex++;
        }

        $html .= '</tr></thead>';

        return $html;

    }

    protected function renderPager() {

        if (!$this->_options['pager']) {
            return '';
        }

        $p = $this->getPagerData();

        $html = $this->renderPagingLinks();
        $html .= $this->renderLocationDiv();

        return $html;
    }

    /**
     * @param $dir Mixed A string or number indicating sorting direction.
     * @return bool True to sort ascending, false to sort descending.
     */
    public static function parseSortDirection($dir) {

        if ($dir === true || $dir === false) {
            return $dir;
        } else if (is_numeric($dir)) {
            return !!$dir;
        } else {
            if (strcasecmp('desc', trim($dir)) == 0) {
                return false;
            } else {
                return true;
            }
        }

    }


    /**
     * Creates a new table from a model class.
     */
    public static function fromModel($modelClassOrResultSet, $options = array()) {

        $modelClass = $modelClassOrResultSet;
        $resultSet = null;

        if ($modelClassOrResultSet instanceof Octopus_Model_ResultSet) {
            $resultSet = $modelClassOrResultSet;
            $modelClass = $resultSet->getModel();
        }

        $model = new $modelClass();
        $table = new Octopus_Html_Table(camel_case($modelClass));

        foreach($model->getFields() as $f) {
            $table->addColumn($f->getFieldName());
        }

        if ($resultSet) $table->setDataSource($resultSet);

        return $table;

    }
}


?>
