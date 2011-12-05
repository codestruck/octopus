<?php

class PagerTest extends Octopus_App_TestCase {

	function setUp() {

		parent::setUp();

		unset($_GET['page']);
		unset($_SERVER['REQUEST_URI']);

	}

	function testEmpty() {

		$p = new Octopus_Html_Pager(
			array(
				'pageSize' => 10
			)
		);
		$p->setDataSource(array());

		$this->assertEquals(0, $p->getTotalItemCount());
		$this->assertEquals(0, $p->getPageCount());

		$this->assertHtmlEquals(
			<<<END
<div class="pager empty">
</div>
END
			,
			$p->render(true)
		);

	}

	function testSinglePage() {

		$p = new Octopus_Html_Pager(array('pageSize' => 10));

		$testData =
			array(
				array("name" => "Coward, Nora","age" => "53",),
				array("name" => "Pizer, Alfred","age" => "82",),
				array("name" => "Mcclain, Dusty","age" => "88",),
				array("name" => "Sanderman, Esther","age" => "16",),
				array("name" => "Leon, Christopher","age" => "67",),
				array("name" => "Adams, Arnold","age" => "18",),
				array("name" => "Corum, Michelle","age" => "19",),
				array("name" => "Meise, Mark","age" => "20",),
				array("name" => "Stovel, Elmer","age" => "14",),
				array("name" => "Redmond, Youlanda","age" => "25",),
				array("name" => "Bayhonan, Isaias","age" => "66",),
				array("name" => "Parobek, Seymour","age" => "34",),
				array("name" => "Williams, Catherine","age" => "76",),
				array("name" => "Rodriguez, John","age" => "62",),
			);


		$p->setDataSource($testData);

		$this->assertEquals(2, $p->getPageCount());
		$this->assertEquals(14, $p->getTotalItemCount());
		$this->assertEquals(array_slice($testData, 0, 10), $p->getItems()->getArray());

	}

	function testMultiPageNav() {

		$p = new Octopus_Html_Pager(array('pageSize' => 5));

		$testData =
			array(
				array("name" => "Coward, Nora","age" => "53",),
				array("name" => "Pizer, Alfred","age" => "82",),
				array("name" => "Mcclain, Dusty","age" => "88",),
				array("name" => "Sanderman, Esther","age" => "16",),
				array("name" => "Leon, Christopher","age" => "67",),

				array("name" => "Adams, Arnold","age" => "18",),
				array("name" => "Corum, Michelle","age" => "19",),
				array("name" => "Meise, Mark","age" => "20",),
				array("name" => "Stovel, Elmer","age" => "14",),
				array("name" => "Redmond, Youlanda","age" => "25",),

				array("name" => "Bayhonan, Isaias","age" => "66",),
				array("name" => "Parobek, Seymour","age" => "34",),
				array("name" => "Williams, Catherine","age" => "76",),
				array("name" => "Rodriguez, John","age" => "62",),
			);


		$p->setDataSource($testData);

		$this->assertEquals(3, $p->getPageCount());
		$this->assertEquals(14, $p->getTotalItemCount());
		$this->assertEquals(0, $p->getCurrentPage());
		$this->assertEquals(array_slice($testData, 0, 5), $p->getItems()->getArray());

		$p->setCurrentPage(1);
		$this->assertEquals(1, $p->getCurrentPage());
		$this->assertEquals(array_slice($testData, 5, 5), $p->getItems()->getArray(), 'setCurrentPage changes items');

		$p->setCurrentPage(2);
		$this->assertEquals(array_slice($testData, 10), $p->getItems()->getArray());

		$p->setCurrentPage(3);
		$this->assertEquals(2, $p->getCurrentPage(), "can't set current page beyond max page");
		$this->assertEquals(array_slice($testData, 10), $p->getItems()->getArray());

	}

	function testChangePageSize() {

		$p = new Octopus_Html_Pager(array('pageSize' => 5));

		$testData =
			array(
				array("name" => "Coward, Nora","age" => "53",),
				array("name" => "Pizer, Alfred","age" => "82",),
				array("name" => "Mcclain, Dusty","age" => "88",),
				array("name" => "Sanderman, Esther","age" => "16",),
				array("name" => "Leon, Christopher","age" => "67",),

				array("name" => "Adams, Arnold","age" => "18",),
				array("name" => "Corum, Michelle","age" => "19",),
				array("name" => "Meise, Mark","age" => "20",),
				array("name" => "Stovel, Elmer","age" => "14",),
				array("name" => "Redmond, Youlanda","age" => "25",),

				array("name" => "Bayhonan, Isaias","age" => "66",),
				array("name" => "Parobek, Seymour","age" => "34",),
				array("name" => "Williams, Catherine","age" => "76",),
				array("name" => "Rodriguez, John","age" => "62",),
			);


		$p->setDataSource($testData);

		$this->assertEquals(array_slice($testData, 0, 5), $p->getItems()->getArray());
		$p->setPageSize(10);
		$this->assertEquals(0, $p->getCurrentPage());
		$this->assertEquals(array_slice($testData, 0, 10), $p->getItems()->getArray(), 'changing page size');

		$p->setPageSize(5);
		$p->setCurrentPage(2);
		$this->assertEquals(array_slice($testData, 10), $p->getItems()->getArray());
		$p->setPageSize(5);
		$this->assertEquals(2, $p->getCurrentPage(), 'not changing page size does not reset page');

		$p->setPageSize(10);
		$this->assertEquals(0, $p->getCurrentPage(), 'setPageSize resets page');

	}

