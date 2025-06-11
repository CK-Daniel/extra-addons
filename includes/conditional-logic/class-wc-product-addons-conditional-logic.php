<?php
/**
 * WooCommerce Product Add-ons Conditional Logic Controller
 *
 * Main controller class for handling conditional logic functionality.
 * This class manages the registration, evaluation, and execution of conditions
 * without modifying existing addon data structures.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditional Logic Controller Class
 *
 * @class   WC_Product_Addons_Conditional_Logic
 * @version 4.0.0
 */
class WC_Product_Addons_Conditional_Logic {

	/**
	 * Singleton instance
	 *
	 * @var WC_Product_Addons_Conditional_Logic
	 */
	private static $instance = null;

	/**
	 * Registered condition types
	 *
	 * @var array
	 */
	private $condition_types = array();

	/**
	 * Registered action types
	 *
	 * @var array
	 */
	private $action_types = array();

	/**
	 * Cache for evaluated conditions
	 *
	 * @var array
	 */
	private $evaluation_cache = array();

	/**
	 * Dependency graph instance
	 *
	 * @var WC_Product_Addons_Dependency_Graph
	 */
	private $dependency_graph = null;

	/**
	 * Conflict resolver instance
	 *
	 * @var WC_Product_Addons_Rule_Conflict_Resolver
	 */
	private $conflict_resolver = null;

	/**
	 * Maximum cascade iterations before warning
	 *
	 * @var int
	 */
	private $max_cascade_iterations = 20;

	/**
	 * Enable detailed debugging
	 *
	 * @var bool
	 */
	private $debug_mode = false;

	/**
	 * Get singleton instance
	 *
	 * @return WC_Product_Addons_Conditional_Logic
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get singleton instance (alias for get_instance)
	 *
	 * @return WC_Product_Addons_Conditional_Logic
	 */
	public static function instance() {
		return self::get_instance();
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Load dependencies
		$this->load_dependencies();
		
		// Initialize components
		$this->dependency_graph = new WC_Product_Addons_Dependency_Graph();
		$this->conflict_resolver = new WC_Product_Addons_Rule_Conflict_Resolver();
		$this->debug_mode = defined( 'WC_PAO_DEBUG' ) && WC_PAO_DEBUG;
		
		// Initialize AJAX queue
		WC_Product_Addons_Ajax_Queue::init();
		
		$this->init();
	}

	/**
	 * Load required class files
	 */
	private function load_dependencies() {
		$base_path = plugin_dir_path( dirname( __FILE__ ) );
		
		// Load new components
		require_once $base_path . 'conditional-logic/class-wc-product-addons-dependency-graph.php';
		require_once $base_path . 'conditional-logic/class-wc-product-addons-rule-conflict-resolver.php';
		require_once $base_path . 'conditional-logic/class-wc-product-addons-ajax-queue.php';
		require_once $base_path . 'conditional-logic/class-wc-product-addons-addon-identifier.php';
		require_once $base_path . 'conditional-logic/class-wc-product-addons-rule-builder.php';
	}

	/**
	 * Initialize conditional logic system
	 */
	private function init() {
		// Register default condition types
		$this->register_default_conditions();
		
		// Register default action types
		$this->register_default_actions();
		
		// Hook into addon display
		add_filter( 'woocommerce_product_addons_get_addon_array', array( $this, 'inject_conditional_logic' ), 10, 2 );
		
		// Hook into price calculations
		add_filter( 'woocommerce_product_addons_price', array( $this, 'modify_addon_price' ), 10, 4 );
		
		// Hook into cart price calculations
		add_filter( 'woocommerce_product_addons_option_price_raw', array( $this, 'modify_addon_price_raw' ), 10, 2 );
		add_filter( 'woocommerce_product_addons_adjust_price', array( $this, 'should_adjust_cart_price' ), 10, 2 );
		
		// Hook to apply conditional prices from form submission
		add_filter( 'woocommerce_product_addon_cart_item_data', array( $this, 'apply_conditional_prices_from_post' ), 20, 4 );
		
		// AJAX handlers
		add_action( 'wp_ajax_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		add_action( 'wp_ajax_nopriv_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		add_action( 'wp_ajax_wc_product_addons_evaluate_rules', array( $this, 'ajax_evaluate_rules' ) );
		add_action( 'wp_ajax_nopriv_wc_product_addons_evaluate_rules', array( $this, 'ajax_evaluate_rules' ) );
		
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'woocommerce_product_addons_panel_after_options', array( $this, 'render_conditional_logic_panel' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
		
		// Frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'wc_product_addon_conditional_logic_data', array( $this, 'inject_addon_conditional_data' ), 10, 2 );
		
		// Allow third-party condition registration
		do_action( 'woocommerce_product_addons_register_conditions', $this );
	}

	/**
	 * Register default condition types
	 */
	private function register_default_conditions() {
		$default_conditions = array(
			'field'     => 'WC_Product_Addons_Condition_Field',
			'product'   => 'WC_Product_Addons_Condition_Product',
			'cart'      => 'WC_Product_Addons_Condition_Cart',
			'user'      => 'WC_Product_Addons_Condition_User',
			'date'      => 'WC_Product_Addons_Condition_Date',
			'rule'      => 'WC_Product_Addons_Condition_Rule',
		);

		foreach ( $default_conditions as $type => $class_name ) {
			$this->register_condition_type( $type, $class_name );
		}
	}

	/**
	 * Register default action types
	 */
	private function register_default_actions() {
		$default_actions = array(
			'visibility'   => 'WC_Product_Addons_Action_Visibility',
			'price'        => 'WC_Product_Addons_Action_Price',
			'requirement'  => 'WC_Product_Addons_Action_Requirement',
			'modifier'     => 'WC_Product_Addons_Action_Modifier',
		);

		foreach ( $default_actions as $type => $class_name ) {
			$this->register_action_type( $type, $class_name );
		}
	}

	/**
	 * Register a condition type
	 *
	 * @param string $type       Condition type identifier
	 * @param string $class_name Class name for the condition
	 */
	public function register_condition_type( $type, $class_name ) {
		$this->condition_types[ $type ] = $class_name;
	}

	/**
	 * Register an action type
	 *
	 * @param string $type       Action type identifier
	 * @param string $class_name Class name for the action
	 */
	public function register_action_type( $type, $class_name ) {
		$this->action_types[ $type ] = $class_name;
	}

	/**
	 * Inject conditional logic data into addon array without modifying existing structure
	 *
	 * @param array $addon    Addon data
	 * @param int   $post_id  Product ID
	 * @return array Modified addon data
	 */
	public function inject_conditional_logic( $addon, $post_id ) {
		// Don't modify existing addon structure, just add conditional logic if it exists
		if ( ! isset( $addon['conditional_logic'] ) ) {
			// Check if there are saved conditional rules for this addon
			$conditional_rules = $this->get_addon_conditional_rules( $post_id, $addon );
			
			if ( ! empty( $conditional_rules ) ) {
				$addon['conditional_logic'] = $conditional_rules;
			}
		}
		
		return $addon;
	}

	/**
	 * Get conditional rules for an addon
	 *
	 * @param int   $post_id Product ID
	 * @param array $addon   Addon data
	 * @return array Conditional rules
	 */
	private function get_addon_conditional_rules( $post_id, $addon ) {
		global $wpdb;
		
		// First check product-specific rules
		$rules = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wc_product_addon_rules 
			WHERE rule_type = 'product' 
			AND scope_id = %d 
			AND enabled = 1 
			ORDER BY priority DESC",
			$post_id
		) );
		
		$conditional_rules = array();
		
		foreach ( $rules as $rule ) {
			$conditions = json_decode( $rule->conditions, true );
			$actions = json_decode( $rule->actions, true );
			
			// Check if this rule applies to this addon
			if ( $this->rule_applies_to_addon( $addon, $conditions, $actions ) ) {
				$conditional_rules[] = array(
					'rule_id'    => $rule->rule_id,
					'conditions' => $conditions,
					'actions'    => $actions,
					'priority'   => $rule->priority,
				);
			}
		}
		
		return $conditional_rules;
	}

