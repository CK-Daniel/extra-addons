<?php
/**
 * Direct test for get_addons functionality
 * Usage: wp eval-file test-get-addons-direct.php
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
    die( 'WordPress not loaded' );
}

echo "=== Testing Get Addons Functionality ===\n\n";

// Check if class exists
if ( ! class_exists( 'WC_Product_Addons_Conditional_Logic' ) ) {
    echo "Error: WC_Product_Addons_Conditional_Logic class not found\n";
    exit;
}

// Get instance
$conditional_logic = WC_Product_Addons_Conditional_Logic::get_instance();

// Test 1: Get Global Addons
echo "1. Testing Global Addons:\n";
echo "-----------------------\n";

$reflection = new ReflectionClass($conditional_logic);
$method = $reflection->getMethod('get_global_addons');
$method->setAccessible(true);
$global_addons = $method->invoke($conditional_logic);

if ( empty( $global_addons ) ) {
    echo "No global addons found.\n";
    
    // Check if any global addon posts exist
    $global_addon_posts = get_posts( array(
        'post_type' => 'global_product_addon',
        'post_status' => 'publish',
        'numberposts' => -1
    ) );
    
    echo "Global addon posts found: " . count( $global_addon_posts ) . "\n";
    
    if ( ! empty( $global_addon_posts ) ) {
        foreach ( $global_addon_posts as $post ) {
            echo "- " . $post->post_title . " (ID: " . $post->ID . ")\n";
            $addons = get_post_meta( $post->ID, '_product_addons', true );
            if ( is_array( $addons ) ) {
                echo "  Addons count: " . count( $addons ) . "\n";
                foreach ( $addons as $addon ) {
                    echo "  - " . ( isset( $addon['name'] ) ? $addon['name'] : 'No name' ) . "\n";
                }
            } else {
                echo "  No addons metadata\n";
            }
        }
    }
} else {
    echo "Found " . count( $global_addons ) . " global addons:\n";
    foreach ( $global_addons as $addon ) {
        echo "- " . $addon['name'] . " (Type: " . $addon['type'] . ", ID: " . $addon['id'] . ")\n";
    }
}

echo "\n";

// Test 2: Get Product Addons for a specific product
echo "2. Testing Product Addons:\n";
echo "-------------------------\n";

// Get a product with addons
$products = get_posts( array(
    'post_type' => 'product',
    'posts_per_page' => 5,
    'meta_query' => array(
        array(
            'key' => '_product_addons',
            'compare' => 'EXISTS'
        )
    )
) );

if ( empty( $products ) ) {
    echo "No products with addons found.\n";
} else {
    foreach ( $products as $product_post ) {
        echo "\nProduct: " . $product_post->post_title . " (ID: " . $product_post->ID . ")\n";
        
        $method = $reflection->getMethod('get_product_addons');
        $method->setAccessible(true);
        $product_addons = $method->invoke($conditional_logic, $product_post->ID);
        
        if ( empty( $product_addons ) ) {
            echo "  No addons found for this product.\n";
            
            // Debug: Check raw metadata
            $raw_addons = get_post_meta( $product_post->ID, '_product_addons', true );
            if ( is_array( $raw_addons ) ) {
                echo "  Raw addons count: " . count( $raw_addons ) . "\n";
            }
        } else {
            echo "  Found " . count( $product_addons ) . " addons:\n";
            foreach ( $product_addons as $addon ) {
                echo "  - " . $addon['name'] . " (Type: " . $addon['type'] . ", ID: " . $addon['id'] . ")\n";
            }
        }
    }
}

echo "\n";

// Test 3: Simulate AJAX call
echo "3. Testing AJAX Response Format:\n";
echo "--------------------------------\n";

// Simulate POST data
$_POST['context'] = 'all';
$_POST['product_id'] = 0;

// Create a mock AJAX response
$response_data = array();

// Get global addons
$global_addons = $method->invoke($conditional_logic);
if ( ! empty( $global_addons ) ) {
    $response_data['global'] = array(
        'label' => 'Global Add-ons',
        'addons' => $global_addons
    );
}

// Output sample response
echo "Sample AJAX response:\n";
echo json_encode( array( 'success' => true, 'data' => $response_data ), JSON_PRETTY_PRINT );

echo "\n\n=== End Test ===\n";
?>