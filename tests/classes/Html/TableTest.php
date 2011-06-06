<?php

Octopus::loadClass('Octopus_Html_TestCase');
Octopus::loadClass('Octopus_Html_Table');

class TableTest extends Octopus_Html_TestCase {

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

        $table->addColumn('name');
        $table->addColumn('age');
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
                <a href="/toggle/active/1" class="toggle active toggleActive">Active</a>
                <a href="/edit/1" class="action edit">Edit</a>
                <a href="/delete/1" class="action delete">Delete</a>
            </td>
        </tr>
        <tr class="even">
            <td class="name firstCell">Joe Smith</td>
            <td class="age">99</td>
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
