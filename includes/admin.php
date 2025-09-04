<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functions for the plugin.
 *
 * @package Ultimate Product Table for WooCommerce
 */

class WCProductTab_TablesAdmin {

    private $WCProductTab_AllTablesAdmin;
    private $WCProductTab_add_table;
    private $WCProductTab_settings;

    public function __construct() {
        $this->WCProductTab_AllTablesAdmin = new WCProductTab_AllTablesAdmin();
        $this->WCProductTab_add_table = new WCProductTab_add_table();
        $this->WCProductTab_settings = new WCProductTab_settings();
    }

     public function add_admin_menu()
    {
        add_menu_page(
            'Ultimate Table',
            'Ultimate Table',
            'manage_options',
            'plugincy-tables',
            array($this->WCProductTab_AllTablesAdmin, 'render_page'),
            WCProductTab_BASE_URL . 'assets/images/table.png',
            25
        );

        add_submenu_page(
            'plugincy-tables',
            'All Tables',
            'All Tables',
            'manage_options',
            'plugincy-tables',
            array($this->WCProductTab_AllTablesAdmin, 'render_page')
        );

        add_submenu_page(
            'plugincy-tables',
            'Add New Table',
            'Add New Table',
            'manage_options',
            'plugincy-add-table',
            array($this->WCProductTab_add_table, 'admin_page_add_table')
        );

        add_submenu_page(
            'plugincy-tables',
            'Settings',
            'Settings',
            'manage_options',
            'plugincy-settings',
            array($this->WCProductTab_settings, 'admin_page_settings')
        );
    }
}

