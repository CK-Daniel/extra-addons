<?php
/**
 * Debug script to check if AJAX handlers are registered
 * Run this with: wp eval-file debug-handlers.php
 */

echo "=== Debugging AJAX Handlers ===\n";

// Check if WordPress is loaded
if ( ! function_exists( 'wp_ajax_test_simple_ajax' ) ) {
    echo "WordPress not fully loaded\n";
} else {
    echo "WordPress loaded\n";
}

// Check if our class exists
if ( class_exists( 'WC_Product_Addons_Conditional_Logic' ) ) {
    echo "✓ WC_Product_Addons_Conditional_Logic class exists\n";
    
    $instance = WC_Product_Addons_Conditional_Logic::get_instance();
    if ( $instance ) {
        echo "✓ Instance created successfully\n";
    } else {
        echo "✗ Failed to create instance\n";
    }
} else {
    echo "✗ WC_Product_Addons_Conditional_Logic class does not exist\n";
}

// Check if WooCommerce is active
if ( class_exists( 'WooCommerce' ) ) {
    echo "✓ WooCommerce is active\n";
} else {
    echo "✗ WooCommerce is not active\n";
}

// Check current user capabilities
$user_id = get_current_user_id();
echo "Current user ID: " . $user_id . "\n";

if ( $user_id ) {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        echo "✓ User can manage WooCommerce\n";
    } else {
        echo "✗ User cannot manage WooCommerce\n";
    }
} else {
    echo "No user logged in\n";
}

// Check global $wp_filter to see if our handlers are registered
global $wp_filter;

$ajax_actions = array(
    'wp_ajax_test_simple_ajax',
    'wp_ajax_wc_pao_get_addons',
    'wp_ajax_wc_pao_get_addon_options'
);

foreach ( $ajax_actions as $action ) {
    if ( isset( $wp_filter[$action] ) && ! empty( $wp_filter[$action] ) ) {
        echo "✓ $action is registered\n";
        foreach ( $wp_filter[$action] as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
                    echo "  - Priority $priority: " . get_class( $callback['function'][0] ) . "::" . $callback['function'][1] . "\n";
                } else {
                    echo "  - Priority $priority: " . (is_string($callback['function']) ? $callback['function'] : 'callable') . "\n";
                }
            }
        }
    } else {
        echo "✗ $action is NOT registered\n";
    }
}

echo "\n=== Testing AJAX Handler Execution ===\n";

// Simulate an AJAX request
$_POST['action'] = 'test_simple_ajax';

// Check if the action exists and can be called
if ( has_action( 'wp_ajax_test_simple_ajax' ) ) {
    echo "✓ wp_ajax_test_simple_ajax action has handlers\n";
    
    // Try to execute the action
    ob_start();
    try {
        do_action( 'wp_ajax_test_simple_ajax' );
        $output = ob_get_clean();
        echo "Action output: " . $output . "\n";
    } catch ( Exception $e ) {
        ob_end_clean();
        echo "Error executing action: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ wp_ajax_test_simple_ajax action has no handlers\n";
}

echo "\n=== End Debug ===\n";
?>