<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installation/Migration Class.
 *
 * Handles the activation/installation of the plugin.
 *
 * @category Installation
 * @version  3.0.0
 */
class WC_Product_Addons_Install {
	/**
	 * Initialize hooks.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public static function init() {
		self::run();
	}

	/**
	 * Run the installation.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	private static function run() {
		$installed_version = get_option( 'wc_pao_version' );

		self::migration_3_0_product();

		// Check the version before running.
		if ( ! defined( 'IFRAME_REQUEST' ) && ( $installed_version !== WC_PRODUCT_ADDONS_VERSION ) ) {
			if ( ! defined( 'WC_PAO_INSTALLING' ) ) {
				define( 'WC_PAO_INSTALLING', true );
			}

			self::update_plugin_version();

			if ( version_compare( $installed_version, '3.0', '<' ) ) {
				self::migration_3_0();
			}

			// Install conditional logic tables if not exists
			self::create_conditional_logic_tables();

			do_action( 'wc_pao_updated' );
		}
	}

	/**
	 * Updates the plugin version in db.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	private static function update_plugin_version() {
		delete_option( 'wc_pao_version' );
		add_option( 'wc_pao_version', WC_PRODUCT_ADDONS_VERSION );
	}

	/**
	 * 3.0 migration script.
	 *
	 * @since 3.0.0
	 */
	private static function migration_3_0() {
		require_once( WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/updates/class-wc-product-addons-migration-3-0.php' );
	}

	/**
	 * 3.0 migration script for product level.
	 *
	 * @since 3.0.0
	 */
	private static function migration_3_0_product() {
		require_once( WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/updates/class-wc-product-addons-migration-3-0-product.php' );
	}

	/**
	 * Create tables for conditional logic functionality.
	 * This only adds new tables and does not modify existing addon data.
	 *
	 * @since 4.0.0
	 */
	private static function create_conditional_logic_tables() {
		global $wpdb;

		$wpdb->hide_errors();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		// Table for storing conditional logic rules
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_product_addon_rules (
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
		) $charset_collate;";

		dbDelta( $sql );

		// Table for tracking rule usage
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_product_addon_rule_usage (
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
		) $charset_collate;";

		dbDelta( $sql );

		// Table for storing reusable formulas
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_product_addon_formulas (
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
		) $charset_collate;";

		dbDelta( $sql );

		// Set database version for conditional logic
		add_option( 'wc_pao_conditional_logic_db_version', '1.0' );
	}
}

WC_Product_Addons_Install::init();