	function testInitPageFromQueryString() {

		$_GET['page'] = 2;

		$p = new Octopus_Html_Pager(array('pageSize' => 5));
		$this->assertEquals(0, $p->getCurrentPage(), '0 initially');

		$testData =
			array(
				array("name" => "Coward, Nora","age" => "53",),
				array("name" => "Pizer, Alfred","age" => "82",),
				array("name" => "Mcclain, Dusty","age" => "88",),
				array("name" => "Sanderman, Esther","age" => "16",),
				array("name" => "Leon, Christopher","age" => "67",),

				array("name" => "Adams, Arnold","age" => "18",),
				array("name" => "Corum, Michelle","age" => "19",),
				array("name" => "Meise, Mark","age" => "20",),
				array("name" => "Stovel, Elmer","age" => "14",),
				array("name" => "Redmond, Youlanda","age" => "25",),

				array("name" => "Bayhonan, Isaias","age" => "66",),
				array("name" => "Parobek, Seymour","age" => "34",),
				array("name" => "Williams, Catherine","age" => "76",),
				array("name" => "Rodriguez, John","age" => "62",),
			);


		$p->setDataSource($testData);
		$this->assertEquals(2, $p->getCurrentPage(), 'current page initialized from querystring');

	}

	function testGetPageNumbers() {

		$p = new Octopus_Html_Pager(array('pageSize' => 1));

		$data = array();
		for($i = 0; $i < 1000; $i++) {
			$data[] = array('id' => $i, 'name' => 'test');
		}
		$p->setDataSource($data);


		$this->assertEquals(
			//    v
			array(1,2,3,4,5,999,1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(1);
		$this->assertEquals(
			//      v
			array(1,2,3,4,5,999,1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(2);
		$this->assertEquals(
			//        v
			array(1,2,3,4,5,999,1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(3);
		$this->assertEquals(
			//        v
			array(2,3,4,5,6,999,1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(4);
		$this->assertEquals(
			//        v
			array(3,4,5,6,7,999,1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(5);
		$this->assertEquals(
			//        v
			array(4,5,6,7,8,999,1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(995);
		$this->assertEquals(
			//              v
			array(994, 995, 996, 997, 998, 999, 1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(996);
		$this->assertEquals(
			//              v
			array(995, 996, 997, 998, 999, 1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(997);
		$this->assertEquals(
			//              v
			array(996, 997, 998, 999, 1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(998);
		$this->assertEquals(
			//                   v
			array(996, 997, 998, 999, 1000),
			$p->getPageNumbers()
		);

		$p->setCurrentPage(999);
		$this->assertEquals(
			//                        v
			array(996, 997, 998, 999, 1000),
			$p->getPageNumbers()
		);

	}

	function testBasicRendering() {

		$data = array();
		for($i = 0; $i < 100; $i++) {
			$data[] = array('id' => $i, 'name' => 'foo');
		}

		$p = new Octopus_Html_Pager();
		$p->setDataSource($data);

		$this->assertHtmlEquals(
			<<<END
<div class="pager first-page">
	<a href="?page=1" class="prev">Previous</a>
	<a href="?page=1" class="current">1</a>
	<a href="?page=2">2</a>
	<a href="?page=3">3</a>
	<a href="?page=4">4</a>
	<a href="?page=5">5</a>
	<span class="sep">&hellip;</span>
	<a href="?page=9">9</a>
	<a href="?page=10">10</a>
	<a href="?page=2" class="next">Next</a>
</div>
END
			,
			$p->render(true)
		);

	}

	function testChangeUrlArgs() {

		$_SERVER['REQUEST_URI'] = '/foo/bar?x=y&active=1';

		$data = array();
		for($i = 0; $i < 3; $i++) {
			$data[] = array('id' => $i, 'name' => 'foo');
		}

		$p = new Octopus_Html_Pager();
		$p->setPageSize(1);
		$p->setDataSource($data);

		$this->assertHtmlEquals(
			<<<END
<div class="pager first-page">
	<a href="/foo/bar?x=y&amp;active=1&amp;page=1" class="prev">Previous</a>
	<a href="/foo/bar?x=y&amp;active=1&amp;page=1" class="current">1</a>
	<a href="/foo/bar?x=y&amp;active=1&amp;page=2">2</a>
	<a href="/foo/bar?x=y&amp;active=1&amp;page=3">3</a>
	<a href="/foo/bar?x=y&amp;active=1&amp;page=2" class="next">Next</a>
</div>
END
			,
			$p->render(true)
		);
	}

}

?>