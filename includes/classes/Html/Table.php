<?php

Octopus::loadExternal('pager_wrapper');

Octopus::loadClass('Octopus_Html_Element');
Octopus::loadClass('Octopus_Html_Table_Column');

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
         * CSS class added to the first cell in a row.
         */
        'firstCellClass' => 'firstCell',

        /**
         * CSS class added to the last cell in a row.
         */
        'lastCellClass' => 'lastCell',

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
         * # of records per page.
         */
        'pageSize' => 30,

        /**
         * Pages to show around the current one.
         */
        'pagerDelta' => 10,

        /**
         * Whether sorting should put the user back on the 1st page.
         */
        'resetPageOnSort' => true,

        'prevPageLinkText' => '&laquo; Previous',
        'nextPageLinkText' => 'Next &raquo;',

        'firstPageLinkText' => '&laquo; First Page',
        'lastPageLinkText' => 'Last Page &raquo;'

    );

    private $_options;

    private $_dataSource = null;
    private $_pagerData = null;

    private $_columns = array();
    private $_sortColumns = array();
    private $_page = null;
    private $_path = null; // path to current page
    private $_qs = null; // current querystring
    private $_pagerOptions = null;

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
            'curPageLinkClassName' => 'current'

        );

        $this->figureOutSortingAndPaging();

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
        }


        $this->_columns[$id] = $column;

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
                    $this->addColumn($id, $options);
                }
            }

        }

        return $this;
    }

    /**
     * @return Mixed A column by ID or false if it is not found.
     */
    public function getColumn($id) {
        return isset($this->_columns[$id]) ? $this->_columns[$id] : false;
    }

    public function getCurrentPage() {
        return $this->getPagerData('page_numbers.current');
    }

    public function render($return = false) {

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

    /**
     * @return Mixed The data being shown in the table.
     */
    public function &getData() {
        $pd = $this->getPagerData();
        $data =& $pd['data'];
        return $data;
    }

    public function setDataSource($dataSource) {
        $this->_dataSource = $dataSource;
        $this->_data = null;
        return $this;
    }


    /**
     * @return Array Full pager data for this table.
     */
    private function getPagerData($key = null) {

        if (!$this->_pagerData) {

            $ds = ($this->_dataSource ? $this->_dataSource : array());

            if ($ds instanceof Octopus_Model_ResultSet) {
                $this->_pagerData = $this->getPagerDataForResultSet($ds);
            } else if (is_array($ds)) {
                $this->_pagerData = $this->getPagerDataForArray($ds);
            } else if (is_string($ds)) {
                $this->_pagerData = $this->getPagerDataForSql($ds);
            } else {
                trigger_error("Unsupported data source for table: " . $ds);
                return false;
            }

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

    private function getPagerDataForArray(&$ar) {

        // TODO: sort arrays

        return Pager_Wrapper_Array($ar, $this->_pagerOptions, $this->_options['pager'] === false);
    }

    private function getPagerDataForSql($sql) {

        $needOrderBy = true;

        foreach($this->_sortColumns as $name => $col) {

            if (!$col->isSorted()) {
                continue;
            }

            $dir = $col->getSorting();

            $sql .= ($needOrderBy ? ' ORDER BY ' : ', ');
            $sql .= "`{$name}` $dir";
            $needOrderBy = false;
        }

        Octopus::loadClass('Octopus_DB');

        return Pager_Wrapper_DB(Octopus_DB::singleton(), $sql, $this->_pagerOptions, $this->_options['pager'] === false);
    }

    private function getPagerDataForResultSet($resultSet) {

        foreach($this->_sortColumns as $name => $col) {

            if (!$col->isSorted()) {
                continue;
            }

            $rs = $rs->orderBy(array($col->id => $col->getSorting()));
        }

        return Pager_Wrapper_ResultSet($rs, $this->_pagerOptions, $this->_options['pager'] === false);
    }

    /**
     * Returns HTML for a div describing the current position in the table.
     */
    protected function renderLocationDiv() {

        $p = $this->getPagerData();

        return <<<END
            <div class="pagerLoc">
            Showing {$p['from']} to {$p['to']} of {$p['totalItems']}
            </div>
END;
    }

    /**
     * Returns the URL to use to sort on the given field.
     */
    protected function getSortingUrl(&$column) {

        $newSorting = array($column->id => 'asc');
        $first = true;
        foreach($this->_sortColumns as $name => $dir) {

            if (!isset($this->_columns[$name])) {
                continue;
            }

            if ($name == $column->id) {

                if ($first) {

                    if (isset($this->_sortColumns[$name])) {
                        $newSorting[$name] = ($this->_sortColumns[$name] == 'asc' ? 'desc' : 'asc');
                    }


                }
            } else {
                $newSorting[$name] = $dir;
            }

            $first = false;
        }

        $sort = array();
        foreach($newSorting as $name => $dir) {
            $sort[] = ($dir == 'desc' ? '!' : '') . $name;
        }

        $newQS = $this->_qs;

        if (!empty($sort)) {
            $newQS[$this->_options['sortArg']] = implode(',', $sort);
        }

        if ($this->_options['resetPageOnSort']) {
            unset($newQS[$this->_options['pageArg']]);
        }

        $newQS = http_build_query($newQS);

        $url = $this->_path;
        if ($newQS) $url .= '?' . $newQS;

        return $url;

    }


    /**
     * Puts content in a <th>
     */
    protected function fillHeaderCell($th, $column) {

        $html = '';
        $close = '';

        if ($column->isSortable()) {
            $html .= '<a href="' . $this->getSortingUrl($column) . '">';
            $close .= '</a>';
        }

        $html .= htmlspecialchars($column->title());

        if ($column->isSorted()) {
            $html .= '<span class="sort' . ($column->isSortedAsc() ? 'Asc' : 'Desc') . 'Marker"></span>';
        }


        $th->append($html . $close);
    }

    /**
     * @return String A nicely-formatted URL for use in the pager.
     */
    protected function getUrlForPaging() {

        $url = preg_replace('/(\?|&+)' . $this->_options['pageArg'] . '=\d*(&+|$)/i', '$1', $_SERVER['REQUEST_URI']);
        $url = rtrim($url, '&');
        $url .= strpos($url, '?') === false ? '?' : '&';

        return $url;
    }

    /**
     * Looks at external factors, like querystring args and session data,
     * and restores the table's state.
     */
    private function figureOutSortingAndPaging() {

        $sessionSortKey = '_octopus_table_' . strtolower($this->id) . '_sort';

        // First, get a clean copy of the querystring to work with. We do
        // this to get around interactions w/ apache rewriting
        $this->_path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $pos = strpos($this->_path, '?');
        if ($pos === false) {
            $this->_qs = array();
        } else {
            parse_str(substr($this->_path, $pos + 1), $this->_qs);
            $this->_path = substr($this->_path, 0, $pos);
        }

        $sortArg = $this->_options['sortArg'];
        $pageArg = $this->_options['pageArg'];

        $sort = null;
        $page = null;

        if (isset($_GET[$sortArg])) {
            $sort = $_GET[$sortArg];
        } else if (isset($_SESSION[$sessionSortKey])) {
            $sort = $_SESSION[$sessionSortKey];
        }

        if (isset($_GET[$pageArg])) {
            $page = $_GET[$pageArg];
        }

        // Ensure the current page's URL reflects the actual state
        $actual = $this->_qs;
        $expected = $this->_qs;

        if ($sort) {
            $expected[$sortArg] = $sort;
        } else {
            unset($expected[$sortArg]);
        }

        if ($page) {
            $expected[$pageArg] = $page;
        } else {
            unset($expected[$pageArg]);
        }

        ksort($expected);
        ksort($actual);

        if (array_diff_key($expected, $actual) || array_diff($expected, $actual)) {
            $query = http_build_query($expected);
            if ($query != '') $query = '?' . $query;
            redirect($this->_path . $query);
        }

        // We're on the right page, initialize the state
        $this->_page = $page;
        $this->_sortColumns = array();

        if ($sort) {
            foreach(explode(',', $sort) as $colID) {

                $asc = true;

                while(substr($colID,0,1) == '!') {
                    $asc = !$asc;
                    $colID = substr($colID,1);
                }

                $col = $this->getColumn($colID);

                if (!$col) {
                    continue;
                }

                $this->_sortColumns[$colID] = $col;
            }
        }

        $_SESSION[$sessionSortKey] = ($sort ? $sort : '');
    }

    /**
     * Hook to tweak a row before it is loaded up with junk.
     */
    protected function prepareBodyRow($tr, $rowIndex) {
        $tr->class = ($rowIndex % 2 ? 'odd' : 'even');
    }

    /**
     * Hook to tweak a cell in the header before it has content added to it.
     */
    protected function prepareHeaderCell($th, $column, $columnIndex, $columnCount) {
        $this->prepareCell($th, $column, $columnIndex, $columnCount);
        if ($column->isSortable()) $th->addClass('sortable');
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

    protected function renderBody() {

        $html = '<tbody>';

        $columnCount = count($this->_columns);

        $rows = $this->getData();

        $tr = new Octopus_Html_Element('tr');
        $td = new Octopus_Html_Element('td');

        $rowIndex = 1;
        foreach($rows as $row) {

            $tr->reset();
            $this->prepareBodyRow($tr, $rowIndex);

            $columnIndex = 1;
            foreach($this->_columns as &$column) {

                $td->reset();
                $this->prepareCell($td, $column, $columnIndex, $columnCount);

                $column->fillCell($td, $row);

                $tr->append($td->render(true));

                $columnIndex++;
            }

            $html .= $tr->render(true);

            $rowIndex++;
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

    protected function renderHeader() {

        $html = $this->renderOpenTag();
        if (substr($html,-1) != '>') $html .= '>';

        $html .= '<thead><tr>';

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

        $html = '<div class="pagerWrapper">';

        if (count($p['data']) < $p['totalItems']) {

            // Pager fucks up our nice urls, so we substitute in good ones.

            $replacement =
                'href="' .
                str_replace('$', '\\$', $this->getUrlForPaging()) .
                $this->_options['pageArg'] . '=$3$4"';

            $links = preg_replace(
                '/href="(.*?)(\?|&amp;)' . $this->_options['pageArg'] . '=(\d+)(.*?)"/i',
                $replacement,
                $p['links']
            );

            $html .= $links;
        }

        $html .= $this->renderLocationDiv();

        $html .= '</div>';

        return $html;
    }

}


?>