<?php

Octopus::loadClass('Octopus_Html_Table');

class HtmlTablePerson extends Octopus_Model {

    protected $fields = array(
        'name' => array(
            'sortable' => false,
            'filter' => true
        ),
        'age' => array('type' => 'numeric')
    );

}

function globalTestFunctionForTable($value) {
    return $value * $value;
}

class MethodOnObjectTestObject {

    public function __construct($num) {
        $this->num = $num;
    }

    public function squareOfNum() {
        return $this->num * $this->num;
    }

}

class TableTest extends Octopus_App_TestCase {

    function testFilterInitFromGet() {

        $_GET['foo'] = 'bar';

        $table = new Octopus_Html_Table('initFromGet');
        $table->addFilter('text', 'foo');

        $filter = $table->getFilter('foo');
        $this->assertEquals('bar', $filter->val());

    }

    function testEmptyTable() {

        $table = new Octopus_Html_Table('empty', array('pager' => false));
        $table->addColumn('name');
        $table->addColumn('age');

        $expected = <<<END
        <table id="empty" border="0" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th class="name firstCell">Name</th>
                    <th class="age lastCell">Age</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
END;

        $this->assertHtmlEquals($expected, $table);
    }

    function testRenderFilters() {

        $table = new Octopus_Html_Table('renderFilters', array('pager' => false));
        $table->addColumn('name');
        $table->addFilter('text', 'foo');

        $table->filter('foo', 'bar');


        $expected = <<<END
<table id="renderFilters" border="0" cellpadding="0" cellspacing="0">
    <thead>
        <tr>
            <td class="filters" colspan="1">
                <form method="get" action="" class="filterForm">
                    <div class="filter foo text firstFilter lastFilter">
                        <label class="filterLabel" for="fooInput">Foo:</label>
                        <input type="text" id="fooInput" class="foo text" name="foo" value="bar" />
                    </div>
                </form>
            </td>
        </tr>
    </thead>
    <thead>
        <tr>
            <th class="name firstCell">Name</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>
END;
        $this->assertHtmlEquals($expected, $table);

    }

