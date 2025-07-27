<?php

/**
 * class_helper.php
 *
 * @package Product Table for WooCommerce
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
                $html .= '<th>' . esc_html($header) . '</th>';
            }
            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';
        $row_count = 1;
        foreach ($products as $product) {
            $html .= '<tr>';
            foreach ($table_data['rows'][0] as $cell) {
                $html .= '<td>' . $this->render_cell_content($cell, $product, $row_count) . '</td>';
            }
            $html .= '</tr>';
            $row_count++;
        }
        $html .= '</tbody>';

        if ($table_data['show_footer']) {
            $html .= '<tfoot><tr>';
            foreach ($table_data['footers'] as $footer) {
                $html .= '<td>' . esc_html($footer) . '</td>';
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
                $content = '<div class="plugincy-view-details"><a href="' . get_permalink($wc_product->get_id()) . '" class="'.(isset($content_settings["button_type"]) ? esc_html($content_settings["button_type"]) : ''). $element['type'] . '">'.(isset($content_settings["button_text"]) ? esc_html($content_settings["button_text"]) : 'View Details').'</a></div>';
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
        }

        return $content;
    }
}
