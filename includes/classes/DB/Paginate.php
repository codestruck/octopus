<?php

Octopus::loadClass('Octopus_Query_Arguments');
Octopus::loadClass('Octopus_DB_Select');

class Octopus_DB_Paginate {

    function Octopus_DB_Paginate() {

        $this->db =& Octopus_DB::singleton();

        $this->info = array(
                               'results_per_page' => 10,
                               'current_page' => 1,
                               );
    }

    function _buildLimit($sql) {

        $start = ($this->info['current_page'] - 1) * $this->info['results_per_page'];
        //$start++;

        $sql = sprintf("%s LIMIT %s, %s", $sql, $start, $this->info['results_per_page']);

        return $sql;

    }

    function query($sql, $sqlArgs = array()) {

        $s = new Octopus_DB_Select($sql, $sqlArgs);
        $sql = $s->getSql();
        $countSql = preg_replace('/select[ ]+\*[ ]+from/i', 'SELECT COUNT(*) FROM', $sql);

        if ($sql != $countSql) {
            $s = new Octopus_DB_Select($countSql);
            $this->info['num_rows'] = $s->getOne();
        } else {
            $query = $s->query();
            $this->info['num_rows'] = $query->numRows();
        }

        $sql = $this->_buildLimit($sql);

        $s = new Octopus_DB_Select($sql, $sqlArgs);
        $query = $s->query();

        return $query;

    }

    function getInfo() {

        $this->info['num_pages'] = ceil($this->info['num_rows'] / $this->info['results_per_page']);

        // calculate prev page
        if ($this->info['current_page'] != 1) {
            $this->info['prev'] = $this->info['current_page'] - 1;
        } else {
            $this->info['prev'] = 0;
        }

        // calculate next page
        if ($this->info['current_page'] != $this->info['num_pages']) {
            $this->info['next'] = $this->info['current_page'] + 1;
        } else {
            $this->info['next'] = 0;
        }

        if (isset($this->info['base_url_args'])) {

            $this->info['base_url_args'] = stripslashes($this->info['base_url_args']);
            $this->info['base_url_args'] = htmlentities($this->info['base_url_args']);
            //$this->info['base_url_args'] = str_replace('"', '&quot;', $this->info['base_url_args']);

            $this->info['original_base_url_args'] = $this->info['base_url_args'];
            $query_args = new Octopus_Query_Arguments($this->info['base_url_args']);
            $query_args->remove('page');
            $this->info['base_url_args'] = $query_args->toString();

            $query_args = new Octopus_Query_Arguments($this->info['base_url_args']);
            $query_args->remove('page');
            $query_args->remove('orderBy');
            $query_args->remove('orderDir');
            $this->info['orderby_base_url'] = $query_args->toString();
        }

        if ($this->info['num_pages'] > 10) {

            $pages = array();

            if ($this->info['current_page'] <= 5) {

                $max = min($this->info['current_page'] + 2, 6);

                for ($i = 1; $i <= $max; ++$i) {
                    $pages[] = $i;
                }

                $pages[] = 'SPACE';
                $pages[] = $this->info['num_pages'] - 1;
                $pages[] = $this->info['num_pages'];

            } else if ($this->info['current_page'] > $this->info['num_pages'] - 5) {

                $pages[] = 1;
                $pages[] = 2;

                $pages[] = 'SPACE';

                $pages[] = $this->info['current_page'] - 2;
                $pages[] = $this->info['current_page'] - 1;
                $pages[] = $this->info['current_page'];

                for ($i = 1; $this->info['current_page'] + $i <= $this->info['num_pages']; ++$i) {
                    $pages[] = $this->info['current_page'] + $i;
                }

            } else {
                //middle

                $pages[] = 1;
                $pages[] = 2;

                $pages[] = 'SPACE';

                $pages[] = $this->info['current_page'] - 2;
                $pages[] = $this->info['current_page'] - 1;
                $pages[] = $this->info['current_page'];
                $pages[] = $this->info['current_page'] + 1;
                $pages[] = $this->info['current_page'] + 2;
                $pages[] = 'SPACE';
                $pages[] = $this->info['num_pages'] - 1;
                $pages[] = $this->info['num_pages'];
            }


        } else {
            $pages = range(1, $this->info['num_pages']);
        }

        $this->info['pages'] = $pages;

        return $this->info;

    }

    function set($key, $value) {
        $this->info[$key] = $value;
    }

}

?>
