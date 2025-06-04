<?php
/**
 * Direct test of conditional logic class
 */

// Find WordPress
$wp_paths = array(
    '/workspaces/extra-addons/../../../wp-config.php',
    '/var/www/html/wp-config.php',
    '../wp-config.php',
    '../../wp-config.php',
    '../../../wp-config.php'
);

$wp_found = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_found = true;
        break;
    }
}

if (!$wp_found) {
    // Try to bootstrap WordPress manually
    $wp_load_paths = array(
        '/workspaces/extra-addons/../../../wp-load.php',
        '/var/www/html/wp-load.php'
    );
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_found = true;
            break;
        }
    }
}

if (!$wp_found) {
    echo "WordPress not found. Manual testing...\n";
    
    // Include our class manually
    require_once(__DIR__ . '/includes/conditional-logic/class-wc-product-addons-conditional-logic.php');
    
    echo "✓ Class file included\n";
    
    if (class_exists('WC_Product_Addons_Conditional_Logic')) {
        echo "✓ Class exists\n";
    } else {
        echo "✗ Class does not exist\n";
    }
    
    exit;
}

echo "WordPress loaded successfully\n";

// Now test our class
if (class_exists('WC_Product_Addons_Conditional_Logic')) {
    echo "✓ WC_Product_Addons_Conditional_Logic class exists\n";
    
    $instance = WC_Product_Addons_Conditional_Logic::get_instance();
    echo "✓ Instance created\n";
    
    // Test if methods exist
    if (method_exists($instance, 'ajax_get_addons')) {
        echo "✓ ajax_get_addons method exists\n";
    } else {
        echo "✗ ajax_get_addons method missing\n";
    }
    
    if (method_exists($instance, 'ajax_get_addon_options')) {
        echo "✓ ajax_get_addon_options method exists\n";
    } else {
        echo "✗ ajax_get_addon_options method missing\n";
    }
    
} else {
    echo "✗ WC_Product_Addons_Conditional_Logic class does not exist\n";
}

// Check for WooCommerce
if (class_exists('WooCommerce')) {
    echo "✓ WooCommerce is loaded\n";
} else {
    echo "✗ WooCommerce is not loaded\n";
}

echo "Test complete\n";
?>