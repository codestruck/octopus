<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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
        $this->assertEquals(1, $p->getPage());
        $this->assertEquals(array_slice($testData, 0, 5), $p->getItems()->getArray());

        $p->setPage(2);
        $this->assertEquals(2, $p->getPage());
        $this->assertEquals(array_slice($testData, 5, 5), $p->getItems()->getArray(), 'setPage changes items');

        $p->setPage(3);
        $this->assertEquals(array_slice($testData, 10), $p->getItems()->getArray());

        $p->setPage(4);
        $this->assertEquals(3, $p->getPage(), "can't set current page beyond max page");
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
        $this->assertEquals(1, $p->getPage());
        $this->assertEquals(array_slice($testData, 0, 10), $p->getItems()->getArray(), 'changing page size');

        $p->setPageSize(5);
        $p->setPage(2);
        $this->assertEquals(array_slice($testData, 5, 5), $p->getItems()->getArray());
        $p->setPageSize(5);
        $this->assertEquals(2, $p->getPage(), 'not changing page size does not reset page');

        $p->setPageSize(10);
        $this->assertEquals(1, $p->getPage(), 'setPageSize resets page');

    }

    function testInitPageFromQueryString() {

        $_GET['page'] = 2;

        $p = new Octopus_Html_Pager(array('pageSize' => 5));
        $this->assertEquals(1, $p->getPage(), '1 initially');

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
        $this->assertEquals(2, $p->getPage(), 'current page initialized from querystring');

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
            array(1,2,3,4),
            $p->getPageNumbers()
        );

        $p->setPage(2);
        $this->assertEquals(
            //      v
            array(1,2,3,4),
            $p->getPageNumbers()
        );

        $p->setPage(3);
        $this->assertEquals(
            //        v
            array(1,2,3,4,5,),
            $p->getPageNumbers()
        );

        $p->setPage(4);
        $this->assertEquals(
            //        v
            array(2,3,4,5,6,),
            $p->getPageNumbers()
        );

        $p->setPage(5);
        $this->assertEquals(
            //        v
            array(3,4,5,6,7,),
            $p->getPageNumbers()
        );

        $p->setPage(6);
        $this->assertEquals(
            //        v
            array(4,5,6,7,8,),
            $p->getPageNumbers()
        );

        $p->setPage(996);
        $this->assertEquals(
            //              v
            array(994, 995, 996, 997, 998,),
            $p->getPageNumbers()
        );

        $p->setPage(997);
        $this->assertEquals(
            //              v
            array(995, 996, 997, 998, 999,),
            $p->getPageNumbers()
        );

        $p->setPage(998);
        $this->assertEquals(
            //              v
            array(996, 997, 998, 999, 1000),
            $p->getPageNumbers()
        );

        $p->setPage(999);
        $this->assertEquals(
            //                   v
            array(996, 997, 998, 999, 1000),
            $p->getPageNumbers()
        );

        $p->setPage(1000);
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
    <a href="?page=1" class="current" title="Page 1">1</a>
    <a href="?page=2" title="Page 2">2</a>
    <a href="?page=3" title="Page 3">3</a>
    <a href="?page=4" title="Page 4">4</a>
    <a href="?page=2" class="next" title="Page 2">Next</a>
    <a href="?page=10" class="last-page" title="Page 10">Last</a>
</div>
END
            ,
            $p->render(true)
        );

    }

    function testHideIrrelevantLinks() {

        $data = array();
        for($i = 0; $i < 30; $i++) {
            $data[] = array('id' => $i, 'name' => "foo $i");
        }

        $p = new Octopus_Html_Pager();
        $p->setDataSource($data);

        $this->assertHtmlEquals(
            <<<END
<div class="pager first-page">
    <a href="?page=1" class="current" title="Page 1">1</a>
    <a href="?page=2" title="Page 2">2</a>
    <a href="?page=3" title="Page 3">3</a>
    <a href="?page=2" class="next" title="Page 2">Next</a>
    <a href="?page=3" class="last-page" title="Page 3">Last</a>
</div>
END
            ,
            $p->render(true)
        );

        $p->setPage(2);

        $this->assertHtmlEquals(
            <<<END
<div class="pager">
    <a href="?page=1" class="first-page" title="Page 1">First</a>
    <a href="?page=1" class="prev" title="Page 1">Previous</a>
    <a href="?page=1" title="Page 1">1</a>
    <a href="?page=2" class="current" title="Page 2">2</a>
    <a href="?page=3" title="Page 3">3</a>
    <a href="?page=3" class="next" title="Page 3">Next</a>
    <a href="?page=3" class="last-page" title="Page 3">Last</a>
</div>
END
            ,
            $p->render(true)
        );

        $p->setPage(3);

        $this->assertHtmlEquals(
            <<<END
<div class="pager last-page">
    <a href="?page=1" class="first-page" title="Page 1">First</a>
    <a href="?page=2" class="prev" title="Page 2">Previous</a>
    <a href="?page=1" title="Page 1">1</a>
    <a href="?page=2" title="Page 2">2</a>
    <a href="?page=3" class="current" title="Page 3">3</a>
</div>
END
            ,
            $p->render(true)
        );

        $p->setPage(1);
        $p->setPageSize(30);

        $this->assertHtmlEquals(
            <<<END
<div class="pager first-page last-page single-page">
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
    <a href="/foo/bar?x=y&amp;active=1&amp;page=1" class="current" title="Page 1">1</a>
    <a href="/foo/bar?x=y&amp;active=1&amp;page=2" title="Page 2">2</a>
    <a href="/foo/bar?x=y&amp;active=1&amp;page=3" title="Page 3">3</a>
    <a href="/foo/bar?x=y&amp;active=1&amp;page=2" class="next" title="Page 2">Next</a>
    <a href="/foo/bar?x=y&amp;active=1&amp;page=3" class="last-page" title="Page 3">Last</a>
</div>
END
            ,
            $p->render(true)
        );
    }

}
