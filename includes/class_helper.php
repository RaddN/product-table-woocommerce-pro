<?php

/**
 * class_helper.php
 *
 * @package Ultimate Product Table for WooCommerce
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCProductTab_Tables_Helper
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcproducttab_tables';
    }

    public function render_table_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
            'products' => '',
            'category' => '',
            'limit' => 10
        ), $atts);

        if (!$atts['id']) {
            return '<p>Please provide a table ID.</p>';
        }

        global $wpdb;
        $table = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $atts['id']));

        if (!$table) {
            return '<p>Table not found.</p>';
        }

        $table_data = json_decode($table->table_data, true);

        $products = $this->get_products_for_table($atts);

        if (empty($products)) {
            return '<p>No products found.</p>';
        }

        return $this->generate_table_html($table_data, $products);
    }

    public function get_products_for_table($atts = [], $query_settings = [], $excluded_products = [])
    {
        if (empty($query_settings)) {

            global $wpdb;

            // Get table data to access query settings
            $table = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $atts['id']));

            if (!$table) {
                return array();
            }

            $table_data = json_decode($table->table_data, true);
            $query_settings = isset($table_data['query_settings']) ? $table_data['query_settings'] : array();

            if (empty($excluded_products)) {
                $excluded_products = isset($query_settings['excluded_products']) ? $query_settings['excluded_products'] : [];
            }
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => isset($query_settings['products_per_page']) ? intval($query_settings['products_per_page']) : 10,
            'orderby' => isset($query_settings['order_by']) ? $query_settings['order_by'] : 'date',
            'order' => isset($query_settings['order']) ? $query_settings['order'] : 'DESC'
        );

        // Exclude products
        if (!empty($excluded_products)) {
            $args['post__not_in'] = $excluded_products;
        }

        // Handle different query types
        $query_type = isset($query_settings['query_type']) ? $query_settings['query_type'] : 'all';

        switch ($query_type) {
            case 'category':
                if (!empty($query_settings['selected_categories'])) {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'slug',
                            'terms' => $query_settings['selected_categories'],
                            'operator' => 'IN'
                        )
                    );
                }
                break;

            case 'tags':
                if (!empty($query_settings['selected_tags'])) {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_tag',
                            'field' => 'slug',
                            'terms' => $query_settings['selected_tags'],
                            'operator' => 'IN'
                        )
                    );
                }
                break;

            case 'brands':
                if (!empty($query_settings['selected_brands'])) {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_brand',
                            'field'    => 'slug',
                            'terms'    => $query_settings['selected_brands'],
                            'operator' => 'IN'
                        )
                    );
                }
                break;

            case 'products':
                if (!empty($query_settings['selected_products'])) {
                    $args['post__in'] = $query_settings['selected_products'];
                    $args['orderby'] = 'post__in';
                }
                break;
        }

        $query = new WP_Query($args);
        return $query->posts;
    }
    public function generate_table_html($table_data, $products)
    {
        $html = '';
        $html .= "<style>";
        foreach ($table_data['rows'][0] as $cell) {
            $cell_settings = isset($cell['elements'][0]) ? $cell['elements'][0] : [];
            $selector = isset($cell_settings['settings']) ? array_keys($cell_settings['settings']) : [];
            foreach ($selector as $key) {
                if ($key !== "content_settings") {
                    if (isset($cell_settings['settings'][$key])) {
                        $html .= $this->generate_element_styles($cell_settings['settings'][$key], $key);
                    }
                }
            }
        }
        $html .= "</style>";
        $html .= '<div class="plugincy-table-container">';
        $html .= '<table class="plugincy-product-table">';

        if ($table_data['show_header']) {
            $html .= '<thead><tr>';
            foreach ($table_data['headers'] as $header) {
                $html .= '<th>' . esc_html($header["title"]) . '</th>';
            }
            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';
        $row_count = 1;
        $count_header = count($table_data["headers"]);
        foreach ($products as $product) {
            $html .= '<tr>';
            $cellcount = 0;
            foreach ($table_data['rows'][0] as $cell) {
                if ($cellcount > -1 && $cellcount < $count_header) {
                    $html .= '<td>' . $this->render_cell_content($cell, $product, $row_count) . '</td>';
                    $cellcount++;
                }
            }
            $html .= '</tr>';
            $row_count++;
        }
        $html .= '</tbody>';

        if ($table_data['show_footer']) {
            $html .= '<tfoot><tr>';
            foreach ($table_data['footers'] as $footer) {
                $html .= '<td>' . esc_html($footer["title"]) . '</td>';
            }
            $html .= '</tr></tfoot>';
        }

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    // generate_element_styles
    public function generate_element_styles($settings, $selector, $class = '')
    {

        $styles = '';

        // image styles
        $styles .= $class . $selector . '{ ';
        foreach ($settings as $property => $value) {
            $styles .= esc_attr($property) . ': ' . esc_attr($value) . '; ';
        }
        $styles .= '}';

        return $styles;
    }

    public function render_cell_content($cell, $product, $row_count)
    {
        $wc_product = wc_get_product($product->ID);

        if (!$wc_product) {
            return '';
        }

        $content = '';

        foreach ($cell['elements'] as $element) {
            $content .= $this->render_cell_content_html($wc_product, $element, $row_count);
        }
        return $content;
    }

    public function render_cell_content_html($wc_product, $element, $row_count = 0)
    {

        $content_settings = isset($element["settings"]) && isset($element["settings"]["content_settings"]) ? $element["settings"]["content_settings"] : [];
        $content = '';
        switch ($element['type']) {
            case 'product_title':
                $content = '<div class="plugincy-product-title ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . esc_html($wc_product->get_name()) . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;

            case 'product_title_link':
                $content = '<div class="plugincy-product-title-link"><a href="' . get_permalink($wc_product->get_id()) . '" class="' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . esc_html($wc_product->get_name()) . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></a></div>';
                break;

            case 'product_price':
                $content = '<div class="plugincy-product-price ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . $wc_product->get_price_html() . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;

            case 'product_image':
                $image = $wc_product->get_image('thumbnail');
                $content = '<div class="plugincy-product-image ' . $element['type'] . '">' . $image . '</div>';
                break;

            case 'add_to_cart':
                $content .= '<div class="plugincy-add-to-cart ' . $element['type'] . '">';
                $content .= '<form class="cart" method="post" enctype="multipart/form-data">';
                $content .= '<input type="hidden" name="add-to-cart" value="' . $wc_product->get_id() . '">';
                $content .= '<button type="submit" class="single_add_to_cart_button button">' . (isset($content_settings["button_text"]) ? esc_html($content_settings["button_text"]) : 'Add to Cart') . '</button>';
                $content .= '</form>';
                $content .= '</div>';
                break;

            case 'view_details':
                $content = '<div class="plugincy-view-details"><a href="' . get_permalink($wc_product->get_id()) . '" class="' . (isset($content_settings["button_type"]) ? esc_html($content_settings["button_type"]) : '') . $element['type'] . '">' . (isset($content_settings["button_text"]) ? esc_html($content_settings["button_text"]) : 'View Details') . '</a></div>';
                break;


            case 'short_description':
                $content = '<div class="plugincy-short-description ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . $wc_product->get_short_description() . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;

            case 'product_rating':
                $rating = $wc_product->get_average_rating();
                $content = '<div class="plugincy-product-rating ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . wc_get_rating_html($rating) . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;

            case 'product_category':
                $categories = get_the_terms($wc_product->get_id(), 'product_cat');
                if ($categories && !is_wp_error($categories)) {
                    $cat_names = array();
                    foreach ($categories as $category) {
                        $cat_names[] = $category->name;
                    }
                    $seperator = isset($content_settings["seperator"]) ? esc_html($content_settings["seperator"]) : ', ';
                    $formatted_names = implode($seperator, array_map(function ($name) {
                        return '<span class="cat_name">' . $name . '</span>';
                    }, $cat_names));
                    $content = '<div class="plugincy-product-category ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . $formatted_names . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                }
                break;

            case 'product_tags':
                $tags = get_the_terms($wc_product->get_id(), 'product_tag');
                if ($tags && !is_wp_error($tags)) {
                    $tag_names = array();
                    foreach ($tags as $tag) {
                        $tag_names[] = $tag->name;
                    }
                    $seperator = isset($content_settings["seperator"]) ? esc_html($content_settings["seperator"]) : ', ';
                    $formatted_names = implode($seperator, array_map(function ($name) {
                        return '<span class="cat_name">' . $name . '</span>';
                    }, $tag_names));
                    $content = '<div class="plugincy-product-tags ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . $formatted_names . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                }
                break;

            case 'product_brands':

                $brands = get_the_terms($wc_product->get_id(), 'product_brand');
                if ($brands && !is_wp_error($brands)) {
                    $brand_names = array();
                    foreach ($brands as $brand) {
                        $brand_names[] = $brand->name;
                    }
                    $seperator = isset($content_settings["seperator"]) ? esc_html($content_settings["seperator"]) : ', ';
                    $formatted_names = implode($seperator, array_map(function ($name) {
                        return '<span class="brand_name">' . esc_html($name) . '</span>';
                    }, $brand_names));
                    $content = '<div class="plugincy-product-brands ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . $formatted_names . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                }
                break;


            case 'product_attributes':
                $attributes = $wc_product->get_attributes();
                if (empty($attributes)) {
                    break;
                }

                // ---- read settings ----
                $include_raw = isset($content_settings['include_attributes']) ? $content_settings['include_attributes'] : '';
                $exclude_raw = isset($content_settings['exclude_attributes']) ? $content_settings['exclude_attributes'] : '';
                $layout      = isset($content_settings['layout']) ? $content_settings['layout'] : 'list'; // table | list | badges
                $link_terms  = !empty($content_settings['link_terms']);

                // helpers
                $to_list = function ($csv) {
                    if (!is_string($csv) || $csv === '') return [];
                    $parts = array_map('trim', explode(',', $csv));
                    $parts = array_filter($parts, function ($v) {
                        return $v !== '';
                    });
                    return array_map(function ($v) {
                        return strtolower($v);
                    }, $parts);
                };
                $normalize_key = function ($attr_name) {
                    // Use human label for matching (e.g., "Color" from "pa_color"), lowercased
                    return strtolower(wc_attribute_label($attr_name));
                };

                $include = $to_list($include_raw); // if empty -> include all
                $exclude = $to_list($exclude_raw);

                // Collect filtered attributes: each item => ['label'=>..., 'values'=>[['name'=>..., 'link'=>string|null], ...]]
                $collected = [];

                foreach ($attributes as $attribute) {
                    $label = wc_attribute_label($attribute->get_name());
                    $key   = $normalize_key($attribute->get_name());

                    // include/exclude checks
                    if (!empty($include) && !in_array($key, $include, true)) {
                        continue;
                    }
                    if (!empty($exclude) && in_array($key, $exclude, true)) {
                        continue;
                    }

                    $values = [];

                    if ($attribute->is_taxonomy()) {
                        // taxonomy terms (objects so we can link)
                        $terms = wc_get_product_terms($wc_product->get_id(), $attribute->get_name(), ['fields' => 'all']);
                        foreach ($terms as $term) {
                            $term_name = $term->name;
                            $term_link = $link_terms ? get_term_link($term) : null;
                            $values[] = [
                                'name' => $term_name,
                                'link' => (!is_wp_error($term_link) && $term_link) ? $term_link : null,
                            ];
                        }
                    } else {
                        // custom text attributes
                        $options = $attribute->get_options();
                        foreach ($options as $opt) {
                            $values[] = [
                                'name' => $opt,
                                'link' => null, // plain text cannot be linked
                            ];
                        }
                    }

                    if (!empty($values)) {
                        $collected[] = [
                            'label'  => $label,
                            'values' => $values,
                        ];
                    }
                }

                if (empty($collected)) {
                    break;
                }

                // ---- render by layout ---- (no prefix/suffix for attributes)
                if ($layout === 'table') {
                    $html_rows = '';
                    foreach ($collected as $attr) {
                        $vals = array_map(function ($v) {
                            $name = esc_html($v['name']);
                            return $v['link'] ? '<a href="' . esc_url($v['link']) . '">' . $name . '</a>' : $name;
                        }, $attr['values']);
                        $html_rows .= '<tr><th class="plugincy-attr-label">' . esc_html($attr['label']) . '</th><td class="plugincy-attr-values">' . implode(', ', $vals) . '</td></tr>';
                    }
                    $content = '<div class="plugincy-product-attributes product_attributes"><table class="plugincy-attributes-table">' . $html_rows . '</table></div>';
                } elseif ($layout === 'badges') {
                    $blocks = '';
                    foreach ($collected as $attr) {
                        $badges = array_map(function ($v) {
                            $inner = esc_html($v['name']);
                            $inner = $v['link'] ? '<a href="' . esc_url($v['link']) . '">' . $inner . '</a>' : $inner;
                            return '<span class="plugincy-attr-badge">' . $inner . '</span>';
                        }, $attr['values']);
                        $blocks .= '<div class="plugincy-attr-group"><span class="plugincy-attr-label">' . esc_html($attr['label']) . ':</span> ' . implode(' ', $badges) . '</div>';
                    }
                    $content = '<div class="plugincy-product-attributes product_attributes plugincy-attributes-badges">' . $blocks . '</div>';
                } else { // default 'list'
                    $items = '';
                    foreach ($collected as $attr) {
                        $vals = array_map(function ($v) {
                            $name = esc_html($v['name']);
                            return $v['link'] ? '<a href="' . esc_url($v['link']) . '">' . $name . '</a>' : $name;
                        }, $attr['values']);
                        $items .= '<li><span class="plugincy-attr-label">' . esc_html($attr['label']) . ':</span> <span class="plugincy-attr-values">' . implode(', ', $vals) . '</span></li>';
                    }
                    $content = '<div class="plugincy-product-attributes product_attributes"><ul class="plugincy-attributes-list">' . $items . '</ul></div>';
                }
                break;


            case 'stock_status':
                $stock_status = $wc_product->get_stock_status();
                $content = '<div class="plugincy-stock-status ' . $element['type'] . ' plugincy-stock-' . $stock_status . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . ucfirst($stock_status) . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;
            case 'product_sku':
                $sku = $wc_product->get_sku();
                $content = '<div class="plugincy-sku ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . $sku . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;
            case 'si':
                $content = '<div class="plugincy-si ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . $row_count . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;

            case 'custom_text':
                $content = '<div class="plugincy-custom-text ' . $element['type'] . '"><span class="prefix">' . (isset($content_settings["prefix_text"]) ? esc_html($content_settings["prefix_text"]) : '') . '</span>' . (isset($content_settings["custom_content"]) ? esc_html($content_settings["custom_content"]) : esc_html($element['content'])) . '<span class="suffix">' . (isset($content_settings["suffix_text"]) ? esc_html($content_settings["suffix_text"]) : '') . '</span></div>';
                break;

            case 'sale_badge':
                if ($wc_product->is_on_sale()) {
                    $badge_text   = !empty($content_settings['text']) ? esc_html($content_settings['text']) : __('Sale', 'ultimate-product-table-for-woocommerce');
                    $show_percent = !isset($content_settings['show_percent']) || $content_settings['show_percent']; // default on
                    $position     = !empty($content_settings['position']) ? esc_attr($content_settings['position']) : 'top-left';

                    $percent_html = '';

                    if ($show_percent) {
                        $regular_price = floatval($wc_product->get_regular_price());
                        $sale_price    = floatval($wc_product->get_sale_price());

                        if ($regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price) {
                            $percent = round((($regular_price - $sale_price) / $regular_price) * 100);
                            $percent_html = ' <span class="plugincy-sale-percent">-' . $percent . '%</span>';
                        }
                    }

                    $content = '<div class="plugincy-sale-badge position-' . $position . '">' .
                        '<span class="plugincy-sale-text">' . $badge_text . $percent_html . '</span>' .
                        '</div>';
                }
                break;


            case 'stock_quantity':
                // --- settings ---
                $only_if_managed   = !isset($content_settings['only_if_managed']) || $content_settings['only_if_managed'] === 'on'; // default on
                $low_threshold     = isset($content_settings['low_stock_threshold']) ? max(0, intval($content_settings['low_stock_threshold'])) : 2;
                $txt_low           = isset($content_settings['low_stock_text']) ? $content_settings['low_stock_text'] : 'Hurry! Only {qty} left';
                $txt_in            = isset($content_settings['in_stock_text']) ? $content_settings['in_stock_text'] : 'In stock: {qty}';
                $txt_out           = isset($content_settings['out_stock_text']) ? $content_settings['out_stock_text'] : 'Out of stock';

                // --- product stock info ---
                $managing = method_exists($wc_product, 'managing_stock') ? $wc_product->managing_stock() : (bool) $wc_product->get_manage_stock();
                $status   = $wc_product->get_stock_status(); // 'instock', 'outofstock', 'onbackorder'
                $qty_raw  = $wc_product->get_stock_quantity(); // may be null for non-managed/variable
                $qty      = is_null($qty_raw) ? null : (int) $qty_raw;

                if ($only_if_managed && !$managing) {
                    // Do not render anything if we only show when stock is managed.
                    break;
                }

                // helper to replace {qty} placeholder (remove if qty unknown)
                $render_text = function ($text, $q) {
                    if (strpos($text, '{qty}') !== false) {
                        if ($q === null) {
                            // remove placeholder and any preceding/trailing extra spaces/colon
                            $text = str_replace('{qty}', '', $text);
                            // tidy common " : " or double spaces
                            $text = preg_replace('/\s*:\s*$/', '', $text);
                            $text = preg_replace('/\s{2,}/', ' ', $text);
                        } else {
                            $text = str_replace('{qty}', (string) max(0, (int)$q), $text);
                        }
                    }
                    return trim($text);
                };

                $css_state = 'in';
                if ($status === 'outofstock') {
                    $css_state = 'out';
                    $message   = $render_text($txt_out, $qty);
                } else {
                    // instock or onbackorder
                    if ($qty !== null && $qty <= $low_threshold) {
                        $css_state = 'low';
                        $message   = $render_text($txt_low, $qty);
                    } else {
                        $css_state = 'in';
                        $message   = $render_text($txt_in, $qty);
                    }
                }

                $content = '<div class="stock_qty ' . esc_attr($css_state) . ' stock_quantity">' . esc_html($message) . '</div>';
                break;


            case 'product_dimensions':
                // --- settings ---
                $format      = isset($content_settings['format']) ? (string) $content_settings['format'] : '{length} × {width} × {height}';
                $show_units  = !isset($content_settings['show_units']) || $content_settings['show_units'] === 'on'; // default on
                $precision   = isset($content_settings['precision']) ? max(0, min(4, intval($content_settings['precision']))) : 0;

                // --- raw values from product (may be '', null or numeric strings) ---
                $len_raw = $wc_product->get_length();
                $wid_raw = $wc_product->get_width();
                $hei_raw = $wc_product->get_height();

                // If nothing is set, don't render
                if ($len_raw === '' && $wid_raw === '' && $hei_raw === '') {
                    break;
                }

                $unit = get_option('woocommerce_dimension_unit', 'cm');

                $fmt = function ($val) use ($precision, $show_units, $unit) {
                    if ($val === '' || $val === null) return '';
                    $num = (float) $val;
                    $out = number_format($num, $precision, wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
                    if ($show_units) {
                        $out .= ' ' . esc_html($unit);
                    }
                    return $out;
                };

                $length = $fmt($len_raw);
                $width  = $fmt($wid_raw);
                $height = $fmt($hei_raw);

                // Build output from format
                $rendered = strtr($format, array(
                    '{length}' => $length,
                    '{width}'  => $width,
                    '{height}' => $height,
                ));

                // Tidy up: collapse multiple separators (e.g., when some values are empty)
                // Normalize spaces around × or x, collapse repeats, then trim.
                $rendered = preg_replace('/\s*[×x]\s*/u', ' × ', $rendered);
                $rendered = preg_replace('/(?:\s×\s){2,}/u', ' × ', $rendered);
                $rendered = trim(preg_replace('/\s{2,}/', ' ', $rendered));

                // If everything ended up empty after formatting, don't output
                if ($rendered === '' || $rendered === '×') {
                    break;
                }

                $content = '<div class="product_dimensions ' . $element['type'] . '">' . esc_html($rendered) . '</div>';
                break;


            case 'product_weight':
                // --- settings ---
                $show_units = !isset($content_settings['show_units']) || $content_settings['show_units'] === 'on'; // default on
                $precision  = isset($content_settings['precision']) ? max(0, min(4, (int) $content_settings['precision'])) : 0;
                $prefix     = isset($content_settings['prefix_text']) ? $content_settings['prefix_text'] : '';
                $suffix     = isset($content_settings['suffix_text']) ? $content_settings['suffix_text'] : '';

                // --- raw value ---
                $weight_raw = $wc_product->get_weight();

                if ($weight_raw === '' || $weight_raw === null) {
                    break;
                }

                $num  = (float) $weight_raw;
                $unit = get_option('woocommerce_weight_unit', 'kg');

                // --- format weight ---
                if ($precision > 0) {
                    // Use requested precision
                    $formatted = number_format(
                        $num,
                        $precision,
                        wc_get_price_decimal_separator(),
                        wc_get_price_thousand_separator()
                    );
                } else {
                    // Keep natural decimals if any (avoid rounding)
                    // wc_format_localized_decimal handles localization
                    $formatted = wc_format_localized_decimal($weight_raw);
                }

                if ($show_units) {
                    $formatted .= ' ' . esc_html($unit);
                }

                $content = '<div class="product_weight ' . $element['type'] . '">'
                    . '<span class="prefix">' . esc_html($prefix) . '</span>'
                    . esc_html($formatted)
                    . '<span class="suffix">' . esc_html($suffix) . '</span>'
                    . '</div>';
                break;


            case 'product_gallery_thumbs':
                // ---- settings ----
                $limit   = isset($content_settings['limit']) ? max(1, min(12, (int) $content_settings['limit'])) : 4;
                $lightbx = !isset($content_settings['lightbox']) || $content_settings['lightbox'] === 'on'; // default on
                $layout  = !empty($content_settings['layout']) ? $content_settings['layout'] : 'inline';   // inline | grid | stacked

                // ---- collect images (gallery first, fall back to featured) ----
                $ids = $wc_product->get_gallery_image_ids();
                $feat = $wc_product->get_image_id();
                if (empty($ids) && $feat) {
                    $ids = array($feat);
                }
                if (!empty($ids) && $feat) {
                    // Ensure featured is first & unique
                    array_unshift($ids, $feat);
                    $ids = array_values(array_unique($ids));
                }
                if (empty($ids)) {
                    break;
                }

                // Limit number of images
                $ids = array_slice($ids, 0, $limit);

                // Grid helper: pick 2 or 3 columns heuristically
                $grid_cols = (count($ids) <= 4) ? 2 : 3;

                $wrapper_classes = array(
                    'product_gallery_thumbs',
                    $element['type'],
                    'layout-' . sanitize_html_class($layout),
                );
                $content_html = '';

                foreach ($ids as $img_id) {
                    $full_url = wp_get_attachment_image_url($img_id, 'full');
                    $alt      = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                    if ($alt === '') {
                        $alt = $wc_product->get_name();
                    }

                    // Thumbnail HTML (respect style controls via CSS on .product_gallery_thumbs img)
                    $thumb = wp_get_attachment_image($img_id, 'thumbnail', false, array(
                        'alt'     => esc_attr($alt),
                        'loading' => 'lazy',
                    ));

                    // Wrap with lightbox link if enabled
                    if ($lightbx && $full_url) {
                        $thumb = '<a href="' . esc_url($full_url) . '" class="plugincy-thumb-link" data-plugincy-lightbox="product-' . esc_attr($wc_product->get_id()) . '">' . $thumb . '</a>';
                    }

                    // Item wrapper
                    $content_html .= '<div class="plugincy-thumb-item">' . $thumb . '</div>';
                }

                // Final container (data attribute helps theme/JS decide columns if desired)
                $content  = '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '" data-cols="' . esc_attr($grid_cols) . '">';
                $content .= $content_html;
                $content .= '</div>';
                break;


            case 'product_id':
                $prefix = isset($content_settings['prefix_text']) ? $content_settings['prefix_text'] : 'ID: ';
                $pid    = $wc_product->get_id();
                $content = '<div class="product_id ' . $element['type'] . '">'
                    . '<span class="prefix">' . esc_html($prefix) . '</span>'
                    . esc_html($pid)
                    . '</div>';
                break;




            /* =========================
 * Regular (Non-sale) Price
 * ========================= */
            case 'product_regular_price':
                $prefix = isset($content_settings['prefix_text']) ? $content_settings['prefix_text'] : '';
                $suffix = isset($content_settings['suffix_text']) ? $content_settings['suffix_text'] : '';
                $hide_if_sale = isset($content_settings['hide_if_sale']) ? ($content_settings['hide_if_sale'] === 'on') : false;

                if ($hide_if_sale && $wc_product->is_on_sale()) {
                    break;
                }

                // Determine regular price (handles simple/variable)
                if ($wc_product->is_type('variable')) {
                    $min = (float) $wc_product->get_variation_regular_price('min', true);
                    $max = (float) $wc_product->get_variation_regular_price('max', true);
                    if ($min === 0.0 && $max === 0.0) {
                        break;
                    }
                    $price_html = ($min === $max) ? wc_price($min) : wc_price($min) . ' – ' . wc_price($max);
                } else {
                    $reg = $wc_product->get_regular_price();
                    if ($reg === '' || $reg === null) {
                        break;
                    }
                    $price_html = wc_price((float) $reg);
                }

                $content = '<div class="product_regular_price ' . $element['type'] . '">'
                    . '<span class="prefix">' . esc_html($prefix) . '</span>'
                    . '<span class="amount">' . $price_html . '</span>'
                    . '<span class="suffix">' . esc_html($suffix) . '</span>'
                    . '</div>';
                break;

            /* ================
 * Sale Price Only
 * ================ */
            case 'product_sale_price':
                $prefix = isset($content_settings['prefix_text']) ? $content_settings['prefix_text'] : '';
                $suffix = isset($content_settings['suffix_text']) ? $content_settings['suffix_text'] : '';
                $hide_if_not_sale = !isset($content_settings['hide_if_not_sale']) || $content_settings['hide_if_not_sale'] === 'on'; // default on

                if ($hide_if_not_sale && !$wc_product->is_on_sale()) {
                    break;
                }
                if (!$wc_product->is_on_sale()) {
                    // If not hiding, but not on sale, fall back to regular price
                    $sale_html = $wc_product->get_price() !== '' ? wc_price((float) $wc_product->get_price()) : '';
                    if ($sale_html === '') {
                        break;
                    }
                } else {
                    if ($wc_product->is_type('variable')) {
                        $min = (float) $wc_product->get_variation_sale_price('min', true);
                        $max = (float) $wc_product->get_variation_sale_price('max', true);
                        if ($min === 0.0 && $max === 0.0) {
                            break;
                        }
                        $sale_html = ($min === $max) ? wc_price($min) : wc_price($min) . ' – ' . wc_price($max);
                    } else {
                        $sale = $wc_product->get_sale_price();
                        if ($sale === '' || $sale === null) {
                            break;
                        }
                        $sale_html = wc_price((float) $sale);
                    }
                }

                $content = '<div class="product_sale_price ' . $element['type'] . '">'
                    . '<span class="prefix">' . esc_html($prefix) . '</span>'
                    . '<span class="amount">' . $sale_html . '</span>'
                    . '<span class="suffix">' . esc_html($suffix) . '</span>'
                    . '</div>';
                break;

            /* ==========================
 * You Save (amount/percent)
 * ========================== */
            case 'price_savings':
                $show_amount  = !isset($content_settings['show_amount']) || $content_settings['show_amount'] === 'on';
                $show_percent = !isset($content_settings['show_percent']) || $content_settings['show_percent'] === 'on';
                $format_str   = isset($content_settings['format']) ? (string) $content_settings['format'] : 'You save {amount} ({percent}%)';

                // Only meaningful if on sale and we have comparable prices
                if (!$wc_product->is_on_sale()) {
                    break;
                }

                if ($wc_product->is_type('variable')) {
                    $reg_min  = (float) $wc_product->get_variation_regular_price('min', true);
                    $sale_min = (float) $wc_product->get_variation_sale_price('min', true);
                    if ($reg_min <= 0 || $sale_min <= 0 || $sale_min >= $reg_min) {
                        break;
                    }
                    $save_amt = $reg_min - $sale_min;
                    $percent  = round(($save_amt / $reg_min) * 100);
                } else {
                    $reg  = $wc_product->get_regular_price();
                    $sale = $wc_product->get_sale_price();
                    if ($reg === '' || $sale === '' || $reg === null || $sale === null) {
                        break;
                    }
                    $reg_f  = (float) $reg;
                    $sale_f = (float) $sale;
                    if ($reg_f <= 0 || $sale_f >= $reg_f) {
                        break;
                    }
                    $save_amt = $reg_f - $sale_f;
                    $percent  = round(($save_amt / $reg_f) * 100);
                }

                // Build replacements
                $repl = array(
                    '{amount}'  => $show_amount  ? wc_price($save_amt) : '',
                    '{percent}' => $show_percent ? $percent : '',
                );

                // If one piece is hidden, clean the format smartly
                $out = strtr($format_str, $repl);
                // Remove empty parentheses/brackets and extra spaces/commas
                $out = preg_replace('/\(\s*\)|\[\s*\]|\{\s*\}/', '', $out);
                $out = preg_replace('/\s{2,}/', ' ', trim($out));
                $out = preg_replace('/\s+,\s+/', ', ', $out);
                $out = trim($out, " -–—:;,");

                if ($out === '') {
                    break;
                }

                $content = '<div class="price_savings ' . $element['type'] . '">' . wp_kses_post($out) . '</div>';
                break;

            // Inside switch ($element['type']) { ... }

            /* ======================
 * Quantity Selector
 * ====================== */
            case 'quantity_selector':
                // settings
                $set_min  = isset($content_settings['min']) ? max(0, (int)$content_settings['min']) : 1;
                $set_max  = isset($content_settings['max']) ? max(0, (int)$content_settings['max']) : 0; // 0 => use product max/stock
                $set_step = isset($content_settings['step']) ? max(1, (int)$content_settings['step']) : 1;

                // respect WooCommerce purchase rules
                $prod_min = (int) (method_exists($wc_product, 'get_min_purchase_quantity') ? $wc_product->get_min_purchase_quantity() : 1);
                $prod_max = (int) (method_exists($wc_product, 'get_max_purchase_quantity') ? $wc_product->get_max_purchase_quantity() : 0); // 0 = unlimited/stock-managed

                // sold individually overrides everything
                if ($wc_product->is_sold_individually()) {
                    $min = $max = 1;
                    $step = 1;
                } else {
                    $min  = max($set_min, $prod_min);
                    // if user set 0, use product max; otherwise clamp to the lower non-zero cap
                    if ($set_max === 0) {
                        $max = $prod_max; // may be 0 meaning "no explicit max"
                    } else {
                        $max = ($prod_max > 0) ? min($set_max, $prod_max) : $set_max;
                    }
                    $step = $set_step;
                }

                // default value within range
                $value = $min > 0 ? $min : 1;

                $qid = 'qty-' . esc_attr($wc_product->get_id()) . '-' . $row_count;

                $attrs = array(
                    'type'  => 'number',
                    'id'    => $qid,
                    'class' => 'plugincy-qty-input',
                    'name'  => 'quantity',
                    'value' => $value,
                    'step'  => $step,
                    'min'   => $min,
                );
                if ($max > 0) {
                    $attrs['max'] = $max;
                }

                $attr_html = '';
                foreach ($attrs as $k => $v) {
                    $attr_html .= ' ' . $k . '="' . esc_attr($v) . '"';
                }

                $content = '<div class="quantity_selector ' . $element['type'] . '"><label for="' . esc_attr($qid) . '" class="screen-reader-text">' . esc_html__('Quantity', 'ultimate-product-table-for-woocommerce') . '</label><input' . $attr_html . ' /></div>';
                break;


            /* ======================
 * Variation Selector
 * ====================== */
            case 'variation_selector':
                if (!$wc_product->is_type('variable')) {
                    break;
                }

                // settings
                $attrs_csv  = isset($content_settings['attributes']) ? trim((string)$content_settings['attributes']) : '';
                $style_type = !empty($content_settings['style']) ? $content_settings['style'] : 'dropdown'; // dropdown | swatches
                $show_labels = !isset($content_settings['show_labels']) || $content_settings['show_labels'] === 'on';

                // available variation attributes from product
                $variation_attrs = $wc_product->get_variation_attributes(); // [ 'attribute_pa_color' => [...], ... ]

                // decide which attributes to render
                $want_keys = array_keys($variation_attrs);
                if ($attrs_csv !== '') {
                    $requested = array_filter(array_map('trim', explode(',', $attrs_csv)));
                    // normalize to 'attribute_{slug}'
                    $requested = array_map(function ($slug) {
                        $slug = ltrim(strtolower($slug));
                        if (strpos($slug, 'attribute_') !== 0) {
                            $slug = 'attribute_' . $slug;
                        }
                        return $slug;
                    }, $requested);
                    $want_keys = array_values(array_intersect($requested, array_keys($variation_attrs)));
                    if (empty($want_keys)) {
                        break;
                    } // nothing valid requested
                }

                $wrap_classes = array('variation_selector', $element['type'], 'style-' . sanitize_html_class($style_type));
                $blocks = '';

                foreach ($want_keys as $name_key) {
                    $options = (array) $variation_attrs[$name_key];
                    $attr_slug = str_replace('attribute_', '', $name_key); // e.g., pa_color
                    $label = wc_attribute_label($attr_slug);

                    if ($style_type === 'dropdown') {
                        // use WooCommerce helper for dropdown
                        ob_start();
                        wc_dropdown_variation_attribute_options(array(
                            'options'  => $options,
                            'attribute' => $name_key,
                            'product'  => $wc_product,
                            'name'     => $name_key,
                            'show_option_none' => __('Choose an option', 'ultimate-product-table-for-woocommerce'),
                            'selected' => '',
                        ));
                        $field = ob_get_clean();

                        $blocks .= '<div class="plugincy-variation-field plugincy-variation-dropdown">'
                            . ($show_labels ? '<label class="plugincy-var-label" for="' . esc_attr($name_key) . '">' . esc_html($label) . '</label>' : '')
                            . $field
                            . '</div>';
                    } else { // swatches (basic radios; JS/CSS can enhance)
                        $items = '';
                        foreach ($options as $opt) {
                            $opt_val = esc_attr($opt);
                            $opt_text = esc_html(wc_attribute_label($opt)); // display term name if taxonomy, else raw
                            // If taxonomy, map to term name properly
                            if (taxonomy_exists($attr_slug)) {
                                $term = get_term_by('slug', $opt, $attr_slug);
                                if ($term && !is_wp_error($term)) {
                                    $opt_text = esc_html($term->name);
                                }
                            }
                            $input_id = 'sw-' . $wc_product->get_id() . '-' . $row_count . '-' . $attr_slug . '-' . sanitize_html_class($opt);
                            $items .= '<label for="' . $input_id . '" class="plugincy-swatch">'
                                . '<input type="radio" id="' . $input_id . '" name="' . esc_attr($name_key) . '" value="' . $opt_val . '" />'
                                . '<span class="plugincy-swatch-chip" data-value="' . $opt_val . '">' . $opt_text . '</span>'
                                . '</label>';
                        }

                        $blocks .= '<div class="plugincy-variation-field plugincy-variation-swatches" data-attribute="' . esc_attr($name_key) . '">'
                            . ($show_labels ? '<div class="plugincy-var-label">' . esc_html($label) . '</div>' : '')
                            . '<div class="plugincy-swatch-group">' . $items . '</div>'
                            . '</div>';
                    }
                }

                // Container; NOTE: these controls should live inside the same <form class="cart"> as your add_to_cart button to submit correctly.
                $content = '<div class="' . esc_attr(implode(' ', $wrap_classes)) . '" data-product-id="' . esc_attr($wc_product->get_id()) . '">' . $blocks . '</div>';
                break;

            // Inside switch ($element['type']) { ... }

            /* ================================
 * Average Rating (Number) e.g. 4.6/5
 * ================================ */
            case 'average_rating_number':
                $fmt   = isset($content_settings['format']) ? (string)$content_settings['format'] : '{avg}/5';
                $dec   = isset($content_settings['decimals']) ? max(0, min(3, (int)$content_settings['decimals'])) : 1;

                $avg = (float) $wc_product->get_average_rating();
                // If there are no ratings, don't render
                if ($avg <= 0) {
                    break;
                }

                $avg_str = number_format_i18n($avg, $dec);
                $out = strtr($fmt, array('{avg}' => $avg_str));

                $content = '<div class="average_rating_number ' . $element['type'] . '">' . esc_html($out) . '</div>';
                break;


            /* ================================
 * Reviews Count
 * ================================ */
            case 'reviews_count':
                $fmt  = isset($content_settings['format']) ? (string)$content_settings['format'] : '({count} reviews)';
                $link = !isset($content_settings['link_to_reviews']) || $content_settings['link_to_reviews'] === 'on';

                $count = (int) $wc_product->get_review_count();
                $out = strtr($fmt, array('{count}' => number_format_i18n($count)));

                if ($link && $count > 0) {
                    $url = get_permalink($wc_product->get_id()) . '#reviews';
                    $out = '<a href="' . esc_url($url) . '">' . esc_html($out) . '</a>';
                } else {
                    $out = esc_html($out);
                }

                $content = '<div class="reviews_count ' . $element['type'] . '">' . $out . '</div>';
                break;


            /* ================================
 * Sale Countdown
 * ================================ */
            case 'sale_countdown':
                $fmt         = isset($content_settings['format']) ? (string)$content_settings['format'] : '{dd}d {hh}h {mm}m {ss}s';
                $expired_txt = isset($content_settings['expired_text']) ? (string)$content_settings['expired_text'] : 'Sale ended';

                if (!$wc_product->is_on_sale()) {
                    break;
                }

                // Try to get a unified sale end datetime
                $end_dt = null;
                if (method_exists($wc_product, 'get_date_on_sale_to')) {
                    $end_dt = $wc_product->get_date_on_sale_to();
                }
                // If variable product and parent has no end date, try variations
                if (!$end_dt && $wc_product->is_type('variable')) {
                    foreach ($wc_product->get_children() as $vid) {
                        $v = wc_get_product($vid);
                        if ($v && $v->is_on_sale() && method_exists($v, 'get_date_on_sale_to')) {
                            $dt = $v->get_date_on_sale_to();
                            if ($dt && (!$end_dt || $dt->getTimestamp() > $end_dt->getTimestamp())) {
                                $end_dt = $dt;
                            }
                        }
                    }
                }
                if (!$end_dt) {
                    break;
                }

                $end_ts = $end_dt->getTimestamp() * 1000; // JS ms

                $cid = 'plugincy-countdown-' . $wc_product->get_id() . '-' . $row_count;

                // Initial server-side render (static), JS will update every second
                $content  = '<div class="sale_countdown ' . $element['type'] . '" id="' . esc_attr($cid) . '"';
                $content .= ' data-end="' . esc_attr($end_ts) . '"';
                $content .= ' data-format="' . esc_attr($fmt) . '"';
                $content .= ' data-expired="' . esc_attr($expired_txt) . '">';
                $content .= esc_html($expired_txt);
                $content .= '</div>';

                // Inline minimal updater (scoped to this element)
                $content .= '<script>(function(){var el=document.getElementById(' . json_encode($cid) . ');
