<?php

/**
 * Plugin Name: Ultimate Product Table for WooCommerce
 * Plugin URI:  https://plugincy.com/ultimate-product-table-for-woocommerce/
 * Description: Create custom WooCommerce product tables with shortcodes
 * Version: 1.0.1
 * Author: Plugincy
 * Author URI: https://plugincy.com
 * license: GPL2
 * Text Domain: ultimate-product-table-for-woocommerce
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if( ! defined( 'WCProductTab_BASE_URL' ) ){
    define( "WCProductTab_BASE_URL", plugin_dir_url(__FILE__) );
}

require_once plugin_dir_path(__FILE__) . 'includes/db.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/all_tables.php';
require_once plugin_dir_path(__FILE__) . 'includes/add_table.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings_page.php';
require_once plugin_dir_path(__FILE__) . 'includes/class_helper.php';

class WCProductTab_INIT
{

    private $table_name;
    private $WCProductTab_TablesDB;
    private $WCProductTab_TablesAdmin;
    private $WCProductTab_add_table;
    private $WCProductTab_AllTablesAdmin;
    private $WCProductTab_Tables_Helper;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcproducttab_tables';
        $this->WCProductTab_TablesDB = new WCProductTab_TablesDB();
        $this->WCProductTab_TablesAdmin = new WCProductTab_TablesAdmin();
        $this->WCProductTab_add_table = new WCProductTab_add_table();
        $this->WCProductTab_AllTablesAdmin = new WCProductTab_AllTablesAdmin();
        $this->WCProductTab_Tables_Helper = new WCProductTab_Tables_Helper();


        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this->WCProductTab_TablesAdmin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_shortcode('wcproducttab_table', array($this->WCProductTab_Tables_Helper, 'render_table_shortcode'));

        add_action('wp_ajax_wcproducttab_get_preview_products', array($this->WCProductTab_add_table, 'ajax_get_preview_products'));

        register_activation_hook(__FILE__, array($this->WCProductTab_TablesDB, 'create_table'));
    }

    public function init()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>Ultimate Product Table for WooCommerce requires WooCommerce to be installed and activated.</p></div>';
            });
            return;
        }
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'plugincy') !== false) {
            wp_enqueue_script('plugincy-admin-js', plugin_dir_url(__FILE__) . 'assets/admin.js', array('jquery'), '1.0.1', true);
            wp_enqueue_style('plugincy-admin-css', plugin_dir_url(__FILE__) . 'assets/admin.css', array(), '1.0.1');

            // Localize script to pass data to JavaScript
            wp_localize_script('plugincy-admin-js', 'wcproducttab_ajax ', array(
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('wcproducttab_nonce'),
                'elements_json' => json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'includes/elements.json'), true)
            ));
        }
    }


    public function enqueue_frontend_scripts()
    {
        wp_enqueue_style('plugincy-frontend-css', plugin_dir_url(__FILE__) . 'assets/frontend.css', array(), '1.0.1');
    }

    public function handle_form_submissions()
    {
        // Handle table creation/update
        if (isset($_POST['wcproducttab_save_table']) && isset($_POST['wcproducttab_nonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['wcproducttab_nonce'])), 'wcproducttab_save_table')) {
            $this->WCProductTab_add_table->save_table();
        }

        // Handle table deletion
        if (isset($_GET['action']) && isset($_GET['_wpnonce']) && $_GET['action'] === 'delete_table' && isset($_GET['id']) && wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'])), 'delete_table_' . sanitize_text_field(wp_unslash($_GET['id'])))) {
            $this->WCProductTab_AllTablesAdmin->delete_table();
        }
    }
}

new WCProductTab_INIT();
