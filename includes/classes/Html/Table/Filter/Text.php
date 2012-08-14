<?php

/**
 * A filter that is just a text box. Actual filter is done via a callback supplied
 * as an option.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Table_Filter_Text extends Octopus_Html_Table_Filter {


    protected function createElement() {

        $attribs = isset($this->options['attributes']) ? $this->options['attributes'] : null;

        $el = Octopus_Html_Form_Field::create('text', $this->id, $attribs);
        $el->name = $this->id;
        return $el;
    }

    protected function defaultApply(Octopus_DataSource $dataSource) {

        if ($dataSource instanceof Octopus_Model_ResultSet) {

            $field = $dataSource->getModelField($this->id);
            if (!$field) return $dataSource;

            return $dataSource->where(array("$this->id LIKE" => wildcardify($this->val())));

        } else {
            return parent::defaultApply($dataSource);
        }

    }
}

