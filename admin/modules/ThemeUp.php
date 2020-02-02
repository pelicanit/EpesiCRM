<?php

class ThemeUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Theme Updater Utility";
    }

    public function required_epesi_modules() {
        return array('Base_Theme');
    }

    public function header() {
        return '<H1>Theme Updater Utility</H1>';
    }

    public function action() {
        set_time_limit(0);
        Cache::clear();
        ModuleManager::create_common_cache();
        Base_ThemeCommon::themeup();
        return true;
    }

    public function start_text() {
        return '<H2>This utility will rebuild Theme Cache files.</H2><br/>'
                . 'After clicking Next button please wait...<br/>'
                . 'Rebuilding theme files may take a while.</center>';
    }

    public function success_text() {
        $text = '<H2><strong>Theme templates cache was successfully updated.</H2>'; 
        return $text;
    }

    public function failure_text() {
        return 'Failure';
    }

}

?>