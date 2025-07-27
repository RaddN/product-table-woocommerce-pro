<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functions for the plugin.
 *
 * @package Product Table for WooCommerce
 */

class WCProductTab_AllTablesAdmin
{

    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcproducttab_tables';
    }

    public function admin_page_all_tables()
    {
        global $wpdb;

        if (isset($_GET['nonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_GET['nonce'])), 'edit_table')) {
            // Display success/error messages
            if (isset($_GET['message'])) {
                $message = sanitize_text_field(wp_unslash($_GET['message']));
                $type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'success';
                echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }


        $tables = $wpdb->get_results("SELECT * FROM $this->table_name ORDER BY created_at DESC");

?>
        <div class="wrap">
            <h1>Product Table for WooCommerce
                <a href="<?php echo esc_url(admin_url('admin.php?page=plugincy-add-table')); ?>" class="page-title-action">Add New</a>
            </h1>

            <div class="plugincy-tables-list">
                <?php if (empty($tables)): ?>
                    <div class="plugincy-no-tables">
                        <div class="plugincy-no-tables-icon">📊</div>
                        <h2>No tables found</h2>
                        <p>Create your first product table to get started.</p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=plugincy-add-table')); ?>" class="button button-primary">Create Table</a>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Shortcode</th>
                                <th>Created By</th>
                                <th>Created On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($table->title); ?></strong></td>
                                    <td>
                                        <code>[wcproducttab_table id="<?php echo esc_attr($table->id); ?>"]</code>
                                        <button type="button" class="button button-small copy-shortcode" data-shortcode="[wcproducttab_table id=&quot;<?php echo esc_attr($table->id); ?>&quot;]">Copy</button>
                                    </td>
                                    <td><?php echo esc_html(get_userdata($table->created_by)->display_name); ?></td>
                                    <td><?php echo esc_html(gmdate('M j, Y g:i a', strtotime($table->created_at))); ?></td>
                                    <td>
                                        <?php
                                        // Generate a nonce for the edit action
                                        $nonce = wp_create_nonce('edit_table');
                                        ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=plugincy-add-table&edit=' . $table->id . '&nonce=' . $nonce)); ?>" class="button button-small">Edit</a>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=plugincy-tables&action=delete_table&id=' . $table->id.'&nonce=' . $nonce), 'delete_table_' . $table->id)); ?>"
                                            class="button button-small button-link-delete"
                                            onclick="return confirm('Are you sure you want to delete this table?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    public function delete_table()
    {
        global $wpdb;

        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['nonce'])), 'edit_table')) {
            return;
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $result = $wpdb->delete($this->table_name, array('id' => $id));

        if ($result) {
            $message = 'Table deleted successfully!';
            $type = 'success';
        } else {
            $message = 'Failed to delete table.';
            $type = 'error';
        }

        $nonce = wp_create_nonce('edit_table');

        wp_redirect(admin_url('admin.php?page=plugincy-tables&nonce=' . $nonce . '&message=' . urlencode($message) . '&type=' . $type));
        exit;
    }
}
