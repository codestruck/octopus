p<?php

SG::loadClass('SG_Nav');

/**
 * @group nav
 */
class SG_Nav_Test extends PHPUnit_Framework_TestCase {

    static $testDir = 'nav_directory';
    static $files = array('a.php', 'b.html', 'c.txt');

    function setUp() {

        @mkdir(self::$testDir);
        foreach(self::$files as $f) {
            touch(self::$testDir . "/$f");
        }

    }

    function tearDown() {
        foreach(self::$files as $f) {
            @unlink(self::$testDir . "/$f");
        }
        @rmdir(self::$testDir);
    }


    function testAddAndFindSimpleItem() {

        $nav = new SG_Nav();

        $nav->add('foo', 'Foo!');

        $item = $nav->find('/foo');
        $this->assertTrue($item == true, "Item not found!");

        $this->assertEquals('foo', $item->getPath());
        $this->assertEquals('Foo!', $item->getText());

    }

    function testAddSimpleItemDeep() {

        $nav = new SG_Nav();

        $levels = array('some', 'item', 'really', 'deep');
        $nav->add('/' . implode('/', $levels));

        $item = $nav->find('some');
        $this->assertTrue($item !== false, 'some not found');

        $item = $item->find('item');
        $this->assertTrue($item !== false, 'item not found');

        $item = $item->find('really');
        $this->assertTrue($item !== false, 'really not found');

        $item = $item->find('deep');
        $this->assertTrue($item !== false, 'deep not found');

    }

    function testAddAndFindChild() {

        $nav = new SG_Nav();

        $nav
            ->add('parent', 'Parent')
                ->add('child', 'Child');

        $parent = $nav->find('parent');
        $this->assertEquals(1, count($parent->getChildren()), 'parent should have a single child');

        $child = $nav->find('/parent/child');
        $this->assertTrue($child !== false, 'Child not found via /parent/child.');
        $this->assertEquals('child', $child->getPath(), '$childs path is wrong');
        $this->assertEquals('parent/child', $child->getFullPath(), 'childs full path is wrong');

        $child = $parent->find('child');
        $this->assertTrue($child !== false, 'Child not found via $parent->find()');
    }

    function testAddRegexItem() {

        $nav = new SG_Nav();

        $nav->add(array(
            'regex' => '/(?P<id>\d+)(?:-(?P<slug>.+))/',
            'file' => '{slug}.php'
        ));


        $item = $nav->find('/42-some-product');
        $this->assertTrue($item !== false, 'matching item not found');
        $this->assertEquals('some-product.php', $item->getFile());

        $item = $nav->find('/some-product');
        $this->assertFalse($item, 'matching item found when it shouldnt be');
    }

    function testAddRegexItemAsChild() {

        $nav = new SG_Nav();

        $bar = $nav->add('foo/bar');
        $bar->add(array('regex' => '#^(?P<category>\w+)/(?P<id>\d+)#', 'file' => 'whatever.php'));

        $shouldNotExist = $nav->find('category/42');
        $this->assertFalse($shouldNotExist, 'Found deep regex item from root');

        $foo = $nav->find('foo');
        $shouldNotExist = $foo->find('category/42');
        $this->assertFalse($shouldNotExist, 'Found regex item under 1st level');

        $bar = $nav->find('foo/bar');
        $shouldExist = $bar->find('category/42');
        $this->assertTrue($shouldExist !== false, 'Regex not found under 2nd level');
        $this->assertEquals('whatever.php', $shouldExist->getFile());

        $shouldExist = $nav->find('foo/bar/category/42');
        $this->assertTrue($shouldExist !== false, 'Regex not found from root');
        $this->assertEquals('whatever.php', $shouldExist->getFile());

    }

    function testGetArgWithRegexItem() {

        $nav = new SG_Nav();
        $nav->add(array('regex' => '/^(?P<id>\d+)(-(?P<slug>[a-z0-9-]+))?/i'));

        $item = $nav->find('42-some-slug');
        $this->assertEquals(42, $item->getArg('id'));
        $this->assertEquals('some-slug', $item->getArg('slug'));
        $this->assertEquals(null, $item->getArg('missing-arg'));
        $this->assertEquals(99, $item->getArg('problems', 99));

        $item = $nav->find('42');
        $this->assertEquals(42, $item->getArg('id'));
        $this->assertEquals(null, $item->getArg('slug'));

    }


    function xtestAddArray() {


        $nav = new SG_Nav();

        $nav->add(array(

            'home' => array(
                'title' => 'Home',
                'children' => array(
                    'privacy' => array('title' => 'Privacy')
                )
            ),
            'products' => array(
                'title' => 'Our Products'
            )

        ));

        $home = $nav->find('home');
        $this->assertEquals('Home', $home->getTitle());

        $privacy = $nav->find('home/privacy');
        $this->assertEquals('Privacy', $privacy->getTitle());

        $products = $nav->find('products');
        $this->assertEquals('Our Products', $products->getTitle());

    }

