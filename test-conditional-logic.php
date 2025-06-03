<?php
/**
 * Test Conditional Logic Integration
 * 
 * This file can be used to test the conditional logic system.
 * Place it in your WordPress root and access it directly.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Test product ID (change this to your test product)
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if (!$product_id) {
    echo '<h2>Product Add-ons Conditional Logic Test</h2>';
    echo '<p>Please provide a product ID in the URL: ?product_id=123</p>';
    
    // Show recent products
    $products = wc_get_products(array('limit' => 10, 'orderby' => 'date', 'order' => 'DESC'));
    if ($products) {
        echo '<h3>Recent Products:</h3>';
        echo '<ul>';
        foreach ($products as $product) {
            echo '<li><a href="?product_id=' . $product->get_id() . '">' . $product->get_name() . ' (ID: ' . $product->get_id() . ')</a></li>';
        }
        echo '</ul>';
    }
    exit;
}

$product = wc_get_product($product_id);
if (!$product) {
    wp_die('Product not found');
}

// Get addons
$addons = WC_Product_Addons_Helper::get_product_addons($product_id);

// Get conditional logic instance
$conditional_logic = WC_Product_Addons_Conditional_Logic::get_instance();

// Get conditional data
$conditional_data = $conditional_logic->get_product_conditional_data($product_id);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Conditional Logic Test - <?php echo esc_html($product->get_name()); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; }
        .addon { background: #f5f5f5; padding: 10px; margin: 10px 0; }
        .conditional-logic { background: #e8f4f8; padding: 10px; margin: 5px 0; }
        pre { background: #f0f0f0; padding: 10px; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>Conditional Logic Test: <?php echo esc_html($product->get_name()); ?></h1>
    
    <div class="section">
        <h2>Product Information</h2>
        <p><strong>Product ID:</strong> <?php echo $product_id; ?></p>
        <p><strong>Product Type:</strong> <?php echo $product->get_type(); ?></p>
        <p><strong>Product URL:</strong> <a href="<?php echo get_permalink($product_id); ?>" target="_blank">View Product</a></p>
    </div>
    
    <div class="section">
        <h2>Product Add-ons (<?php echo count($addons); ?> found)</h2>
        <?php if (empty($addons)): ?>
            <p class="warning">No add-ons found for this product.</p>
        <?php else: ?>
            <?php foreach ($addons as $index => $addon): ?>
                <div class="addon">
                    <h3>Addon <?php echo $index + 1; ?>: <?php echo esc_html($addon['name']); ?></h3>
                    <p><strong>Type:</strong> <?php echo esc_html($addon['type']); ?></p>
                    <p><strong>Display:</strong> <?php echo esc_html(isset($addon['display']) ? $addon['display'] : 'default'); ?></p>
                    <p><strong>Required:</strong> <?php echo !empty($addon['required']) ? '<span class="success">Yes</span>' : 'No'; ?></p>
                    <p><strong>Field Name:</strong> <?php echo esc_html($addon['field_name']); ?></p>
                    
                    <?php if (!empty($addon['conditional_logic'])): ?>
                        <div class="conditional-logic">
                            <h4>Conditional Logic Rules:</h4>
                            <pre><?php echo esc_html(json_encode($addon['conditional_logic'], JSON_PRETTY_PRINT)); ?></pre>
                        </div>
                    <?php else: ?>
                        <p class="warning">No conditional logic rules for this addon.</p>
                    <?php endif; ?>
                    
                    <?php if (!empty($addon['options'])): ?>
                        <h4>Options:</h4>
                        <ul>
                            <?php foreach ($addon['options'] as $option): ?>
                                <li>
                                    <?php echo esc_html($option['label']); ?>
                                    <?php if (!empty($option['price'])): ?>
                                        (<?php echo wc_price($option['price']); ?>)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Conditional Logic Data</h2>
        <?php if (!empty($conditional_data)): ?>
            <pre><?php echo esc_html(json_encode($conditional_data, JSON_PRETTY_PRINT)); ?></pre>
        <?php else: ?>
            <p class="warning">No conditional logic data found for this product.</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Database Check</h2>
        <?php
        global $wpdb;
        
        // Check if tables exist
        $rules_table = $wpdb->prefix . 'wc_product_addon_rules';
        $conditions_table = $wpdb->prefix . 'wc_product_addon_conditions';
        $actions_table = $wpdb->prefix . 'wc_product_addon_actions';
        
        $rules_exists = $wpdb->get_var("SHOW TABLES LIKE '$rules_table'") === $rules_table;
        $conditions_exists = $wpdb->get_var("SHOW TABLES LIKE '$conditions_table'") === $conditions_table;
        $actions_exists = $wpdb->get_var("SHOW TABLES LIKE '$actions_table'") === $actions_table;
        ?>
        
        <p><strong>Rules Table:</strong> <?php echo $rules_exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></p>
        <p><strong>Conditions Table:</strong> <?php echo $conditions_exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></p>
        <p><strong>Actions Table:</strong> <?php echo $actions_exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></p>
        
        <?php if ($rules_exists): ?>
            <?php
            $rule_count = $wpdb->get_var("SELECT COUNT(*) FROM $rules_table WHERE (rule_type = 'product' AND scope_id = $product_id) OR rule_type = 'global'");
            ?>
            <p><strong>Rules for this product:</strong> <?php echo $rule_count; ?></p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>JavaScript Integration Check</h2>
        <p>Check the browser console for JavaScript errors.</p>
        <p>The following scripts should be loaded on the product page:</p>
        <ul>
            <li>woocommerce-addons-extra-digital.js</li>
            <li>conditional-logic.js</li>
        </ul>
    </div>
    
    <div class="section">
        <h2>Recommendations</h2>
        <?php if (empty($addons)): ?>
            <p>1. Add some product add-ons to this product first.</p>
        <?php endif; ?>
        
        <?php if (!$rules_exists || !$conditions_exists || !$actions_exists): ?>
            <p>2. <span class="error">Database tables are missing!</span> Run the installation script or use the force-create-tables.php tool.</p>
        <?php endif; ?>
        
        <?php if (!empty($addons) && empty($conditional_data)): ?>
            <p>3. Create conditional logic rules for this product in the admin panel.</p>
        <?php endif; ?>
    </div>
</body>
</html>