<?php
/**
 * Product Add-ons Exclude Global Column Functions
 * 
 * Add this code to your theme's functions.php file or include it as a separate file
 * These functions add a column to the WooCommerce products list showing exclude add-ons status
 */

// 1) Add the column header
add_filter( 'manage_edit-product_columns', function( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'price' === $key ) {
            $new['exclude_global_addons'] = __( 'Exclude global add-ons', 'woocommerce-product-addons' );
        }
    }
    return $new;
} );

// 2) Render the column
add_action( 'manage_product_posts_custom_column', function( $column, $post_id ) {
    if ( 'exclude_global_addons' !== $column ) {
        return;
    }

    // Get the product and check the meta value
    $product = wc_get_product( $post_id );
    if ( $product ) {
        $excluded = $product->get_meta( '_product_addons_exclude_global' );
    } else {
        // Fallback to direct meta query
        $excluded = get_post_meta( $post_id, '_product_addons_exclude_global', true );
    }

    // The meta value is stored as '0' or '1' string
    $is_excluded = ! empty( $excluded ) && '1' === $excluded;

    // Optional: dump the raw value for debugging — comment out after you verify!
    // printf( '<small style="color:#999;">meta: %s</small><br>', esc_html( var_export( $excluded, true ) ) );

    // Display checkmark if excluded, dash if not
    echo $is_excluded ? '✅' : '—';
}, 10, 2 );

// 3) Make it sortable
add_filter( 'manage_edit-product_sortable_columns', function( $columns ) {
    $columns['exclude_global_addons'] = 'exclude_global_addons';
    return $columns;
} );

add_action( 'pre_get_posts', function( $query ) {
    if (
        is_admin()
        && 'product' === $query->get( 'post_type' )
        && 'exclude_global_addons' === $query->get( 'orderby' )
    ) {
        // Use a meta query that includes all products
        $query->set( 'meta_query', array(
            'exclude_clause' => array(
                'relation' => 'OR',
                array(
                    'key' => '_product_addons_exclude_global',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => '_product_addons_exclude_global',
                    'value' => '0',
                    'compare' => '=',
                ),
                array(
                    'key' => '_product_addons_exclude_global',
                    'value' => '1',
                    'compare' => '=',
                ),
            ),
        ) );
        
        // Custom orderby to handle NULL values properly
        $query->set( 'orderby', 'exclude_clause' );
    }
} );

// Add custom orderby SQL for proper NULL handling
add_filter( 'posts_orderby', function( $orderby, $query ) {
    if (
        is_admin()
        && 'product' === $query->get( 'post_type' )
        && 'exclude_global_addons' === $query->get( 'orderby' )
    ) {
        global $wpdb;
        $order = strtoupper( $query->get( 'order' ) ) === 'DESC' ? 'DESC' : 'ASC';
        
        // Sort with NULL/0 values first when ASC, last when DESC
        $orderby = "CASE 
            WHEN mt1.meta_value IS NULL THEN 0 
            WHEN mt1.meta_value = '' THEN 0 
            WHEN mt1.meta_value = '0' THEN 0 
            ELSE 1 
        END {$order}, {$wpdb->posts}.post_title ASC";
    }
    return $orderby;
}, 10, 2 );

// 4) Optional: Add a filter dropdown to filter products by exclude status
add_action( 'restrict_manage_posts', function() {
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
} );

// Apply the filter
add_filter( 'parse_query', function( $query ) {
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
} );

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

// 5) Add CSS to make the column wider
add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( $screen && 'edit-product' === $screen->id ) {
        ?>
        <style>
            .wp-list-table .column-exclude_global_addons {
                width: 140px !important;
                text-align: center;
            }
            @media screen and (max-width: 782px) {
                .wp-list-table .column-exclude_global_addons {
                    display: none !important;
                }
            }
        </style>
        <?php
    }
} );