    function testAlias() {

        $nav = new SG_Nav();
        $nav->add('home', 'Home');

        $nav->alias('home', '/');

        $home = $nav->find('/');
        $this->assertTrue($home !== false, 'home not found!');
        $this->assertEquals('Home', $home->getText());
    }

    function testDeepOptionSetting() {

        $expected = 'expected value';

        $nav = new SG_Nav();

        $nav->add('one/two/three/four');
        $item = $nav->find('one');
        $item->setOption('boxes', $expected);

        $item = $nav;
        foreach(array('one', 'two', 'three', 'four') as $level) {
            $item = $item->find($level);
            $this->assertEquals($expected, $item->getOption('boxes'), "getOption failed for $level");
        }

        $item = $nav->find('one/two/three');
        $item->setOption('boxes', false);

        $two = $nav->find('one/two');
        $this->assertEquals($expected, $two->getOption('boxes'));

        $this->assertFalse($item->getOption('boxes'));
        $item = $item->find('four');
        $this->assertFalse($item->getOption('boxes'));

        $item = $nav->find('one/two');
        $item->setOption('non_propagating', true, false);

        $three = $item->find('three');
        $this->assertNull($three->getOption('non_propagating'), 'option propagated when it shouldnt have');

        $parent = $nav->add('regex_parent');
        $regexItem = $parent->add(array('regex' => '/^\d+$/'));

        $parent->setOption('foo', 'bar');
        $this->assertEquals('bar', $regexItem->getOption('foo'), 'getOption failed on plain-jane regex item');

        $regexItem = $nav->find('regex_parent/42');
        $this->assertTrue($regexItem !== false, 'regex item not found');
        $this->assertEquals('bar', $regexItem->getOption('foo'), 'getOption failed on regex find result');

        $parent->setOption('answer', 42);
        $this->assertEquals(42, $regexItem->getOption('answer'), 'getOption failed on regex find result generated before setOption called on parent');

    }

    function testAddDirectory() {

        $dir = 'nav_directory';
        $files = array('a.php', 'b.html', 'c.txt');

        @mkdir($dir);
        foreach($files as $f) {
            touch("$dir/$f");
        }

        $nav = new SG_Nav();

        $nav->add(array('directory' => 'nav_directory'));

        $item = $nav->find('nav_directory');
        $this->assertEquals('Nav Directory', $item->getText());

        $children = $item->getChildren();
        foreach($children as $child) {
            $this->assertEquals($dir . '/' . array_shift($files), $child->getFile());
        }

        $nav->alias('nav_directory', '/');
        $item = $nav->find('/');
        $this->assertEquals('Nav Directory', $item->getText());
    }

    function testDirectoryAtRoot() {

        $dir = 'nav_directory';
        $files = array('a.php', 'b.html', 'c.txt');

        @mkdir($dir);
        foreach($files as $f) {
            touch("$dir/$f");
        }

        $nav = new SG_Nav();
        $nav->addRootDirectory($dir);

        $a = $nav->find('a');
        $this->assertTrue($a !== false, 'a not found');

    }

    function testDirectoryIndexFile() {

        $dir = self::$testDir;

        $nav = new SG_Nav();
        $nav->add(array('directory' => $dir));

        $item = $nav->find($dir);
        $this->assertEquals("$dir/index.php", $item->getFile());

    }

    function testRegexWithDirectoryAtRoot() {


        $nav = new SG_Nav();
        $nav->addRootDirectory(self::$testDir);
        $nav->add(array('regex' => '/^(?P<id>\d+)-(?P<slug>[a-z0-9-]+)/i'));

        $file = $nav->find('a');
        $this->assertTrue($file !== false, 'item for a not found');
        $this->assertEquals('A', $file->getText(), 'text is wrong for a');

        $virtual = $nav->find('42-some-slug');
        $this->assertTrue($virtual !== false, 'virtual item not found');
        $this->assertEquals('42-some-slug', $virtual->getPath(), 'path is wrong for virtual item');

    }

    function testDecorateExistingItem() {

        $nav = new SG_Nav();
        $nav->addRootDirectory(self::$testDir);

        $nav->add('a', array('text' => 'test text', 'title' => 'test title', 'test option' => true));

        $item = $nav->find('a');

        $this->assertEquals('test text', $item->getText());
        $this->assertEquals('test title', $item->getTitle());
        $this->assertTrue($item->getOption('test option'));
        $this->assertEquals(self::$testDir . '/a.php', $item->getFile());
    }


}



?>
