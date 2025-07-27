<?php
/**
 * Database connection and query functions.
 *
 * @package Product Table for WooCommerce
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCProductTab_TablesDB {

    private $table_name;

    public function __construct() {
         global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcproducttab_tables';

    }

    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            table_data longtext NOT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

}