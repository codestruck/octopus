<?php

/**
 * A "filter" that just changes the number of items on the page.
 */
class Octopus_Html_Table_Filter_PageSize extends Octopus_Html_Table_Filter {

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

?>