<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class RestrictionTest extends PHPUnit_Framework_TestCase {

    function testLooksLikeFieldExpression() {

        $tests = array(
            '' => false,
            'title' => true,
            'title not' => true,
            'title not like' => true,
            'title in (select * from whatever)' => false,
            'title !=' => true,
            'author.name LIKE' => true
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, Octopus_Model_Restriction_Field::looksLikeFieldExpression($input), $input);
        }

    }

}
