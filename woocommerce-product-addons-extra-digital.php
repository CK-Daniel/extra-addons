<?php
/**
 * Plugin Name: WooCommerce Product Add-ons Extra Digital
 * Plugin URI: https://extra.co.il/
 * Description: Add extra options to products which your customers can select from, when adding to the cart, with an optional fee for each extra option. Add-ons can be checkboxes, a select box, or custom text input.
 * Version: 1.0.0
 * Author: WooCommerce
 * Author URI: https://extra.co.il/
 * Requires at least: 3.8
 * Tested up to: 5.7
 * WC tested up to: 5.3
 * WC requires at least: 3.0
 * Text Domain: woocommerce-product-addons-extra-digital
 * Domain Path: /languages/
 * Copyright: © 2025 Extra Digital
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 * @return string
 */
function woocommerce_product_addons_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Product Add-ons Extra Digital requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-product-addons-extra-digital' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

register_activation_hook( __FILE__, 'woocommerce_product_addons_activation' );

/**
 * Activation.
 *
 * @since 3.0.0
 */
function woocommerce_product_addons_activation() {
	set_transient( 'wc_pao_activation_notice', true, 60 );
	set_transient( 'wc_pao_pre_wc_30_notice', true, 60 );
	
	// Ensure conditional logic tables are created on activation
	if ( class_exists( 'WC_Product_Addons_Install' ) ) {
		WC_Product_Addons_Install::init();
	}
}

// Subscribe to automated translations.
add_filter( 'woocommerce_translations_updates_for_woocommerce-product-addons-extra-digital', '__return_true' );

add_action( 'plugins_loaded', 'woocommerce_product_addons_init', 9 );


