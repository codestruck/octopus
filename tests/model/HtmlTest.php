<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

function custom_function($model, $field) {
    return 'from custom function';
}

class Html extends Octopus_Model {
    protected $fields = array(
        'body1' => array(
            'type' => 'html',
        ),
        'body2' => array(
            'type' => 'html',
            'escape' => false,
        ),
        'body3' => array(
            'type' => 'html',
            'escape' => 'custom_method',
        ),
        'body4' => array(
            'type' => 'html',
            'escape' => 'custom_function',
        ),
        'tag1' => array(
            'type' => 'html',
            'allow_tags' => 'p',
        ),
        'tag2' => array(
            'type' => 'html',
            'allow_tags' => 'a',
        ),
        'tag3' => array(
            'type' => 'html',
            'allow_tags' => 'strong,a[href]',
        ),
     
        
    );
    
    public function custom_method($model, $field) {
        return 'from custom method';
    }
    
}

/**
 * @group Model
 */
class ModelHtmlTest extends Octopus_App_TestCase {
    
    function __construct() {
        Octopus_DB_Schema_Model::makeTable('html');
    }

    function setUp() {

        parent::setUp();

        $db =& Octopus_DB::singleton();
        $db->query('TRUNCATE htmls');
        
        $str = '<p>some <strong>strings</strong> have <a href="http://cnn.com/" class="linkClass">links</a>.</p>';
        $this->rawString = $str;
        
        $html = new Html();
        $html->body1 = $str;
        $html->body2 = $str;
        $html->body3 = $str;
        $html->body4 = $str;
        $html->tag1 = $str;
        $html->tag2 = $str;
        $html->tag3 = $str;
        $html->save();
        
    }

    function testDefault() {
        $html = new Html(1);
        $html->escape();
        $this->assertEquals(h($this->rawString), $html->body1);
    }

    function testEscapeFalse() {
        $html = new Html(1);
        $html->escape();
        $this->assertEquals($this->rawString, $html->body2);
    }

    function testCustomMethod() {
        $html = new Html(1);
        $html->escape();
        $this->assertEquals('from custom method', $html->body3);
    }

    function testCustomFunction() {
        $html = new Html(1);
        $html->escape();
        $this->assertEquals('from custom function', $html->body4);
    }
    
    function testHtmlTagP() {
        $html = new Html(1);
        $html->escape();
        $this->assertEquals('<p>some strings have links.</p>', $html->tag1);
    }

    function testHtmlTag2() {
        $html = new Html(1);
        $html->escape();
        $this->assertEquals('some strings have <a>links</a>.', $html->tag2);
    }

    function testHtmlTag3() {
        $html = new Html(1);
        $html->escape();
        $this->assertEquals('some <strong>strings</strong> have <a href="http://cnn.com/">links</a>.', $html->tag3);
    }

}
