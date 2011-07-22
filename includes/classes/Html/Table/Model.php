<?php

Octopus::loadClass('Html_Element');

class Octopus_Html_Table_Model {

    private $modelType;
    private $columns;
    private $images;
    private $table;

    public function __construct($modelType) {
        $this->modelType = $modelType;
        $this->table = new Octopus_Html_Element('table');
    }

    public function addClass($classname) {
        $this->table->addClass($classname);
    }

    public function addColumn($name, $desc = null, $function = null) {

        if ($desc === null) {
            $desc = humanize($name);
        }

        $this->columns[] = array(
            'name' => $name,
            'desc' => $desc,
            'function' => $function,
            'type' => 'column',
        );
    }

    public function addColumnGroup($name, $desc = null) {

        if ($desc === null) {
            $desc = humanize($name);
        }

        $this->columns[] = array(
            'name' => $name,
            'desc' => $desc,
            'function' => null,
            'type' => 'group',
        );
    }

    public function addImage($group, $images, $key, $desc = null) {
        if ($desc === null) {
            $desc = humanize($name);
        }

        if (!isset($this->images[$group])) {
            $this->images[$group] = array();
        }

        $this->images[$group][] = array(
            'images' => $images,
            'key' => $key,
            'desc' => $desc,
        );

    }

    public function render() {

        $output = '';

        $output .= <<<END
<thead>
<tr>
END;

        foreach ($this->columns as $column) {
            $output .= <<<END
<th>{$column['desc']}</th>

END;
        }

        $output .= '</tr></thead>';

        $output .= $this->renderRows();

        $this->table->setAttribute('cellspacing', 0)->setAttribute('width', '100%');
        $this->table->html($output);

        return $this->table->render(true);

    }

    private function getResults() {

        $class = camel_case($this->modelType, true);
        $model = new $class();
        return $model->_find();
    }

    private function renderRows() {

        $output = '<tbody>';

        $results = $this->getResults();

        foreach ($results as $row) {

            $output .= '<tr>';

            foreach ($this->columns as $column) {
                $output .= '<td>' . $this->processCell($column, $row) . '</td>';
            }

            $output .= '</tr>';

        }

        $output .= '</tbody>';

        return $output;

    }

    private function processCell($column, $row) {

        $value = '';

        if ($column['type'] == 'column') {
            $value = $row[ $column['name'] ];
        } else if ($column['type'] == 'group') {
            $value = $this->renderImages($column, $row);
        }

        if ($column['function'] !== null) {
            $fnc = $column['function'];

            if (is_object($value) && method_exists($value, $fnc)) {
                $value = $value->$fnc($value, $row, $column);
            } else {
                $value = $fnc($value, $row, $column);
            }
        }

        return $value;
    }

    private function renderImages($column, $row) {
        $group = $column['name'];
        if (!isset($this->images[ $group ])) {
            return '';
        }

        foreach ($this->images[ $group ] as $image) {

            $on = $image['images'][1];
            $off = $image['images'][0];

            if ($row[ $image['key'] ]) {
                $on = $image['images'][0];
                $off = $image['images'][1];
            }

            return sprintf('<img src="%s" data-alt-img="%s" class="toggleGeneric cursor" data-table="%s" data-item_id="%s" data-key="%s" data-field="%s" data-on="%s" title="%s" />',
                $on,
                $off,
                $row->getTableName(),
                $row->id,
                $row->getPrimaryKey(),
                $image['key'],
                $row[ $image['key'] ] ? 1 : 0,
                $image['desc']
            );
        }
    }

    public function __toString() {
        return $this->render();
    }

}

