<?php

/**
 * Helper for paginating data. Wraps another Octopus_DataSource and serves
 * as a limited view of it. Also handles rendering pagination links.
 */
class Octopus_Paginate {

    public static $defaults = array(

    	/**
    	 * URL of the current page. If null, REQUEST_URI is used.
    	 */
    	'currentUrl' => null,

        /**
         * QueryString arg used to specify the page.
         */
        'pageArg' => 'page',

        /**
         * # of items to show per page.
         */
        'perPage' => 10,

        /**
         * View to use to render a pager
         */
        'view' => null,

    );

    private $dataSource = null;
    private $limitedDataSource = null;
    private $options;
    private $page = 0;

    public function __construct($dataSource, $options = array()) {

        if (is_array($dataSource)) {
            $dataSource = new Octopus_DataSource_Array($dataSource);
        } else if (is_string($dataSource)) {
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

    public function count() {
    	return $this->dataSource->count();
    }

    /**
     * @return Number The # of pages.
     */
    public function getPageCount() {

        $count = count($this->dataSource);
        return ceil($count / $this->options['perPage']);

    }

    public function getCurrentPage() {
    	throw new Octopus_Exception("Not implemented: " . __METHOD__);
    }

    /**
     * @return Array Page numbers to be displayed.
     */
    public function getPageNumbers() {
        return range(1, $this->getPageCount());
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
    	return count($this);
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

    /**
     * Renders a pagination control.
     */
    public function render($return = false) {

    	$html = $this->makeLinks();

  		if ($return) {
  			return $html;
  		} else {
  			echo $html;
  			return $this;
  		}

    }


}

