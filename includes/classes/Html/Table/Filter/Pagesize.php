<?php

/**
 * A "filter" that just changes the number of items on the page.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Table_Filter_Pagesize extends Octopus_Html_Table_Filter {

    protected function createElement() {

        $el = new Octopus_Html_Form_Field_Select('select', $this->id, 'Items Per Page');

        $el->addOption(10);
        $el->addOption(20);
        $el->addOption(50);


        return $el;

    }

    public function apply(Octopus_DataSource $dataSource) {
        $this->table->setPageSize($this->val());
        return $dataSource;
    }

}

