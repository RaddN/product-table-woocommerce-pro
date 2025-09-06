<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * add_table.php for the plugin.
 *
 * @package Ultimate Product Table for WooCommerce
 */

class WCProductTab_add_table
{

    private $table_name;
    private $elements_json;
    private $WCProductTab_Tables_Helper;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcproducttab_tables';
        $this->elements_json = json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'elements.json'), true);
        $this->WCProductTab_Tables_Helper = new WCProductTab_Tables_Helper();
    }

    public function admin_page_add_table()
    {
        $edit_id = 0;
        if (isset($_GET['nonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_GET['nonce'])), 'edit_table')) {
            $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        }

        $table_data = null;

        if ($edit_id) {
            global $wpdb;
            $table_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $edit_id));
        }

        // Get WooCommerce product categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));

        // Get WooCommerce product tags
        $tags = get_terms(array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
        ));

        // Get all products for product selection
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        // Check if WooCommerce has products
        $has_products = !empty($products);

        // Parse existing table data
        $existing_data = $table_data ? json_decode($table_data->table_data, true) : null;
        $query_settings = $existing_data['query_settings'] ?? array();
        $existing_layout = $existing_data['layout'] ?? 'table';

?>
        <div class="wrap">
            <h1><?php echo $edit_id ? 'Edit Table' : 'Add New Table'; ?></h1>

            <div class="plugincy-table-builder">
                <form method="post" action="" id="plugincy-table-form">
                    <?php wp_nonce_field('wcproducttab_save_table', 'wcproducttab_nonce'); ?>

                    <!-- Layout Selection -->
                    <div id="plugincy-layout-chooser" class="plugincy-layout-chooser" <?php echo $edit_id ? 'style="display:none;"' : ''; ?>>
                        <h2>Choose a Layout</h2>
                        <p class="description">Pick a layout to start building your product view. You can change it later.</p>
                        <div class="plugincy-layout-grid">
                            <label class="plugincy-layout-card" data-layout="table">
                                <input type="radio" name="layout_choice" value="table">
                                <div class="plugincy-layout-thumb plugincy-layout-thumb--table"></div>
                                <div class="plugincy-layout-title">Table Layout</div>
                                <div class="plugincy-layout-desc">Classic rows & columns.</div>
                            </label>
                            <label class="plugincy-layout-card" data-layout="comparison">
                                <input type="radio" name="layout_choice" value="comparison">
                                <div class="plugincy-layout-thumb plugincy-layout-thumb--comparison"></div>
                                <div class="plugincy-layout-title">Comparison Table</div>
                                <div class="plugincy-layout-desc">Compare products side-by-side.</div>
                            </label>
                            <label class="plugincy-layout-card" data-layout="grid">
                                <input type="radio" name="layout_choice" value="grid">
                                <div class="plugincy-layout-thumb plugincy-layout-thumb--grid"></div>
                                <div class="plugincy-layout-title">Grid Layout</div>
                                <div class="plugincy-layout-desc">Card-based product grid.</div>
                            </label>
                            <label class="plugincy-layout-card" data-layout="list">
                                <input type="radio" name="layout_choice" value="list">
                                <div class="plugincy-layout-thumb plugincy-layout-thumb--list"></div>
                                <div class="plugincy-layout-title">List Layout</div>
                                <div class="plugincy-layout-desc">Compact stacked list.</div>
                            </label>
                            <!-- Coming soon examples -->
                            <div class="plugincy-layout-card plugincy-layout-card--disabled" aria-disabled="true">
                                <div class="plugincy-layout-badge">Coming soon</div>
                                <div class="plugincy-layout-thumb plugincy-layout-thumb--masonry"></div>
                                <div class="plugincy-layout-title">Masonry</div>
                                <div class="plugincy-layout-desc">Dynamic tile layout.</div>
                            </div>
                            <div class="plugincy-layout-card plugincy-layout-card--disabled" aria-disabled="true">
                                <div class="plugincy-layout-badge">Coming soon</div>
                                <div class="plugincy-layout-thumb plugincy-layout-thumb--slider"></div>
                                <div class="plugincy-layout-title">Slider</div>
                                <div class="plugincy-layout-desc">Carousel of products.</div>
                            </div>
                        </div>
                        <div class="plugincy-layout-actions">
                            <button type="button" class="button button-primary" id="plugincy-layout-continue">Continue</button>
                        </div>
                    </div>


                    <div class="plugincy-form-group">
                        <label for="table-title">Table Title</label>
                        <input type="text" id="table-title" name="table_title" value="<?php echo $table_data ? esc_attr($table_data->title) : ''; ?>" required>
                    </div>

                    <!-- Shortcode Display Section -->
                    <div class="plugincy-shortcode-section" <?php echo !$edit_id ? 'style="display:none;"' : ''; ?>>
                        <h3>Shortcode</h3>
                        <div class="plugincy-shortcode-display">
                            <code id="table-shortcode">[wcproducttab_table id="<?php echo esc_attr($edit_id); ?>"]</code>
                            <button type="button" class="button button-small copy-shortcode" data-shortcode="[wcproducttab_table id=&quot;<?php echo esc_attr($edit_id); ?>&quot;]">Copy</button>
                        </div>
                        <p class="description">Use this shortcode to display the table on any page or post.</p>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="plugincy-tabs" id="plugincy-builder-tabs" <?php echo $edit_id ? '' : 'style="display:none;"'; ?>>
                        <nav class="nav-tab-wrapper">
                            <a href="#" class="nav-tab nav-tab-active" data-tab="columns">Columns</a>
                            <a href="#" class="nav-tab" data-tab="query">Query</a>
                            <a href="#" class="nav-tab" data-tab="design">Design</a>
                            <a href="#" class="nav-tab" data-tab="options">Options</a>
                            <a href="#" class="nav-tab" data-tab="search-filter">Search Filter</a>
                            <a href="#" class="nav-tab" data-tab="settings">Settings</a>
                        </nav>

                        <!-- Columns Tab -->
                        <div class="tab-content" id="tab-columns">
                            <div class="plugincy-table-editor">
                                <h3>Table Structure</h3>
                                <p class="description">Design your table layout. The first row defines what product information will be displayed for each product.</p>

                                <div class="plugincy-table-container">
                                    <div class="plugincy-table-controls">
                                        <button type="button" class="button" id="add-column" style="<?php echo !$existing_layout === "comparison" ? 'display:block;' : 'display:none;'; ?>">Add Column</button>
                                        <button type="button" class="button" id="add-row" style="<?php echo $existing_layout === "comparison" ? 'display:block;' : 'display:none;'; ?>">Add Row</button>
                                        <div class="plugincy-editable-cell" data-row="1" data-col="0">
                                            <div class="plugincy-element" style="cursor: pointer;padding: 0;background: transparent;">
                                                <button type="button" class="button plugincy-edit-element" data-type="product_table" id="style-table" title="Edit Table">Customize Table</button>
                                            </div>
                                        </div>
                                        <div class="plugincy-row-info" id="row-info-message" style="display:none;">
                                            <p><strong>Note:</strong> Additional rows will be automatically generated based on your product query settings.</p>
                                        </div>
                                    </div>

                                    <div class="plugincy-table-wrapper">
                                        <table id="plugincy-editable-table" class="plugincy-editable-table">
                                            <thead id="table-header">
                                                <tr>
                                                    <th class="tableactionhead" colspan="5"></th>
                                                    <th class="plugincy-action-column">Actions</th>
                                                </tr>
                                                <tr id="table-head-management">
                                                    <th contenteditable="true" class="plugincy-editable-header">Column 1</th>
                                                    <th contenteditable="true" class="plugincy-editable-header">Column 2</th>
                                                    <th contenteditable="true" class="plugincy-editable-header">Column 3</th>
                                                    <th contenteditable="true" class="plugincy-editable-header">Column 4</th>
                                                    <th contenteditable="true" class="plugincy-editable-header">Column 5</th>
                                                    <th>
                                                        <span class="button"><span class="dashicons dashicons-admin-customizer"></span></span>
                                                        <span class="button"><span class="dashicons dashicons-visibility"></span></span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="table-body">
                                                <tr>
                                                    <td class="plugincy-editable-cell">
                                                        <div class="plugincy-cell-content">
                                                            <div class="plugincy-add-element">+</div>
                                                        </div>
                                                    </td>
                                                    <td class="plugincy-editable-cell">
                                                        <div class="plugincy-cell-content">
                                                            <div class="plugincy-add-element">+</div>
                                                        </div>
                                                    </td>
                                                    <td class="plugincy-editable-cell">
                                                        <div class="plugincy-cell-content">
                                                            <div class="plugincy-add-element">+</div>
                                                        </div>
                                                    </td>
                                                    <td class="plugincy-editable-cell">
                                                        <div class="plugincy-cell-content">
                                                            <div class="plugincy-add-element">+</div>
                                                        </div>
                                                    </td>
                                                    <td class="plugincy-editable-cell">
                                                        <div class="plugincy-cell-content">
                                                            <div class="plugincy-add-element">+</div>
                                                        </div>
                                                    </td>
                                                    <td class="plugincy-action-column">
                                                        <button type="button" class="button button-small delete-row" style="display:none;">Delete</button>
                                                    </td>
                                                </tr>
                                                <tr class="plugincy-preview-loading-row">
                                                    <td colspan="6" class="plugincy-preview-loading" style="text-align: center;">
                                                        <p>Configure your query settings to see product preview.</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot id="table-footer">
                                                <tr>
                                                    <td contenteditable="true" class="plugincy-editable-footer">Footer 1</td>
                                                    <td contenteditable="true" class="plugincy-editable-footer">Footer 2</td>
                                                    <td contenteditable="true" class="plugincy-editable-footer">Footer 3</td>
                                                    <td contenteditable="true" class="plugincy-editable-footer">Footer 4</td>
                                                    <td contenteditable="true" class="plugincy-editable-footer">Footer 5</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="plugincy-preview-controls">
                                        <button type="button" class="button" id="refresh-preview">Refresh Preview</button>
                                        <button type="button" class="button" id="clear-excluded">Clear All Exclusions</button>
                                        <span class="plugincy-excluded-count" id="excluded-count" style="margin-left: 15px;"></span>
                                    </div>
                                    <?php if (!$has_products): ?>
                                        <div class="plugincy-no-products-notice">
                                            <div class="plugincy-no-products-icon">📦</div>
                                            <h3>No Products Found</h3>
                                            <p><strong>WooCommerce products are required to create product tables.</strong></p>
                                            <p>Please add products to your WooCommerce store first.</p>
                                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="button button-primary">Add Your First Product</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Query Tab -->
                        <div class="tab-content" id="tab-query" style="display:none;">
                            <h3>Product Query Settings</h3>
                            <p class="description">Configure which products should be displayed in your table.</p>

                            <div class="plugincy-query-settings">
                                <div class="plugincy-form-group">
                                    <label for="query-type">Query Type</label>
                                    <select id="query-type" name="query_type">
                                        <option value="all" <?php selected($query_settings['query_type'] ?? 'all', 'all'); ?>>All Products</option>
                                        <option value="category" <?php selected($query_settings['query_type'] ?? '', 'category'); ?>>By Category</option>
                                        <option value="tags" <?php selected($query_settings['query_type'] ?? '', 'tags'); ?>>By Tags</option>
                                        <option value="products" <?php selected($query_settings['query_type'] ?? '', 'products'); ?>>Specific Products</option>
                                    </select>
                                    <p class="description">Choose how to filter the products for your table.</p>
                                </div>

                                <!-- Category Selection -->
                                <div class="plugincy-form-group" id="category-selection" style="display:none;">
                                    <label for="selected-categories">Select Categories</label>
                                    <select id="selected-categories" name="selected_categories[]" multiple style="height: 120px;">
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo esc_attr($category->slug); ?>"
                                                <?php echo in_array($category->slug, $query_settings['selected_categories'] ?? []) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($category->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Hold Ctrl (or Cmd) to select multiple categories.</p>
                                </div>

                                <!-- Tags Selection -->
                                <div class="plugincy-form-group" id="tags-selection" style="display:none;">
                                    <label for="selected-tags">Select Tags</label>
                                    <select id="selected-tags" name="selected_tags[]" multiple style="height: 120px;">
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo esc_attr($tag->slug); ?>"
                                                <?php echo in_array($tag->slug, $query_settings['selected_tags'] ?? []) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($tag->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Hold Ctrl (or Cmd) to select multiple tags.</p>
                                </div>

                                <!-- Product Selection -->
                                <div class="plugincy-form-group" id="products-selection" style="display:none;">
                                    <label for="selected-products">Select Products</label>
                                    <select id="selected-products" name="selected_products[]" multiple style="height: 200px;">
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo esc_attr($product->ID); ?>"
                                                <?php echo in_array($product->ID, $query_settings['selected_products'] ?? []) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($product->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Hold Ctrl (or Cmd) to select multiple products.</p>
                                </div>

                                <?php
                                // Parse existing excluded products if editing
                                $excluded_products = isset($existing_data['query_settings']['excluded_products']) ? $existing_data['query_settings']['excluded_products'] : array();
                                ?>
                                <input type="hidden" name="excluded_products" id="excluded-products-input" value="<?php echo esc_attr($excluded_products); ?>">

                                <!-- Products Per Page -->
                                <div class="plugincy-form-group">
                                    <label for="products-per-page">Products Per Page</label>
                                    <input type="number" id="products-per-page" name="products_per_page"
                                        value="<?php echo isset($query_settings['products_per_page']) ? esc_attr($query_settings['products_per_page']) : null; ?>" min="1" max="100">
                                    <p class="description">Maximum number of products to display in the table (1-100).</p>
                                </div>

                                <!-- Order Settings -->
                                <div class="plugincy-form-group">
                                    <label for="order-by">Order By</label>
                                    <select id="order-by" name="order_by">
                                        <option value="date" <?php selected($query_settings['order_by'] ?? 'date', 'date'); ?>>Date Created</option>
                                        <option value="title" <?php selected($query_settings['order_by'] ?? '', 'title'); ?>>Product Title</option>
                                        <option value="menu_order" <?php selected($query_settings['order_by'] ?? '', 'menu_order'); ?>>Menu Order</option>
                                        <option value="rand" <?php selected($query_settings['order_by'] ?? '', 'rand'); ?>>Random</option>
                                        <option value="price" <?php selected($query_settings['order_by'] ?? '', 'price'); ?>>Price</option>
                                        <option value="popularity" <?php selected($query_settings['order_by'] ?? '', 'popularity'); ?>>Popularity</option>
                                        <option value="rating" <?php selected($query_settings['order_by'] ?? '', 'rating'); ?>>Average Rating</option>
                                    </select>
                                </div>

                                <div class="plugincy-form-group">
                                    <label for="order">Order Direction</label>
                                    <select id="order" name="order">
                                        <option value="DESC" <?php selected($query_settings['order'] ?? 'DESC', 'DESC'); ?>>Descending</option>
                                        <option value="ASC" <?php selected($query_settings['order'] ?? '', 'ASC'); ?>>Ascending</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Other tabs remain the same -->
                        <!-- Design Tab -->
                        <div class="tab-content" id="tab-design" style="display:none;">
                            <h3>Design Settings</h3>
                            <p>Design options will be implemented in future updates.</p>
                        </div>

                        <!-- Options Tab -->
                        <div class="tab-content" id="tab-options" style="display:none;">
                            <div class="plugincy-table-settings">
                                <h3>Table Options</h3>
                                <div class="plugincy-settings-row">
                                    <label>
                                        <input type="checkbox" id="show-header" name="show_header" value="1"
                                            <?php checked($existing_data['show_header'] ?? true); ?>> Show Header
                                    </label>
                                    <label>
                                        <input type="checkbox" id="show-footer" name="show_footer" value="1"
                                            <?php checked($existing_data['show_footer'] ?? false); ?>> Show Footer
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Search & Filter Tab -->
                        <div class="tab-content" id="tab-search-filter" style="display:none;">
                            <h3>Search & Filter</h3>
                            <p>Search and filter options will be implemented in future updates.</p>
                        </div>

                        <!-- Settings Tab -->
                        <div class="tab-content" id="tab-settings" style="display:none;">
                            <h3>Additional Settings</h3>
                            <p>Additional settings will be implemented in future updates.</p>
                        </div>
                    </div>

                    <div class="plugincy-form-actions">
                        <button type="submit" name="wcproducttab_save_table" class="button button-primary">
                            <?php echo $edit_id ? 'Update Table' : 'Create Table'; ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=plugincy-tables')); ?>" class="button">Cancel</a>
                    </div>

                    <input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_id); ?>">
                    <input type="hidden" name="table_data" id="table-data-input" value="">
                </form>
            </div>
        </div>

        <!-- Element Selection Modal -->
        <div id="plugincy-element-modal" class="plugincy-modal">
            <div class="plugincy-modal-content">
                <div class="plugincy-modal-header">
                    <h3>Add Element</h3>
                    <span class="plugincy-close">&times;</span>
                </div>
                <div class="plugincy-modal-body">
                    <div class="plugincy-element-options">
                        <?php
                        // Load elements from JSON file
                        $elements = $this->elements_json ?? array();
                        if (empty($elements)) {
                            echo '<p>No elements available. Please check the elements.json file.</p>';
                            return;
                        }
                        foreach ($elements as $element) {
                            $element_type = $element['el_type'];
                            if ($element_type === "product_table") {
                                continue;
                            }
                            $element_label = $element['el_name'];
                            $element_description = $element['el_description'] ?? '';
                        ?>
                            <div class="plugincy-element-option" data-type="<?php echo esc_attr($element_type); ?>">
                                <strong><?php echo esc_html($element_label); ?></strong>
                                <p><?php echo esc_html($element_description); ?></p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($table_data): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    function waitForPlugincy() {
                        if (typeof window.plugincyLoadTableData === 'function') {
                            const tableData = <?php echo $table_data->table_data; ?>;
                            window.plugincyLoadTableData(tableData);
                        } else {
                            setTimeout(waitForPlugincy, 100);
                        }
                    }
                    waitForPlugincy();
                });
            </script>
        <?php endif; ?>
        <script>
            window.__PLUGINCY_EDIT_MODE__ = <?php echo $edit_id ? 'true' : 'false'; ?>;
            window.__PLUGINCY_EXISTING_LAYOUT__ = <?php echo json_encode($existing_layout); ?>;
        </script>
<?php
    }

    // Add this AJAX handler method to the WCProductTab_add_table class

    public function ajax_get_preview_products()
    {
        check_ajax_referer('wcproducttab_nonce', 'nonce');

        $query_settings = isset($_POST['query_settings']) ? json_decode(stripslashes(sanitize_text_field(wp_unslash($_POST['query_settings']))), true) : [];
        $excluded_products = isset($_POST['excluded_products']) ? array_map('intval', $_POST['excluded_products']) : array();
        $table_data = isset($_POST['table_data']) ? json_decode(stripslashes(sanitize_text_field(wp_unslash($_POST['table_data']))), true) : null;
        $count_header = count($table_data["headers"]);
        $products = $this->WCProductTab_Tables_Helper->get_products_for_table([], $query_settings, $excluded_products);

        $html = '';
        $row_count = 1;

        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product) {
                if ($table_data && isset($table_data['rows'])) {
                    $row_template_index = 0 % count($table_data['rows']);
                    $current_row_template = $table_data['rows'][$row_template_index];

                    $html .= '<tr class="plugincy-product-preview-row" data-product-id="' . $product->ID . '" data-row-template="' . $row_template_index . '">';
                    $cellcount = 0;

                    foreach ($current_row_template as $cell) {
                        if ($cellcount > -1 && $cellcount < $count_header) {
                            $html .= '<td>';
                            if (isset($cell['elements']) && !empty($cell['elements'])) {
                                foreach ($cell['elements'] as $element) {
                                    $html .= $this->render_element($element, $wc_product, $row_count);
                                }
                            }
                            $html .= '</td>';
                            $cellcount++;
                        }
                    }

                    $html .= '<td>
                    <span class="button"><span class="dashicons dashicons-admin-customizer"></span></span>
                    <button type="button" class="button button-small remove-product" data-product-id="' . $product->ID . '">Remove</button>
                    </td>';
                } else {
                    // Fallback structure
                    $image = $wc_product->get_image('thumbnail');
                    $html .= '<tr class="plugincy-product-preview-row" data-product-id="' . $product->ID . '">';
                    $html .= '<td>' . $image . '</td>';
                    $html .= '<td>' . esc_html($wc_product->get_name()) . '</td>';
                    $html .= '<td>' . $wc_product->get_price_html() . '</td>';
                    $html .= '<td>' . esc_html($wc_product->get_stock_status()) . '</td>';
                    $html .= '<td><button type="button" class="button button-small remove-product" data-product-id="' . $product->ID . '">Remove</button></td>';
                }
                $html .= '</tr>';
                $row_count++;
            }
        }

        wp_send_json_success($html);
    }

    private function render_element($element, $wc_product, $row_count)
    {
        $output = '';

        $settings = isset($element['settings']) ? $element['settings'] : array();
        $selector = array_keys($settings);
        $styles = '';
        foreach ($selector as $key) {
            // Generate styles from settings
            if ($key !== "content_settings") {
                $styles .= $this->WCProductTab_Tables_Helper->generate_element_styles($settings[$key], $key);
            }
        }

        $output = $this->WCProductTab_Tables_Helper->render_cell_content_html($wc_product, $element, $row_count);

        $output .= '<style>' . esc_html($styles) . '</style>';

        // $output .= json_encode(array(
        //     'type' => $element['type'],
        //     'settings' => $settings,
        //     'row_index' => $row_index
        // ));

        return $output;
    }

    public function save_table()
    {
        global $wpdb;

        check_ajax_referer('wcproducttab_save_table', 'wcproducttab_nonce');

        $title = isset($_POST['table_title']) ? sanitize_text_field(wp_unslash($_POST['table_title'])) : "";
        $table_data = isset($_POST['table_data']) ? stripslashes(sanitize_text_field(wp_unslash($_POST['table_data']))) : [];
        $edit_id = isset($_POST['edit_id']) ? intval(sanitize_text_field(wp_unslash($_POST['edit_id']))) : 0;

        // Save query settings
        $query_settings = array(
            'query_type' => isset($_POST['query_type']) ? sanitize_text_field(wp_unslash($_POST['query_type'])) : "",
            'selected_categories' => isset($_POST['selected_categories']) ? array_map('sanitize_text_field', wp_unslash($_POST['selected_categories'])) : array(),
            'selected_tags' => isset($_POST['selected_tags']) ? array_map('sanitize_text_field', wp_unslash($_POST['selected_tags'])) : array(),
            'selected_products' => isset($_POST['selected_products']) ? array_map('intval', wp_unslash($_POST['selected_products'])) : array(),
            'excluded_products' => isset($_POST['excluded_products'])
                ? array_map('intval', is_array($_POST['excluded_products'])
                    ? $_POST['excluded_products']
                    : array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['excluded_products']))))))
                : array(),
            'products_per_page' => isset($_POST['products_per_page']) ? intval(sanitize_text_field(wp_unslash($_POST['products_per_page']))) : 10,
            'order_by' => isset($_POST['order_by']) ? sanitize_text_field(wp_unslash($_POST['order_by'])) : "date",
            'order' => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : "desc"
        );

        // Combine table data with query settings
        $table_data_array = json_decode($table_data, true);
        if (!$table_data_array) {
            $table_data_array = array();
        }
        $table_data_array['query_settings'] = $query_settings;
        $table_data = json_encode($table_data_array);

        if ($edit_id > 0) {
            $result = $wpdb->update(
                $this->table_name,
                array(
                    'title' => $title,
                    'table_data' => $table_data,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $edit_id)
            );
        } else {
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'title' => $title,
                    'table_data' => $table_data,
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                )
            );
        }

        $nonce = wp_create_nonce('edit_table');
        if ($result !== false) {
            $table_id = $edit_id > 0 ? $edit_id : $wpdb->insert_id;
            $message = $edit_id > 0 ? 'Table updated successfully!' : 'Table created successfully!';
            wp_redirect(admin_url('admin.php?page=plugincy-add-table&edit=' . $table_id . '&nonce=' . $nonce . '&message=' . urlencode($message) . '&type=success'));
            exit;
        } else {
            $message = 'Failed to save table.';
            wp_redirect(admin_url('admin.php?page=plugincy-add-table&nonce=' . $nonce . '&message=' . urlencode($message) . '&type=error'));
            exit;
        }
    }
}
