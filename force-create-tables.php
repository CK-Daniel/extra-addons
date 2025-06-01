<?php
/**
 * Force create conditional logic tables
 * 
 * This script can be run directly to create the database tables
 * Run this file by accessing it in your browser: yoursite.com/wp-content/plugins/extra-addons/force-create-tables.php
 */

// Security check - only allow if WordPress is loaded
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_config_paths = [
        __DIR__ . '/../../../../wp-config.php',
        __DIR__ . '/../../../wp-config.php', 
        __DIR__ . '/../../wp-config.php',
        __DIR__ . '/../wp-config.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress not found. Please run this script from within WordPress admin or ensure WordPress is properly loaded.');
    }
}

// Security check - only allow admins
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this script.');
}

global $wpdb;

echo "<!DOCTYPE html><html><head><title>Force Create Tables</title></head><body>";
echo "<h1>Product Add-ons Conditional Logic - Force Table Creation</h1>";

// Get database info
$charset_collate = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix;

echo "<p><strong>Database Prefix:</strong> {$prefix}</p>";

// Table 1: Rules
$table_name = $prefix . 'wc_product_addon_rules';
echo "<h2>Creating Table: {$table_name}</h2>";

$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
    rule_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_name VARCHAR(255) NOT NULL,
    rule_type ENUM('product', 'global', 'category', 'tag') DEFAULT 'product',
    scope_id BIGINT(20) UNSIGNED DEFAULT NULL,
    conditions LONGTEXT NOT NULL,
    actions LONGTEXT NOT NULL,
    priority INT(11) DEFAULT 10,
    enabled TINYINT(1) DEFAULT 1,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    usage_count BIGINT(20) DEFAULT 0,
    created_by BIGINT(20) UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id),
    KEY idx_rule_type_scope (rule_type, scope_id),
    KEY idx_enabled_priority (enabled, priority),
    KEY idx_dates (start_date, end_date)
) {$charset_collate};";

$result1 = $wpdb->query($sql);
echo "<p>Result: " . ($result1 !== false ? "✅ Success" : "❌ Failed: " . $wpdb->last_error) . "</p>";

// Table 2: Usage
$usage_table = $prefix . 'wc_product_addon_rule_usage';
echo "<h2>Creating Table: {$usage_table}</h2>";

$sql = "CREATE TABLE IF NOT EXISTS {$usage_table} (
    usage_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id BIGINT(20) UNSIGNED NOT NULL,
    order_id BIGINT(20) UNSIGNED DEFAULT NULL,
    user_id BIGINT(20) UNSIGNED DEFAULT NULL,
    session_id VARCHAR(255) DEFAULT NULL,
    product_id BIGINT(20) UNSIGNED NOT NULL,
    addon_name VARCHAR(255) NOT NULL,
    original_price DECIMAL(10,2) DEFAULT NULL,
    modified_price DECIMAL(10,2) DEFAULT NULL,
    modification_details LONGTEXT,
    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usage_id),
    KEY idx_rule_id (rule_id),
    KEY idx_order_id (order_id),
    KEY idx_user_id (user_id),
    KEY idx_product_addon (product_id, addon_name),
    KEY idx_used_at (used_at)
) {$charset_collate};";

$result2 = $wpdb->query($sql);
echo "<p>Result: " . ($result2 !== false ? "✅ Success" : "❌ Failed: " . $wpdb->last_error) . "</p>";

// Table 3: Formulas
$formulas_table = $prefix . 'wc_product_addon_formulas';
echo "<h2>Creating Table: {$formulas_table}</h2>";

$sql = "CREATE TABLE IF NOT EXISTS {$formulas_table} (
    formula_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    formula_name VARCHAR(255) NOT NULL,
    formula_expression TEXT NOT NULL,
    variables LONGTEXT NOT NULL,
    validation_rules LONGTEXT DEFAULT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT NULL,
    is_global TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (formula_id),
    UNIQUE KEY idx_formula_name (formula_name),
    KEY idx_category (category)
) {$charset_collate};";

$result3 = $wpdb->query($sql);
echo "<p>Result: " . ($result3 !== false ? "✅ Success" : "❌ Failed: " . $wpdb->last_error) . "</p>";

// Verify tables exist
echo "<h2>Verification</h2>";
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$prefix}wc_product_addon%'");
echo "<p>Tables found:</p><ul>";
foreach ($tables as $table) {
    $table_name = array_values((array)$table)[0];
    echo "<li>✅ {$table_name}</li>";
}
echo "</ul>";

// Update version
update_option('wc_pao_conditional_logic_db_version', '1.0');
echo "<p>✅ Database version updated to 1.0</p>";

echo "<h2>Complete!</h2>";
echo "<p><strong>All tables should now be created.</strong> You can now go back to the conditional logic page and try saving a rule.</p>";
echo "<p><a href='/wp-admin/edit.php?post_type=product&page=addon-conditional-logic'>← Back to Conditional Logic</a></p>";

echo "</body></html>";
?>