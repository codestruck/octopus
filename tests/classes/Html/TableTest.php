<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class TableTest extends Octopus_App_TestCase {

    var $rawData = array(
        /* Test Data {{{ */
        array(    'id',     'name',         'age'           ),
        array(    1,        'Hamish Grant',         '49'            ),
        array(    2,        'Charles Bowman',           '32'            ),
        array(    3,        'Palmer Douglas',           '52'            ),
        array(    4,        'James Conrad',         '61'            ),
        array(    5,        'Leroy Short',          '41'            ),
        array(    6,        'Ahmed Rasmussen',          '67'            ),
        array(    7,        'Russell Foster',           '36'            ),
        array(    8,        'Dustin Snider',            '43'            ),
        array(    9,        'Boris Vega',           '47'            ),
        array(    10,       'Emery Lewis',          '60'            ),
        array(    11,       'Thomas Wheeler',           '31'            ),
        array(    12,       'Hop Castro',           '35'            ),
        array(    13,       'Luke Valdez',          '47'            ),
        array(    14,       'Demetrius Hester',         '55'            ),
        array(    15,       'Berk Barrera',         '48'            ),
        array(    16,       'Jackson Benjamin',         '37'            ),
        array(    17,       'Hop Terrell',          '51'            ),
        array(    18,       'Oscar Cotton',         '44'            ),
        array(    19,       'Cedric Fulton',            '41'            ),
        array(    20,       'Nicholas Moss',            '65'            ),
        array(    21,       'John Lynch',           '63'            ),
        array(    22,       'Nathan Alston',            '25'            ),
        array(    23,       'Wyatt Nash',           '66'            ),
        array(    24,       'Ignatius Walls',           '40'            ),
        array(    25,       'Cedric Barrera',           '42'            ),
        array(    26,       'Oleg Little',          '45'            ),
        array(    27,       'Alfonso Acevedo',          '40'            ),
        array(    28,       'Louis Ramsey',         '49'            ),
        array(    29,       'Benedict Wade',            '64'            ),
        array(    30,       'Ira Morse',            '48'            ),
        array(    31,       'Gil Clements',         '28'            ),
        array(    32,       'Denton Suarez',            '28'            ),
        array(    33,       'Edward Skinner',           '58'            ),
        array(    34,       'Davis Garner',         '55'            ),
        array(    35,       'Louis Kramer',         '57'            ),
        array(    36,       'Amir Donaldson',           '36'            ),
        array(    37,       'Jeremy Faulkner',          '36'            ),
        array(    38,       'Tanek Vargas',         '25'            ),
        array(    39,       'Hilel Anthony',            '67'            ),
        array(    40,       'Ezekiel King',         '51'            ),
        array(    41,       'Tarik Todd',           '59'            ),
        array(    42,       'Yardley Cline',            '54'            ),
        array(    43,       'Baker King',           '47'            ),
        array(    44,       'Sawyer Riggs',         '62'            ),
        array(    45,       'Julian Henry',         '40'            ),
        array(    46,       'Dillon Hull',          '51'            ),
        array(    47,       'Keaton Williamson',            '63'            ),
        array(    48,       'Sebastian Sosa',           '60'            ),
        array(    49,       'Talon Frye',           '61'            ),
        array(    50,       'Samuel Nelson',            '38'            ),
        array(    51,       'Herman Lynn',          '34'            ),
        array(    52,       'Craig Leonard',            '42'            ),
        array(    53,       'Travis Mejia',         '64'            ),
        array(    54,       'Harlan Hunter',            '54'            ),
        array(    55,       'Denton Alexander',         '28'            ),
        array(    56,       'Lucian Forbes',            '48'            ),
        array(    57,       'Dalton Stark',         '47'            ),
        array(    58,       'Brody Petersen',           '41'            ),
        array(    59,       'Wade Mckinney',            '55'            ),
        array(    60,       'Porter Horton',            '40'            ),
        array(    61,       'Benedict Thomas',          '65'            ),
        array(    62,       'Elton Campbell',           '43'            ),
        array(    63,       'Oliver Bates',         '26'            ),
        array(    64,       'Luke Jordan',          '47'            ),
        array(    65,       'Elton Rice',           '47'            ),
        array(    66,       'Griffith Hendricks',           '61'            ),
        array(    67,       'Wing Murray',          '55'            ),
        array(    68,       'Ishmael Ross',         '42'            ),
        array(    69,       'Stone Puckett',            '24'            ),
        array(    70,       'Keegan Lancaster',         '44'            ),
        array(    71,       'Vaughan Melendez',         '40'            ),
        array(    72,       'Sean Walters',         '46'            ),
        array(    73,       'Dominic Norton',           '48'            ),
        array(    74,       'Felix Gallegos',           '25'            ),
        array(    75,       'Steven Moses',         '59'            ),
        array(    76,       'Geoffrey Bernard',         '33'            ),
        array(    77,       'Cody Baldwin',         '62'            ),
        array(    78,       'Trevor Lara',          '53'            ),
        array(    79,       'Elvis Harrison',           '41'            ),
        array(    80,       'Ezekiel Wiggins',          '65'            ),
        array(    81,       'Reese Tran',           '47'            ),
        array(    82,       'Carl Raymond',         '45'            ),
        array(    83,       'Marvin Battle',            '30'            ),
        array(    84,       'Colby Hayes',          '45'            ),
        array(    85,       'Dalton English',           '29'            ),
        array(    86,       'Amal Newton',          '32'            ),
        array(    87,       'Cyrus Suarez',         '33'            ),
        array(    88,       'Bert Roth',            '57'            ),
        array(    89,       'Perry Butler',         '29'            ),
        array(    90,       'Cairo Jenkins',            '28'            ),
        array(    91,       'August Fields',            '24'            ),
        array(    92,       'Laith Hull',           '58'            ),
        array(    93,       'Asher Cain',           '60'            ),
        array(    94,       'Coby Goodman',         '33'            ),
        array(    95,       'Merritt Mclaughlin',           '45'            ),
        array(    96,       'Rashad Moses',         '30'            ),
        array(    97,       'Zachary Terry',            '25'            ),
        array(    98,       'Christian Acosta',         '60'            ),
        array(    99,       'Victor Rice',          '66'            ),
        array(    100,      'Ashton Vaughn',            '42'            ),
        /* }}} */
    );
    var $arrayData;

    function __construct() {
        parent::__construct();

        $headers = array_shift($this->rawData);
        $this->arrayData = array();
        while($raw = array_shift($this->rawData)) {
            $row = array();
            for($i = 0; $i < count($headers); $i++) {
                $row[$headers[$i]] = $raw[$i];
            }
            $this->arrayData[] = $row;
        }
    }

    function setUp() {
        parent::setUp();

        $_SESSION = array();
        $_GET = array();
        $_POST = array();
        unset($_SERVER['REQUEST_URI']);
    }

    function testGetFilterValues() {

        $table = new Octopus_Html_Table('getFilterValues');

        $this->assertEquals(array(), $table->getFilterValues());
        $table->filter('foo', 'bar');
        $this->assertEquals(array(), $table->getFilterValues(), 'values for non-existant filters not returned');

        $table->addFilter('text', 'foo');
        $this->assertEquals(array('foo' => 'bar'), $table->getFilterValues(), 'value available after filter added');

        $table->filter('baz', 'bat');
        $this->assertEquals(array('foo' => 'bar'), $table->getFilterValues(), 'value for non-existant filter not returned even when there are filters');

        $table->unfilter();
        $this->assertEquals(array(), $table->getFilterValues());

    }

    function testActionLinksGetGoodClassNames() {

        $table = new Octopus_Html_Table('actionTest');
        $table->addColumns(array(
            'actions' => array(
                'view person' => array(
                    'url' => '/view/{$id}'
                )
            )
        ));

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");

        $table->setDataSource(HtmlTablePerson::all());

        $html = $table->render(true);

        Octopus::loadExternal('simplehtmldom');
        $dom = str_get_dom($html);

        $actions = $dom->find('a.action');
        $this->assertEquals(3, count($actions));

        foreach($actions as $a) {
            $this->assertEquals('action view-person', $a->class);
        }

    }

    function testAttributesOnActionLinks() {

        $table = new Octopus_Html_Table('actionTest', array('pager' => false));
        $table->addColumns(array(
            'actions' => array(
                'view person' => array(
                    'url' => '/view/{$id}',
                    'rel'  => 'nofollow',
                    'target' => '_blank',
                    'class' => 'custom-class'
                )
            )
        ));

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");

        $table->setDataSource(HtmlTablePerson::all());

        $html = $table->render(true);

        Octopus::loadExternal('simplehtmldom');
        $dom = str_get_dom($html);

        $actions = $dom->find('a');
        $this->assertEquals(3, count($actions));

        foreach($actions as $a) {
            $this->assertEquals('nofollow', $a->rel);
            $this->assertEquals('_blank', $a->target);
            $this->assertEquals('custom-class', $a->class);
        }

    }


    function testModifierFunctionIsOnModel() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");

        $table = new Octopus_Html_Table('id', array('pager' => false));
        $table->setDataSource(HtmlTablePerson::all());
        $table->addColumn('name', 'Name', 'method_uppercase');

        $this->assertHtmlEquals(
            <<<END
<table id="id" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name firstCell sortable">
                <a href="?sort=name"><span class="sortMarker">Name</span></a>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name firstCell">JOE BLOW</td>
        </tr>
        <tr class="even">
            <td class="name firstCell">JANE BLOW</td>
        </tr>
        <tr class="odd">
            <td class="name firstCell">JOHN SMITH</td>
        </tr>
    </tbody>
</table>
END
            , $table->render(true)
        );

    }

    function testFunctionErrorNotEscaped() {

        $table = new Octopus_Html_Table('id', array('pager' => false));
        $table->setDataSource(HtmlTablePerson::all());
        $table->addColumn('name', 'Name', 'not_exist');

        $this->assertHtmlEquals(
            <<<END
<table id="id" border="0" cellpadding="0" cellspacing="0"><thead class="columns"><tr><th class="name firstCell sortable"><a href="?sort=name"><span class="sortMarker">Name</span></a></th></tr></thead><tbody><tr class="odd"><td class="name firstCell"><span style="color:red;">Function not found: not_exist</span></td></tr><tr class="even"><td class="name firstCell"><span style="color:red;">Function not found: not_exist</span></td></tr><tr class="odd"><td class="name firstCell"><span style="color:red;">Function not found: not_exist</span></td></tr></tbody></table>
END
            , $table->render(true));

    }

    function testResultSetAsSelectFilterDataSource() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");

        $table = new Octopus_Html_Table('select');
        $filter = $table->addFilter('select', 'person', HtmlTablePerson::all());

        $element = $filter->getElement($table);

        $this->assertHtmlEquals(
            <<<END
            <select id="personInput" class="person select" name="person">
                <option value="" selected>Choose One</option>
                <option value="1">Joe Blow</option>
                <option value="2">Jane Blow</option>
                <option value="3">John Smith</option>
            </select>
END
            ,
            $element->render(true)
        );

    }

    function testPostActions() {

        return $this->markTestSkipped("Need to merge pwaiter octopus branch");

        $table = new Octopus_Html_Table('postActions');
        $table->addColumn('name');

        $actions = $table->addColumn('actions');
        $actions->addAction('delete', array('post' => true));
        $actions->addAction('other', array('method' => 'post'));
        $actions->addToggle('toggle1', array('foo','bar'), array('foo','bar'), array('post' => true));
        $actions->addToggle('toggle2', array('foo','bar'), array('foo','bar'), array('method' => 'post'));


        $table->setDataSource(array(
            array('name' => 'Joe')
        ));

        $ar = $table->toArray();
        array_shift($ar);

        $row = array_shift($ar);
        $this->assertHtmlEquals(
            <<<END
            <a href="" class="action delete method-post methodPost" title="Delete">Delete</a>
            <a href="" class="action other method-post methodPost" title="Other">Other</a>
            <a href="foo" class="toggle toggle1 method-post methodPost toggleInactive" data-alt-content="bar" data-alt-href="bar">foo</a>
            <a href="foo" class="toggle toggle2 method-post methodPost toggleInactive" data-alt-content="bar" data-alt-href="bar">foo</a>
END
            ,
            $row[1]
        );

    }

    function testPageWrittenToSession() {

        $table = new Octopus_Html_Table('selectFilter', array('redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->setDataSource($this->arrayData);
        $table->setPageSize(10);
        $this->assertEquals(10, $table->getPageCount(), 'wrong # of pages');

        $table->setPage(5);
        $this->assertEquals(5, $table->getPage(), "page wasn't set properly");

        $key = 'octopus-table--selectFilter-page';

        $this->assertTrue(!empty($_SESSION[$key]), "session page key ($key) empty");
        $this->assertEquals(5, $_SESSION[$key]);
    }

    function testSortingWrittenToSession() {

        $table = new Octopus_Html_Table('selectFilter', array('pager' => false, 'redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->setDataSource(HtmlTablePerson::all());

        $table->sort('!age');

        $key = 'octopus-table--selectFilter-sorting';
        $this->assertTrue(!empty($_SESSION[$key]), "session key ($key) empty");
        $this->assertEquals(array('age' => false), $_SESSION[$key]);
    }

    function testFilterWrittenToSession() {

        $table = new Octopus_Html_Table('selectFilter', array('pager' => false, 'redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->addFilter('select', 'age', array(50 => 'Fifty', 99 => 'Ninety-nine'));
        $table->setDataSource(HtmlTablePerson::all());

        $table->filter('age', 99);

        $key = 'octopus-table--selectFilter-filters';

        $this->assertTrue(!empty($_SESSION[$key]), "session filter key ($key) is not empty");
        $this->assertEquals(
            array('age' => 99),
            $_SESSION[$key]
        );
    }

    function testInitFilterFromSession() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");

        $_SESSION['octopus-table--filterFromSession-filters'] = array(
            'age' => '99'
        );

        $table = new Octopus_Html_Table('filterFromSession', array('pager' => false, 'redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');

        $this->assertEquals(array(), $table->getFilterValues(), 'no filter values with no filters added');

        $table->addFilter('select', 'age', array(50 => 'Fifty', 99 => 'Ninety-nine'));
        $this->assertEquals(array('age' => '99'), $table->getFilterValues());

        $table->setDataSource(HtmlTablePerson::all());

        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('John Smith', 99),
            ),
            $table->toArray()
        );

    }

    function testInitFilterFromQueryString() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");

        $_GET['age'] = '99';

        $table = new Octopus_Html_Table('selectFilter', array('pager' => false));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->addFilter('select', 'age', array(50 => 'Fifty', 99 => 'Ninety-nine'));
        $table->setDataSource(HtmlTablePerson::all());

        //dump_r($table->getFilterValues());

        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('John Smith', 99),
            ),
            $table->toArray()
        );

    }

    function testInitSortFromQueryString() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");

        $_GET['sort'] = '!age';

        $table = new Octopus_Html_Table('selectFilter', array('pager' => false));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->setDataSource(HtmlTablePerson::all());

        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('John Smith', 99),
                array('Joe Blow', 50),
                array('Jane Blow', 50)
            ),
            $table->toArray()
        );


        $table->reset();

        $_GET['sort'] = '!age,name';
        $table->setDataSource(HtmlTablePerson::all());


        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('John Smith', 99),
                array('Jane Blow', 50),
                array('Joe Blow', 50),
            ),
            $table->toArray()
        );

    }

    function testInitPageFromQueryString() {

        $table = new Octopus_Html_Table('paging', array('pageSize' => 2));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->setDataSource(HtmlTablePerson::all());

        $_GET['page'] = '2';

        $table->restore();

        $this->assertEquals(2, $table->getPage());

    }

    function testInitPageFromQueryStringAfterSort() {

        $table = new Octopus_Html_Table('paging', array('pageSize' => 2, 'redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->setDataSource(HtmlTablePerson::all());

        $table->setDefaultSorting('name');

        $_GET['page'] = '2';

        $table->restore();

        $this->assertEquals(2, $table->getPage());

    }

    function testEmptyTable() {

        $table = new Octopus_Html_Table('empty', array('pager' => false));
        $table->addColumn('name');
        $table->addColumn('age');

        $expected = <<<END
        <table id="empty" class="empty" border="0" cellpadding="0" cellspacing="0">
            <thead class="columns">
                <tr>
                    <th class="name firstCell">Name</th>
                    <th class="age lastCell">Age</th>
                </tr>
            </thead>
            <tbody class="emptyNotice">
                <tr>
                    <td class="emptyNotice" colspan="2">
                        <p>Sorry, there is nothing to display. Try another search or filter.</p>
                    </td>
                </tr>
            </tbody>
        </table>
END;

        $this->assertHtmlEquals($expected, $table);
    }

    function testSetFilters() {

        $table = new Octopus_Html_Table('setFilters', array('redirectCallback' => false));
        $table->addColumn('name');
        $table->addFilter('text', 'foo');

        $this->assertEquals(array(), $table->getFilterValues());

        $table->filter('foo', 'bar');
        $this->assertEquals(array('foo' => 'bar'), $table->getFilterValues());

        $table->filter(false);
        $this->assertEquals(array(), $table->getFilterValues());

        $table->filter(array('foo' => 'bar', 'invalid' => 'something'));
        $this->assertEquals(array('foo' => 'bar'), $table->getFilterValues());

        $table->unfilter();
        $this->assertEquals(array(), $table->getFilterValues());
    }

    function testRenderFilters() {

        $table = new Octopus_Html_Table('renderFilters', array('pager' => false, 'redirectCallback' => false));
        $table->addColumn('name');
        $table->addFilter('text', 'foo');
        $table->filter('foo', 'bar');

        $this->assertEquals(array('foo' => 'bar'), $table->getFilterValues());

        $expected = <<<END
<table id="renderFilters" class="empty" border="0" cellpadding="0" cellspacing="0">
    <thead class="filters">
        <tr>
            <td class="filters" colspan="1">
                <form method="get" action="" class="filterForm">
                    <div class="filter foo text firstFilter lastFilter">
                        <label class="filterLabel" for="fooInput">Foo:</label>
                        <input type="text" id="fooInput" class="foo text" name="foo" value="bar" />
                    </div>
                    <a href="?clearfilters=1" class="clearFilters">Clear Filters</a>
                </form>
            </td>
        </tr>
    </thead>
    <thead class="columns">
        <tr>
            <th class="name firstCell">Name</th>
        </tr>
    </thead>
    <tbody class="emptyNotice">
        <tr>
            <td class="emptyNotice" colspan="1">
                <p>Sorry, there is nothing to display. Try another search or filter.</p>
            </td>
        </tr>
    </tbody>
</table>
END;
        $this->assertHtmlEquals($expected, $table);

    }

    function testTextFilter() {

        $table = new Octopus_Html_Table('textFilter', array('redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');

        $el = $table->addFilter('text', 'name');
        $this->assertTrue($el instanceof Octopus_Html_Table_Filter);

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 45),
                    ('John Smith', 99)
        ");

        $resultSet = HtmlTablePerson::all();
        $this->assertEquals(3, $resultSet->count(), "wrong # of records in table");

        $table->setDataSource($resultSet);
        $table->filter('name', 'blow');

        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('Joe Blow', 50),
                array('Jane Blow', 45)
            ),
            $table->toArray()
        );

        $table->unfilter();

        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('Joe Blow', 50),
                array('Jane Blow', 45),
                array('John Smith', 99)
            ),
            $table->toArray(),
            'unfilter should reset filter state'
        );

    }

    function testFilterResultSetHook() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");


        $table = new Octopus_Html_Table('selectFilter', array('pager' => false, 'redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');

        $table->addFilter(
            'select',
            'sex',
            array(
                'options' => array('M' => 'Male', 'F' => 'Female'),
                'function' => array(__CLASS__, 'filter_testFilterResultSetHook')
            )
        );

        $table->setDataSource(HtmlTablePerson::all());

        $table->filter(array('sex' => 'M'));

        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('Joe Blow', 50),
                array('John Smith', 99)
            ),
            $table->toArray()
        );

    }

    public static function filter_testFilterResultSetHook($filter, $resultSet) {

        if ($filter->val() == 'M') {

            return $resultSet->where(array(
                array('name LIKE' => 'Joe%'),
                'OR',
                array('name LIKE' => 'John%'),
            ));

        } else if ($filter->val() == 'F') {

            return $resultSet->where(array(
                array('name LIKE' => 'Jane%'),
            ));
        } else {
            return $resultSet;
        }

    }

    function testSelectFilter() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");


        $table = new Octopus_Html_Table('selectFilter', array('pager' => false, 'redirectCallback' => false));
        $table->addColumn('name');
        $table->addColumn('age');
        $table->addFilter('select', 'age', array(50 => 'Fifty', 99 => 'Ninety-nine'));
        $table->setDataSource(HtmlTablePerson::all());

        $table->filter(array('age' => 99));

        $this->assertEquals(
            array(
                array('Name', 'Age'),
                array('John Smith', 99)
            ),
            $table->toArray()
        );

    }

    function testCompactAddColumnsFormat() {

        $table = new Octopus_Html_Table('compactAddColumns');
        $table->addColumns(array(
            'name',
            'some_funky_col' => 'A nice label',
            'foo' => array('desc' => 'bar'),
            'test_toggle' => array('type' => 'toggle', 'url' => array('test_toggle_inactive_url', 'test_toggle_active_url')),
            'toggles' => array(
                'toggle1',
                'toggle2' => array('desc' => array('NOT ACTIVE', 'ACTIVE'))
            ),
            'test_action' => array('type' => 'action'),
            'actions' => array(
                'action1',
                'action2' => array('url' => 'action2_url')
            )
        ));

        $name = $table->getColumn('name');
        $this->assertTrue(!!$name, 'name column not found');

        $some_funky_col = $table->getColumn('some_funky_col');
        $this->assertTrue(!!$some_funky_col, 'some_funky_col column not found');
        $this->assertEquals('A nice label', $some_funky_col->title());

        $foo = $table->getColumn('foo');
        $this->assertTrue(!!$foo, 'foo column not found');
        $this->assertEquals('bar', $foo->title());


        $test_toggle = $table->getColumn('test_toggle');
        $this->assertTrue(!!$test_toggle, 'test_toggle column not found');
        $t = $test_toggle->getAction('test_toggle');
        $this->assertTrue(!!$t, 'toggle action not found on test_toggle');
        $this->assertEquals('test_toggle_inactive_url', $t->getInactiveUrl());
        $this->assertEquals('test_toggle_active_url', $t->getActiveUrl());

        $toggles = $table->getColumn('toggles');
        $this->assertTrue(!!$toggles, 'toggles column not found');
        $this->assertEquals(2, count($toggles->getActions()), 'wrong # of actions');

        $toggle1 = $toggles->getAction('toggle1');
        $this->assertTrue(!!$toggle1, 'toggle1 not found');

        $toggle2 = $toggles->getAction('toggle2');
        $this->assertTrue(!!$toggle2, 'toggle2 not found');
        $this->assertEquals('NOT ACTIVE', $toggle2->getInactiveContent());
        $this->assertEquals('ACTIVE', $toggle2->getActiveContent());


        $test_action = $table->getColumn('test_action');
        $this->assertTrue(!!$test_action, 'test_action column not found');
        $a = $test_action->getAction('test_action');
        $this->assertTrue(!!$a, 'test_action not found');

        $actions = $table->getColumn('actions');
        $this->assertTrue(!!$actions, 'actions column not found');

        $this->assertEquals(2, count($actions->getActions()));

        $action1 = $actions->getAction('action1');
        $this->assertTrue(!!$action1, 'action1 not found');

        $action2 = $actions->getAction('action2');
        $this->assertTrue(!!$action2, 'action2 not found');
        $this->assertEquals('action2_url', $action2->url());

    }

    function testEscapeHtml() {

        $table = new Octopus_Html_Table('escape');
        $table->addColumn('name');
        $table->setDataSource(array(
            array('name' => '<b>I AM TRYING TO CHEAT!!!</b>')
        ));

        $this->assertEquals(
            array(
                array('Name'),
                array('&lt;b&gt;I AM TRYING TO CHEAT!!!&lt;/b&gt;')
            ),
            $table->toArray()
        );

    }

    function testMethodOnObject() {

        $table = new Octopus_Html_Table('methodOnObject');
        $table->addColumn('name');
        $table->addColumn('obj', 'Magic Number', 'squareOfNum');
        $table->setDataSource(array(
            array('name' => 'Joe Blow', 'obj' => new MethodOnObjectTestObject(5))
        ));

        $this->assertEquals(
            array(
                array('Name', 'Magic Number'),
                array('Joe Blow', 25)
            ),
            $table->toArray()
        );

    }

    function threeArgTestFunction($value, $row, $column) {
        $this->assertEquals(25, $value, "value is wrong");
        $this->assertEquals(array('name' => 'Joe Blow', 'num' => 25), $row);
        $this->assertTrue(!!$column, 'column not supplied');
        $this->assertEquals('num', $column->id);
        return $value * 2;
    }

    function testFunctionWith3Args() {

        $table = new Octopus_Html_Table('threeArgs');
        $table->addColumn('name');
        $table->addColumn('num', 'Magic Number', array($this, 'threeArgTestFunction'));
        $table->setDataSource(array(
            array('name' => 'Joe Blow', 'num' => 25)
        ));

        $this->assertEquals(
            array(
                array('Name', 'Magic Number'),
                array('Joe Blow', 50)
            ),
            $table->toArray()
        );
    }

    function test2ArgBuiltInFunctions() {

        $val = '  3  ';

        $funcs = array(
            'htmlspecialchars',
            'trim', 'ltrim', 'rtrim',
            'nl2br',
            // TODO More?
        );

        foreach($funcs as $f) {

            $table = new Octopus_Html_Table('builtIn2Arg', array('useSession' => false));
            $table->addColumn('name');
            $table->addColumn('foo', 'Foo', $f);
            $table->setDataSource(array(
                array('name' => 'Joe Blow', 'foo' => $val)
            ));

            $this->assertEquals(
                array(
                    array('Name', 'Foo'),
                    array('Joe Blow', $f($val))
                ),
                $table->toArray(),
                "failed on $f"
            );

        }


    }

    function testGlobalFunction() {

        $table = new Octopus_Html_Table('func', array('pager' => false));
        $table->addColumn('name');
        $table->addColumn('num', 'Magic Number', 'globalTestFunctionForTable');
        $table->setDataSource(array(
            array( 'name' => 'Joe Blow', 'num' => 3 ),
            array( 'name' => 'Jane Blow', 'num' => 5 )
        ));

        $this->assertEquals(
            array(
                array('Name', 'Magic Number'),
                array('Joe Blow', 9),
                array('Jane Blow', 25)
            ),
            $table->toArray()
        );

    }

    function testSimpleTable() {

        $expected = <<<END
<table id="simpleTable" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name firstCell">Name</th>
            <th class="age lastCell">Age</th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name firstCell">Joe Blow</td>
            <td class="age lastCell">50</td>
        </tr>
        <tr class="even">
            <td class="name firstCell">Joe Smith</td>
            <td class="age lastCell">99</td>
        </tr>
    </tbody>
</table>
END;

        $table = new Octopus_Html_Table('simpleTable', array('pager' => false));

        $table->addColumn('name', array('sortable' => false));
        $table->addColumn('age', array('sortable' => false));
        $table->setDataSource(
            array(
                array('name' => 'Joe Blow', 'age' => 50),
                array('name' => 'Joe Smith', 'age' => 99)
            )
        );

        $this->assertHtmlEquals(
            $expected,
            $table->render(true)
        );

    }

    function testComplexTable() {

        $expected = <<<END
<table id="complexTable" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name"><span class="sortMarker">Name</span></a></th>
            <th class="age sortable"><a href="?sort=age"><span class="sortMarker">Age</span></a></th>
            <th class="actions lastCell">Actions</th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name firstCell">Joe Blow</td>
            <td class="age">50</td>
            <td class="actions lastCell">
                <a href="/toggle/deactivate/1" class="toggle active toggleActive" data-alt-content="Inactive" data-alt-href="/toggle/activate/1">Active</a>
                <a href="/edit/1" class="action edit" title="Edit">Edit</a>
                <a href="/delete/1" class="action delete" title="Delete">Delete</a>
            </td>
        </tr>
        <tr class="even">
            <td class="name firstCell">Joe Smith</td>
            <td class="age">99</td>
            <td class="actions lastCell">
                <a href="/toggle/activate/2" class="toggle active toggleInactive" data-alt-content="Active" data-alt-href="/toggle/deactivate/2">Inactive</a>
                <a href="/edit/2" class="action edit" title="Edit">Edit</a>
                <a href="/delete/2" class="action delete" title="Delete">Delete</a>
            </td>
        </tr>
    </tbody>
</table>
END;

        $table = new Octopus_Html_Table('complexTable', array('pager' => false ));

        $table->addColumn('name', array('sortable' => true));
        $table->addColumn('age', array('sortable' => true));

        $col = $table->addColumn('actions', array('sortable' => false));
        $col->addToggle('active', array('Inactive', 'Active'), array('/toggle/activate/{$person_id}', '/toggle/deactivate/{$person_id}'));
        $col->addAction('edit', '/edit/{$person_id}');
        $col->addAction('delete', '/delete/{$person_id}');

        $table->setDataSource(
            array(
                array('person_id' => 1, 'name' => 'Joe Blow', 'age' => 50, 'active' => true),
                array('person_id' => 2, 'name' => 'Joe Smith', 'age' => 99, 'active' => false)
            )
        );

        $this->assertHtmlEquals(
            $expected,
            $table->render(true)
        );

    }

    function testColumnSorting() {

        $col = new Octopus_Html_Table_Column('test', array('sortable' => true), null);

        $this->assertFalse($col->isSorted(), "should not be sorted by default");
        $this->assertFalse($col->isSortedAsc(), "should not be sorted asc by default");
        $this->assertFalse($col->isSortedDesc(), "should not be sorted desc by default");

        $ascValues = array('asc', 1, true);
        $descValues = array('desc', 0);

        foreach($ascValues as $v) {
            $col->sort($v);
            $this->assertTrue($col->isSortedAsc(), "failed to sort asc w/ $v");
            $this->assertFalse($col->isSortedDesc());
            $this->assertTrue($col->isSorted());
            $col->sort();
            $this->assertTrue($col->isSorted(), "should be sorted after flip");
            $this->assertFalse($col->isSortedAsc(), "failed to flip sorting w/ $v");
            $this->assertTrue($col->isSortedDesc(), "failed to flip sorting w/ $v");

            $col->unsort();
            $this->assertFalse($col->isSorted(), "should not be sorted ");
            $this->assertFalse($col->isSortedAsc(), "should not be sorted asc");
            $this->assertFalse($col->isSortedDesc(), "should not be sorted desc ");
        }


        foreach($descValues as $v) {
            $col->sort($v);
            $this->assertFalse($col->isSortedAsc(), "failed to sort asc w/ $v");
            $this->assertTrue($col->isSortedDesc());
            $this->assertTrue($col->isSorted());

            $col->sort();
            $this->assertTrue($col->isSorted(), "should be sorted after flip");
            $this->assertTrue($col->isSortedAsc(), "failed to flip sorting w/ $v");
            $this->assertFalse($col->isSortedDesc(), "failed to flip sorting w/ $v");

            $col->unsort();
            $this->assertFalse($col->isSorted(), "should not be sorted ");
            $this->assertFalse($col->isSortedAsc(), "should not be sorted asc");
            $this->assertFalse($col->isSortedDesc(), "should not be sorted desc ");
        }

    }

    function resetDatabase()
    {
        $db = Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS html_table_persons');

        $db->query('
            CREATE TABLE html_table_persons (
                html_table_person_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar(250) NOT NULL,
                `age` int(11) NOT NULL
            )
        ');

        return $db;
    }

    function testResultSetSorting() {

        $db = $this->resetDatabase();

        $db->query("

            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('John Smith', 99)

        ");

        $people = HtmlTablePerson::all();
        $this->assertEquals(2, $people->count(), "wrong # of records in the table");

        $table = new Octopus_Html_Table('resultSetSorting', array('pager' => false));
        $table->addColumn('name');
        $table->addColumn('age');

        $table->setDataSource($people);

        $expected = <<<END
<table id="resultSetSorting" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name"><span class="sortMarker">Name</span></a></th>
            <th class="age lastCell sortable"><a href="?sort=age"><span class="sortMarker">Age</span></a></th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name firstCell">Joe Blow</td>
            <td class="age lastCell">50</td>
        </tr>
        <tr class="even">
            <td class="name firstCell">John Smith</td>
            <td class="age lastCell">99</td>
        </tr>
    </tbody>
</table>
END;

        $this->assertHtmlEquals(
            $expected,
            $table->render(true),
            "initial state renders wrong"
        );

        $table->sort('name');

        $expected = <<<END
<table id="resultSetSorting" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name sorted firstCell sortable sortAsc"><a href="?sort=%21name"><span class="sortMarker">Name</span></a></th>
            <th class="age lastCell sortable"><a href="?sort=age%2Cname"><span class="sortMarker">Age</span></a></th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name sorted firstCell">Joe Blow</td>
            <td class="age lastCell">50</td>
        </tr>
        <tr class="even">
            <td class="name sorted firstCell">John Smith</td>
            <td class="age lastCell">99</td>
        </tr>
    </tbody>
</table>
END;

        $this->assertHtmlEquals(
            $expected,
            $table->render(true),
            "sort name asc renders wrong"
        );


        $table->sort('!name');

        $expected = <<<END
<table id="resultSetSorting" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name sorted firstCell sortable sortDesc"><a href="?sort=name"><span class="sortMarker">Name</span></a></th>
            <th class="age lastCell sortable"><a href="?sort=age%2C%21name"><span class="sortMarker">Age</span></a></th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name sorted firstCell">John Smith</td>
            <td class="age lastCell">99</td>
        </tr>
        <tr class="even">
            <td class="name sorted firstCell">Joe Blow</td>
            <td class="age lastCell">50</td>
        </tr>
    </tbody>
</table>
END;

        $this->assertHtmlEquals(
            $expected,
            $table->render(true),
            "sort name desc renders wrong"
        );
    }

    function testPaging() {

        $table = new Octopus_Html_Table('paging');
        $table->addColumn('name');
        $table->addColumn('age');
        $table->setDataSource($this->arrayData);

        $table->setPageSize(10);

        $this->assertEquals(10, $table->getPageCount());
        for($i = 1; $i <= 10; $i++) {
            $table->setPage($i);
            $this->assertEquals($i, $table->getPage());
        }
        $table->setPage(1);


        $tableHead = <<<END

<table id="paging" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name"><span class="sortMarker">Name</span></a></th>
            <th class="age lastCell sortable"><a href="?sort=age"><span class="sortMarker">Age</span></a></th>
        </tr>
    </thead>
    <tbody>
END;

        $tableFoot = <<<END
    </tbody>
    <tfoot>
        <tr>
            <td class="pager" colspan="2">
                <div class="pager">
                    <div class="pagerLinks"><a href="?page=1" class="current" title="Page 1">1</a><a href="?page=2" title="Page 2">2</a><a href="?page=3" title="Page 3">3</a><a href="?page=4" title="Page 4">4</a><a href="?page=2" class="next" title="Page 2">Next</a><a href="?page=10" class="last-page" title="Page 10">Last</a></div>
                    <div class="pagerLoc">Showing <span class="pagerRangeStart">1</span> to <span class="pagerRangeEnd">10</span> of <span class="pagerItemCount">100</span></div>
                </div>
            </td>
        </tr>
    </tfoot>
</table>
END;

        $expected = $tableHead;
        $even = false;
        foreach(array_slice($this->arrayData, 0, 10) as $row) {
            $class = ($even ? 'even' : 'odd');
            $expected .= <<<END
            <tr class="$class">
                <td class="name firstCell">{$row['name']}</td>
                <td class="age lastCell">{$row['age']}</td>
            </tr>
END;
            $even = !$even;
        }

        $expected .= $tableFoot;
        $firstPage = $expected;


        $this->assertEquals(1, $table->getPage());
        $this->assertHtmlEquals(
            $expected,
            $table->render(true)
        );

        $table->nextPage();

        $tableHead = <<<END

<table id="paging" border="0" cellpadding="0" cellspacing="0">
    <thead class="columns">
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name"><span class="sortMarker">Name</span></a></th>
            <th class="age lastCell sortable"><a href="?sort=age"><span class="sortMarker">Age</span></a></th>
        </tr>
    </thead>
    <tbody>
END;

        $tableFoot = <<<END
    </tbody>
    <tfoot>
        <tr>
            <td class="pager" colspan="2">
                <div class="pager">
                    <div class="pagerLinks"><a href="?page=1" class="first-page" title="Page 1">First</a><a href="?page=1" class="prev" title="Page 1">Previous</a><a href="?page=1" title="Page 1">1</a><a href="?page=2" class="current" title="Page 2">2</a><a href="?page=3" title="Page 3">3</a><a href="?page=4" title="Page 4">4</a><a href="?page=3" class="next" title="Page 3">Next</a><a href="?page=10" class="last-page" title="Page 10">Last</a></div>
                    <div class="pagerLoc">Showing <span class="pagerRangeStart">11</span> to <span class="pagerRangeEnd">20</span> of <span class="pagerItemCount">100</span></div>
                </div>
            </td>
        </tr>
    </tfoot>
</table>
END;

        $expected = $tableHead;
        $even = false;
        foreach(array_slice($this->arrayData, 10, 10) as $row) {
            $class = ($even ? 'even' : 'odd');
            $expected .= <<<END
            <tr class="$class">
                <td class="name firstCell">{$row['name']}</td>
                <td class="age lastCell">{$row['age']}</td>
            </tr>
END;
            $even = !$even;
        }

        $expected .= $tableFoot;

        $this->assertEquals(2, $table->getPage());
        $this->assertHtmlEquals(
            $expected,
            $table->render(true)
        );

        $table->sort('name');
        $this->assertEquals(1, $table->getPage(), 'page should be reset on sort');

        $expected = str_replace('th class="name firstCell sortable"', 'th class="name sorted firstCell sortable sortAsc"', $firstPage);
        $expected = str_replace('td class="name firstCell"', 'td class="name sorted firstCell"', $expected);
        $expected = preg_replace('/\?sort=name/', '?sort=%21name', $expected);
        $expected = preg_replace('/\?sort=age/', '?sort=age%2Cname', $expected);
        $expected = preg_replace('/\<tbody\>.*\<\/tbody\>/s', '', $expected);

        $actual = $table->render(true);
        $actual = preg_replace('/\<tbody\>.*\<\/tbody\>/s', '', $actual);

        $this->assertHtmlEquals($expected, $actual);

    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function globalTestFunctionForTable($value) {
    return $value * $value;
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class MethodOnObjectTestObject {

    public function __construct($num) {
        $this->num = $num;
    }

    public function squareOfNum() {
        return $this->num * $this->num;
    }

}
