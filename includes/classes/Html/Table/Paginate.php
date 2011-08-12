<?php

class Octopus_Html_Table_Paginate {

    private $dataSource;
    private $options;
    private $columns;

    public function __construct($dataSource, $options, $columns) {
        $this->dataSource = $dataSource;
        $this->options = $options;
        $this->columns = $columns;

        if (!isset($this->options['currentPage'])) {
            $this->options['currentPage'] = 1;
        }
    }

    public function getData() {

        if ($this->dataSource instanceof Octopus_Model_ResultSet) {
            return $this->getModelData();
        } else if (is_array($this->dataSource)) {
            return $this->getArrayData();
        } else if (is_string($this->dataSource)) {
            return $this->getSqlData();
        }

    }

    public function getModelData() {

        $data = $this->dataSource;

        $total = $this->dataSource->count();
        $page = $this->options['currentPage'];
        $pages = ceil($total / $this->options['perPage']);
        $from = ($page - 1) * $this->options['perPage'] + 1;
        $to = min(($page) * $this->options['perPage'], $total);
        $page_numbers = ($pages > 1) ? range(1, $pages) : array(1);

        // sort
        foreach ($this->columns as $col) {
            $data = $col->sortResultSet($data);
        }

        // then limit
        $data = $data->limit(($from - 1), $this->options['perPage']);

        return array(
            'currentPage' => $page,
            'totalItems' => $total,
            'from' => $from,
            'to' => $to,
            'totalPages' => $pages,
            'page_numbers' => $page_numbers,
            'data' => $data,
        );

    }

    public function getSqlData() {
        $sql = $this->dataSource;
        $db =& Octopus_DB::singleton();

        $countSql = preg_replace('/select[ ]+\*[ ]+from/i', 'SELECT COUNT(*) FROM', $sql);
        $total = $db->getOne($countSql);

        $page = $this->options['currentPage'];
        $pages = ceil($total / $this->options['perPage']);
        $from = ($page - 1) * $this->options['perPage'] + 1;
        $to = min(($page) * $this->options['perPage'], $total);
        $page_numbers = ($pages > 1) ? range(1, $pages) : array(1);

        // sort
        $needOrderBy = true;
        foreach ($this->columns as $col) {

            if (!$col->isSorted($this->dataSource)) {
                continue;
            }

            $dir = $col->getSorting();

            $sql .= ($needOrderBy ? ' ORDER BY ' : ', ');
            $sql .= "`{$col->id}` $dir";
            $needOrderBy = false;
        }

        // then limit
        $sql .= sprintf(' LIMIT %d, %d', $from - 1, $this->options['perPage']);
        $query = $db->query($sql, true);
        $data = array();
        while ($data[] = $query->fetchRow()) {}

        return array(
            'currentPage' => $page,
            'totalItems' => $total,
            'from' => $from,
            'to' => $to,
            'totalPages' => $pages,
            'page_numbers' => $page_numbers,
            'data' => $data,
        );
    }

    public function getArrayData() {
        throw new Octopus_Exception('Array data source not implemented yet!');
    }

    /**
     * @return String A nicely-formatted URL for use in the pager.
     */
    public static function getUrlForPaging($options) {

        $url = preg_replace('/(\?|&+)' . $options['pageArg'] . '=\d*(&+|$)/i', '$1', $_SERVER['REQUEST_URI']);
        $url = rtrim($url, '&');
        $url .= strpos($url, '?') === false ? '?' : '&';

        return $url;
    }

    public static function makeLinks($pagerData, $options) {

        $html = '<div class="pagerLinks">';

        $elements = array();

        if (count($pagerData['page_numbers']) > 1) {

            if ($pagerData['currentPage'] > 2) {
                $link = new Octopus_Html_Element('a');
                $link->href = self::getUrlForPaging($options) . $options['pageArg'] . '=1';
                $link->html($options['firstPageLinkText']);
                $elements[] = $link;
            }

            if ($pagerData['currentPage'] > 1) {
                $link = new Octopus_Html_Element('a');
                $link->href = self::getUrlForPaging($options) . $options['pageArg'] . '=' . ($pagerData['currentPage'] - 1);
                $link->html($options['prevPageLinkText']);
                $elements[] = $link;
            }

            foreach ($pagerData['page_numbers'] as $page) {

                if ($page == $pagerData['currentPage']) {
                    $link = new Octopus_Html_Element('span');
                } else {
                    $link = new Octopus_Html_Element('a');
                    $link->href = self::getUrlForPaging($options) . $ptions['pageArg'] . '=' . $page;
                }

                $link->text($page);
                $link->title = 'Page ' . $page;

                $elements[] = $link;
            }

            if ($pagerData['currentPage'] < $pagerData['totalPages']) {
                $link  = new Octopus_Html_Element('a');
                $link->href = self::getUrlForPaging($options) . $options['pageArg'] . '=' . ($pagerData['currentPage'] + 1);
                $link->html($options['nextPageLinkText']);
                $elements[] = $link;
            }

            if ($pagerData['currentPage'] < $pagerData['totalPages'] && $pagerData['currentPage'] + 1 < $pagerData['totalPages']) {
                $link  = new Octopus_Html_Element('a');
                $link->href = self::getUrlForPaging($options) . $options['pageArg'] . '=' . $pagerData['totalPages'];
                $link->html($options['lastPageLinkText']);
                $elements[] = $link;
            }

            $html .= implode('&nbsp;', array_map('trim', $elements));
        }


        $html .= '</div>';

        return $html;
    }

}

