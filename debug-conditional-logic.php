<?php
/**
 * Debug Conditional Logic Script Loading
 * 
 * Place this in WordPress root and access directly to check script loading
 */

require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get current screen info
$screen = isset($_GET['screen']) ? $_GET['screen'] : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Conditional Logic Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Conditional Logic Debug Information</h1>
    
    <div class="status info">
        <h2>Script Loading Check</h2>
        <p><strong>Current Page:</strong> <?php echo isset($_GET['page']) ? esc_html($_GET['page']) : 'Not set'; ?></p>
        <p><strong>Script Should Load On:</strong> addon-conditional-logic</p>
    </div>
    
    <h2>JavaScript Localization Check</h2>
    <p>Check browser console for the following:</p>
    <pre>
console.log('wc_product_addons_params:', typeof wc_product_addons_params !== 'undefined' ? wc_product_addons_params : 'NOT DEFINED');
console.log('jQuery:', typeof jQuery !== 'undefined' ? 'LOADED' : 'NOT LOADED');
console.log('jQuery UI Sortable:', typeof jQuery !== 'undefined' && jQuery.fn.sortable ? 'LOADED' : 'NOT LOADED');
    </pre>
    
    <h2>Nonce Configuration</h2>
    <table>
        <tr>
            <th>Action</th>
            <th>Expected Nonce</th>
            <th>Generated Nonce</th>
        </tr>
        <tr>
            <td>Get Rules</td>
            <td>wc_pao_conditional_logic</td>
            <td><?php echo wp_create_nonce('wc_pao_conditional_logic'); ?></td>
        </tr>
        <tr>
            <td>Save Rule</td>
            <td>wc_pao_conditional_logic</td>
            <td><?php echo wp_create_nonce('wc_pao_conditional_logic'); ?></td>
        </tr>
    </table>
    
    <h2>Database Tables Check</h2>
    <?php
    global $wpdb;
    $tables = array(
        'wc_product_addon_rules',
        'wc_product_addon_conditions',
        'wc_product_addon_actions'
    );
    
    foreach ($tables as $table) {
        $full_table = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
        ?>
        <div class="status <?php echo $exists ? 'success' : 'error'; ?>">
            <strong><?php echo $full_table; ?>:</strong> <?php echo $exists ? 'EXISTS' : 'MISSING'; ?>
        </div>
        <?php
    }
    ?>
    
    <h2>Quick Links</h2>
    <ul>
        <li><a href="<?php echo admin_url('edit.php?post_type=product&page=addon-conditional-logic'); ?>">Go to Conditional Logic Admin</a></li>
        <li><a href="<?php echo admin_url('edit.php?post_type=product&page=addons'); ?>">Go to Product Add-ons</a></li>
        <li><a href="<?php echo admin_url('edit.php?post_type=product&page=addon-setup-database'); ?>">Go to Database Setup</a></li>
    </ul>
    
    <script>
        console.log('=== Conditional Logic Debug ===');
        console.log('wc_product_addons_params:', typeof wc_product_addons_params !== 'undefined' ? wc_product_addons_params : 'NOT DEFINED');
        console.log('jQuery:', typeof jQuery !== 'undefined' ? 'LOADED' : 'NOT LOADED');
        console.log('jQuery UI Sortable:', typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.sortable ? 'LOADED' : 'NOT LOADED');
        
        if (typeof wc_product_addons_params !== 'undefined') {
            console.log('Available properties:', Object.keys(wc_product_addons_params));
        }
    </script>
</body>
</html>