	/**
	 * Check if a rule applies to a specific addon
	 *
	 * @param array $addon      Addon data
	 * @param array $conditions Rule conditions
	 * @param array $actions    Rule actions
	 * @return bool
	 */
	private function rule_applies_to_addon( $addon, $conditions, $actions ) {
		// Check if any action targets this addon
		foreach ( $actions as $action ) {
			if ( isset( $action['target'] ) ) {
				if ( $action['target'] === 'self' || 
					 $action['target'] === 'all' || 
					 ( isset( $action['target_addon'] ) && $action['target_addon'] === $addon['name'] ) ) {
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * Modify addon price based on conditional logic
	 *
	 * @param float  $price    Original price
	 * @param array  $addon    Addon data
	 * @param int    $key      Option key
	 * @param string $type     Price type
	 * @return float Modified price
	 */
	public function modify_addon_price( $price, $addon, $key, $type ) {
		if ( ! isset( $addon['conditional_logic'] ) || empty( $addon['conditional_logic'] ) ) {
			return $price;
		}
		
		// Evaluate conditions and apply price modifications
		$context = $this->get_evaluation_context();
		
		foreach ( $addon['conditional_logic'] as $rule ) {
			if ( $this->evaluate_conditions( $rule['conditions'], $context ) ) {
				foreach ( $rule['actions'] as $action ) {
					if ( $action['type'] === 'price' || $action['type'] === 'modify_price' ) {
						$price = $this->apply_price_modification( $price, $action, $context );
					}
				}
			}
		}
		
		return $price;
	}

	/**
	 * Get context for condition evaluation
	 *
	 * @return array Evaluation context
	 */
	private function get_evaluation_context() {
		$context = array(
			'user'      => wp_get_current_user(),
			'cart'      => WC()->cart,
			'product'   => wc_get_product( get_the_ID() ),
			'timestamp' => current_time( 'timestamp' ),
			'location'  => $this->get_user_location(),
		);
		
		return apply_filters( 'woocommerce_product_addons_evaluation_context', $context );
	}

	/**
	 * Get user location data
	 *
	 * @return array Location data
	 */
	private function get_user_location() {
		$location = array(
			'country'  => WC()->customer ? WC()->customer->get_billing_country() : '',
			'state'    => WC()->customer ? WC()->customer->get_billing_state() : '',
			'postcode' => WC()->customer ? WC()->customer->get_billing_postcode() : '',
		);
		
		return $location;
	}

	/**
	 * Evaluate conditions
	 *
	 * @param array $conditions Conditions to evaluate
	 * @param array $context    Evaluation context
	 * @return bool Whether conditions are met
	 */
	public function evaluate_conditions( $conditions, $context ) {
		if ( empty( $conditions ) ) {
			return true;
		}
		
		// Generate cache key
		$cache_key = md5( json_encode( $conditions ) . json_encode( $context ) );
		
		// Check cache
		if ( isset( $this->evaluation_cache[ $cache_key ] ) ) {
			return $this->evaluation_cache[ $cache_key ];
		}
		
		$result = true;
		
		foreach ( $conditions as $condition_group ) {
			$group_result = $this->evaluate_condition_group( $condition_group, $context );
			
			// Default to AND logic between groups
			$result = $result && $group_result;
		}
		
		// Cache result
		$this->evaluation_cache[ $cache_key ] = $result;
		
		return $result;
	}

	/**
	 * Evaluate a condition group
	 *
	 * @param array $condition_group Condition group
	 * @param array $context        Evaluation context
	 * @return bool Whether group conditions are met
	 */
	private function evaluate_condition_group( $condition_group, $context ) {
		if ( ! isset( $condition_group['conditions'] ) || empty( $condition_group['conditions'] ) ) {
			return true;
		}
		
		$match_type = isset( $condition_group['match_type'] ) ? $condition_group['match_type'] : 'all';
		$results = array();
		
		foreach ( $condition_group['conditions'] as $condition ) {
			$evaluator = $this->get_condition_evaluator( $condition['type'] );
			
			if ( $evaluator ) {
				$results[] = $evaluator->evaluate( $condition, $context );
			}
		}
		
		if ( $match_type === 'all' ) {
			return ! in_array( false, $results, true );
		} elseif ( $match_type === 'any' ) {
			return in_array( true, $results, true );
		}
		
		return true;
	}

	/**
	 * Get condition evaluator instance
	 *
	 * @param string $type Condition type
	 * @return object|null Condition evaluator instance
	 */
	private function get_condition_evaluator( $type ) {
		if ( ! isset( $this->condition_types[ $type ] ) ) {
			return null;
		}
		
		$class_name = $this->condition_types[ $type ];
		
		if ( ! class_exists( $class_name ) ) {
			$file_path = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/conditional-logic/conditions/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
			
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
		
		if ( class_exists( $class_name ) ) {
			return new $class_name();
		}
		
		return null;
	}

	/**
	 * Apply price modification
	 *
	 * @param float $price   Original price
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 * @return float Modified price
	 */
	private function apply_price_modification( $price, $action, $context ) {
		$handler = $this->get_action_handler( 'price' );
		
		if ( $handler ) {
			return $handler->execute( $price, $action, $context );
		}
		
		return $price;
	}

	/**
	 * Get action handler instance
	 *
	 * @param string $type Action type
	 * @return object|null Action handler instance
	 */
	private function get_action_handler( $type ) {
		if ( ! isset( $this->action_types[ $type ] ) ) {
			return null;
		}
		
		$class_name = $this->action_types[ $type ];
		
		if ( ! class_exists( $class_name ) ) {
			$file_path = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/conditional-logic/actions/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
			
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
		
		if ( class_exists( $class_name ) ) {
			return new $class_name();
		}
		
		return null;
	}

	/**
	 * AJAX handler for condition evaluation with cascading support
	 */
	public function ajax_evaluate_conditions() {
		check_ajax_referer( 'wc-product-addons-conditional-logic', 'security' );
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$addon_data = isset( $_POST['addon_data'] ) ? json_decode( stripslashes( $_POST['addon_data'] ), true ) : array();
		$selections = isset( $_POST['selections'] ) ? json_decode( stripslashes( $_POST['selections'] ), true ) : array();
		
		if ( ! $product_id ) {
			wp_send_json_error( 'Invalid product ID' );
		}
		
		// Build context with current selections
		$context = $this->get_evaluation_context();
		$context['selections'] = $selections;
		
		// Evaluate with cascading support
		$results = $this->evaluate_cascading_conditions( $addon_data, $context );
		
		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for evaluating database-stored rules
	 */
	public function ajax_evaluate_rules() {
		check_ajax_referer( 'wc-product-addons-conditional-logic', 'security' );
		
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$addon_data = isset( $_POST['addon_data'] ) ? json_decode( stripslashes( $_POST['addon_data'] ), true ) : array();
		$selections = isset( $_POST['selections'] ) ? json_decode( stripslashes( $_POST['selections'] ), true ) : array();
		$user_data = isset( $_POST['user_data'] ) ? json_decode( stripslashes( $_POST['user_data'] ), true ) : array();
		$cart_data = isset( $_POST['cart_data'] ) ? json_decode( stripslashes( $_POST['cart_data'] ), true ) : array();
		
		error_log( 'Rule evaluation started for product: ' . $product_id );
		error_log( 'Addon data: ' . json_encode( $addon_data ) );
		error_log( 'Selections: ' . json_encode( $selections ) );
		
		if ( ! $product_id ) {
			wp_send_json_error( 'Invalid product ID' );
		}
		
		// Load rules from database
		$rules = $this->load_rules_for_product( $product_id );
		error_log( 'Loaded rules: ' . json_encode( $rules ) );
		
		if ( empty( $rules ) ) {
			// Return success with empty actions rather than error
			wp_send_json_success( array( 
				'actions' => array(), 
				'message' => 'No rules found',
				'debug' => array(
					'product_id' => $product_id,
					'addon_count' => count( $addon_data ),
					'selection_count' => count( $selections )
				)
			) );
		}
		
		// Build evaluation context
		$context = array(
			'product_id' => $product_id,
			'selections' => $selections,
			'addon_data' => $addon_data,
			'user_data' => $user_data,
			'cart_data' => $cart_data,
			'timestamp' => current_time( 'timestamp' ),
		);
		
		// Evaluate rules and collect actions
		$actions_to_apply = array();
		
		foreach ( $rules as $rule ) {
			error_log( 'Evaluating rule: ' . $rule['name'] );
			
			$rule_conditions_met = $this->evaluate_rule_conditions( $rule['conditions'], $context );
			error_log( 'Rule "' . $rule['name'] . '" conditions met: ' . ( $rule_conditions_met ? 'YES' : 'NO' ) );
			
			if ( $rule_conditions_met ) {
				foreach ( $rule['actions'] as $action ) {
					error_log( 'Processing action: ' . json_encode( $action ) );
					$processed_action = $this->process_rule_action( $action, $context );
					if ( $processed_action ) {
						$actions_to_apply[] = $processed_action;
						error_log( 'Action to apply: ' . json_encode( $processed_action ) );
					} else {
						error_log( 'Action processing returned null for: ' . json_encode( $action ) );
					}
				}
			}
		}
		
		$result = array(
			'actions' => $actions_to_apply,
			'rules_evaluated' => count( $rules ),
			'actions_count' => count( $actions_to_apply ),
			'rules' => $rules,
			'context' => $context
		);
		
		error_log( 'Final result: ' . json_encode( $result ) );
		wp_send_json_success( $result );
	}

	/**
	 * Load active rules for a product
	 */
	private function load_rules_for_product( $product_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		
		// Load global rules and product-specific rules
		$rules = $wpdb->get_results( $wpdb->prepare("
			SELECT *, rule_name as name FROM {$table_name} 
			WHERE enabled = 1 
			AND (
				rule_type = 'global' 
				OR (rule_type = 'product' AND scope_id = %d)
				OR (rule_type = 'category' AND EXISTS (
					SELECT 1 FROM {$wpdb->term_relationships} tr
					JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tr.object_id = %d 
					AND tt.taxonomy = 'product_cat'
					AND tt.term_id = scope_id
				))
			)
			ORDER BY priority ASC
		", $product_id, $product_id ), ARRAY_A );
		
		// Parse JSON fields
		foreach ( $rules as &$rule ) {
			$rule['conditions'] = json_decode( $rule['conditions'], true ) ?: array();
			$rule['actions'] = json_decode( $rule['actions'], true ) ?: array();
		}
		
		return $rules;
	}

	/**
	 * Evaluate rule conditions
	 */
	private function evaluate_rule_conditions( $conditions, $context ) {
		if ( empty( $conditions ) ) {
			return true;
		}
		
		foreach ( $conditions as $condition ) {
			$condition_met = $this->evaluate_single_condition( $condition, $context );
			error_log( 'Condition result: ' . json_encode( $condition ) . ' => ' . ( $condition_met ? 'TRUE' : 'FALSE' ) );
			
			// For now, use AND logic - all conditions must be met
			if ( ! $condition_met ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Evaluate a single condition
	 */
	private function evaluate_single_condition( $condition, $context ) {
		$type = $condition['type'] ?? '';
		$config = $condition['config'] ?? array();
		
		switch ( $type ) {
			case 'addon_selected':
				return $this->evaluate_addon_selected_condition( $config, $context );
			case 'addon_field':
				return $this->evaluate_addon_field_condition( $config, $context );
			case 'product_price':
				return $this->evaluate_product_price_condition( $config, $context );
			case 'user_logged_in':
				return $this->evaluate_user_logged_in_condition( $config, $context );
			default:
				error_log( 'Unknown condition type: ' . $type );
				return false;
		}
	}

	/**
	 * Evaluate addon selected condition - Optimized with caching
	 */
	private function evaluate_addon_selected_condition( $config, $context ) {
		$addon_id = $config['condition_addon'] ?? '';
		$option_value = $config['condition_option'] ?? '';
		$state = $config['condition_state'] ?? 'selected';
		
		// Cache evaluation results for identical conditions
		static $evaluation_cache = array();
		$cache_key = md5( serialize( array( $addon_id, $option_value, $state, $context['selections'] ?? array() ) ) );
		
		if ( isset( $evaluation_cache[ $cache_key ] ) ) {
			return $evaluation_cache[ $cache_key ];
		}
		
		error_log( "Evaluating addon_selected: addon={$addon_id}, option={$option_value}, state={$state}" );
		
		$selections = $context['selections'] ?? array();
		
		// Build selection index for fast lookups
		static $selection_index_cache = array();
		$selections_key = md5( serialize( $selections ) );
		
		if ( ! isset( $selection_index_cache[ $selections_key ] ) ) {
			$selection_index_cache[ $selections_key ] = $this->build_selection_index( $selections );
		}
		
		$selection_index = $selection_index_cache[ $selections_key ];
		
		// Find the addon selection using index
		$selected_value = null;
		
		// Try direct lookup
		if ( isset( $selection_index[ $addon_id ] ) ) {
			$selected_value = $selection_index[ $addon_id ]['value'] ?? null;
			error_log( "Found exact match for {$addon_id} using index" );
		} else {
			// Try variations using index
			$variations = array(
				strtolower( $addon_id ),
				preg_replace( '/_(?:global|product|category)_\d+$/', '', $addon_id ),
				preg_replace( '/^(.*?)_(?:product|global|category).*$/', '$1', $addon_id ),
				WC_Product_Addons_Addon_Identifier::normalize_name( $addon_id )
			);
			
			foreach ( $variations as $variation ) {
				if ( isset( $selection_index[ $variation ] ) ) {
					$selected_value = $selection_index[ $variation ]['value'] ?? null;
					error_log( "Found match for {$addon_id} using variation {$variation}" );
					break;
				}
			}
		}
		
		if ( $selected_value === null ) {
			error_log( "No match found for addon_id: {$addon_id} in selections" );
		}
		
		error_log( "Selected value for {$addon_id}: " . ( $selected_value ?? 'NULL' ) );
		error_log( "Comparing with option value: {$option_value}" );
		
		// Flexible value comparison using optimized matching
		$is_selected = $this->values_match( $selected_value, $option_value );
		
		error_log( "Is selected: " . ( $is_selected ? 'YES' : 'NO' ) );
		
		$result = ( $state === 'selected' ) ? $is_selected : ! $is_selected;
		$evaluation_cache[ $cache_key ] = $result;
		
		return $result;
	}

	/**
	 * Build selection index for fast lookups
	 */
	private function build_selection_index( $selections ) {
		$index = array();
		
		foreach ( $selections as $selection_id => $selection ) {
			// Index by ID
			$index[ $selection_id ] = $selection;
			
			// Index by name if available
			if ( isset( $selection['name'] ) && ! empty( $selection['name'] ) ) {
				$index[ $selection['name'] ] = $selection;
				$index[ strtolower( $selection['name'] ) ] = $selection;
				$index[ WC_Product_Addons_Addon_Identifier::normalize_name( $selection['name'] ) ] = $selection;
			}
			
			// Index by variations
			$variations = array(
				strtolower( $selection_id ),
				preg_replace( '/_(?:global|product|category)_\d+$/', '', $selection_id ),
				preg_replace( '/^(.*?)_(?:product|global|category).*$/', '$1', $selection_id ),
				WC_Product_Addons_Addon_Identifier::normalize_name( $selection_id )
			);
			
			foreach ( $variations as $variation ) {
				if ( $variation && ! isset( $index[ $variation ] ) ) {
					$index[ $variation ] = $selection;
				}
			}
		}
		
		return $index;
	}

	/**
	 * Check if two values match using various strategies
	 */
	private function values_match( $value1, $value2 ) {
		if ( $value1 === null || $value2 === null ) {
			return false;
		}
		
		// Exact match
		if ( $value1 === $value2 ) {
			return true;
		}
		
		// Case-insensitive match
		if ( strcasecmp( $value1, $value2 ) === 0 ) {
			return true;
		}
		
		// Sanitized match
		if ( sanitize_title( $value1 ) === sanitize_title( $value2 ) ) {
			return true;
		}
		
		// Handle option value format: label-index (e.g., test-1)
		$base1 = preg_replace( '/-\d+$/', '', $value1 );
		$base2 = preg_replace( '/-\d+$/', '', $value2 );
		
		// Check if bases match
		if ( $base1 === $value2 || $base2 === $value1 || 
			 strcasecmp( $base1, $value2 ) === 0 || strcasecmp( $base2, $value1 ) === 0 ||
			 strcasecmp( $base1, $base2 ) === 0 ) {
			error_log( "Matched by base value: {$base1} / {$base2}" );
			return true;
		}
		
		return false;
	}

	/**
	 * Evaluate addon field condition
	 */
	private function evaluate_addon_field_condition( $config, $context ) {
		// Implementation for field value conditions
		return true; // Placeholder
	}

	/**
	 * Evaluate product price condition
	 */
	private function evaluate_product_price_condition( $config, $context ) {
		// Implementation for product price conditions
		return true; // Placeholder
	}

	/**
	 * Evaluate user logged in condition
	 */
	private function evaluate_user_logged_in_condition( $config, $context ) {
		return is_user_logged_in();
	}

	/**
	 * Process rule action for frontend application
	 */
	private function process_rule_action( $action, $context ) {
		$type = $action['type'] ?? '';
		$config = $action['config'] ?? array();
		
		$processed_action = array(
			'type' => $type,
			'original_config' => $config
		);
		
		switch ( $type ) {
			case 'hide_addon':
			case 'show_addon':
				$target = $config['action_addon'] ?? '';
				$processed_action['target_addon'] = $this->resolve_addon_target( $target, $context );
				$processed_action['original_target'] = $target;
				error_log( "Action {$type}: target={$target}, resolved={$processed_action['target_addon']}" );
				break;
				
			case 'hide_option':
			case 'show_option':
				$processed_action['target_addon'] = $this->resolve_addon_target( $config['action_addon'] ?? '', $context );
				$processed_action['target_option'] = $config['action_option'] ?? '';
				break;
				
			case 'set_price':
				$processed_action['target_addon'] = $this->resolve_addon_target( $config['action_addon'] ?? '', $context );
				$processed_action['target_option'] = $config['action_option'] ?? '';
				$processed_action['new_price'] = floatval( $config['action_price'] ?? 0 );
				error_log( "Set price action: addon={$processed_action['target_addon']}, option={$processed_action['target_option']}, price={$processed_action['new_price']}" );
				break;
				
			case 'make_required':
			case 'make_optional':
				$processed_action['target_addon'] = $this->resolve_addon_target( $config['action_addon'] ?? '', $context );
				break;
				
			case 'set_label':
				$processed_action['target_addon'] = $this->resolve_addon_target( $config['action_addon'] ?? '', $context );
				$processed_action['new_label'] = $config['action_label'] ?? '';
				break;
				
			case 'set_description':
				$processed_action['target_addon'] = $this->resolve_addon_target( $config['action_addon'] ?? '', $context );
				$processed_action['new_description'] = $config['action_description'] ?? '';
				break;
				
			default:
				error_log( 'Unknown action type: ' . $type );
				return null;
		}
		
		return $processed_action;
	}

	/**
	 * Resolve addon target from ID to name - Optimized with caching
	 */
	private function resolve_addon_target( $addon_id, $context ) {
		// Cache resolved targets for performance
		static $target_cache = array();
		$cache_key = md5( $addon_id . serialize( $context['addon_data'] ?? array() ) );
		
		if ( isset( $target_cache[ $cache_key ] ) ) {
			return $target_cache[ $cache_key ];
		}
		
		// Use the addon identifier helper
		$parsed = WC_Product_Addons_Addon_Identifier::parse_identifier( $addon_id );
		
		// Build index for fast lookups if we have multiple addons
		if ( isset( $context['addon_data'] ) && is_array( $context['addon_data'] ) && count( $context['addon_data'] ) > 3 ) {
			$addon_index = $this->build_addon_index( $context['addon_data'] );
			
			// Try direct lookup in index
			if ( isset( $addon_index[ $addon_id ] ) ) {
				$result = $addon_index[ $addon_id ];
				$target_cache[ $cache_key ] = $result;
				return $result;
			}
			
			// Try variations
			$variations = array(
				strtolower( $addon_id ),
				preg_replace( '/_(?:global|product|category)_\d+$/', '', $addon_id ),
				preg_replace( '/^(.*?)_(?:product|global|category).*$/', '$1', $addon_id ),
				WC_Product_Addons_Addon_Identifier::normalize_name( $addon_id )
			);
			
			foreach ( $variations as $variation ) {
				if ( isset( $addon_index[ $variation ] ) ) {
					$result = $addon_index[ $variation ];
					$target_cache[ $cache_key ] = $result;
					error_log( "Resolved addon target using index variation: {$addon_id} => {$result}" );
					return $result;
				}
			}
		}
		
		// Fallback to linear search for small datasets
		if ( isset( $context['addon_data'] ) && is_array( $context['addon_data'] ) ) {
			foreach ( $context['addon_data'] as $addon ) {
				// Direct ID match
				if ( isset( $addon['id'] ) && $addon['id'] === $addon_id ) {
					$target_cache[ $cache_key ] = $addon['id'];
					return $addon['id'];
				}
				if ( isset( $addon['identifier'] ) && $addon['identifier'] === $addon_id ) {
					$target_cache[ $cache_key ] = $addon['identifier'];
					return $addon['identifier'];
				}
				
				// Use the advanced matching logic from WC_Product_Addons_Addon_Identifier
				if ( isset( $addon['id'] ) && WC_Product_Addons_Addon_Identifier::names_match( $addon['id'], $addon_id ) ) {
					error_log( "Resolved addon target using names_match: {$addon_id} => {$addon['id']}" );
					$target_cache[ $cache_key ] = $addon['id'];
					return $addon['id'];
				}
				if ( isset( $addon['identifier'] ) && WC_Product_Addons_Addon_Identifier::names_match( $addon['identifier'], $addon_id ) ) {
					error_log( "Resolved addon target using identifier match: {$addon_id} => {$addon['identifier']}" );
					$target_cache[ $cache_key ] = $addon['identifier'];
					return $addon['identifier'];
				}
				
				// Check by name for backward compatibility
				if ( isset( $addon['name'] ) && 
					WC_Product_Addons_Addon_Identifier::names_match( $addon['name'], $addon_id ) ) {
					$result = isset( $addon['id'] ) ? $addon['id'] : $addon['name'];
					$target_cache[ $cache_key ] = $result;
					return $result;
				}
			}
		}
		
		// Return parsed name or original ID
		$result = ! empty( $parsed['name'] ) ? $parsed['name'] : $addon_id;
		$target_cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Build addon index for fast lookups
	 */
	private function build_addon_index( $addon_data ) {
		$index = array();
		
		foreach ( $addon_data as $addon ) {
			$target_id = isset( $addon['id'] ) ? $addon['id'] : ( isset( $addon['identifier'] ) ? $addon['identifier'] : null );
			
			if ( ! $target_id ) {
				continue;
			}
			
			// Index by all possible identifiers
			if ( isset( $addon['id'] ) ) {
				$index[ $addon['id'] ] = $target_id;
			}
			if ( isset( $addon['identifier'] ) ) {
				$index[ $addon['identifier'] ] = $target_id;
			}
			if ( isset( $addon['name'] ) ) {
				$index[ $addon['name'] ] = $target_id;
				$index[ strtolower( $addon['name'] ) ] = $target_id;
				$index[ WC_Product_Addons_Addon_Identifier::normalize_name( $addon['name'] ) ] = $target_id;
			}
			if ( isset( $addon['field_name'] ) ) {
				$index[ $addon['field_name'] ] = $target_id;
			}
			if ( isset( $addon['display_name'] ) ) {
				$index[ $addon['display_name'] ] = $target_id;
			}
			
			// Add variations
			$base_variations = array(
				preg_replace( '/_(?:global|product|category)_\d+$/', '', $target_id ),
				preg_replace( '/^(.*?)_(?:product|global|category).*$/', '$1', $target_id )
			);
			
			foreach ( $base_variations as $variation ) {
				if ( $variation && $variation !== $target_id ) {
					$index[ $variation ] = $target_id;
				}
			}
		}
		
		return $index;
	}

	/**
	 * Inject conditional logic data into addon containers
	 */
	public function inject_addon_conditional_data( $addon, $addon_name ) {
		// Get the current product ID
		global $post, $product;
		$product_id = 0;
		
		if ( $product && is_object( $product ) ) {
			$product_id = $product->get_id();
		} elseif ( $post ) {
			$product_id = $post->ID;
		}
		
		// Generate a unique addon identifier using our unified system
		$scope = isset( $addon['global_addon_id'] ) ? 'global' : 'product';
		$addon_identifier = WC_Product_Addons_Addon_Identifier::generate_identifier( $addon, $product_id, $scope );
		
		// Get rules that might affect this addon
		$rules_data = $this->get_rules_affecting_addon( $addon_identifier, $product_id );
		
		// Output data for JavaScript
		$data = array(
			'addon_identifier' => $addon_identifier,
			'addon_name' => $addon_name,
			'field_name' => WC_Product_Addons_Addon_Identifier::get_field_name( $addon ),
			'scope' => $scope,
			'has_rules' => ! empty( $rules_data ),
			'options' => $this->prepare_addon_options_data( $addon ),
			'rule_targets' => $rules_data,
		);
		
		// Also add debug info if in debug mode
		if ( $this->debug_mode ) {
			$data['debug'] = array(
				'addon_data' => $addon,
				'product_id' => $product_id,
			);
		}
		
		echo sprintf(
			'<script type="application/json" class="wc-pao-conditional-data" data-addon-identifier="%s">%s</script>',
			esc_attr( $addon_identifier ),
			wp_json_encode( $data )
		);
	}

	/**
	 * Get rules that might affect an addon
	 */
	private function get_rules_affecting_addon( $addon_identifier, $product_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		
		// Get rules that target this addon
		$rules = $wpdb->get_results( $wpdb->prepare("
			SELECT rule_name, rule_type, actions 
			FROM {$table_name} 
			WHERE enabled = 1 
			AND (
				rule_type = 'global' 
				OR (rule_type = 'product' AND scope_id = %d)
			)
			AND actions LIKE %s
		", $product_id, '%' . $wpdb->esc_like( $addon_identifier ) . '%' ), ARRAY_A );
		
		$rule_targets = array();
		foreach ( $rules as $rule ) {
			$actions = json_decode( $rule['actions'], true );
			if ( is_array( $actions ) ) {
				foreach ( $actions as $action ) {
					$target = $action['config']['action_addon'] ?? '';
					if ( WC_Product_Addons_Addon_Identifier::names_match( $target, $addon_identifier ) ) {
						$rule_targets[] = array(
							'rule_name' => $rule['rule_name'],
							'rule_type' => $rule['rule_type'],
							'action_type' => $action['type'] ?? '',
						);
					}
				}
			}
		}
		
		return $rule_targets;
	}

	/**
	 * Generate a unique addon key
	 */
	private function generate_addon_key( $addon, $addon_name ) {
		$field_name = $addon['field_name'] ?? sanitize_title( $addon_name );
		return $addon_name . '_' . $field_name;
	}

	/**
	 * Check if addon has conditional rules
	 */
	private function addon_has_conditional_rules( $addon_key, $product_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'wc_product_addon_rules';
		
		$count = $wpdb->get_var( $wpdb->prepare("
			SELECT COUNT(*) FROM {$table_name} 
			WHERE enabled = 1 
			AND (
				rule_type = 'global' 
				OR (rule_type = 'product' AND scope_id = %d)
			)
			AND (actions LIKE %s OR actions LIKE %s)
		", $product_id, '%' . $addon_key . '%', '%' . explode('_', $addon_key)[0] . '%' ) );
		
		return $count > 0;
	}

	/**
	 * Prepare addon options data
	 */
	private function prepare_addon_options_data( $addon ) {
		$options = array();
		
		if ( isset( $addon['options'] ) && is_array( $addon['options'] ) ) {
			foreach ( $addon['options'] as $index => $option ) {
				$options[] = array(
					'label' => $option['label'] ?? '',
					'value' => sanitize_title( $option['label'] ?? '' ) . '-' . ($index + 1),
					'key' => sanitize_title( $option['label'] ?? '' ),
					'index' => $index + 1,
					'price' => $option['price'] ?? 0
				);
			}
		}
		
		return $options;
	}

	/**
	 * Evaluate conditions with cascading rule support
	 *
	 * @param array $addon_data Array of addon data with conditional logic
	 * @param array $context    Evaluation context
	 * @return array Results array
	 */
	private function evaluate_cascading_conditions( $addon_data, $context ) {
		$iteration = 0;
		$results = array();
		$previous_state = array();
		$state_history = array();
		$actions_by_iteration = array();
		
		// Initialize results
		foreach ( $addon_data as $addon ) {
			$results[ $addon['name'] ] = array(
				'visible'         => true,
				'required'        => isset( $addon['required'] ) ? $addon['required'] : false,
				'price_modifiers' => array(),
				'option_modifiers' => array(),
				'modifications'   => array(),
			);
		}
		
		// Build dependency graph and check for cycles
		$all_rules = $this->get_all_rules_from_addons( $addon_data );
		$this->dependency_graph->build_from_rules( $all_rules );
		
		// Validate dependency graph
		$validation = $this->dependency_graph->validate();
		if ( ! $validation['valid'] ) {
			// Log errors but continue with evaluation
			foreach ( $validation['errors'] as $error ) {
				error_log( 'WC Product Addons Rule Error: ' . $error );
				
				// Add user notification
				if ( current_user_can( 'manage_woocommerce' ) ) {
					wc_add_notice( $error, 'error' );
				}
			}
		}
		
		// Log warnings
		foreach ( $validation['warnings'] as $warning ) {
			if ( $this->debug_mode ) {
				error_log( 'WC Product Addons Rule Warning: ' . $warning );
			}
		}
		
		// Get evaluation order
		$evaluation_layers = $this->dependency_graph->get_evaluation_layers();
		
		do {
			$iteration++;
			$state_changed = false;
			$iteration_actions = array();
			
			// Store current state
			$current_state = $this->serialize_state( $results );
			
			// Check for state loop (oscillation)
			if ( in_array( $current_state, $state_history, true ) ) {
				error_log( sprintf(
					'WC Product Addons: State loop detected at iteration %d. Breaking evaluation.',
					$iteration
				) );
				
				if ( current_user_can( 'manage_woocommerce' ) ) {
					wc_add_notice( 
						__( 'Conditional logic evaluation stopped: rule state loop detected.', 'woocommerce-product-addons-extra-digital' ),
						'warning'
					);
				}
				break;
			}
			
			$state_history[] = $current_state;
			
			if ( $current_state !== $previous_state ) {
				$state_changed = true;
				$previous_state = $current_state;
			}
			
			// Update context
			$context['rule_results'] = $results;
			$context['iteration'] = $iteration;
			$context['evaluation_layers'] = $evaluation_layers;
			
			// Evaluate rules by layer for proper dependency order
			foreach ( $evaluation_layers as $layer_index => $layer_addons ) {
				$layer_actions = array();
				
				foreach ( $layer_addons as $addon_name ) {
					$rules = $this->get_rules_for_addon( $addon_data, $addon_name );
					
					foreach ( $rules as $rule ) {
						if ( $this->evaluate_conditions( $rule['conditions'], $context ) ) {
							foreach ( $rule['actions'] as $action ) {
								// Add rule metadata for conflict resolution
								$action['rule_id'] = $rule['id'] ?? uniqid();
								$action['rule_priority'] = $rule['priority'] ?? 10;
								$action['rule_name'] = $rule['name'] ?? '';
								$action['evaluation_layer'] = $layer_index;
								
								$layer_actions[] = $action;
							}
						}
					}
				}
				
				// Resolve conflicts within layer
				if ( ! empty( $layer_actions ) ) {
					$resolved_actions = $this->conflict_resolver->resolve_conflicts( $layer_actions );
					$iteration_actions = array_merge( $iteration_actions, $resolved_actions );
				}
			}
			
			// Apply all resolved actions for this iteration
			foreach ( $iteration_actions as $action ) {
				$this->apply_action_to_results( $results, $action, $context );
			}
			
			$actions_by_iteration[ $iteration ] = $iteration_actions;
			
			// Check iteration limit
			if ( $iteration >= $this->max_cascade_iterations ) {
				error_log( sprintf(
					'WC Product Addons: Maximum cascade iterations (%d) reached. Evaluation stopped.',
					$this->max_cascade_iterations
				) );
				
				if ( current_user_can( 'manage_woocommerce' ) ) {
					wc_add_notice( 
						sprintf(
							__( 'Conditional logic evaluation stopped after %d iterations to prevent infinite loop.', 'woocommerce-product-addons-extra-digital' ),
							$this->max_cascade_iterations
						),
						'warning'
					);
				}
				break;
			}
			
		} while ( $state_changed );
		
		// Add evaluation metadata
		$results['_metadata'] = array(
			'iterations' => $iteration,
			'cycles_detected' => ! empty( $validation['errors'] ),
			'warnings' => $validation['warnings'],
			'evaluation_layers' => count( $evaluation_layers ),
			'total_actions' => array_sum( array_map( 'count', $actions_by_iteration ) ),
			'conflicts_resolved' => count( $this->conflict_resolver->get_conflict_log() ),
		);
		
		if ( $this->debug_mode ) {
			$results['_debug'] = array(
				'dependency_graph' => $this->dependency_graph->get_visualization_data(),
				'actions_by_iteration' => $actions_by_iteration,
				'conflict_log' => $this->conflict_resolver->get_conflict_log(),
			);
		}
		
		return $results;
	}

	/**
	 * Get rules sorted by priority for proper dependency handling
	 *
	 * @param array $addon_data Array of addon data
	 * @return array Prioritized rules
	 */
	private function get_prioritized_rules( $addon_data ) {
		$rules = array();
		
		foreach ( $addon_data as $addon ) {
			if ( isset( $addon['conditional_logic'] ) ) {
				foreach ( $addon['conditional_logic'] as $rule ) {
					$priority = isset( $rule['priority'] ) ? $rule['priority'] : 10;
					$rules[] = array(
						'addon_name' => $addon['name'],
						'rule'       => $rule,
						'priority'   => $priority,
					);
				}
			}
		}
		
		// Sort by priority (higher priority = lower number, executes first)
		usort( $rules, function( $a, $b ) {
			return $a['priority'] <=> $b['priority'];
		} );
		
		return $rules;
	}

	/**
	 * Check if an action is cascading (affects other addons)
	 *
	 * @param array $action Action configuration
	 * @return bool
	 */
	private function is_cascading_action( $action ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;
		$target = isset( $config['target'] ) ? $config['target'] : 'self';
		
		return in_array( $target, array( 'all', 'other', 'category', 'except' ) );
	}

	/**
	 * Apply cascading action that affects multiple addons
	 *
	 * @param array $results All addon results
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 */
	private function apply_cascading_action( &$results, $action, $context ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;
		$target = isset( $config['target'] ) ? $config['target'] : 'self';
		
		foreach ( $results as $addon_name => &$addon_results ) {
			$should_apply = false;
			
			switch ( $target ) {
				case 'all':
					$should_apply = true;
					break;
					
				case 'other':
					$target_addon = isset( $config['target_addon'] ) ? $config['target_addon'] : '';
					$should_apply = ( $addon_name === $target_addon );
					break;
					
				case 'category':
					// This would require addon category metadata
					$should_apply = $this->addon_in_category( $addon_name, $config, $context );
					break;
					
				case 'except':
					$except_addons = isset( $config['except_addons'] ) ? $config['except_addons'] : array();
					$should_apply = ! in_array( $addon_name, $except_addons );
					break;
			}
			
			if ( $should_apply ) {
				$this->apply_action_to_results( $addon_results, $action, $context );
			}
		}
	}

	/**
	 * Check if addon is in specified category
	 *
	 * @param string $addon_name Addon name
	 * @param array  $config     Action configuration
	 * @param array  $context    Evaluation context
	 * @return bool
	 */
	private function addon_in_category( $addon_name, $config, $context ) {
		// This would be implemented based on how addon categories are defined
		// For now, return false
		return false;
	}


	/**
	 * Render conditional logic panel in admin
	 *
	 * @param int   $post_id Product ID
	 * @param array $addon   Addon data
	 * @param int   $loop    Loop index
	 */
	public function render_conditional_logic_panel( $post_id, $addon, $loop ) {
		include WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/admin/views/html-addon-conditional-logic.php';
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();
		
		if ( $screen && in_array( $screen->id, array( 'product', 'edit-product' ) ) ) {
			wp_enqueue_script(
				'wc-product-addons-conditional-logic-admin',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/conditional-logic-admin.js',
				array( 'jquery', 'jquery-ui-sortable' ),
				WC_PRODUCT_ADDONS_VERSION,
				true
			);
			
			// Use improved CSS if it exists, otherwise fall back to original
			$css_file = file_exists( WC_PRODUCT_ADDONS_PLUGIN_PATH . '/assets/css/conditional-logic-admin-improved.css' ) 
				? 'conditional-logic-admin-improved.css' 
				: 'conditional-logic-admin.css';
			
			wp_enqueue_style(
				'wc-product-addons-conditional-logic-admin',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/' . $css_file,
				array(),
				WC_PRODUCT_ADDONS_VERSION
			);
			
			wp_localize_script( 'wc-product-addons-conditional-logic-admin', 'wc_product_addons_conditional_logic', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wc-product-addons-conditional-logic' ),
				'i18n'     => array(
					'add_condition'    => __( 'Add Condition', 'woocommerce-product-addons-extra-digital' ),
					'add_action'       => __( 'Add Action', 'woocommerce-product-addons-extra-digital' ),
					'remove'           => __( 'Remove', 'woocommerce-product-addons-extra-digital' ),
					'if'               => __( 'IF', 'woocommerce-product-addons-extra-digital' ),
					'then'             => __( 'THEN', 'woocommerce-product-addons-extra-digital' ),
					'and'              => __( 'AND', 'woocommerce-product-addons-extra-digital' ),
					'or'               => __( 'OR', 'woocommerce-product-addons-extra-digital' ),
				),
			) );
		}
	}

	/**
	 * Enqueue frontend scripts
	 */
	public function enqueue_frontend_scripts() {
		// Only load on product pages or where addons might be displayed
		if ( is_product() || is_shop() || is_cart() ) {
			
			// Check if the main addons script is registered/enqueued
			$dependencies = array( 'jquery' );
			if ( wp_script_is( 'woocommerce-addons-extra-digital', 'registered' ) ) {
				$dependencies[] = 'woocommerce-addons-extra-digital';
			}
			
			wp_enqueue_script(
				'wc-product-addons-conditional-logic',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/conditional-logic.js',
				$dependencies,
				WC_PRODUCT_ADDONS_VERSION,
				true
			);
			
			// Enqueue frontend CSS
			wp_enqueue_style(
				'wc-product-addons-conditional-logic-frontend',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/conditional-logic-frontend.css',
				array(),
				WC_PRODUCT_ADDONS_VERSION
			);
			
			wp_localize_script( 'wc-product-addons-conditional-logic', 'wc_product_addons_conditional_logic', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wc-product-addons-conditional-logic' ),
				'debug'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
			) );
			
			// Add AJAX queue manager
			wp_add_inline_script( 
				'wc-product-addons-conditional-logic', 
				WC_Product_Addons_Ajax_Queue::get_queue_manager_js(),
				'before' 
			);
			
			// Also check if we need to trigger the main addon scripts
			if ( ! wp_script_is( 'woocommerce-addons-extra-digital', 'enqueued' ) && class_exists( 'WC_Product_Addons_Display' ) ) {
				$display = $GLOBALS['Product_Addon_Display'] ?? new WC_Product_Addons_Display();
				if ( method_exists( $display, 'addon_scripts' ) ) {
					$display->addon_scripts();
				}
			}
		}
	}

	/**
	 * Serialize state for comparison
	 *
	 * @param array $state State array
	 * @return string Serialized state
	 */
	private function serialize_state( $state ) {
		// Remove metadata before serialization
		$clean_state = $state;
		unset( $clean_state['_metadata'], $clean_state['_debug'] );
		return md5( json_encode( $clean_state ) );
	}

	/**
	 * Get all rules from addon data
	 *
	 * @param array $addon_data Addon data array
	 * @return array All rules indexed by target
	 */
	private function get_all_rules_from_addons( $addon_data ) {
		$rules = array();
		
		foreach ( $addon_data as $addon ) {
			if ( isset( $addon['conditional_logic'] ) && is_array( $addon['conditional_logic'] ) ) {
				foreach ( $addon['conditional_logic'] as $rule ) {
					$rule['target_addon'] = $addon['name'];
					$rules[] = $rule;
				}
			}
		}
		
		return $rules;
	}

	/**
	 * Get rules for specific addon
	 *
	 * @param array $addon_data Addon data
	 * @param string $addon_name Addon name
	 * @return array Rules for addon
	 */
	private function get_rules_for_addon( $addon_data, $addon_name ) {
		foreach ( $addon_data as $addon ) {
			if ( $addon['name'] === $addon_name && isset( $addon['conditional_logic'] ) ) {
				return $addon['conditional_logic'];
			}
		}
		return array();
	}

	/**
	 * Apply action to results array
	 *
	 * @param array &$results Results array
	 * @param array $action Action to apply
	 * @param array $context Evaluation context
	 */
	private function apply_action_to_results( &$results, $action, $context ) {
		$target = $this->get_action_target_addon( $action );
		
		if ( ! isset( $results[ $target ] ) ) {
			$results[ $target ] = array(
				'visible'         => true,
				'required'        => false,
				'price_modifiers' => array(),
				'option_modifiers' => array(),
				'modifications'   => array(),
			);
		}
		
		switch ( $action['type'] ) {
			case 'show_addon':
				$results[ $target ]['visible'] = true;
				break;
				
			case 'hide_addon':
				$results[ $target ]['visible'] = false;
				break;
				
			case 'make_required':
				$results[ $target ]['required'] = true;
				break;
				
			case 'make_optional':
				$results[ $target ]['required'] = false;
				break;
				
			case 'set_price':
			case 'adjust_price':
				$results[ $target ]['price_modifiers'][] = $action;
				break;
				
			case 'show_option':
			case 'hide_option':
				$results[ $target ]['option_modifiers'][] = $action;
				break;
				
			default:
				$results[ $target ]['modifications'][] = $action;
		}
	}

	/**
	 * Get action target addon name
	 *
	 * @param array $action Action configuration
	 * @return string Target addon name
	 */
	private function get_action_target_addon( $action ) {
		if ( isset( $action['target_addon'] ) ) {
			return $action['target_addon'];
		}
		
		if ( isset( $action['config']['action_addon'] ) ) {
			return $this->resolve_addon_target( $action['config']['action_addon'], array() );
		}
		
		return 'unknown';
	}

	/**
	 * Modify addon price raw value for cart calculations
	 *
	 * @param float $price  Original price
	 * @param array $option Option data
	 * @return float Modified price
	 */
	public function modify_addon_price_raw( $price, $option ) {
		// Get current cart context
		$cart_context = $this->get_cart_evaluation_context();
		
		// Check if we have active price modifications from conditional logic
		if ( isset( $cart_context['price_modifications'] ) ) {
			$option_key = isset( $option['label'] ) ? sanitize_title( $option['label'] ) : '';
			
			if ( $option_key && isset( $cart_context['price_modifications'][ $option_key ] ) ) {
				$modification = $cart_context['price_modifications'][ $option_key ];
				
				// Apply price modification
				if ( isset( $modification['type'] ) && $modification['type'] === 'set_price' ) {
					return floatval( $modification['value'] );
				}
			}
		}
		
		return $price;
	}

	/**
	 * Check if cart price should be adjusted
	 *
	 * @param bool  $adjust    Whether to adjust price
	 * @param array $cart_item Cart item data
	 * @return bool
	 */
	public function should_adjust_cart_price( $adjust, $cart_item ) {
		// Always allow price adjustments when addons are present
		return $adjust;
	}

	/**
	 * Get cart evaluation context
	 *
	 * @return array Cart context
	 */
	private function get_cart_evaluation_context() {
		// This would be populated from session or AJAX data
		// For now, return empty array
		return apply_filters( 'woocommerce_product_addons_cart_evaluation_context', array() );
	}
	
	/**
	 * Apply conditional prices from POST data to cart items
	 *
	 * @param array  $data    Cart item data from addon
	 * @param array  $addon   Addon configuration
	 * @param int    $product_id Product ID
	 * @param array  $post_data POST data
	 * @return array Modified cart item data
	 */
	public function apply_conditional_prices_from_post( $data, $addon, $product_id, $post_data ) {
		// Check if we have conditional price data in POST
		if ( ! is_array( $data ) || empty( $post_data ) ) {
			return $data;
		}
		
		// Process each cart item data entry
		foreach ( $data as $key => $item ) {
			if ( ! isset( $item['field_name'] ) || ! isset( $item['value'] ) ) {
				continue;
			}
			
			// Build the conditional price key
			$field_name = 'addon-' . $item['field_name'];
			$option_value = $item['value'];
			
			// Look for the value in the actual field value (for selects)
			if ( isset( $post_data[ $field_name ] ) ) {
				$actual_value = $post_data[ $field_name ];
				$price_key = 'conditional_price_' . $field_name . '_' . $actual_value;
				
				if ( isset( $post_data[ $price_key ] ) ) {
					$conditional_price = floatval( $post_data[ $price_key ] );
					$data[ $key ]['price'] = $conditional_price;
					$data[ $key ]['conditional_price_applied'] = true;
					
					// Log for debugging
					if ( $this->debug_mode ) {
						error_log( sprintf(
							'Applied conditional price: %s = %s (was %s)',
							$price_key,
							$conditional_price,
							isset( $item['price'] ) ? $item['price'] : 'not set'
						) );
					}
				}
			}
		}
		
		return $data;
	}
}

// Note: This class is initialized in the main plugin file after all dependencies are loaded