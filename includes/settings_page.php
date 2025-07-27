<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functions for the plugin.
 *
 * @package Product Table for WooCommerce
 */

class WCProductTab_settings
{

    public function __construct()
    {
    
    }

    public function admin_page_settings()
    {
?>
        <div class="wrap">
            <h1>Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wcproducttab_settings');
                do_settings_sections('wcproducttab_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Table Style</th>
                        <td>
                            <select name="wcproducttab_default_style">
                                <option value="default">Default</option>
                                <option value="modern">Modern</option>
                                <option value="minimal">Minimal</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Products Per Page</th>
                        <td>
                            <input type="number" name="wcproducttab_products_per_page" value="10" min="1" max="100">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }
}