function woocommerce_product_addons_init() {
	load_plugin_textdomain( 'woocommerce-product-addons-extra-digital', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_product_addons_missing_wc_notice' );
		return;
	}

	if ( ! class_exists( 'WC_Product_Addons' ) ) :
		define( 'WC_PRODUCT_ADDONS_VERSION', '4.0.0' ); // WRCS: DEFINED_VERSION.
		define( 'WC_PRODUCT_ADDONS_MAIN_FILE', __FILE__ );
		define( 'WC_PRODUCT_ADDONS_PLUGIN_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
		define( 'WC_PRODUCT_ADDONS_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		/**
		 * Main class.
		 */
		class WC_Product_Addons {

			protected $groups_controller;

			/**
			 * Constructor.
			 */
			public function __construct() {
				$this->init();
				add_action( 'init', array( $this, 'init_post_types' ), 20 );
				add_action( 'init', array( 'WC_Product_Addons_install', 'init' ) );
				add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
				add_action( 'admin_notices', array( $this, 'notices' ) );
			}

			/**
			 * Initializes plugin classes.
			 *
			 * @version 2.9.0
			 */
			public function init() {
				require_once( dirname( __FILE__ ) . '/includes/class-wc-product-addons-helper.php' );

				// Pre 3.0 conversion helper to be remove in future.
				require_once( dirname( __FILE__ ) . '/includes/updates/class-wc-product-addons-3-0-conversion-helper.php' );

				require_once( dirname( __FILE__ ) . '/includes/class-wc-product-addons-install.php' );

				// Core (models)
				require_once( dirname( __FILE__ ) . '/includes/groups/class-wc-product-addons-group-validator.php' );
				require_once( dirname( __FILE__ ) . '/includes/groups/class-wc-product-addons-global-group.php' );
				require_once( dirname( __FILE__ ) . '/includes/groups/class-wc-product-addons-product-group.php' );
				require_once( dirname( __FILE__ ) . '/includes/groups/class-wc-product-addons-groups.php' );

				// Conditional Logic System
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/class-wc-product-addons-condition-evaluator.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/conditions/abstract-wc-product-addons-condition.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/conditions/class-wc-product-addons-condition-field.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/conditions/class-wc-product-addons-condition-product.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/conditions/class-wc-product-addons-condition-cart.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/conditions/class-wc-product-addons-condition-user.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/conditions/class-wc-product-addons-condition-date.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/conditions/class-wc-product-addons-condition-rule.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/actions/abstract-wc-product-addons-action.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/actions/class-wc-product-addons-action-visibility.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/actions/class-wc-product-addons-action-price.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/actions/class-wc-product-addons-action-requirement.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/actions/class-wc-product-addons-action-modifier.php' );
				require_once( dirname( __FILE__ ) . '/includes/conditional-logic/class-wc-product-addons-conditional-logic.php' );

				// Admin
				if ( is_admin() ) {
					require_once( dirname( __FILE__ ) . '/includes/admin/class-wc-product-addons-privacy.php' );
					require_once( dirname( __FILE__ ) . '/includes/admin/class-wc-product-addons-admin.php' );
					$GLOBALS['Product_Addon_Admin'] = new WC_Product_Addons_Admin();
				}

				require_once( dirname( __FILE__ ) . '/includes/class-wc-product-addons-display.php' );
				require_once( dirname( __FILE__ ) . '/includes/class-wc-product-addons-cart.php' );
				require_once( dirname( __FILE__ ) . '/includes/class-wc-product-addons-ajax.php' );

				$GLOBALS['Product_Addon_Display'] = new WC_Product_Addons_Display();
				$GLOBALS['Product_Addon_Cart']    = new WC_Product_Addons_Cart();
				new WC_Product_Addons_Cart_Ajax();
				
				// Initialize conditional logic system
				error_log( 'Main plugin: Initializing conditional logic system' );
				WC_Product_Addons_Conditional_Logic::get_instance();
				error_log( 'Main plugin: Conditional logic system initialized' );
				
				// Include test AJAX page for debugging
				if ( is_admin() ) {
					include_once( dirname( __FILE__ ) . '/test-admin-ajax.php' );
				}
			}

			/**
			 * Init post types used for addons.
			 */
			public function init_post_types() {
				register_post_type(
					'global_product_addon',
					array(
						'public'              => false,
						'show_ui'             => false,
						'capability_type'     => 'product',
						'map_meta_cap'        => true,
						'publicly_queryable'  => false,
						'exclude_from_search' => true,
						'hierarchical'        => false,
						'rewrite'             => false,
						'query_var'           => false,
						'supports'            => array( 'title' ),
						'show_in_nav_menus'   => false,
					)
				);

				register_taxonomy_for_object_type( 'product_cat', 'global_product_addon' );
			}

			/**
			 * Initialize the REST API
			 *
			 * @since 2.9.0
			 * @param WP_Rest_Server $wp_rest_server
			 */
			public function rest_api_init( $wp_rest_server ) {
				require_once( dirname( __FILE__ ) . '/includes/api/wc-product-add-ons-groups-controller-v1.php' );
				$this->groups_controller = new WC_Product_Add_Ons_Groups_Controller();
				$this->groups_controller->register_routes();
			}

			/**
			 * Plugin action links
			 */
			public function action_links( $links ) {
				$plugin_links = array(
					'<a href="https://woocommerce.com/my-account/tickets/">' . __( 'Support', 'woocommerce-product-addons-extra-digital' ) . '</a>',
					'<a href="https://docs.woocommerce.com/document/product-add-ons/">' . __( 'Documentation', 'woocommerce-product-addons-extra-digital' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}

			/**
			 * On activation.
			 * Runs on activation. Assigns a notice message to a WordPress option.
			 */
			public function notices() {
				$show_activate_notice = get_transient( 'wc_pao_activation_notice' );

				if ( $show_activate_notice ) {
					echo '<div class="notice is-dismissible updated"><p><strong>' . __( 'WooCommerce Product Add-ons Extra Digital is ready to go!', 'woocommerce-product-addons-extra-digital' ) . '</strong></p><p>' . __( 'Create an add-on that applies to every product, or apply it to specific categories. Create an add-on for an individual product by editing the product.', 'woocommerce-product-addons-extra-digital' ) . '</p><p><a href="' . esc_url( admin_url() ) . 'edit.php?post_type=product&page=addons" class="button button-primary">' . __( 'Create add-ons', 'woocommerce-product-addons-extra-digital' ) . '</a>&nbsp;&nbsp;<a href="' . esc_url( admin_url() ) . 'edit.php?post_type=product" class="button">' . __( 'Find products', 'woocommerce-product-addons-extra-digital' ) . '</a></p></div>';

					delete_transient( 'wc_pao_activation_notice' );
				}
			}
		}

		new WC_Product_Addons();

	endif;
}
