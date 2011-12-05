<?php

function run_creates() {
    $db = Octopus_DB::singleton();

    $sql = "CREATE TABLE IF NOT EXISTS settings (

                `name` varchar(100) NOT NULL,
                `value` text,
                PRIMARY KEY (`name`)

            );
            ";

    $db->query($sql);

    // FindTest
    $sql = "CREATE TABLE find_posts (
            `find_post_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` varchar ( 255 ) NOT NULL,
            `slug` varchar ( 255 ) NOT NULL,
            `body` text NOT NULL,
            `author_id` INT( 10 ) NULL,
            `active` TINYINT NOT NULL DEFAULT 1,
            `display_order` INT( 10 ) NOT NULL DEFAULT 0,
            `created` DATETIME NOT NULL,
            `updated` DATETIME NOT NULL
            );
            ";

    $db->query($sql);

    $sql = "
            CREATE TABLE find_authors (
            `find_author_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar ( 255 ) NOT NULL,
            `active` TINYINT NOT NULL DEFAULT 1
            )
            ";

    $db->query($sql);

    $sql = "
            CREATE TABLE find_categories (
                `find_category_id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar(255) NOT NULL
            )
    ";
    $db->query($sql);

    $sql = "

            CREATE TABLE find_category_find_post_join (
                `find_post_id` int not null,
                `find_category_id` int not null
            )

    ";
    $db->query($sql);

    // MinCrudTest
    $sql = "CREATE TABLE minposts (
            `minpost_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` varchar ( 255 ) NOT NULL,
            `slug` varchar ( 255 ) NOT NULL,
            `body` text NOT NULL,
            `active` TINYINT NOT NULL,
            `display_order` INT( 10 ) NOT NULL,
            `created` DATETIME NOT NULL,
            `updated` DATETIME NOT NULL,
            `cost` DECIMAL (6, 2) NOT NULL
            )
            ";

    $db->query($sql);

    // HeirarchyTest
    $sql = "CREATE TABLE users (
            `user_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);

    $sql = "CREATE TABLE containers (
            `container_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT( 10 ) NOT NULL,
            `name` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);

    $sql = "CREATE TABLE things (
            `thing_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `container_id` INT( 10 ) NOT NULL,
            `name` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);

    // Escape and more
    Octopus_DB_Schema_Model::makeTable('product');
    Octopus_DB_Schema_Model::makeTable('group');

    Octopus_DB_Schema_Model::makeTable('hammer');
    Octopus_DB_Schema_Model::makeTable('nail');
    Octopus_DB_Schema_Model::makeTable('sledgehammer');
    Octopus_DB_Schema_Model::makeTable('lack');
    Octopus_DB_Schema_Model::makeTable('notext');

    Octopus_DB_Schema_Model::makeTable('Comment');
    Octopus_DB_Schema_Model::makeTable('CommentUser');
    Octopus_DB_Schema_Model::makeTable('Boat');
    Octopus_DB_Schema_Model::makeTable('Car');

    Octopus_DB_Schema_Model::makeTable('Schemab');
    Octopus_DB_Schema_Model::makeTable('Schemac');
    Octopus_DB_Schema_Model::makeTable('Schemad');

    Octopus_DB_Schema_Model::makeTable('HtmlTablePerson');

    // NonStandard
    $sql = "CREATE TABLE IF NOT EXISTS nonstandard (
            `different_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` varchar ( 255 ) NOT NULL,
            `created` DATETIME NOT NULL,
            `updated` DATETIME NOT NULL
            )
            ";

    $db->query($sql);

    $sql = "CREATE TABLE IF NOT EXISTS differentbs (
            `different_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` varchar ( 255 ) NOT NULL,
            `created` DATETIME NOT NULL,
            `updated` DATETIME NOT NULL
            )
            ";

    $db->query($sql);

    $sql = "CREATE TABLE IF NOT EXISTS randomtable (
            `foobar` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` varchar ( 255 ) NOT NULL,
            `created` DATETIME NOT NULL,
            `updated` DATETIME NOT NULL
            )
            ";

    $db->query($sql);

    $sql = "CREATE TABLE IF NOT EXISTS differentds (
            `differentd_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `kazi` varchar ( 255 ) NOT NULL,
            `created` DATETIME NOT NULL,
            `updated` DATETIME NOT NULL,
            `extra` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);


    $sql = "CREATE TABLE IF NOT EXISTS categories (
            `category_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);

    // RelationFilter
    $sql = "CREATE TABLE comments (
            `comment_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `content` varchar ( 255 ) NOT NULL,
            `item_type` varchar ( 255 ) NOT NULL,
            `item_id` INT( 10 ) NOT NULL
            )
            ";

    $db->query($sql);

    $sql = "CREATE TABLE cars (
            `car_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);

    $sql = "CREATE TABLE boats (
            `boat_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);

    // Trigger
    $sql = "CREATE TABLE triggers (
            `trigger_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` varchar ( 255 ) NOT NULL,
            `a` varchar ( 255 ) NOT NULL,
            `b` varchar ( 255 ) NOT NULL,
            `c` varchar ( 255 ) NOT NULL,
            `d` varchar ( 255 ) NOT NULL,
            `e` varchar ( 255 ) NOT NULL
            )
            ";

    $db->query($sql);

}

function run_drops() {
    $db = Octopus_DB::singleton();
    $db->query("DROP TABLE IF EXISTS find_category_find_post_join");
    $db->query("DROP TABLE IF EXISTS find_posts");
    $db->query("DROP TABLE IF EXISTS find_authors");
    $db->query("DROP TABLE IF EXISTS find_categories");

    $db->query('DROP TABLE IF EXISTS minposts');

    $db->query('DROP TABLE IF EXISTS users');
    $db->query('DROP TABLE IF EXISTS containers');
    $db->query('DROP TABLE IF EXISTS things');

    $db->query('DROP TABLE IF EXISTS groups');
    $db->query('DROP TABLE IF EXISTS products');
    $db->query('DROP TABLE IF EXISTS group_product_join');

    $db->query('DROP TABLE IF EXISTS hammers');
    $db->query('DROP TABLE IF EXISTS nails');
    $db->query('DROP TABLE IF EXISTS sledgehammers');

    $db->query('DROP TABLE IF EXISTS nonstandard');
    $db->query('DROP TABLE IF EXISTS differentbs');
    $db->query('DROP TABLE IF EXISTS randomtable');
    $db->query('DROP TABLE IF EXISTS differentds');
    $db->query('DROP TABLE IF EXISTS categories');
    $db->query('DROP TABLE IF EXISTS lacks');
    $db->query('DROP TABLE IF EXISTS notexts');

    $db->query('DROP TABLE IF EXISTS comments');
    $db->query('DROP TABLE IF EXISTS cars');
    $db->query('DROP TABLE IF EXISTS boats');

    $db->query('DROP TABLE IF EXISTS triggers');

    $db->query('DROP TABLE IF EXISTS schemabs');
    $db->query('DROP TABLE IF EXISTS schemacs');
    $db->query('DROP TABLE IF EXISTS schemads');

    $db->query('DROP TABLE IF EXISTS html_table_persons');

}
