<?php

Octopus::loadExternal('pager_wrapper');

/**
 *
 */
class Octopus_Html_Table_Listing {

    static $defaults = array(

        'cellPadding' => 0,

        'cellSpacing' => 0,

        'border' => 0,

        'class' => 'sgTable',

        /**
         * Argument used on the querystring to specify the col to sort on.
         */
        'sortArg' => 'sort',

        /**
         * Argument used in the querystring to indicate the current page.
         */
        'pageArg' => 'page',

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

    static $fieldDefaults = array(

        'sortable' => null,

        /**
         * Function used to filter content in this field, or false to
         * render as is.
         */
        'filter' => 'htmlspecialchars'

    );

    var $db;

    var $_dataSource = null;
    var $_pagerData = null;

    var $_fields = array();
    var $_sortFields = null;
    var $_page = null;
    var $_path = null; // path to current page
    var $_qs = null; // current querystring
    var $_pagerOptions = null;

    function __construct($id, $fields = null, $options = null) {

        if (is_array($id) && $fields == null && $options === null) {
            $options = $id;
        } else {
            if ($options === null) $options = array();
            $options['id'] = $id;
        }

        $this->options = array_merge($options, self::$defaults);
        $this->_pagerOptions = array(
            'perPage' => $this->options['pageSize'],
            'urlVar' => $this->options['pageArg'],
            'delta' => $this->options['pagerDelta'],
            'firstPageText' => $this->options['firstPageLinkText'],
            'lastPageText' => $this->options['lastPageLinkText'],
            'firstPagePre' => '',
            'firstPagePost' => '',
            'lastPagePre' => '',
            'lastPagePost' => '',
            'nextImg' => $this->options['nextPageLinkText'],
            'prevImg' => $this->options['prevPageLinkText'],
            'curPageLinkClassName' => 'current'

        );

        $this->db = Octopus_DB::singleton();

        $this->_initialize();

        if ($fields) {
            $this->add($fields);
        }
    }

    function &add($fields) {

        if (!$fields) {
            return $this;
        } else if (!is_array($fields)) {
            $fields = array($fields);
        }

        foreach($fields as $key => $value) {

            $f = is_array($value) ? $value : array();

            if (is_string($key)) {
                $f['name'] = $key;
                $key = null;
            } else if (is_string($value)) {
                $f['name'] = $value;
                $value = null;
            }

            $f = array_merge(self::$fieldDefaults, $f);

            // Allow setting name w/o 'name' key
            if (!isset($f['name']) && isset($f[0])) {
                $f['name'] = $f[0];
                unset($f[0]);
            }

            // Shim over some old field parameters
            if (isset($f['nosort'])) {
                $f['sortable'] = false;
                unset($f['nosort']);
            }

            if (!isset($f['sortable'])) {

                if (isset($f['content'])) {
                    $f['sortable'] = (strpos($f['content'], '%%') !== false);
                } else {
                    // this is a straight-up DB field (probably)
                    $f['sortable'] = true;
                }
            }

            // Normalize some field props
            if (empty($f['label'])) {

                if (!empty($f['desc'])) {
                    $f['label'] = $f['desc'];
                } else if (!empty($f['name'])) {
                    $f['label'] = $this->_deriveLabelFromFieldName($f['name']);
                }

            }

            $f['sorted'] = $this->_getSortStatus($f);

            $this->_fields[$f['name']] = $f;
        }


        return $this;
    }

    function getCurrentPage() {
        return $this->_getPagerData('page_numbers.current');
    }

    function &getData() {
        $pd = $this->_getPagerData();
        $data =& $pd['data'];
        return $data;
    }

    /**
     * @return Array Full pager data for this table.
     */
    function &_getPagerData($key = null) {

        if (!$this->_pagerData) {

            $sql = $this->_dataSource;

            if (class_exists('Octopus_Model_ResultSet') && $sql instanceof Octopus_Model_ResultSet) {
                $sql = $sql->getSql();
            }

            if ($this->_sortFields) {

                $needOrderBy = true;

                foreach($this->_sortFields as $name => $dir) {

                    $isRealField = false;
                    foreach($this->_fields as &$f) {
                        if ($f['name'] == $name) {
                            $isRealField = true;
                            break;
                        }
                    }

                    if ($isRealField) {
                        $sql .= ($needOrderBy ? ' ORDER BY ' : ', ');
                        $sql .= "`$name` $dir";
                        $needOrderBy = false;
                    }
                }
            }

            $this->_pagerData = Pager_Wrapper_DB($this->db, $sql, $this->_pagerOptions);
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


    function isEmpty() {
        return $this->count() == 0;
    }

    /**
     * @return Number The total # of records.
     */
    function count() {

        return $this->_getPagerData('totalItems');

    }

    /**
     * Returns HTML for a div describing the current position in the table.
     */
    function renderLocationDiv() {

        $p = $this->_getPagerData();

        return <<<END
            <div class="pagerLoc">
            Showing {$p['from']} to {$p['to']} of {$p['totalItems']}
            </div>
END;
    }

    function &setDataSource($dataSource) {
        $this->_dataSource = $dataSource;
        $this->_data = null;
        return $this;
    }

    function _deriveLabelFromFieldName($name) {

        // HACK: For action_ cols, don't show a label
        if (preg_match('/^action_/i', $name)) {
            return '';
        }

        $name = preg_replace('/_id$/i', '', $name);
        $name = str_replace('_', ' ', $name);
        $name = ucwords($name);

        return $name;
    }

    function __toString() {

        $html =
            $this->_renderHeader() .
            $this->_renderBody() .
            $this->_renderFooter();

        return $html;

    }

    function _getClassesForField(&$f) {

        $classes = array($f['name']);

        if ($f['sorted'] !== false) {

            foreach($this->_sortFields as $field => $dir) {

                if ($f['name'] == $field) {
                    $classes[] = 'sorted';
                    $classes[] = $f['sorted'];
                }
                break;
            }
        }

        return implode(' ', $classes);
    }

    /**
     * Returns the actual HTML that should be rendered for the given field.
     */
    function _getContentForField(&$f, &$row) {

        $content = '';
        $filterFunc = isset($f['filter']) ? $f['filter'] : 'htmlspecialchars';

        if (!isset($f['content'])) {

            // Just the row value
            if (isset($row[$f['name']]) || $row[$f['name']] === null) {
                return $filterFunc ? $filterFunc($row[$f['name']]) : $row[$f['name']];
            } else {
                return '<span style="color:red;">Missing column: "' . htmlspecialchars($f['name']) . '"</span>';
            }

        } else {

            if (strpos($f['content'], '%%') === false) {
                // no replacing needed
                return $f;
            }

            $content = $f['content'];

            $names = array_keys($row);
            $values = array_values($row);

            foreach($names as $index => $name) {
                $names[$index] = '%%' . $name . '%%';
            }

            if ($filterFunc) {
                foreach($values as $index => $value) {
                    $values[$index] = $filterFunc($value);
                }
            }

            // SUPER DUPER HACK. This is used to add the 'checked' attribute
            // to <inputs> rendered in this column. The format is
            // %%{field_name|value_if_true}%%. value_if_true gets subbed in
            // if the value in $row[field_name] evaluates to true.
            $conditionalPattern = '/%%\{(.+?)\|(.+?)\}%%/';
            while (preg_match($conditionalPattern, $content, $m)) {

                $name = $m[1];
                $valIfTrue = $m[2];

                if (isset($row[$name]) && $row[$name]) {
                    $replacement = $valIfTrue;
                } else {
                    $replacement = '';
                }
                $content = preg_replace($conditionalPattern, $replacement, $content, 1);
            }


            return str_replace($names, $values, $content);
        }

        return $filterFunc ? $filterFunc($content) : $content;
    }

    /**
     * Returns the URL to use to sort on the given field.
     */
    function _getSortingUrl(&$field) {

        $newSorting = array($field['name'] => 'asc');
        $first = true;
        foreach($this->_sortFields as $name => $dir) {

            if (!isset($this->_fields[$name])) {
                continue;
            }

            if ($name == $field['name']) {

                if ($first) {

                    if (isset($this->_sortFields[$name])) {
                        $newSorting[$name] = ($this->_sortFields[$name] == 'asc' ? 'desc' : 'asc');
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
            $newQS[$this->options['sortArg']] = implode(',', $sort);
        }

        if ($this->options['resetPageOnSort']) {
            unset($newQS[$this->options['pageArg']]);
        }

        $newQS = http_build_query($newQS);

        $url = $this->_path;
        if ($newQS) $url .= '?' . $newQS;

        return $url;

    }

    /**
     * @return mixed false if not sorted, 'asc' or 'desc' otherwise
     */
    function _getSortStatus(&$f) {

        if (isset($this->_sortFields[$f['name']])) {
            return $this->_sortFields[$f['name']];
        }

        return false;
    }

    function _getThContentForField(&$f) {

        $html = '';
        $close = '';

        if ($f['sortable']) {
            $html .= '<a href="' . $this->_getSortingUrl($f) . '">';
            $close .= '</a>';
        }

        $html .= htmlspecialchars($f['label']);

        if ($f['sorted'] !== false) {
            $html .= '<span class="sort' . ucwords($f['sorted']) . 'Marker"></span>';
        }


        return $close . $html;

    }


    function _getUrlForPaging() {

        $url = preg_replace('/(\?|&+)' . $this->options['pageArg'] . '=\d*(&+|$)/i', '$1', $_SERVER['REQUEST_URI']);
        $url = rtrim($url, '&');
        $url .= strpos($url, '?') === false ? '?' : '&';

        return $url;
    }

    /**
     * Looks at external factors, like querystring args and session data,
     * and restores the table's state.
     */
    function _initialize() {

        $sessionSortKey = '_sg_table_' . strtolower($this->options['id']) . '_sort';

        // First, get a clean copy of the querystring to work with. We do
        // this to get around interactions w/ apache rewriting
        $this->_path = $_SERVER['REQUEST_URI'];
        $pos = strpos($this->_path, '?');
        if ($pos === false) {
            $this->_qs = array();
        } else {
            parse_str(substr($this->_path, $pos + 1), $this->_qs);
            $this->_path = substr($this->_path, 0, $pos);
        }

        $sortArg = $this->options['sortArg'];
        $pageArg = $this->options['pageArg'];

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
        $this->_sortFields = array();

        if ($sort) {
            foreach(explode(',', $sort) as $field) {
                $asc = true;
                while(substr($field,0,1) == '!') {
                    $asc = !$asc;
                    $field = substr($field,1);
                }
                $this->_sortFields[$field] = ($asc ? 'asc' : 'desc');
            }
        }

        $_SESSION[$sessionSortKey] = ($sort ? $sort : '');
    }

    /**
     * Filters an options array, leaving only html attributes. This
     * lets you put html attributes directly in the $options array
     * passed to the ctor, rather than needing to stick them in an
     * 'attributes' key.
     */
    function &_optionsToAttributes($opts) {
        $attrs = array();

        $banned = array('sortArg', 'pageSize', 'prevPageLinkText', 'nextPageLinkText', 'resetPageOnSort', 'pagerDelta', 'pageArg');

        foreach($opts as $key => $value) {
            if (!in_array($key, $banned)) {
                $attrs[$key] = $value;
            }
        }

        return $attrs;
    }

    function _renderBody() {

        $html = '<tbody>';

        $index = 1;
        $fieldCount = count($this->_fields);

        $rows = $this->getData();
        foreach($rows as &$row) {

            $class = ($index % 2) ? 'even' : 'odd';

            $html .= "<tr class=\"$class\">";

            $col = 1;
            foreach($this->_fields as &$f) {

                $class = $this->_getClassesForField($f);

                if ($col == 1) {
                    $class .= ' first';
                } else if ($col == $fieldCount) {
                    $class .= ' last';
                }

                $content = $this->_getContentForField($f, $row);

                $html .= "<td class=\"$class\">$content</td>";

                $col++;
            }

            $html .= '</tr>';
            $index++;
        }

        $html .= '</tbody>';


        return $html;

    }

    function _renderFooter() {

        $pager = $this->_renderPager();
        if (!$pager) return '</table>';

        $html = '<tfoot><tr><td class="pager" colspan="' . count($this->_fields) . '">';
        $html .= $pager;
        $html .= '</td></tr></tfoot></table>';

        return $html;
    }

    function _renderHeader() {

        $attrs = $this->_optionsToAttributes($this->options);
        $html = '<table';

        foreach($attrs as $attr => $value) {
            $html .= " $attr=\"$value\"";
        }

        $html .= '><thead><tr>';

        foreach($this->_fields as $f) {

            $class = $this->_getClassesForField($f);

            $html .= "<th class=\"$class\">";
            $html .= $this->_getThContentForField($f);
            $html .= '</th>';

        }

        $html .= '</tr></thead>';

        return $html;

    }

    function _renderPager() {

        $p = $this->_getPagerData();

        $html = '<div class="pagerWrapper">';

        if (count($p['data']) < $p['totalItems']) {

            // Pager fucks up our nice urls, so we substitute in good ones.

            $replacement =
                'href="' .
                str_replace('$', '\\$', $this->_getUrlForPaging()) .
                $this->options['pageArg'] . '=$3$4"';

            $links = preg_replace(
                '/href="(.*?)(\?|&amp;)' . $this->options['pageArg'] . '=(\d+)(.*?)"/i',
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