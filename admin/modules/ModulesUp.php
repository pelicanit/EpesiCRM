<?php

class ModulesUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Update load priority array";
    }

    public function header() {
        return 'Update load priority array';
    }

    public function action() {
        Cache::clear();
        ModuleManager::create_load_priority_array();
        return true;
    }

    public function start_text() {
        return '<H2>This utility will rebuild load priority array.</H2><br/><div>After clicking Next button please wait...</div>';
    }

    public function success_text() {
        $text = '<H2>Load priority array was successfully updated.</H2>';
        return $text;
    }

    public function failure_text() {
        return '';
    }

}

?>