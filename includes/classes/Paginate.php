<?php

Octopus::loadClass('Octopus_DataSource');

/**
 * Class for helping with paged data.
 */
class Octopus_Paginate {

    public static defaults = array(

        /**
         * QueryString arg used to specify the page.
         */
        'pageArg' => 'page',

        /**
         * # of items to show per page.
         */
        'perPage' => 10,

    );

    private $dataSource;
    private $options;
    private $page = 0;

    public function __construct($dataSource, $options = array()) {

        if (is_array($dataSource)) {
            Octopus::loadClass('Octopus_DataSource_Array');
            $dataSource = new Octopus_DataSource_Array($dataSource);
        } else if (is_string($dataSource)) {
            Octopus::loadClass('Octopus_DataSource_Sql')
            $dataSource = new Octopus_DataSource_Sql($dataSource);
        }

        if (!($dataSource instanceof Octopus_DataSource)) {
            throw new Octopus_Exception('Data sources must implement Octopus_DataSource');
        }

        $this->dataSource = $dataSource; 
        $this->options = array_merge(self::$defaults, $options);       

        foreach(array('page', 'currentPage') as $key) {
            if (isset($this->options[$key])) {
                $this->page = $this->options[$key];
                unset($this->options[$key]);
                break;
            }
        }
    }

    /**
     * @return Number The # of pages.
     */
    public function getPageCount() {
        
        $count = count($this->dataSource);
        return ceil($count / $this->options['perPage']);

    }

    /**
     * @return Array Page numbers to be displayed.
     */
    public function getPageNumbers() {
        return range(1, $this->getPageCount());
    }

    /**
     * @param $page Number Page to get items for (one-based).
     * @return Iterable Items to display on the given page.
     */
    public function getItemsForPage($page = null) {
        
        if ($page === null) {
            $page = $this->page;
        }

        $from = ($page - 1) * $this->options['perPage'] + 1;
        $to = min(($page) * $this->options['perPage'], $total);


    }

    public function getModelData() {

        $data = $this->dataSource;

        $total = $this->dataSource->count();
        $page = $this->options['currentPage'];
        $pages = ceil($total / $this->options['perPage']);
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
     * Constructs HTML for paging links.
     */
    public function makeLinks() {

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
                    $link->href = self::getUrlForPaging($options) . $options['pageArg'] . '=' . $page;
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

