<?php

/**
 * A sortable, pageable, and filterable <table>.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
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
         * Sorting to use if none has been specified.
         */
        'defaultSorting' => array(),

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
        'pager' => true,

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
         * Default filter values to apply.
         */
        'defaultFilters' => array(),

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

    private $_columns = array();

    // Original data source passed to setDataSource()
    private $_originalDataSource = null;

    private $_filteredAndSortedDataSource = null;
    private $_currentDataSourceFilters = null; // filter values used when $_filteredAndSortedDataSource was created
    private $_currentDataSourceSorting = null; // sorting values used when $_filteredAndSortedDataSource was created

    private $_filters = array(); // Keys = id, values = Octopus_Html_Table_Filter object
    private $_filterValues = array(); // Keys = id, values = corresponding filter
    private $_page = 1;
    private $_pager = null; // Octopus_Html_Pager
    private $_sorting = array();

    private $_restored = false;
    private $_sortCalled = false;
    private $_filterCalled = false;
    private $_setPageCalled = false;

    private $_sessionKeys = array();

    private $_styler = null;

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
    }

/*******************************************************************************
 *
 * Public Methods
 *
 ******************************************************************************/

    /**
     * Add a column to this table.
     * @param $id string Unique ID for this column.
     * @param $title string Header title for this column.
     * @param $function Mixed Function to use to generate the content for this
     * column.
     * @param $options Any extra options.
     */
    public function addColumn($id, $title = null, $function = null, $options = null) {

        $column = null;

        if ($id instanceof Octopus_Html_Table_Column) {
            $column = $id;
        } else {
            $column = $this->createColumn($id, $title, $function, $options);
        }

        $this->_columns[$column->id] = $column;

        // Ensure that, as we add columns, they get sorting state data applied to them
        $this->applySortingToColumns();

        $this->rememberState('sorting');

        $this->invalidate();

        return $column;
    }

    /**
     * Adds one or more columns to this table.
     */
    public function addColumns(/* polymorphic */) {

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
            $filter = Octopus_Html_Table_Filter::create($this, $type, $id, $label, $options);
        }

        if (!$filter) {
            return;
        }

        if (isset($this->_filterValues[$filter->id])) {
            $filter->val($this->_filterValues[$filter->id]);
        }

        $this->_filters[$filter->id] = $filter;

        // This new filter might correspond to a value previously passed to
        // filter(), so ensure data is requeried.
        $this->invalidate();

        return $filter;
    }

    /**
     * Removes any filters on this table deletes them from session storage.
     */
    public function clearFilters() {
        $this->unfilter();
        $this->forgetState('filter');
        return $this;
    }

    /**
     * @return Number The total # of records.
     */
    public function count() {
        $ds = $this->getDataSource();
        return $ds ? $ds->count() : 0;
    }

    /**
     * Creates an HTML element that includes paging links.
     * Octopus_Html_Table_Filter_Pager uses this when rendering a pager in
     * the filter area.
     */
    public function createPagerElement() {

        $pager = $this->getPager();

        $el = new Octopus_Html_Element('div', array('class' => 'pager'));

        $links = new Octopus_Html_Element('div', array('class' => 'pagerLinks'));
        $pager->renderInto($links);
        $el->append($links);

        $pos = $this->createPositionElement();
        if ($pos) $el->append($pos);

        return $el;
    }

    /**
     * Applies a filter to the data in this table. This will modify the actual
     * data source, so you can use getDataSource() after calling this to get
     * the filtered data.
     * @see ::unfilter
     */
    public function filter() {

        $args = func_get_args();

        // let $table->filter(false) == $table->unfilter()
        if (count($args) == 1 && $args[0] === false) {
            return $this->unfilter();
        }

        $filterID = null;

        foreach($args as $arg) {

            if (!is_array($arg)) {
                if ($filterID === null) {
                    $filterID = $arg;
                } else {

                    if ($arg !== null) {
                        $this->_filterValues[$filterID] = array('set' => true, 'value' => $arg);
                    }

                    $filterID = null;
                }
                continue;
            }

            foreach($arg as $id => $value) {

                if ($value === null) {
                    continue;
                }

                $this->_filterValues[$id] = array('set' => true, 'value' => $value);
            }
        }

        foreach($this->_filterValues as $key => $info) {

            if (!$info['set']) {
                continue;
            }

            $filter = $this->getFilter($key);
            if ($filter) $filter->val($info['value']);

        }

        $this->invalidate();

        $this->rememberState();

        $this->_filterCalled = true;

        return $this;
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

        return $uri . '?' . octopus_http_build_query($qs, '&');
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

    /**
     * @return Mixed The data being shown in the table.
     * @deprecated Use getItems()
     */
    public function &getData() {
        return $this->getItems();
    }

    /**
     * @return Mixed The current data source being displayed in this table, or
     * null if none is being displayed.
     */
    public function getDataSource() {
        $p = $this->getPager();
        return $p->getDataSource();
    }

    /**
     * Sets the Octopus_DataSource this table uses to retrieve data for display.
     * @param Mixed $dataSource An array or an Octopus_DataSource.
     * @return Octopus_Html_Table $this
     */
    public function setDataSource($dataSource) {

        $p = $this->getPager();

        $p->setDataSource($dataSource);
        $this->_originalDataSource = $p->getDataSource(); // Pager automatically converts e.g. arrays to DataSources

        $this->_restored = false;
        $this->_sortCalled = false;
        $this->_filterCalled = false;
        $this->_setPageCalled = false;

        $this->invalidate();

        return $this;
    }


    public function getDefaultFilters() {
        return $this->_options['defaultFilters'];
    }

    public function setDefaultFilters($filters) {
        $this->_options['defaultFilters'] = $filters;
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

    /**
     * @return Mixed Either an Octopus_Html_Table_Filter or false if none is
     * found.
     */
    public function getFilter($id) {
        return isset($this->_filters[$id]) ? $this->_filters[$id] : false;
    }

    /**
     * @return Array An array where keys are filter ids and values are the
     * values being used to filter on that id. The resulting array will only
     * contain values for filters that have already been added via addFilter().
     * @see addFilter
     */
    public function getFilterValues() {

        $values = array();

        $qs = $this->getQueryString();

        $sessionKey = $this->getSessionKey('filters');

        if ($sessionKey && isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
            $sessionValues = $_SESSION[$sessionKey];
        } else {
            $sessionValues = array();
        }

        foreach($this->_filters as $id => $filter) {

            if (isset($this->_filterValues[$id])) {

                if ($this->_filterValues[$id]['set']) {
                    $values[$id] = $this->_filterValues[$id]['value'];
                }

            } else if (isset($qs[$id])) {
                $values[$id] = $qs[$id];
                $this->_filterValues[$id] = array('set' => true, 'value' => $qs[$id]);
            } else if (isset($sessionValues[$id])) {
                $values[$id] = $sessionValues[$id];
                $this->_filterValues[$id] = array('set' => true, 'value' => $sessionValues[$id]);
            }

        }


        return $values;
    }

    /**
     * @return Mixed The actual data being shown in the table.
     */
    public function &getItems() {
        $p = $this->getPager();
        $items = $p->getItems();
        return $items;
    }

    public function getPage() {
        $p = $this->getPager();
        return $p->getPage();
    }

    public function setPage($page) {
        return $this->internalSetPage($page, true);
    }

    public function getPageCount() {
        $p = $this->getPager();
        return $p->getPageCount();
    }

    /**
     * @return Octopus_Html_Pager
     */
    public function getPager() {
        return $this->internalGetPager(true);
    }

    public function getPageSize() {
        $p = $this->getPager();
        return $p->getPageSize();
    }

    public function setPageSize($size) {
        $p = $this->internalGetPager(false);
        $p->setPageSize($size);
        return $this;
    }

    /**
     * @return Array Keys are column ids, values are true for sort ascending,
     * false for sort descending.
     */
    public function getSorting() {

        if ($this->_sorting !== null) {

            // Sorting has been set manually, so use that
            return $this->createSortingArray($this->_sorting);

        } else {
            return $this->getDefaultSorting();
        }

    }

    public function hasRows() {
        return !!$this->count();
    }

    public function isEmpty() {
        return !$this->count();
    }

    public function isSorted() {
        return !!count($this->getSorting());
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
     * Removes the column with the given ID from this table.
     */
    public function removeColumn($id) {

        unset($this->_columns[$id]);

        $this->rememberState();

        $this->invalidate();

        return $this;

    }

    /**
     * Removes a filter from this table.
     */
    public function removeFilter($id) {

        if (isset($this->_filters[$id])) {

            unset($this->_filters[$id]);
            $this->rememberState();
            $this->invalidate();

        }

        return $this;

    }

    public function render($return = false, $escape = self::ESCAPE_ATTRIBUTES) {

        if (!$this->_restored) {
            $this->restore();
        }

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
     * @return Octopus_Html_Table $this
     */
    public function reset() {

        parent::reset();

        foreach($this->_columns as $c) {
            $c->sort(false);
        }

        foreach($this->_filters as $f) {
            $f->clear();
        }

        $this->forgetState();

        return $this;
    }

    /**
     * Restores this table's state from the querystring and session storage.
     * Applies default filtering and sorting if none is specified. If
     * ::sort(), ::filter(), or ::setPage() have been called before this
     * method is called, the corresponding thing will not be restored.
     */
    public function restore() {

        $qs = $this->getQueryString();

        $this->clearFiltersIfNeeded($qs);

        // Check querystring / session for sorting information
        if (!$this->_sortCalled) {
            $this->restoreSorting($qs) ||
                $this->restoreSorting($_SESSION, true) ||
                    $this->sort($this->getDefaultSorting());
        }

        // Check querystring / session for filter data
        if (!$this->_filterCalled) {

            $this->restoreFilters($qs) ||
                $this->restoreFilters($_SESSION, true) ||
                    $this->filter($this->getDefaultFilters());

        }

        // Check querystring / session for page
        if (!$this->_setPageCalled) {
            $this->restorePage($qs) ||
                $this->restorePage($_SESSION, true);
        }

        $this->ensureQueryStringMatchesTableState();

        $this->_restored = true;

        return $this;

    }

    /**
     * Manually sorts this table. Generally speaking, you want to use
     * setDefaultSorting() rather than this method, because calling this will
     * override any sorting in the querystring (e.g., /whatever?sort=age gets
     * overwritten when you call sort('name')).
     * @see setDefaultSorting
     * @see unsort
     */
    public function sort(/* lots of different ways */) {

        $args = func_get_args();

        // Let sort(false) == unsort()
        if (count($args) === 1 && $args[0] === false) {
            $args = array();
        }

        $this->_sorting = $this->resolveSortColumnArgs($args, $newSortingArgs);

        // $newSortingArgs is now an array in the form
        // array( 'column id' => true or false)

        $this->applySortingToColumns();

        $this->rememberState();

        if ($this->_options['resetPageOnSort']) {
            $this->internalSetPage(1, false); // set page, but allow restoring page from qs later
        }

        $this->invalidate();

        $this->_sortCalled = true;

        return $this;
    }

    /**
     * @return Array An array of arrays where each member is a row in the table.
     * Items in each row array will contain the rendered content for that cell.
     * The first row in the result will contain column headers.
     */
    public function &toArray() {

        if (!$this->_restored) {
            $this->restore();
        }

        $result = array();

        $header = array();
        foreach($this->getColumns() as $col) {
            $header[] = $col->title();
        }
        $result[] = $header;

        $this->renderBody($result);

        return $result;
    }

    /**
     * Restores the datasource to its state before filter() was called.
     */
    public function unfilter() {

        foreach($this->_filters as $filter) {
            $filter->clear();
        }

        foreach($this->getFilterValues() as $key => $value) {
            $this->_filterValues[$key]['set'] = false;
        }

        $this->invalidate();

        $this->rememberState();

        return $this;
    }

    /**
     * Removes any sorting applied to this table
     */
    public function unsort() {
        return $this->sort(false);
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

/*******************************************************************************
 *
 * Protected Methods
 *
 ******************************************************************************/

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

            $element = $filter->getElement();
            if ($element) {
                $wrap->append($element);
            }

            $parent->append($wrap);

            $index++;
        }

    }

    protected function clearFiltersIfNeeded($qs) {

        // Check if the user has clicked the 'clear filters' link.
        $clearFiltersArg = $this->_options['clearFiltersArg'];

        if ($clearFiltersArg && !empty($qs[$clearFiltersArg])) {

            foreach($this->_filters as $id => $filter) {
                unset($qs[$id]);
            }

            if ($this->_options['resetSortingOnClearFilters']) {
                $sortArg = $this->_options['sortArg'];
                unset($qs[$sortArg]);
            }

            $this->forgetState();

            unset($qs[$clearFiltersArg]);

            return $this->reloadWithNewArgs($qs);
        }

        return;
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
     * Creates the little note describing where you are in the table.
     */
    protected function createPositionElement() {

        $pager = $this->getPager();

        $page = $pager->getPage();
        $pageCount = $pager->getPageCount();
        $pageSize = $pager->getPageSize();
        $itemCount = count($pager->getDataSource());

        if ($itemCount === 0) {
            return;
        }

        $first = (($page - 1) * $pageSize) + 1;
        $last = min($first + $pageSize - 1, $itemCount);

        $first = number_format($first);
        $last = number_format($last);
        $itemCount = number_format($itemCount);

        $el = new Octopus_Html_Element('div', array('class' => 'pagerLoc'));
        $el->html(<<<END
Showing <span class="pagerRangeStart">$first</span> to <span class="pagerRangeEnd">$last</span> of <span class="pagerItemCount">$itemCount</span>
END
        );

        return $el;
    }

    /**
     * If the table's current filters, sorting state, and page are not in the
     * querystring of the current page, redirects the user to a new URL that
     * includes all that information. This ensures that the state of the table
     * is always bookmarkable. To disable this functionality, set the
     * redirectCallback option to false.
     */
    protected function ensureQueryStringMatchesTableState() {

        if (empty($this->_options['redirectCallback'])) {
            return;
        }

        $sortArg = $this->_options['sortArg'];
        $pageArg = $this->_options['pageArg'];

        $uri = $this->getRequestURI(false);
        $actual = $expected = $this->getQueryString();

        $sorting = $this->getSortString();

        if ($sorting) {
            $expected[$sortArg] = $sorting;
        } else {
            unset($expected[$sortArg]);
        }

        $page = $this->getPage();

        if ($page && $page != 1) { // don't redirect to page=1
            $expected[$pageArg] = $page;
        } else {
            unset($expected[$pageArg]);
        }

        $filterValues = $this->getFilterValues();

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
     * Clears out any session storage.
     */
    protected function forgetState($what = null) {

        if ($what === null) $what = array('sorting', 'page', 'filters');
        if (!is_array($what)) $what = array($what);

        foreach($what as $key) {
            $key = $this->getSessionKey($key);
            unset($_SESSION[$key]);
        }

    }

    /**
     * Applies any filters set on this table to the table's data source. Then
     * sorts it.
     */
    protected function getFilteredAndSortedDataSource() {

        if ($this->_filteredAndSortedDataSource) {
            return $this->_filteredAndSortedDataSource;
        }

        $ds = $this->_originalDataSource;

        if (!$ds) {
            return;
        }

        $filterValues = $this->getFilterValues();
        $sorting = $this->getSorting();

        foreach($filterValues as $id => $value) {

            $filter = $this->getFilter($id);
            if (!$filter) continue;

            $filter->val($value);
            $ds = $filter->apply($ds);

        }

        foreach(array_reverse($sorting, true) as $id => $dir) {

            $col = $this->getColumn($id);
            $col->sort($dir);
            if ($col) {
                $ds = $col->applySorting($ds);
            }

        }

        $this->_filteredAndSortedDataSource = $ds;

        return $ds;
    }

    /**
     * @return Mixed The first column in the sort order, or null if table is
     * unsorted.
     */
    protected function getPrimarySortColumn() {

        foreach($this->getSorting() as $id => $dir) {

            $col = $this->getColumn($id);
            if ($col) return $col;

        }

    }

    /**
     * @return Array $_GET values.
     */
    protected function getQueryString() {

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

        return $qs;
    }


    /**
     * @return Number The page the user asked for, which may or may not be
     * valid.
     */
    protected function getRequestedPage() {


        if ($this->_page !== null) {
            return $this->_page;
        }

        $qs = $this->getQueryString();
        $pageArg = $this->_options['pageArg'];
        $page = 1;

        if (isset($qs[$pageArg])) {
            $page = $qs[$pageArg];
        } else if (($sessionKey = $this->getSessionKey('page')) && isset($_SESSION[$sessionKey])) {
            $page = $_SESSION[$sessionKey];
        }

        $page = preg_replace('/[^\d]+/', '', $page);

        return $page;
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

    /**
     * @return Mixed The key used to track $thing in $_SESSION, or false if
     * you're not supposed to use the session.
     */
    protected function getSessionKey($thing) {

        if (!$this->_options['useSession']) {
            return false;
        }

        $keys =& $this->_sessionKeys;

        if (isset($keys[$thing])) {
            return $keys[$thing];
        }

        if (!isset($keys['base'])) {
            $uri = $this->getRequestURI(false);
            $keys['base'] = 'octopus-table-' . substr(to_slug($uri), 0, 100) . '-' . $this->id . '-';
        }

        return ($keys[$thing] = $keys['base'] . $thing);
    }

    /**
     * @return String The URL to use to sort on the given column.
     */
    protected function getSortingUrl(Octopus_Html_Table_Column $column) {

        $dataSource = $this->getDataSource();

        if (!$column->isSortable($dataSource)) {
            return '';
        }

        $newSorting = array($column->id => true);

        foreach($this->getSorting() as $id => $dir) {

            if ($id == $column->id && count($newSorting) === 1) {

                // Clicking on the thing that is already primarily sorted
                // inverts the sort order of that column.
                $newSorting[$id] = !$dir;

            } else {
                $newSorting[$id] = $dir;
            }

        }

        $qs = $this->getQueryString();
        $arg = $this->_options['sortArg'];
        $qs[$arg] = $this->getSortString($newSorting);

        if ($this->_options['resetPageOnSort']) {
            unset($qs[$this->_options['pageArg']]);
        }

        $qs = octopus_http_build_query($qs, '&');

        $url = $this->getRequestURI(false);
        if ($qs) $url .= '?' . $qs;

        return $url;
    }

    /**
     * @return String Comma-delimited string describing current sorting. Used
     * to store the sorting in the querystring. Format of the string is:
     *
     *        col1,!col2,col3
     *
     * "!" means to invert sorting.
     */
    protected function getSortString($sorting = null) {

        $result = '';
        if ($sorting === null) $sorting = $this->getSorting();

        foreach($sorting as $id => $dir) {

            if ($result) $result .= ',';
            $result .= ($dir ? '' : '!') . $id;

        }

        return $result;
    }

    /**
     * Called when the page, filters, or sorting changes. Forces the datasource
     * that provides the current page of data to be rebuilt.
     */
    protected function invalidate() {
        $this->_filteredAndSortedDataSource = null;
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
            $primary = $this->getPrimarySortColumn();
            if ($primary === $column) {
                $sortClass = ($desc ? 'sortDesc' : 'sortAsc');
            }
        }

        $th->addClass('sortable', $sortClass);
    }

    /**
     * Reloads the current page, changing the querystring arguments.
     */
    protected function reloadWithNewArgs($args) {

        $uri = $this->getRequestURI(false);

        $qs = octopus_http_build_query($args, '&');
        if ($qs) $uri .= '?' . $qs;

        $this->redirect($uri);
    }

     /**
     * Writes the current state of the table to the session.
     */
    protected function rememberState($what = null) {

        if (!$what || $what === 'sorting') {

            $sortingKey = $this->getSessionKey('sorting');
            if ($sortingKey) $_SESSION[$sortingKey] = $this->getSorting();

        }

        if (!$what || $what === 'page') {
            $pageKey = $this->getSessionKey('page');
            if ($pageKey) $_SESSION[$pageKey] = $this->getRequestedPage();
        }

        if (!$what || $what === 'filters') {

            $filtersKey = $this->getSessionKey('filters');

            if ($filtersKey) {
                // Don't overwrite values for filters that have not been added yet
                $sessionValues = isset($_SESSION[$filtersKey]) ? $_SESSION[$filtersKey] : array();
                $_SESSION[$filtersKey] = array_merge($sessionValues, $this->getFilterValues());
            }
        }

    }

    protected function renderBody(&$array = null) {

        $columnCount = count($this->_columns);

        if ($this->isEmpty()) {
            $td = new Octopus_Html_Element('td', array('class' => 'emptyNotice', 'colspan' => $columnCount));
            $td->html($this->_options['emptyContent']);
            return '<tbody class="emptyNotice"><tr>' . $td . '</tr></tbody>';
        }

        $html = '<tbody>';

        // Reuse tr and td objects for each row because we can
        $tr = new Octopus_Html_Element('tr');
        $td = new Octopus_Html_Element('td');

        $rowIndex = 1;
        foreach($this->getItems() as $row) {

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

    protected function renderFooter() {

        $pager = $this->renderPager();
        if (!$pager) return '</table>';

        $html = '<tfoot><tr><td class="pager" colspan="' . count($this->_columns) . '">';
        $html .= $pager;
        $html .= '</td></tr></tfoot></table>';

        return $html;
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

    protected function renderPager() {

        if (!$this->_options['pager']) {
            return '';
        }

        $el = $this->createPagerElement();

        if ($el instanceof Octopus_Html_Element) {
            return $el->render(true);
        } else {
            return $el ? $el : '';
        }
    }

    protected function restoreFilters($ar, $useSessionKey = false) {

        if (empty($this->_filters)) {
            return;
        }

        $key = 'filters';
        if ($useSessionKey) $key = $this->getSessionKey('filters');
        if (!$key || !array_key_exists($key, $ar)) return false;

        $ar = $ar[$key];

        $filterIDs = array_keys($this->_filters);
        $filterValues = array();

        foreach($filterIDs as $id) {

            if (array_key_exists($id, $ar)) {
                $filterValues[$id] = $ar[$id];
            }

        }

        if (count($filterValues) > 0) {
            $this->filter($filterValues);
            return true;
        }

        return false;

    }

    protected function restorePage($ar, $useSessionKey = false) {

        $pageArg = $this->_options['pageArg'];
        if ($useSessionKey) $pageArg = $this->getSessionKey($pageArg);
        if (!$pageArg) return false;

        if (array_key_exists($pageArg, $ar)) {

            $this->internalSetPage($ar[$pageArg], false);
            return true;

        }

        return false;
    }

    protected function restoreSorting($ar, $useSessionKey = false) {

        $sortArg = $this->_options['sortArg'];
        if ($useSessionKey) $sortArg = $this->getSessionKey($sortArg);
        if (!$sortArg) return false;


        if (!array_key_exists($sortArg, $ar)) {
            return false;
        }

        $sorting = $this->createSortingArray($ar[$sortArg]);
        $this->sort($sorting);

        return true;

    }

/*******************************************************************************
 *
 * Private Methods
 *
 ******************************************************************************/

    /**
     * Ensures that the state of individual column objects reflects the actual
     * sorting state of the table.
     */
    private function applySortingToColumns() {

        $seen = array();

        foreach($this->getColumns() as $col) {
            $col->unsort();
        }

        foreach($this->getSorting() as $id => $dir) {
            $col = $this->getColumn($id);
            if ($col) $col->sort($dir);
        }
    }

    private function createSortingArray(/* $arg1, $arg2 */) {

        $result = array();

        foreach(func_get_args() as $arg) {

            if (!$arg) {
                continue;
            }

            if (is_string($arg)) {
                $arg = self::parseSortString($arg);
            }

            foreach($arg as $id => $dir) {

                $col = $this->getColumn($id);

                if (!$col || isset($result[$id])) {
                    continue;
                }

                $result[$id] = self::parseSortDirection($dir);
            }

        }

        return $result;
    }

    private function debugging() {
        return !empty($this->_options['debug']);
    }

    private function internalGetPager($updateDataSource = true) {

        if (!$this->_pager) {
            $pagerOptions = $this->_options;
            $this->_pager = new Octopus_Html_Pager($this->_options);
        }

        if ($updateDataSource || !$this->_pager->getDataSource()) {
            $this->_pager->setDataSource($this->getFilteredAndSortedDataSource());
        }

        $this->_pager->setPage($this->getRequestedPage());

        return $this->_pager;
    }

    private function internalSetPage($page, $markAsCalled) {
        $this->_page = $page;
        $this->rememberState();
        $this->_setPageCalled = $markAsCalled;
        return $this;
    }

    private function redirect($uri) {

        $callback = $this->_options['redirectCallback'];
        if (!$callback) return false;

        call_user_func($callback, u($uri));
        return true;
    }

    /**
     * Takes the mishmash of arguments passed to sort(), and returns an array
     * in a the format:
     *
     *    array('column_id' => true, 'column_id' => false)
     *
     * Where true means sort ascending and false means sort descending.
     *
     */
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

            $cols[$col] = !!$asc;
        }

        return $cols;
    }

    /**
     * Parses a string in the format 'col1,!col2' into an array whose keys are
     * column ids and values are sort direction (true = asc, false = desc).
     */
    private static function parseSortString($str) {

        $result = array();
        foreach(explode(',', $str) as $s) {

            $s = trim($s);
            $asc = true;

            while($s && $s[0] === '!') {
                $asc = !$asc;
                $s = substr($s, 1);
            }

            if ($s) {
                $result[$s] = $asc;
            }

        }

        return $result;

    }


}
