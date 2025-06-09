<?php
/**
 * Script to create a sample conditional logic rule for testing
 * 
 * This creates a rule that:
 * - When "test" option is selected in Test addon
 * - Hide "tester123" option in example1 addon
 * - Set price of "tester125" to 999
 */

// Load WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

global $wpdb;

$table_name = $wpdb->prefix . 'wc_product_addon_rules';

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

if ( ! $table_exists ) {
	die( "Error: Conditional logic tables not found. Please activate the plugin first.\n" );
}

// Rule 1: Hide tester123 when test is selected
$rule1 = array(
	'rule_name' => 'Hide tester123 when test selected',
	'rule_type' => 'global',
	'scope_id' => null,
	'conditions' => json_encode( array(
		array(
			'type' => 'addon_selected',
			'config' => array(
				'condition_addon' => 'test',
				'condition_option' => 'test',
				'condition_state' => 'selected'
			)
		)
	)),
	'actions' => json_encode( array(
		array(
			'type' => 'hide_option',
			'config' => array(
				'action_addon' => 'example1',
				'action_option' => 'tester123'
			)
		)
	)),
	'priority' => 10,
	'enabled' => 1
);

// Rule 2: Set price when test is selected
$rule2 = array(
	'rule_name' => 'Set tester125 price when test selected',
	'rule_type' => 'global',
	'scope_id' => null,
	'conditions' => json_encode( array(
		array(
			'type' => 'addon_selected',
			'config' => array(
				'condition_addon' => 'test',
				'condition_option' => 'test',
				'condition_state' => 'selected'
			)
		)
	)),
	'actions' => json_encode( array(
		array(
			'type' => 'set_price',
			'config' => array(
				'action_target_level' => 'option',
				'action_addon' => 'example1',
				'action_option' => 'tester125',
				'action_price' => '999'
			)
		)
	)),
	'priority' => 20,
	'enabled' => 1
);

// Delete existing rules for clean testing
$wpdb->query( "DELETE FROM $table_name WHERE rule_name IN ('Hide tester123 when test selected', 'Set tester125 price when test selected')" );

// Insert rules
$result1 = $wpdb->insert( $table_name, $rule1 );
$result2 = $wpdb->insert( $table_name, $rule2 );

if ( $result1 && $result2 ) {
	echo "Success! Created 2 sample conditional logic rules:\n";
	echo "1. Hide 'tester123' option when 'test' is selected\n";
	echo "2. Set 'tester125' price to 999 when 'test' is selected\n\n";
	
	// Show all rules
	$all_rules = $wpdb->get_results( "SELECT rule_id, rule_name, rule_type, enabled FROM $table_name ORDER BY priority ASC" );
	echo "All rules in database:\n";
	foreach ( $all_rules as $rule ) {
		echo "- [{$rule->rule_id}] {$rule->rule_name} (Type: {$rule->rule_type}, Enabled: {$rule->enabled})\n";
	}
} else {
	echo "Error creating rules. Database error: " . $wpdb->last_error . "\n";
}