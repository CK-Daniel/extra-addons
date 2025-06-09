<?php
/**
 * Product Add-ons Exclude Global Column
 * 
 * This file adds a custom column to the WooCommerce products list table
 * showing whether each product has "Exclude add-ons" enabled or not.
 * 
 * Usage: Include this file in your theme's functions.php or as a must-use plugin
 */

// Add custom column to product list
add_filter( 'manage_product_posts_columns', 'add_exclude_addons_column', 20 );
function add_exclude_addons_column( $columns ) {
    $new_columns = array();
    
    // Insert the new column after the product name
    foreach ( $columns as $key => $column ) {
        $new_columns[$key] = $column;
        
        if ( 'name' === $key ) {
            $new_columns['exclude_addons'] = __( 'Exclude Add-ons', 'woocommerce-product-addons' );
        }
    }
    
    return $new_columns;
}

// Populate the custom column with data
add_action( 'manage_product_posts_custom_column', 'populate_exclude_addons_column', 10, 2 );
function populate_exclude_addons_column( $column, $post_id ) {
    if ( 'exclude_addons' === $column ) {
        $product = wc_get_product( $post_id );
        
        if ( $product ) {
            $exclude_global = $product->get_meta( '_product_addons_exclude_global' );
            
            // The meta value is stored as '0' or '1' string
            if ( ! empty( $exclude_global ) && '1' === $exclude_global ) {
                echo '<span style="color: #d63638; font-weight: bold;">✖ ' . __( 'Yes', 'woocommerce-product-addons' ) . '</span>';
            } else {
                echo '<span style="color: #46b450;">✓ ' . __( 'No', 'woocommerce-product-addons' ) . '</span>';
            }
        }
    }
}

// Make the column sortable
add_filter( 'manage_edit-product_sortable_columns', 'make_exclude_addons_column_sortable' );
function make_exclude_addons_column_sortable( $columns ) {
    $columns['exclude_addons'] = 'exclude_addons';
    return $columns;
}

// Handle the sorting
add_action( 'pre_get_posts', 'exclude_addons_column_orderby' );
function exclude_addons_column_orderby( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    
    if ( 'exclude_addons' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', '_product_addons_exclude_global' );
        $query->set( 'orderby', 'meta_value' );
    }
}

// Optional: Add a filter to show only products with/without exclude enabled
add_action( 'restrict_manage_posts', 'add_exclude_addons_filter_dropdown' );
function add_exclude_addons_filter_dropdown() {
    global $typenow;
    
    if ( 'product' === $typenow ) {
        $selected = isset( $_GET['exclude_addons_filter'] ) ? $_GET['exclude_addons_filter'] : '';
        ?>
        <select name="exclude_addons_filter" id="exclude_addons_filter">
            <option value=""><?php _e( 'All products', 'woocommerce-product-addons' ); ?></option>
            <option value="excluded" <?php selected( $selected, 'excluded' ); ?>><?php _e( 'Exclude add-ons: Yes', 'woocommerce-product-addons' ); ?></option>
            <option value="included" <?php selected( $selected, 'included' ); ?>><?php _e( 'Exclude add-ons: No', 'woocommerce-product-addons' ); ?></option>
        </select>
        <?php
    }
}

// Apply the filter
add_filter( 'parse_query', 'filter_products_by_exclude_addons' );
function filter_products_by_exclude_addons( $query ) {
    global $pagenow, $typenow;
    
    if ( 'edit.php' === $pagenow && 'product' === $typenow && isset( $_GET['exclude_addons_filter'] ) && '' !== $_GET['exclude_addons_filter'] ) {
        if ( 'excluded' === $_GET['exclude_addons_filter'] ) {
            $query->query_vars['meta_key'] = '_product_addons_exclude_global';
            $query->query_vars['meta_value'] = '1';
        } elseif ( 'included' === $_GET['exclude_addons_filter'] ) {
            $query->query_vars['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_product_addons_exclude_global',
                    'value' => '0',
                ),
                array(
                    'key' => '_product_addons_exclude_global',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }
    }
}

/**
 * Helper function to check if a product has exclude add-ons enabled
 * 
 * @param int $product_id Product ID
 * @return bool True if exclude is enabled, false otherwise
 */
function product_has_exclude_addons_enabled( $product_id ) {
    $product = wc_get_product( $product_id );
    
    if ( ! $product ) {
        return false;
    }
    
    $exclude_global = $product->get_meta( '_product_addons_exclude_global' );
    
    return ! empty( $exclude_global ) && '1' === $exclude_global;
}

/**
 * Get all products with exclude add-ons enabled
 * 
 * @return array Array of product IDs
 */
function get_products_with_exclude_addons() {
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => '_product_addons_exclude_global',
                'value' => '1',
            ),
        ),
    );
    
    $query = new WP_Query( $args );
    
    return $query->posts;
}

/**
 * Get count of products with exclude add-ons enabled
 * 
 * @return int Number of products
 */
function count_products_with_exclude_addons() {
    $args = array(
        'post_type' => 'product',
        'meta_query' => array(
            array(
                'key' => '_product_addons_exclude_global',
                'value' => '1',
            ),
        ),
    );
    
    $query = new WP_Query( $args );
    
    return $query->found_posts;
}