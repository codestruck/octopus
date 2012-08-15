<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class RouterTest extends PHPUnit_Framework_TestCase {

    function testOverride() {

        $r = new Octopus_Router();
        $r->alias('/', '/test');
        $r->alias('/', '/something');
        $this->assertEquals('/something', $r->resolve('/'));

    }

    function testSimpleAlias() {

        $r = new Octopus_Router();
        $r->alias('/shortpath', '/some/long/path');

        $this->assertEquals('/some/long/path', $r->resolve('/shortpath'));
    }

    function testRegexAlias() {

        $r = new Octopus_Router();

        $r->alias(
            '/{$id}',
            '/products/view/{$id}',
            array(
                'id' => '[0-9]+'
            )
        );

        $this->assertEquals('/products/view/50', $r->resolve('/50'), 'simple resolution');
        $this->assertEquals('/whatever', $r->resolve('/whatever'));
        $this->assertEquals('/40d', $r->resolve('/40d'));
    }

    function testComplexRegexAlias() {

        $r = new Octopus_Router();

        $r->alias(
            '/{$id}/{$slug}',
            '/products/view/{$id}/{$slug}',
            array(
                'id' => '\d+',
                'slug' => '[a-z0-9-]+'
            )
        );

        $this->assertEquals('/products/view/50/Some-Fun-Slug-42', $r->resolve('/50/Some-Fun-Slug-42'));

    }

    function testInlineRegexPattern() {

        $r = new Octopus_Router();
        $r->alias('/{(?<id>\d+)}', '/products/view/{$id}');

        $this->assertEquals('/products/view/50', $r->resolve('/50'));

    }

    function testRouteArgsToAction() {

        $r = new Octopus_Router();
        $r->alias('/{$id}', '/products/view/{$id}', array('id' => '\d+'));

        $this->assertEquals('/products/view/50/detailed', $r->resolve('/50/detailed'));

    }

    function testRootAlias() {

        $r = new Octopus_Router();
        $r->alias('/', '/home');

        $this->assertEquals('/home', $r->resolve('/'));
        $this->assertEquals('/products', $r->resolve('/products'));

    }

    function testFavorMostRecentlyAddedRoute() {

        $r = new Octopus_Router();
        $r->alias('/offers', '/cagi/offers');
        $r->alias('/offers/cancel/{$id}', '/cagi/cancel-offer/{$id}', array('id' => '\d+'));
        $this->assertEquals('/cagi/cancel-offer/6', $r->resolve('/offers/cancel/6'));

    }

}
