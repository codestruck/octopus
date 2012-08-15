<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Table_Filter_Pager extends Octopus_Html_Table_Filter {

    protected function createElement() {
        return $this->table->createPagerElement();
    }

}

