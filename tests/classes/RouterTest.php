<?php

class RouterTest extends PHPUnit_Framework_TestCase {

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

}

?>