if(!el) return;var end=parseInt(el.getAttribute("data-end"),10)||0,fmt=el.getAttribute("data-format")||"{dd}d {hh}h {mm}m {ss}s",exp=el.getAttribute("data-expired")||"Sale ended";
function pad(n){return (n<10?"0":"")+n;}
function tick(){
 var now=Date.now(),diff=end-now;
 if(diff<=0){el.textContent=exp; clearInterval(iv); return;}
 var s=Math.floor(diff/1000);
 var dd=Math.floor(s/86400); s%=86400;
 var hh=Math.floor(s/3600); s%=3600;
 var mm=Math.floor(s/60); var ss=s%60;
 var out=fmt.replace("{dd}",dd).replace("{hh}",pad(hh)).replace("{mm}",pad(mm)).replace("{ss}",pad(ss));
 el.textContent=out;
}
var iv=setInterval(tick,1000); tick();})();</script>';
                break;


            /* ================================
 * Stock Progress (sold vs available)
 * ================================ */
            case 'stock_progress':
                $goal_opt   = isset($content_settings['goal']) ? max(0, (int)$content_settings['goal']) : 0;
                $show_text  = !isset($content_settings['show_text']) || $content_settings['show_text'] === 'on';
                $text_fmt   = isset($content_settings['text_format']) ? (string)$content_settings['text_format'] : '{sold} sold / {available} left';

                $sold = (int) (method_exists($wc_product, 'get_total_sales') ? $wc_product->get_total_sales() : (int) get_post_meta($wc_product->get_id(), 'total_sales', true));
                $managing = method_exists($wc_product, 'managing_stock') ? $wc_product->managing_stock() : (bool) $wc_product->get_manage_stock();
                $qty = $managing ? $wc_product->get_stock_quantity() : null;

                $goal = $goal_opt;
                if ($goal === 0) {
                    if (!is_null($qty)) {
                        // derive from known stock and sales
                        $goal = max(0, $sold + (int)$qty);
                    } else {
                        // fall back: unknown total, fake a goal to render a bar (avoid div-by-zero)
                        $goal = max(1, $sold);
                    }
                }

                $available = max(0, $goal - $sold);
                $pct = $goal > 0 ? max(0, min(100, round(($sold / $goal) * 100))) : 0;

                $text = strtr($text_fmt, array(
                    '{sold}'      => number_format_i18n($sold),
                    '{available}' => number_format_i18n($available),
                    '{goal}'      => number_format_i18n($goal),
                    '{percent}'   => $pct,
                ));

                $content  = '<div class="stock_progress ' . $element['type'] . '" role="img" aria-label="' . esc_attr($text) . '">';
                $content .=   '<div class="track" style="position:relative; width:100%;"><div class="bar" style="width:' . esc_attr($pct) . '%;"></div></div>';
                if ($show_text) {
                    $content .= '<div class="stock_text">' . esc_html($text) . '</div>';
                }
                $content .= '</div>';
                break;


            /* ================================
 * Total Sales
 * ================================ */
            case 'total_sales':
                $fmt = isset($content_settings['format']) ? (string)$content_settings['format'] : '{count} sold';
                $count = (int) (method_exists($wc_product, 'get_total_sales') ? $wc_product->get_total_sales() : (int) get_post_meta($wc_product->get_id(), 'total_sales', true));
                $out = strtr($fmt, array('{count}' => number_format_i18n($count)));
                $content = '<div class="total_sales ' . $element['type'] . '">' . esc_html($out) . '</div>';
                break;

            // Inside switch ($element['type']) { ... }

            /* ================================
 * Badges (New/Hot/Bestseller)
 * ================================ */
            case 'product_badges':
                $new_days   = isset($content_settings['new_days']) ? max(1, (int)$content_settings['new_days']) : 7;
                $hot_sales  = isset($content_settings['hot_sales']) ? max(1, (int)$content_settings['hot_sales']) : 10;
                $best_sales = isset($content_settings['bestseller_sales']) ? max(1, (int)$content_settings['bestseller_sales']) : 100;
                $show_labels = !isset($content_settings['show_labels']) || $content_settings['show_labels'] === 'on';

                $badges = array();

                // NEW: published within N days
                $created = method_exists($wc_product, 'get_date_created') ? $wc_product->get_date_created() : null;
                if ($created) {
                    $age_days = floor((current_time('timestamp') - $created->getTimestamp()) / DAY_IN_SECONDS);
                    if ($age_days >= 0 && $age_days < $new_days) {
                        $badges[] = array('key' => 'new', 'label' => __('New', 'ultimate-product-table-for-woocommerce'));
                    }
                }

                // HOT & BESTSELLER: based on total sales
                $sales = (int) (method_exists($wc_product, 'get_total_sales') ? $wc_product->get_total_sales() : (int) get_post_meta($wc_product->get_id(), 'total_sales', true));
                if ($sales >= $hot_sales) {
                    $badges[] = array('key' => 'hot', 'label' => __('Hot', 'ultimate-product-table-for-woocommerce'));
                }
                if ($sales >= $best_sales) {
                    $badges[] = array('key' => 'bestseller', 'label' => __('Bestseller', 'ultimate-product-table-for-woocommerce'));
                }

                if (empty($badges)) {
                    break;
                }

                $html = '';
                foreach ($badges as $b) {
                    $text = $show_labels ? esc_html($b['label']) : '';
                    $title = esc_attr($b['label']);
                    $html .= '<span class="badge ' . esc_attr($b['key']) . '" title="' . $title . '" aria-label="' . $title . '">' . $text . '</span>';
                }

                $content = '<div class="product_badges ' . $element['type'] . '">' . $html . '</div>';
                break;


            /* ================================
 * Shipping Class
 * ================================ */
            case 'shipping_class':
                $link = isset($content_settings['link']) && $content_settings['link'] === 'on';

                $sc_id  = (int) $wc_product->get_shipping_class_id();
                if ($sc_id <= 0) {
                    break;
                }

                $term = get_term($sc_id, 'product_shipping_class');
                if (!$term || is_wp_error($term)) {
                    break;
                }

                $name = $term->name;
                if ($link) {
                    $url = get_term_link($term);
                    if (!is_wp_error($url)) {
                        $content = '<div class="shipping_class ' . $element['type'] . '"><a href="' . esc_url($url) . '">' . esc_html($name) . '</a></div>';
                        break;
                    }
                }
                $content = '<div class="shipping_class ' . $element['type'] . '">' . esc_html($name) . '</div>';
                break;


            /* ================================
 * Tax Status / Class
 * ================================ */
            case 'tax_info':
                $show_status = !isset($content_settings['show_status']) || $content_settings['show_status'] === 'on';
                $show_class  = !isset($content_settings['show_class'])  || $content_settings['show_class']  === 'on';
                $sep         = isset($content_settings['separator']) ? (string)$content_settings['separator'] : ' • ';

                if (!$show_status && !$show_class) {
                    break;
                }

                // Status
                $status_slug = method_exists($wc_product, 'get_tax_status') ? $wc_product->get_tax_status() : '';
                // Map to human readable
                $status_map = array(
                    'taxable'    => __('Taxable', 'ultimate-product-table-for-woocommerce'),
                    'shipping'   => __('Shipping only', 'ultimate-product-table-for-woocommerce'),
                    'none'       => __('None', 'ultimate-product-table-for-woocommerce'),
                    ''           => '',
                );
                $status_label = isset($status_map[$status_slug]) ? $status_map[$status_slug] : ucfirst($status_slug);

                // Class (slug)
                $class_slug = method_exists($wc_product, 'get_tax_class') ? $wc_product->get_tax_class() : '';
                // Build a slug->name map from Woo settings to display proper names
                $class_display = '';
                if ($class_slug === '' || $class_slug === 'standard') {
                    $class_display = __('Standard', 'ultimate-product-table-for-woocommerce');
                } else {
                    $names = WC_Tax::get_tax_classes(); // array of class NAMES
                    $map = array();
                    foreach ($names as $n) {
                        $map[sanitize_title($n)] = $n;
                    }
                    $class_display = isset($map[$class_slug]) ? $map[$class_slug] : ucwords(str_replace(array('-', '_'), ' ', $class_slug));
                }

                $parts = array();
                if ($show_status && $status_label !== '') {
                    $parts[] = esc_html($status_label);
                }
                if ($show_class  && $class_display !== '') {
                    $parts[] = esc_html($class_display);
                }

                if (empty($parts)) {
                    break;
                }

                $content = '<div class="tax_info ' . $element['type'] . '">' . implode(esc_html($sep), $parts) . '</div>';
                break;

            // Inside switch ($element['type']) { ... }

            /* ================================
 * External / Affiliate Button
 * ================================ */
            case 'external_button':
                if (!$wc_product->is_type('external')) {
                    break;
                }

                $btn_text = isset($content_settings['button_text']) && $content_settings['button_text'] !== ''
                    ? $content_settings['button_text']
                    : ($wc_product->get_button_text() ?: __('Buy Now', 'ultimate-product-table-for-woocommerce'));

                $url      = $wc_product->get_product_url();
                if (!$url) {
                    break;
                }

                $new_tab  = !isset($content_settings['new_tab']) || $content_settings['new_tab'] === 'on'; // default on
                $target   = $new_tab ? ' target="_blank" rel="noopener nofollow sponsored"' : '';

                $content  = '<div class="external_button ' . $element['type'] . '">';
                $content .= '<a href="' . esc_url($url) . '"' . $target . ' class="plugincy-external-btn">' . esc_html($btn_text) . '</a>';
                $content .= '</div>';
                break;


            /* ================================
 * Downloadable Files (list)
 * ================================ */
            case 'download_links':
                if (!$wc_product->is_downloadable()) {
                    break;
                }

                $limit     = isset($content_settings['limit']) ? max(1, min(50, (int)$content_settings['limit'])) : 5;
                $show_size = isset($content_settings['show_size']) && $content_settings['show_size'] === 'on';

                $downloads = $wc_product->get_downloads(); // array of WC_Product_Download
                if (empty($downloads)) {
                    break;
                }

                $items = array_slice(array_values($downloads), 0, $limit);

                // helper: try to resolve filesize for local files
                $file_size_str = function ($url) {
                    // Only attempt for local files in uploads dir
                    $uploads = wp_get_upload_dir();
                    if (isset($uploads['baseurl'], $uploads['basedir']) && strpos($url, $uploads['baseurl']) === 0) {
                        $path = str_replace($uploads['baseurl'], $uploads['basedir'], $url);
                        if (file_exists($path) && is_readable($path)) {
                            $bytes = filesize($path);
                            if ($bytes !== false) {
                                $sizes = array('B', 'KB', 'MB', 'GB', 'TB');
                                $i = 0;
                                while ($bytes >= 1024 && $i < count($sizes) - 1) {
                                    $bytes /= 1024;
                                    $i++;
                                }
                                return sprintf('%s %s', number_format_i18n($bytes, $i ? 2 : 0), $sizes[$i]);
                            }
                        }
                    }
                    return '';
                };

                $list = '';
                foreach ($items as $dl) {
                    /** @var WC_Product_Download $dl */
                    $name = $dl->get_name();
                    $url  = $dl->get_file();
                    $size = ($show_size && $url) ? $file_size_str($url) : '';
                    $label = esc_html($name . ($size ? ' (' . $size . ')' : ''));
                    $list .= '<li><a href="' . esc_url($url) . '" class="plugincy-download-link">' . $label . '</a></li>';
                }

                if ($list === '') {
                    break;
                }

                $content = '<div class="download_links ' . $element['type'] . '"><ul class="plugincy-download-list">' . $list . '</ul></div>';
                break;


            /* ================================
 * SKU as Code (text or QR)
 * ================================ */
            case 'sku_code':
                $render = !empty($content_settings['render']) ? $content_settings['render'] : 'text'; // text | qr
                $prefix = isset($content_settings['prefix_text']) ? $content_settings['prefix_text'] : 'SKU: ';
                $sku    = $wc_product->get_sku();
                if (!$sku) {
                    break;
                }

                if ($render === 'qr') {
                    // Generate QR via external service; size controlled by CSS, but request a decent base size
                    $qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=' . rawurlencode($sku);
                    $content = '<div class="sku_code ' . $element['type'] . '"><img src="' . esc_url($qr_src) . '" alt="' . esc_attr__('SKU QR', 'ultimate-product-table-for-woocommerce') . '"></div>';
                } else {
                    $content = '<div class="sku_code ' . $element['type'] . '"><span class="prefix">' . esc_html($prefix) . '</span>' . esc_html($sku) . '</div>';
                }
                break;


            /* ================================
 * Publish Date
 * ================================ */
            case 'publish_date':
                $fmt    = isset($content_settings['format']) ? $content_settings['format'] : 'M j, Y';
                $prefix = isset($content_settings['prefix_text']) ? $content_settings['prefix_text'] : 'Added: ';

                $dt = method_exists($wc_product, 'get_date_created') ? $wc_product->get_date_created() : null;
                if (!$dt) {
                    break;
                }

                $date_str = date_i18n($fmt, $dt->getTimestamp());
                $content  = '<div class="publish_date ' . $element['type'] . '"><span class="prefix">' . esc_html($prefix) . '</span>' . esc_html($date_str) . '</div>';
                break;


            /* ================================
 * Last Updated (Modified Date)
 * ================================ */
            case 'modified_date':
                $fmt    = isset($content_settings['format']) ? $content_settings['format'] : 'M j, Y';
                $prefix = isset($content_settings['prefix_text']) ? $content_settings['prefix_text'] : 'Updated: ';

                $dt = method_exists($wc_product, 'get_date_modified') ? $wc_product->get_date_modified() : null;
                if (!$dt) {
                    break;
                }

                $date_str = date_i18n($fmt, $dt->getTimestamp());
                $content  = '<div class="modified_date ' . $element['type'] . '"><span class="prefix">' . esc_html($prefix) . '</span>' . esc_html($date_str) . '</div>';
                break;

            // Inside switch ($element['type']) { ... }

            /* ================================
 * Long Description (excerpted)
 * ================================ */
            case 'long_description':
                $word_limit   = isset($content_settings['word_limit']) ? max(10, min(400, (int)$content_settings['word_limit'])) : 40;
                $show_ellipsis = !isset($content_settings['text-overflow']) || $content_settings['text-overflow'] === 'on'; // default on

                $raw = $wc_product->get_description();
                if ($raw === '' || $raw === null) {
                    break;
                }

                // Strip shortcodes/HTML, then trim to word limit
                $text = wp_strip_all_tags(strip_shortcodes($raw));
                $words = preg_split('/\s+/u', trim($text));
                if (!$words) {
                    break;
                }

                if (count($words) > $word_limit) {
                    $words = array_slice($words, 0, $word_limit);
                    $text  = implode(' ', $words);
                    if ($show_ellipsis) {
                        $text .= '…';
                    }
                } else {
                    $text = implode(' ', $words);
                }

                $content = '<div class="long_description ' . $element['type'] . '">' . esc_html($text) . '</div>';
                break;


            /* ================================
 * Low Stock Badge
 * ================================ */
            case 'low_stock_badge':
                $threshold  = isset($content_settings['threshold']) ? max(1, (int)$content_settings['threshold']) : 5;
                $label      = isset($content_settings['label']) ? (string)$content_settings['label'] : 'Low stock';
                $show_count = !isset($content_settings['show_count']) || $content_settings['show_count'] === 'on'; // default on

                // Determine stock qty only when managed
                $managing = method_exists($wc_product, 'managing_stock') ? $wc_product->managing_stock() : (bool)$wc_product->get_manage_stock();
                $qty_raw  = $wc_product->get_stock_quantity(); // may be null
                $qty      = is_null($qty_raw) ? null : (int)$qty_raw;

                if (!$managing || $qty === null || $qty <= 0 || $qty > $threshold) {
                    break;
                }

                $text = $label;
                if ($show_count) {
                    $text .= ' (' . $qty . ')';
                }

                $content = '<span class="low_stock_badge ' . $element['type'] . '" aria-label="' . esc_attr($text) . '">' . esc_html($text) . '</span>';
                break;


            /* ================================
 * Backorder Message
 * ================================ */
            case 'backorder_message':
                $msg = isset($content_settings['label']) ? (string)$content_settings['label'] : 'Available on backorder';

                // Show when backorders are allowed (any mode: yes/notify)
                $allows = method_exists($wc_product, 'backorders_allowed') ? $wc_product->backorders_allowed() : in_array($wc_product->get_backorders(), array('yes', 'notify'), true);
                if (!$allows) {
                    break;
                }

                $content = '<div class="backorder_message ' . $element['type'] . '">' . esc_html($msg) . '</div>';
                break;

            // Inside switch ($element['type']) { ... }
            // Inside switch ($element['type']) { ... }
            case 'vendor_info':
                // Options
                $source       = isset($content_settings['source']) ? strtolower(trim($content_settings['source'])) : 'wp_user'; // wp_user|dokan|wcfm|wcvendors|auto
                $link_enable  = !isset($content_settings['link']) || $content_settings['link'] === 'on'; // default on
                $link_format  = isset($content_settings['link_format']) ? trim((string)$content_settings['link_format']) : '';
                $show_rating  = isset($content_settings['show_rating']) && $content_settings['show_rating'] === 'on';

                $product_id = $wc_product->get_id();
                $vendor_wp_id = (int) get_post_field('post_author', $product_id);

                $name = '';
                $url = '';
                $rating_html = '';
                $username = '';

                // helpers
                $render = function ($name, $url, $rating_html) use ($element, $link_enable) {
                    if ($name === '' && $rating_html === '') {
                        return '';
                    }
                    $label = esc_html($name);
                    if ($link_enable && $url) {
                        $label = '<a href="' . esc_url($url) . '">' . $label . '</a>';
                    }
                    return '<div class="vendor_info ' . $element['type'] . '">' . $label . ($rating_html ? ' ' . $rating_html : '') . '</div>';
                };

                $fallback_username_from_wp = function ($uid) {
                    $u = get_user_by('id', $uid);
                    if (!$u) return '';
                    // prefer nicename for URLs; fallback to user_login
                    return $u->user_nicename ?: $u->user_login;
                };

                $try_wp_user = function ($uid) use ($fallback_username_from_wp) {
                    $u = get_user_by('id', $uid);
                    if (!$u) return array('', '', '');
                    $name = $u->display_name ?: $u->user_login;
                    $url  = get_author_posts_url($uid);
                    $username = $fallback_username_from_wp($uid);
                    return array($name, $url, $username);
                };

                $try_dokan = function ($uid, $show_rating) use ($fallback_username_from_wp) {
                    if (!function_exists('dokan')) return array('', '', '', '');
                    $vendor = dokan()->vendor->get($uid);
                    if (!$vendor) return array('', '', '', '');
                    $name = $vendor->get_shop_name();
                    $url  = $vendor->get_shop_url();
                    // Try shop slug for username, fallback to WP nicename
                    $username = method_exists($vendor, 'get_shop_slug') ? $vendor->get_shop_slug() : $fallback_username_from_wp($uid);

                    $rating_html = '';
                    if ($show_rating && function_exists('dokan_get_seller_rating')) {
                        $rt = dokan_get_seller_rating($uid); // ['rating'=>float, 'count'=>int]
                        if (!empty($rt['rating'])) {
                            $rating_html = wc_get_rating_html((float)$rt['rating']);
                        }
                    }
                    return array($name, $url, $username, $rating_html);
                };

                $try_wcfm = function ($uid, $show_rating) use ($fallback_username_from_wp) {
                    if (!function_exists('wcfmmp_get_store')) return array('', '', '', '');
                    $store = wcfmmp_get_store($uid);
                    if (!$store) return array('', '', '', '');
                    $name = method_exists($store, 'get_shop_name') ? $store->get_shop_name() : '';
                    $url  = method_exists($store, 'get_shop_url')  ? $store->get_shop_url()  : '';
                    // WCFM doesn't expose slug universally; fallback to WP nicename
                    $username = $fallback_username_from_wp($uid);

                    $rating_html = '';
                    if ($show_rating) {
                        $avg = method_exists($store, 'get_avg_rating') ? (float)$store->get_avg_rating() : 0;
                        if ($avg > 0) {
                            $rating_html = wc_get_rating_html($avg);
                        }
                    }
                    return array($name, $url, $username, $rating_html);
                };

                $try_wcv = function ($uid) use ($fallback_username_from_wp) {
                    if (!class_exists('WCV_Vendors')) return array('', '', '', '');
                    $name = method_exists('WCV_Vendors', 'get_vendor_shop_name') ? WCV_Vendors::get_vendor_shop_name($uid) : '';
                    $url  = method_exists('WCV_Vendors', 'get_vendor_shop_page') ? WCV_Vendors::get_vendor_shop_page($uid) : '';
                    $username = $fallback_username_from_wp($uid);
                    return array($name, $url, $username, '');
                };

                // Resolve by selected source (with graceful fallback)
                switch ($source) {
                    case 'dokan':
                        list($name, $url, $username, $rating_html) = $try_dokan($vendor_wp_id, $show_rating);
                        if ($name === '') {
                            list($name, $url, $username) = $try_wp_user($vendor_wp_id);
                        }
                        break;

                    case 'wcfm':
                        list($name, $url, $username, $rating_html) = $try_wcfm($vendor_wp_id, $show_rating);
                        if ($name === '') {
                            list($name, $url, $username) = $try_wp_user($vendor_wp_id);
                        }
                        break;

                    case 'wcvendors':
                        list($name, $url, $username, $rating_html) = $try_wcv($vendor_wp_id);
                        if ($name === '') {
                            list($name, $url, $username) = $try_wp_user($vendor_wp_id);
                        }
                        break;

                    case 'auto':
                        list($name, $url, $username, $rating_html) = $try_dokan($vendor_wp_id, $show_rating);
                        if ($name === '') list($name, $url, $username, $rating_html) = $try_wcfm($vendor_wp_id, $show_rating);
                        if ($name === '') list($name, $url, $username, $rating_html) = $try_wcv($vendor_wp_id);
                        if ($name === '') list($name, $url, $username) = $try_wp_user($vendor_wp_id);
                        break;

                    case 'wp_user':
                    default:
                        list($name, $url, $username) = $try_wp_user($vendor_wp_id);
                        break;
                }

                // Build custom link if requested
                if ($link_enable) {
                    if ($link_format !== '') {
                        // Replace placeholders {id} and {username}
                        $final_url = strtr($link_format, array(
                            '{id}'       => (string) $vendor_wp_id,
                            '{username}' => (string) $username,
                        ));
                        // Support relative URLs by making them site-absolute
                        if (strpos($final_url, 'http://') !== 0 && strpos($final_url, 'https://') !== 0) {
                            $final_url = home_url('/' . ltrim($final_url, '/\\'));
                        }
                        $url = $final_url;
                    }
                    // else: keep detected $url (vendor page / author archive)
                } else {
                    $url = '';
                }

                $content = $render($name, $url, $rating_html);
                break;



            /* ================================
 * Shortcode Renderer
 * ================================ */
            case 'shortcode':
                $code = isset($content_settings['code']) ? (string)$content_settings['code'] : '';
                if ($code === '') {
                    break;
                }
                // Execute shortcode
                $sc_html = do_shortcode($code);
                if ($sc_html === '') {
                    break;
                }

                $content = '<div class="shortcode ' . $element['type'] . '">' . $sc_html . '</div>';
                break;


            /* ================================
 * Featured Badge
 * ================================ */
            case 'featured_badge':
                $label = isset($content_settings['label']) ? (string)$content_settings['label'] : 'Featured';
                if (!$wc_product->is_featured()) {
                    break;
                }

                $content = '<span class="featured_badge ' . $element['type'] . '" aria-label="' . esc_attr($label) . '">'
                    . esc_html($label)
                    . '</span>';
                break;

            /* ================================
 * Free Shipping Badge (simple rule)
 * ================================ */
            case 'free_shipping_badge':
                $threshold     = isset($content_settings['price_threshold']) ? max(0, (float)$content_settings['price_threshold']) : 0.0;
                $class_slug    = isset($content_settings['shipping_class']) ? sanitize_title($content_settings['shipping_class']) : 'free-shipping';
                $label         = isset($content_settings['label']) ? (string)$content_settings['label'] : 'Free Shipping';

                // Check by price threshold
                $eligible_price = false;
                if ($threshold > 0) {
                    if ($wc_product->is_type('variable')) {
                        $min_price = (float) $wc_product->get_variation_price('min', true);
                        $eligible_price = ($min_price >= $threshold);
                    } else {
                        $price = (float) $wc_product->get_price();
                        $eligible_price = ($price >= $threshold);
                    }
                }

                // Check by shipping class
                $eligible_class = false;
                $sc_slug = $wc_product->get_shipping_class(); // slug or ''
                if ($sc_slug && $class_slug && $sc_slug === $class_slug) {
                    $eligible_class = true;
                }

                if (!($eligible_price || $eligible_class)) {
                    break;
                }

                $content = '<span class="free_shipping_badge ' . $element['type'] . '">' . esc_html($label) . '</span>';
                break;


            /* ================================
 * Social Share (Facebook, X, Pinterest, Copy)
 * ================================ */
            case 'social_share':
                $show_copy = !isset($content_settings['show_copy']) || $content_settings['show_copy'] === 'on';
                $url   = get_permalink($wc_product->get_id());
                if (!$url) {
                    break;
                }
                $title = $wc_product->get_name();
                $img   = wp_get_attachment_image_url($wc_product->get_image_id(), 'full');

                $fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url);
                $tw = 'https://twitter.com/intent/tweet?text=' . rawurlencode($title) . '&url=' . rawurlencode($url);
                $pi = 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($url) . '&description=' . rawurlencode($title) . ($img ? '&media=' . rawurlencode($img) : '');

                $links  = '<a class="share fb" href="' . esc_url($fb) . '" target="_blank" rel="noopener">' . esc_html__('Facebook', 'ultimate-product-table-for-woocommerce') . '</a>';
                $links .= ' <a class="share tw" href="' . esc_url($tw) . '" target="_blank" rel="noopener">' . esc_html__('X', 'ultimate-product-table-for-woocommerce') . '</a>';
                $links .= ' <a class="share pi" href="' . esc_url($pi) . '" target="_blank" rel="noopener">' . esc_html__('Pinterest', 'ultimate-product-table-for-woocommerce') . '</a>';

                if ($show_copy) {
                    $btn_id = 'copylink-' . $wc_product->get_id() . '-' . $row_count;
                    $links .= ' <button type="button" id="' . esc_attr($btn_id) . '" class="share copy">' . esc_html__('Copy Link', 'ultimate-product-table-for-woocommerce') . '</button>';
                    $links .= '<script>(function(){var b=document.getElementById(' . json_encode($btn_id) . '); if(!b) return; b.addEventListener("click",function(){var u=' . json_encode($url) . '; if(navigator.clipboard){navigator.clipboard.writeText(u).then(function(){b.textContent="' . esc_js(__('Copied!', 'ultimate-product-table-for-woocommerce')) . '"; setTimeout(function(){b.textContent="' . esc_js(__('Copy Link', 'ultimate-product-table-for-woocommerce')) . '";},1200);});}else{var i=document.createElement("input");i.value=u;document.body.appendChild(i);i.select();try{document.execCommand("copy");b.textContent="' . esc_js(__('Copied!', 'ultimate-product-table-for-woocommerce')) . '"; setTimeout(function(){b.textContent="' . esc_js(__('Copy Link', 'ultimate-product-table-for-woocommerce')) . '";},1200);}catch(e){}document.body.removeChild(i);}});})();</script>';
                }

                $content = '<div class="social_share ' . $element['type'] . '">' . $links . '</div>';
                break;


            /* ================================
 * QR Code (Permalink)
 * ================================ */
            case 'qr_permalink':
                $label = isset($content_settings['label']) ? (string)$content_settings['label'] : '';
                $plink = get_permalink($wc_product->get_id());
                if (!$plink) {
                    break;
                }

                $qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=' . rawurlencode($plink);
                $content  = '<div class="qr_permalink ' . $element['type'] . '">';
                if ($label !== '') {
                    $content .= '<div class="qr_label">' . esc_html($label) . '</div>';
                }
                $content .= '<img src="' . esc_url($qr_src) . '" alt="' . esc_attr__('QR Code', 'ultimate-product-table-for-woocommerce') . '" loading="lazy" />';
                $content .= '</div>';
                break;


            /* ================================
            * Custom Link
            * ================================ */
            case 'custom_link':
                $href  = isset($content_settings['url']) ? trim((string)$content_settings['url']) : '';
                $label = isset($content_settings['label']) ? (string)$content_settings['label'] : 'Size guide';
                if ($href === '') {
                    break;
                }

                // Support relative URLs
                if (strpos($href, 'http://') !== 0 && strpos($href, 'https://') !== 0 && strpos($href, '#') !== 0 && strpos($href, 'mailto:') !== 0 && strpos($href, 'tel:') !== 0) {
                    $href = home_url('/' . ltrim($href, '/\\'));
                }

                $content = '<div class="custom_link ' . $element['type'] . '"><a href="' . esc_url($href) . '">' . esc_html($label) . '</a></div>';
                break;

            // Add inside render_cell_content_html() switch:
            case 'product_meta_field':
                $cs = isset($element['settings']['content_settings']) ? $element['settings']['content_settings'] : [];

                $meta_key    = isset($cs['meta_key']) ? trim($cs['meta_key']) : '';
                $prefix_text = isset($cs['prefix_text']) ? $cs['prefix_text'] : '';
                $suffix_text = isset($cs['suffix_text']) ? $cs['suffix_text'] : '';
                $format      = isset($cs['format']) ? $cs['format'] : 'text';
                $date_format = isset($cs['date_format']) ? $cs['date_format'] : 'Y-m-d';
                $fallback    = isset($cs['fallback']) ? $cs['fallback'] : '';
                $allow_html  = (isset($cs['allow_html']) && $cs['allow_html'] === 'on');

                if ($meta_key === '') {
                    $content = '';
                    break;
                }

                // Get meta
                $raw = $wc_product->get_meta($meta_key, true);

                // Normalize arrays/objects
                if (is_array($raw) || is_object($raw)) {
                    $raw = wp_json_encode($raw);
                }
                $raw = is_string($raw) ? trim($raw) : $raw;

                // Handle missing
                if ($raw === '' || $raw === null) {
                    if ($fallback === '') {
                        $content = '';
                        break;
                    }
                    $value_html = esc_html($fallback);
                } else {
                    switch ($format) {
                        case 'number':
                            $num = is_numeric($raw) ? (float) $raw : null;
                            if ($num === null) {
                                $value_html = esc_html($fallback);
                            } else {
                                // Localized number
                                $value_html = esc_html(number_format_i18n($num));
                            }
                            break;

                        case 'date':
                            // Accept timestamp or parseable date string
                            if (is_numeric($raw)) {
                                $ts = (int) $raw;
                                // If it looks like milliseconds, convert
                                if ($ts > 2000000000) {
                                    $ts = (int) floor($ts / 1000);
                                }
                            } else {
                                $ts = strtotime($raw);
                            }
                            if (!$ts) {
                                $value_html = esc_html($fallback);
                            } else {
                                $value_html = esc_html(date_i18n($date_format, $ts));
                            }
                            break;

                        case 'price':
                            // Strip non-numeric except dot/comma/minus
                            $clean = preg_replace('/[^\d\.\,\-]/', '', (string) $raw);
                            // Convert comma decimal to dot if needed
                            if (strpos($clean, ',') !== false && strpos($clean, '.') === false) {
                                $clean = str_replace(',', '.', $clean);
                            }
                            $amount = is_numeric($clean) ? (float) $clean : null;
                            if ($amount === null) {
                                $value_html = esc_html($fallback);
                            } else {
                                $value_html = wc_price($amount);
                            }
                            break;

                        case 'text':
                        default:
                            if ($allow_html) {
                                $value_html = wp_kses_post($raw);
                            } else {
                                $value_html = esc_html($raw);
                            }
                            break;
                    }
                }

                $content  = '<div class="product_meta_field ' . esc_attr($element['type']) . ' product_meta_field-' . sanitize_html_class($meta_key) . '">';
                $content .= '<span class="prefix">' . esc_html($prefix_text) . '</span>';
                $content .= $value_html;
                $content .= '<span class="suffix">' . esc_html($suffix_text) . '</span>';
                $content .= '</div>';
                break;


            // container

            case 'container':
                $columns = !empty($element['columns']) && is_array($element['columns']) ? $element['columns'] : [];

                $content  = '<div class="plugincy-row" style="display:flex;justify-content:space-around;gap:10px;">';

                foreach ($columns as $colIndex => $colElements) {
                    $content .= sprintf(
                        '<div class="plugincy-container-col" style="flex-basis:50%;" data-container-col="%d">',
                        esc_attr($colIndex)
                    );

                    if (is_array($colElements)) {
                        foreach ($colElements as $childElement) {
                            $content .= $this->render_cell_content_html($wc_product, $childElement, $row_count);
                        }
                    }

                    $content .= '</div>';
                }

                $content .= '</div>';
                break;
        }

        return $content;
    }
}
