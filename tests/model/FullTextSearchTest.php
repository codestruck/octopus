<?php

class FullTextSearchUser extends Octopus_Model {

	protected $fields = array(
		'name' => array('search' => true),
		'email' => array('search' => 2.0),
		'bio' => array('search' => array('weight' => 1.1)),
		'favorite_quote',
	);

	protected $search = array(
		'favorite_quote' => 9
	);

}

class FullTextSearchMessage extends Octopus_Model {

	protected $fields = array(

		'from' => array('type' => 'hasOne', 'model' => 'FullTextSearchUser', 'cascade' => false,),
		'to' => array('type' => 'hasOne', 'model' => 'FullTextSearchUser', 'cascade' => false,),
		'subject',
		'body' => array('type' => 'text', 'size' => PHP_INT_MAX),
		'created',
		'updated',
		'active',

	);

	protected $search = array(
		'from' => array(
			'weight' => 1.1
		),
		'subject' => 1.5,
		'body',
	);

}



class FullTextSearchTest extends Octopus_App_TestCase {

	function setUp() {

		parent::setUp();

		Octopus_DB_Schema_Model::makeTable('FullTextSearchUser');
		Octopus_DB_Schema_Model::makeTable('FullTextSearchMessage');

		$db = Octopus_DB::singleton();
		$db->query('TRUNCATE TABLE full_text_search_users;');
		$db->query('TRUNCATE TABLE full_text_search_messages;');

		$joe = new FullTextSearchUser(array('name' => 'Joe Blow', 'email' => 'joeblow@test.com'));
		$joe->save();

		$jane = new FullTextSearchUser(array('name' => 'Jane Doe', 'email' => 'janedoe@test.com'));
		$jane->save();

		$message1 = new FullTextSearchMessage(array(
			'subject' => 'You may already be a winner foo bar baz bat!',
			'body' => <<<END
We left in pretty good time, and came after nightfall to Klausenburgh.
Here I stopped for the night at the Hotel Royale.  I had for dinner,
or rather supper, a chicken done up some way with red pepper, which
was very good but thirsty.  (Mem. get recipe for Mina.) I asked the
waiter, and he said it was called "paprika hendl," and that, as it was
a national dish, I should be able to get it anywhere along the
Carpathians.

I found my smattering of German very useful here, indeed, I don't know
how I should be able to get on without it.
END
			,
			'from' => $jane,
			'to' => $joe,
		));
		$message1->save();

		$message2 = new FullTextSearchMessage(array(
			'subject' => 'LOSE INCHES IN HOUR$!',
			'body' => <<<END
Yet do not suppose, because I complain a little or because I can
conceive a consolation for my toils which I may never know, that I am
wavering in my resolutions.  Those are as fixed as fate, and my voyage
is only now delayed until the weather shall permit my embarkation.  The
winter has been dreadfully severe, but the spring promises well, and it
is considered as a remarkably early season, so that perhaps I may sail
sooner than I expected.  I shall do nothing rashly:  you know me
sufficiently to confide in my prudence and considerateness whenever the
safety of others is committed to my care. Foo bar baz bat.
END
			,
			'from' => $joe,
			'to' => $jane,
		));
		$message2->save();

		$message3 = new FullTextSearchMessage(array(
			'subject' => 'Foo bar baz bat',
			'body' => <<<END
I lay back against the cushions, puffing at my cigar, while Holmes,
leaning forward, with his long, thin forefinger checking off the points
upon the palm of his left hand, gave me a sketch of the events which had
led to our journey.
END
			,
			'from' => $joe,
			'to' => $jane,
		));
		$message3->save();


	}

	function testReadWeightsFromSearchVar() {

		$fields = FullTextSearchMessage::__getSearchFields();
		foreach($fields as &$f) {
			$f['field'] = $f['field']->getFieldName();
		}
		unset($f);

		$this->assertEquals(
			array(
				'from' => array('field' => 'from', 'weight' => 1.1),
				'subject' => array('field' => 'subject', 'weight' => 1.5),
				'body' => array('field' => 'body', 'weight' => 1),
			),
			$fields
		);

	}

	function testReadWeightsFromSearchVarAndFieldDefinitions() {

		$fields = FullTextSearchUser::__getSearchFields();
		foreach($fields as &$f) {
			$f['field'] = $f['field']->getFieldName();
		}
		unset($f);

		$this->assertEquals(
			array(
				'name' => array('field' => 'name', 'weight' => 1),
				'email' => array('field' => 'email', 'weight' => 2),
				'bio' => array('field' => 'bio', 'weight' => 1.1),
				'favorite_quote' => array('field' => 'favorite_quote', 'weight' => 9),
			),
			$fields
		);


	}

	/**
	 * @dataProvider provideQueries
	 */
	function testQueryParsing($desc, $input, $expected) {

        $matcher = new Octopus_Model_FullTextMatcher_PHP();

		$this->assertEquals(
			$expected ? explode(' ', $expected) : array(),
			$matcher->tokenize($input),
			"$desc: $input"
		);

	}

	function provideQueries() {

		return array(

			array('', '', ''),
			array('white space', "\t\n\t\t\r", ''),
			array('basic stop words', "i the of for and that by as a about me ever each", ''),
			array('', "i'm a bit tired", "bit tired"),
			array('', "im a bit tired", "bit tired"),
			array('', "i've got a question", 'question'),
			array('quoted literals', '"i\'ve" got a question', "i've question"),
			array('', "repeat repeat repeat", "repeat repeat repeat"),
			array('split on white space', "foo\t\rbar", 'foo bar'),
			array('', 'ignore, most; punctuation. including: (parentheses)', 'ignore most punctuation including parentheses'),


		);

	}

	function testBasicMatching() {

		$messages = FullTextSearchMessage::all()->matching('permit my embarkation');
		$this->assertEquals(1, count($messages), 'correct # of messages found');
		$message = $messages->first();
		$this->assertTrue(!!$message, 'message found');
		$this->assertEquals(2, $message->id, 'correct message found');

	}

	function testWeighting() {

        // Test that name (weighted 1) sorts below bio (weighted 1.1)

        $name = new FullTextSearchUser(array('name' => 'foo bar', 'bio' => 'blerg'));
        $bio = new FullTextSearchUser(array('name' => 'blerg', 'bio' => 'foo bar'));

        $name->save();
        $bio->save();

        $this->assertEquals(
            array($bio->id, $name->id),
            FullTextSearchUser::all()->matching('foo bar')->map('id')
        );

	}

	function testFilterAfterFullTextMatch() {

		$messages = FullTextSearchMessage::all()
			->matching('foo bar baz bat')
			->where('from', 1);


		$this->assertEquals(2, count($messages));
		$this->assertEquals(array(3, 2), $messages->map('id'));

	}

    function testSearchRelatedModels() {

        $message = FullTextSearchMessage::all()->first();

        $from = $message->from;
        $from->favorite_quote = "bawidababa";
        $from->save();

        $messages = FullTextSearchMessage::all()->matching('bawidababa');
        $this->assertEquals(array($message->id), $messages->map('id'), 'searched on related model field');



    }

}