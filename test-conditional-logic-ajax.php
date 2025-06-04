<?php
/**
 * Test the conditional logic AJAX handlers
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Set current user as admin
wp_set_current_user( 1 );

// Initialize the conditional logic class
if ( class_exists( 'WC_Product_Addons_Conditional_Logic' ) ) {
    $conditional_logic = WC_Product_Addons_Conditional_Logic::get_instance();
    
    echo "Testing get_addons AJAX handler...\n\n";
    
    // Simulate AJAX request
    $_POST['action'] = 'wc_pao_get_addons';
    $_POST['context'] = 'all';
    $_POST['security'] = wp_create_nonce( 'wc_pao_conditional_logic' );
    
    // Capture output
    ob_start();
    $conditional_logic->ajax_get_addons();
    $output = ob_get_clean();
    
    echo "Response:\n";
    echo $output . "\n\n";
    
    // Decode and display
    $response = json_decode( $output, true );
    if ( $response ) {
        echo "Decoded response:\n";
        print_r( $response );
    }
    
    // Test getting addon options
    if ( ! empty( $response['data'] ) ) {
        foreach ( $response['data'] as $group ) {
            if ( ! empty( $group['addons'] ) ) {
                $first_addon = $group['addons'][0];
                echo "\n\nTesting get_addon_options for addon: " . $first_addon['name'] . " (ID: " . $first_addon['id'] . ")\n";
                
                $_POST['action'] = 'wc_pao_get_addon_options';
                $_POST['addon_id'] = $first_addon['id'];
                
                ob_start();
                $conditional_logic->ajax_get_addon_options();
                $options_output = ob_get_clean();
                
                echo "Options response:\n";
                echo $options_output . "\n";
                
                $options_response = json_decode( $options_output, true );
                if ( $options_response ) {
                    echo "Decoded options:\n";
                    print_r( $options_response );
                }
                
                break;
            }
        }
    }
} else {
    echo "WC_Product_Addons_Conditional_Logic class not found!\n";
}