    function testTextFilter() {

        $table = new Octopus_Html_Table('textFilter');
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

    function testSelectFilter() {

        $db = $this->resetDatabase();
        $db->query("
            INSERT INTO html_table_persons (`name`, `age`)
                VALUES
                    ('Joe Blow', 50),
                    ('Jane Blow', 50),
                    ('John Smith', 99)
        ");


        $table = new Octopus_Html_Table('selectFilter', array('pager' => false));
        $table->addColumn('name');
        $table->addColumn('age');

        $select = $table->addFilter('select', 'age', array(50 => 'Fifty', 99 => 'Ninety-nine'));

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

    function twoArgTestFunction($value, $row) {
        $this->assertEquals(25, $value, "value is wrong");
        $this->assertEquals(array('name' => 'Joe Blow', 'num' => 25), $row);
        return $value * 2;
    }

    function testFunctionWith2Args() {

        $table = new Octopus_Html_Table('twoArgs');
        $table->addColumn('name');
        $table->addColumn('num', 'Magic Number', array($this, 'twoArgTestFunction'));
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

        $val = '  foo  ';

        $funcs = array(
            'htmlspecialchars',
            'trim', 'ltrim', 'rtrim'
            // TODO More?
        );

        foreach($funcs as $f) {

            $table = new Octopus_Html_Table('builtIn2Arg');
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
    <thead>
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
    <thead>
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name">Name</a></th>
            <th class="age sortable"><a href="?sort=age">Age</a></th>
            <th class="actions lastCell">Actions</th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name firstCell">Joe Blow</td>
            <td class="age">50</td>
            <td class="actions lastCell">
                <a href="/toggle/active/1" class="toggle active toggleActive" data-alt="Inactive">Active</a>
                <a href="/edit/1" class="action edit">Edit</a>
                <a href="/delete/1" class="action delete">Delete</a>
            </td>
        </tr>
        <tr class="even">
            <td class="name firstCell">Joe Smith</td>
            <td class="age">99</td>
            <td class="actions lastCell">
                <a href="/toggle/active/2" class="toggle active toggleInactive" data-alt="Active">Inactive</a>
                <a href="/edit/2" class="action edit">Edit</a>
                <a href="/delete/2" class="action delete">Delete</a>
            </td>
        </tr>
    </tbody>
</table>
END;

        $table = new Octopus_Html_Table('complexTable', array('pager' => false ));

        $table->addColumn('name', array('sortable' => true));
        $table->addColumn('age', array('sortable' => true));

        $col = $table->addColumn('actions');
        $col->addToggle('active', array('Inactive', 'Active'), '/toggle/active/{$person_id}');
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

            $col->sort(false);
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

            $col->sort(false);
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
    <thead>
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name">Name</a></th>
            <th class="age lastCell sortable"><a href="?sort=age">Age</a></th>
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
    <thead>
        <tr>
            <th class="name sorted sortAsc firstCell sortable"><a href="?sort=%21name">Name</a></th>
            <th class="age lastCell sortable"><a href="?sort=age%2Cname">Age</a></th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name sorted sortAsc firstCell">Joe Blow</td>
            <td class="age lastCell">50</td>
        </tr>
        <tr class="even">
            <td class="name sorted sortAsc firstCell">John Smith</td>
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
    <thead>
        <tr>
            <th class="name sorted sortDesc firstCell sortable"><a href="?sort=name">Name</a></th>
            <th class="age lastCell sortable"><a href="?sort=age%2C%21name">Age</a></th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name sorted sortDesc firstCell">John Smith</td>
            <td class="age lastCell">99</td>
        </tr>
        <tr class="even">
            <td class="name sorted sortDesc firstCell">Joe Blow</td>
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

        $rawData = array(
            /* Test Data {{{ */
            array(	'id',		'name',			'age'			),
            array(	1,		'Hamish Grant',			'49'			),
            array(	2,		'Charles Bowman',			'32'			),
            array(	3,		'Palmer Douglas',			'52'			),
            array(	4,		'James Conrad',			'61'			),
            array(	5,		'Leroy Short',			'41'			),
            array(	6,		'Ahmed Rasmussen',			'67'			),
            array(	7,		'Russell Foster',			'36'			),
            array(	8,		'Dustin Snider',			'43'			),
            array(	9,		'Boris Vega',			'47'			),
            array(	10,		'Emery Lewis',			'60'			),
            array(	11,		'Thomas Wheeler',			'31'			),
            array(	12,		'Hop Castro',			'35'			),
            array(	13,		'Luke Valdez',			'47'			),
            array(	14,		'Demetrius Hester',			'55'			),
            array(	15,		'Berk Barrera',			'48'			),
            array(	16,		'Jackson Benjamin',			'37'			),
            array(	17,		'Hop Terrell',			'51'			),
            array(	18,		'Oscar Cotton',			'44'			),
            array(	19,		'Cedric Fulton',			'41'			),
            array(	20,		'Nicholas Moss',			'65'			),
            array(	21,		'John Lynch',			'63'			),
            array(	22,		'Nathan Alston',			'25'			),
            array(	23,		'Wyatt Nash',			'66'			),
            array(	24,		'Ignatius Walls',			'40'			),
            array(	25,		'Cedric Barrera',			'42'			),
            array(	26,		'Oleg Little',			'45'			),
            array(	27,		'Alfonso Acevedo',			'40'			),
            array(	28,		'Louis Ramsey',			'49'			),
            array(	29,		'Benedict Wade',			'64'			),
            array(	30,		'Ira Morse',			'48'			),
            array(	31,		'Gil Clements',			'28'			),
            array(	32,		'Denton Suarez',			'28'			),
            array(	33,		'Edward Skinner',			'58'			),
            array(	34,		'Davis Garner',			'55'			),
            array(	35,		'Louis Kramer',			'57'			),
            array(	36,		'Amir Donaldson',			'36'			),
            array(	37,		'Jeremy Faulkner',			'36'			),
            array(	38,		'Tanek Vargas',			'25'			),
            array(	39,		'Hilel Anthony',			'67'			),
            array(	40,		'Ezekiel King',			'51'			),
            array(	41,		'Tarik Todd',			'59'			),
            array(	42,		'Yardley Cline',			'54'			),
            array(	43,		'Baker King',			'47'			),
            array(	44,		'Sawyer Riggs',			'62'			),
            array(	45,		'Julian Henry',			'40'			),
            array(	46,		'Dillon Hull',			'51'			),
            array(	47,		'Keaton Williamson',			'63'			),
            array(	48,		'Sebastian Sosa',			'60'			),
            array(	49,		'Talon Frye',			'61'			),
            array(	50,		'Samuel Nelson',			'38'			),
            array(	51,		'Herman Lynn',			'34'			),
            array(	52,		'Craig Leonard',			'42'			),
            array(	53,		'Travis Mejia',			'64'			),
            array(	54,		'Harlan Hunter',			'54'			),
            array(	55,		'Denton Alexander',			'28'			),
            array(	56,		'Lucian Forbes',			'48'			),
            array(	57,		'Dalton Stark',			'47'			),
            array(	58,		'Brody Petersen',			'41'			),
            array(	59,		'Wade Mckinney',			'55'			),
            array(	60,		'Porter Horton',			'40'			),
            array(	61,		'Benedict Thomas',			'65'			),
            array(	62,		'Elton Campbell',			'43'			),
            array(	63,		'Oliver Bates',			'26'			),
            array(	64,		'Luke Jordan',			'47'			),
            array(	65,		'Elton Rice',			'47'			),
            array(	66,		'Griffith Hendricks',			'61'			),
            array(	67,		'Wing Murray',			'55'			),
            array(	68,		'Ishmael Ross',			'42'			),
            array(	69,		'Stone Puckett',			'24'			),
            array(	70,		'Keegan Lancaster',			'44'			),
            array(	71,		'Vaughan Melendez',			'40'			),
            array(	72,		'Sean Walters',			'46'			),
            array(	73,		'Dominic Norton',			'48'			),
            array(	74,		'Felix Gallegos',			'25'			),
            array(	75,		'Steven Moses',			'59'			),
            array(	76,		'Geoffrey Bernard',			'33'			),
            array(	77,		'Cody Baldwin',			'62'			),
            array(	78,		'Trevor Lara',			'53'			),
            array(	79,		'Elvis Harrison',			'41'			),
            array(	80,		'Ezekiel Wiggins',			'65'			),
            array(	81,		'Reese Tran',			'47'			),
            array(	82,		'Carl Raymond',			'45'			),
            array(	83,		'Marvin Battle',			'30'			),
            array(	84,		'Colby Hayes',			'45'			),
            array(	85,		'Dalton English',			'29'			),
            array(	86,		'Amal Newton',			'32'			),
            array(	87,		'Cyrus Suarez',			'33'			),
            array(	88,		'Bert Roth',			'57'			),
            array(	89,		'Perry Butler',			'29'			),
            array(	90,		'Cairo Jenkins',			'28'			),
            array(	91,		'August Fields',			'24'			),
            array(	92,		'Laith Hull',			'58'			),
            array(	93,		'Asher Cain',			'60'			),
            array(	94,		'Coby Goodman',			'33'			),
            array(	95,		'Merritt Mclaughlin',			'45'			),
            array(	96,		'Rashad Moses',			'30'			),
            array(	97,		'Zachary Terry',			'25'			),
            array(	98,		'Christian Acosta',			'60'			),
            array(	99,		'Victor Rice',			'66'			),
            array(	100,		'Ashton Vaughn',			'42'			),
            /* }}} */
        );

        $headers = array_shift($rawData);
        $data = array();
        while($raw = array_shift($rawData)) {
            $row = array();
            for($i = 0; $i < count($headers); $i++) {
                $row[$headers[$i]] = $raw[$i];
            }
            $data[] = $row;
        }


        $table = new Octopus_Html_Table('paging');
        $table->addColumn('name');
        $table->addColumn('age');
        $table->setDataSource($data);

        $table->setPageSize(10);

        $tableHead = <<<END

<table id="paging" border="0" cellpadding="0" cellspacing="0">
    <thead>
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name">Name</a></th>
            <th class="age lastCell sortable"><a href="?sort=age">Age</a></th>
        </tr>
    </thead>
    <tbody>
END;

        $tableFoot = <<<END
    </tbody>
    <tfoot>
        <tr>
            <td class="pager" colspan="2">
                <div class="pagerLinks"><span class="current">1</span>&nbsp;<a href="?page=2" title="page 2">2</a>&nbsp;<a href="?page=3" title="page 3">3</a>&nbsp;<a href="?page=4" title="page 4">4</a>&nbsp;<a href="?page=5" title="page 5">5</a>&nbsp;<a href="?page=6" title="page 6">6</a>&nbsp;<a href="?page=7" title="page 7">7</a>&nbsp;<a href="?page=8" title="page 8">8</a>&nbsp;<a href="?page=9" title="page 9">9</a>&nbsp;<a href="?page=10" title="page 10">10</a>&nbsp;<a href="?page=2" title="next page">Next &raquo;</a>&nbsp;</div>
                <div class="pagerLoc"> Showing 1 to 10 of 100 </div>
            </td>
        </tr>
    </tfoot>
</table>
END;

        $expected = $tableHead;
        $even = false;
        foreach(array_slice($data, 0, 10) as $row) {
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
    <thead>
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name">Name</a></th>
            <th class="age lastCell sortable"><a href="?sort=age">Age</a></th>
        </tr>
    </thead>
    <tbody>
END;

        $tableFoot = <<<END
    </tbody>
    <tfoot>
        <tr>
            <td class="pager" colspan="2">
                <div class="pagerLinks"><a href="?page=1" title="previous page">&laquo; Previous</a>&nbsp;<a href="?page=1" title="page 1">1</a>&nbsp;<span class="current">2</span>&nbsp;<a href="?page=3" title="page 3">3</a>&nbsp;<a href="?page=4" title="page 4">4</a>&nbsp;<a href="?page=5" title="page 5">5</a>&nbsp;<a href="?page=6" title="page 6">6</a>&nbsp;<a href="?page=7" title="page 7">7</a>&nbsp;<a href="?page=8" title="page 8">8</a>&nbsp;<a href="?page=9" title="page 9">9</a>&nbsp;<a href="?page=10" title="page 10">10</a>&nbsp;<a href="?page=3" title="next page">Next &raquo;</a>&nbsp;</div>
                <div class="pagerLoc"> Showing 11 to 20 of 100 </div>
            </td>
        </tr>
    </tfoot>
</table>
END;

        $expected = $tableHead;
        $even = false;
        foreach(array_slice($data, 10, 10) as $row) {
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

        $sortedFirstPage = preg_replace('/(<t(d|h) class="name)/', '$1 sorted sortAsc', $firstPage);
        $sortedFirstPage = preg_replace('/\?sort=name/', '?sort=%21name', $sortedFirstPage);
        $sortedFirstPage = preg_replace('/\?sort=age/', '?sort=age%2Cname', $sortedFirstPage);

        $table->sort('name');
        $this->assertEquals(1, $table->getPage(), 'page should be reset on sort');
        $this->assertHtmlEquals(
            $sortedFirstPage,
            $table->render(true),
            "should render 1st page after sort"
        );


    }

    function xtestImageActionsAndToggles() {

        $expected = <<<END
<table id="complexTable" border="0" cellpadding="0" cellspacing="0">
    <thead>
        <tr>
            <th class="name firstCell sortable"><a href="?sort=name">Name</a></th>
            <th class="actions lastCell">Actions</th>
        </tr>
    </thead>
    <tbody>
        <tr class="odd">
            <td class="name firstCell">Joe Blow</td>
            <td class="actions lastCell">
                <a href="/toggle/active/1" class="toggle active toggleActive"></a>
                <a href="/edit/1" class="action edit">Edit</a>
                <a href="/delete/1" class="action delete">Delete</a>
            </td>
        </tr>
        <tr class="even">
            <td class="name firstCell">Joe Smith</td>
            <td class="actions lastCell">
                <a href="/toggle/active/2" class="toggle active toggleInactive">Active</a>
                <a href="/edit/2" class="action edit">Edit</a>
                <a href="/delete/2" class="action delete">Delete</a>
            </td>
        </tr>
    </tbody>
</table>
END;

        $table = new Octopus_Html_Table('complexTable', array('pager' => false ));

        $table->addColumn('name', array('sortable' => true));
        $table->addColumn('age', array('sortable' => true));

        $col = $table->addColumn('actions');
        $col->addToggle('active', '/toggle/active/{$person_id}');
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

}

?>
