<?php

Octopus::loadClass('Octopus_Settings');
Octopus::loadClass('Octopus_DB_Schema');
$DB_VERSIONS = array();

class Octopus_DB_Upgrades {

    function Octopus_DB_Upgrades($dir) {
        $this->settings =& Octopus_Settings::singleton();
        $this->dir = $dir;
    }

    function getGroups() {

        $groups = array();

        foreach (glob($this->dir . '*/install.php') as $file) {

            $nice_name = str_replace($this->dir, '', $file);
            $nice_name = str_replace('/install.php', '', $nice_name);
            $groups[] = $nice_name;

        }

        return $groups;

    }

    function getAvailableUpgrades() {

        $availableGroups = array();
        $groups = $this->getGroups();

        foreach ($groups as $group) {

            $current = $this->getCurrentGroupVersion($group);
            $available = $this->getAvailableGroupVersion($group);

            if ($current > 0 && version_compare($current, $available, '<')) {
                $availableGroups[] = $group;
            }

        }

        return $availableGroups;

    }

    function getAvailableInstalls() {

        $availableGroups = array();
        $groups = $this->getGroups();

        foreach ($groups as $group) {

            $current = $this->getCurrentGroupVersion($group);

            if ($current == 0) {
                $availableGroups[] = $group;
            }

        }

        return $availableGroups;

    }

    function getCurrentGroupVersion($group) {

        $old_db_reporting = db_error_reporting(DB_NONE);

        $key = "_db_version_$group";
        $version = $this->settings->get_setting($key);

        db_error_reporting($old_db_reporting);

        return floatval($version);

    }

    function setCurrentGroupVersion($group, $version) {

        $key = "_db_version_$group";
        $version = $this->settings->set($key, $version);

    }

    function getAvailableGroupVersion($group) {

        $this->loadGroup($group);

        $fnc = 'upgrade_database_' . $group . '_version';
        if (function_exists($fnc)) {
            return $fnc();
        }

        return false;

    }

    function loadGroup($group) {

        $file = $this->dir . $group . '/install.php';
        if (!is_file($file)) {
            return false;
        }

        require_once($file);
        return true;

    }

    function runUpgrade($group) {

        $this->loadGroup($group);
        $fnc = 'upgrade_database_' . $group;

        $previous = $this->getCurrentGroupVersion($group);
        $next = $this->getAvailableGroupVersion($group);

        $fnc($previous);

        $this->setCurrentGroupVersion($group, $next);

    }

}

?>
