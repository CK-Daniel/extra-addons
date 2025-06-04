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
		error_log( 'WC_Product_Addons_Conditional_Logic: Constructor called' );
		$this->init();
	}

	/**
	 * Initialize conditional logic system
	 */
	private function init() {
		error_log( 'WC_Product_Addons_Conditional_Logic: init() called' );
		error_log( 'WC_Product_Addons_Conditional_Logic: Current user ID: ' . get_current_user_id() );
		error_log( 'WC_Product_Addons_Conditional_Logic: Can manage WooCommerce: ' . ( current_user_can( 'manage_woocommerce' ) ? 'yes' : 'no' ) );
		error_log( 'WC_Product_Addons_Conditional_Logic: Is admin: ' . ( is_admin() ? 'yes' : 'no' ) );
		
		// Register default condition types
		$this->register_default_conditions();
		
		// Register default action types
		$this->register_default_actions();
		
		// Hook into addon display
		add_filter( 'woocommerce_product_addons_get_addon_array', array( $this, 'inject_conditional_logic' ), 10, 2 );
		
		// Hook into price calculations
		add_filter( 'woocommerce_product_addons_price', array( $this, 'modify_addon_price' ), 10, 4 );
		
		// AJAX handlers - register immediately
		error_log( 'WC_Product_Addons_Conditional_Logic: Registering AJAX handlers immediately' );
		add_action( 'wp_ajax_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		add_action( 'wp_ajax_nopriv_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		
		// Admin AJAX handlers
		add_action( 'wp_ajax_wc_pao_get_rules', array( $this, 'ajax_get_rules' ) );
		add_action( 'wp_ajax_wc_pao_save_conditional_rule', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_wc_pao_get_rule', array( $this, 'ajax_get_rule' ) );
		add_action( 'wp_ajax_wc_pao_duplicate_rule', array( $this, 'ajax_duplicate_rule' ) );
		add_action( 'wp_ajax_wc_pao_toggle_rule', array( $this, 'ajax_toggle_rule' ) );
		add_action( 'wp_ajax_wc_pao_delete_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_wc_pao_update_rule_priorities', array( $this, 'ajax_update_rule_priorities' ) );
		add_action( 'wp_ajax_wc_pao_get_addons', array( $this, 'ajax_get_addons' ) );
		add_action( 'wp_ajax_wc_pao_get_addon_options', array( $this, 'ajax_get_addon_options' ) );
		
		// Simple test handler
		add_action( 'wp_ajax_test_simple_ajax', array( $this, 'test_simple_ajax' ) );
		
		error_log( 'WC_Product_Addons_Conditional_Logic: AJAX handlers registered immediately' );
		
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'woocommerce_product_addons_panel_after_options', array( $this, 'render_conditional_logic_panel' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
		
		// Frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'woocommerce_product_addons_end', array( $this, 'output_conditional_data' ), 10 );
		
		// Allow third-party condition registration
		do_action( 'woocommerce_product_addons_register_conditions', $this );
		
		error_log( 'WC_Product_Addons_Conditional_Logic: init() completed' );
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
			// Handle new action structure with config
			if ( isset( $action['config'] ) ) {
				$target_level = isset( $action['config']['action_target_level'] ) ? $action['config']['action_target_level'] : '';
				$target_addon = isset( $action['config']['action_addon'] ) ? $action['config']['action_addon'] : '';
				
				// If target level is 'addon' (entire addon) and addon matches
				if ( $target_level === 'addon' && $target_addon === $addon['name'] ) {
					return true;
				}
				
				// If target level is 'option' (specific option) and addon matches
				if ( $target_level === 'option' && $target_addon === $addon['name'] ) {
					return true;
				}
			}
			
			// Handle legacy action structure
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
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
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
	 * Evaluate conditions with cascading rule support
	 *
	 * @param array $addon_data Array of addon data with conditional logic
	 * @param array $context    Evaluation context
	 * @return array Results array
	 */
	private function evaluate_cascading_conditions( $addon_data, $context ) {
		$max_iterations = 10; // Prevent infinite loops
		$iteration = 0;
		$results = array();
		$previous_state = array();
		
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
		
		do {
			$iteration++;
			$state_changed = false;
			
			// Store current state to compare
			$current_state = json_encode( $results );
			
			if ( $current_state !== $previous_state ) {
				$state_changed = true;
				$previous_state = $current_state;
			}
			
			// Update context with current rule results
			$context['rule_results'] = $results;
			$context['iteration'] = $iteration;
			
			// Evaluate all rules in priority order
			$prioritized_rules = $this->get_prioritized_rules( $addon_data );
			
			foreach ( $prioritized_rules as $rule_data ) {
				$addon_name = $rule_data['addon_name'];
				$rule = $rule_data['rule'];
				
				if ( $this->evaluate_conditions( $rule['conditions'], $context ) ) {
					foreach ( $rule['actions'] as $action ) {
						// Check if this action affects other addons (cascading)
						if ( $this->is_cascading_action( $action ) ) {
							$this->apply_cascading_action( $results, $action, $context );
						} else {
							// Apply to current addon
							$this->apply_action_to_results( $results[ $addon_name ], $action, $context );
						}
					}
				}
			}
			
		} while ( $state_changed && $iteration < $max_iterations );
		
		// Log if we hit max iterations (potential circular dependency)
		if ( $iteration >= $max_iterations ) {
			error_log( 'WC Product Addons: Maximum iterations reached in cascading evaluation. Possible circular dependency.' );
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
	 * Apply action to results array
	 *
	 * @param array $results Results array
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 */
	private function apply_action_to_results( &$results, $action, $context ) {
		$handler = $this->get_action_handler( $action['type'] );
		
		if ( $handler && method_exists( $handler, 'apply_to_results' ) ) {
			$handler->apply_to_results( $results, $action, $context );
		}
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
		
		// Check if we're on the product edit pages or the conditional logic admin page
		if ( $screen && ( in_array( $screen->id, array( 'product', 'edit-product' ) ) || 
			( isset( $_GET['page'] ) && $_GET['page'] === 'addon-conditional-logic' ) ) ) {
			
			wp_enqueue_script(
				'wc-product-addons-conditional-logic-admin',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/conditional-logic-admin.js',
				array( 'jquery', 'jquery-ui-sortable' ),
				WC_PRODUCT_ADDONS_VERSION,
				true
			);
			
			wp_enqueue_style(
				'wc-product-addons-conditional-logic-admin',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/conditional-logic-admin.css',
				array(),
				WC_PRODUCT_ADDONS_VERSION
			);
			
			wp_localize_script( 'wc-product-addons-conditional-logic-admin', 'wc_product_addons_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'save_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'get_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'get_rules_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'duplicate_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'toggle_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'delete_rule_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'update_priorities_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'get_addons_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'get_addon_options_nonce' => wp_create_nonce( 'wc_pao_conditional_logic' ),
				'search_products_nonce' => wp_create_nonce( 'search-products' ),
				'search_categories_nonce' => wp_create_nonce( 'search-categories' ),
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
		if ( is_product() || is_shop() ) {
			wp_enqueue_script(
				'wc-product-addons-conditional-logic',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/conditional-logic.js',
				array( 'jquery', 'woocommerce-addons-extra-digital' ),
				WC_PRODUCT_ADDONS_VERSION,
				true
			);
			
			wp_localize_script( 'wc-product-addons-conditional-logic', 'wc_product_addons_conditional_logic', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wc-product-addons-conditional-logic' ),
			) );
		}
	}

	/**
	 * Output conditional logic data for frontend
	 */
	public function output_conditional_data() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		$product_id = $product->get_id();
		$conditional_data = $this->get_product_conditional_data( $product_id );

		if ( ! empty( $conditional_data ) ) {
			echo '<script type="text/javascript">';
			echo 'window.wc_product_addons_conditional_data = ' . wp_json_encode( $conditional_data ) . ';';
			echo '</script>';
		}
	}

	/**
	 * Get conditional logic data for a product
	 *
	 * @param int $product_id Product ID
	 * @return array Conditional data
	 */
	public function get_product_conditional_data( $product_id ) {
		global $wpdb;

		$rules = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wc_product_addon_rules 
			WHERE (rule_type = 'product' AND scope_id = %d) 
			OR rule_type = 'global'
			AND enabled = 1 
			ORDER BY priority DESC",
			$product_id
		) );

		$conditional_data = array(
			'rules' => array(),
			'product_id' => $product_id
		);

		foreach ( $rules as $rule ) {
			$conditions = json_decode( $rule->conditions, true );
			$actions = json_decode( $rule->actions, true );

			$conditional_data['rules'][] = array(
				'rule_id' => $rule->rule_id,
				'name' => $rule->rule_name,
				'type' => $rule->rule_type,
				'conditions' => $conditions,
				'actions' => $actions,
				'priority' => $rule->priority
			);
		}

		return $conditional_data;
	}

	/**
	 * AJAX handler to get all rules
	 */
	public function ajax_get_rules() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		global $wpdb;
		
		$rules = $wpdb->get_results( 
			"SELECT * FROM {$wpdb->prefix}wc_product_addon_rules 
			ORDER BY priority DESC, rule_id DESC"
		);
		
		wp_send_json_success( $rules );
	}
	
	/**
	 * AJAX handler to save a rule
	 */
	public function ajax_save_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$rule_data = json_decode( stripslashes( $_POST['rule_data'] ), true );
		
		if ( empty( $rule_data ) ) {
			wp_send_json_error( 'Invalid rule data' );
		}
		
		global $wpdb;
		
		// Prepare data for insertion
		$data = array(
			'rule_name' => sanitize_text_field( $rule_data['rule_name'] ),
			'rule_type' => sanitize_text_field( $rule_data['rule_scope'] ),
			'scope_id' => ! empty( $rule_data['scope_id'] ) ? intval( $rule_data['scope_id'] ) : 0,
			'conditions' => wp_json_encode( $rule_data['conditions'] ),
			'actions' => wp_json_encode( $rule_data['actions'] ),
			'priority' => $this->get_next_priority(),
			'enabled' => 1,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' )
		);
		
		$format = array( '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' );
		
		if ( ! empty( $rule_data['rule_id'] ) ) {
			// Update existing rule
			$data['updated_at'] = current_time( 'mysql' );
			unset( $data['created_at'] );
			
			$result = $wpdb->update(
				"{$wpdb->prefix}wc_product_addon_rules",
				$data,
				array( 'rule_id' => intval( $rule_data['rule_id'] ) ),
				$format,
				array( '%d' )
			);
		} else {
			// Insert new rule
			$result = $wpdb->insert(
				"{$wpdb->prefix}wc_product_addon_rules",
				$data,
				$format
			);
		}
		
		if ( false === $result ) {
			wp_send_json_error( 'Failed to save rule' );
		}
		
		wp_send_json_success( array( 'rule_id' => $wpdb->insert_id ) );
	}
	
	/**
	 * AJAX handler to get a single rule
	 */
	public function ajax_get_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$rule_id = intval( $_POST['rule_id'] );
		
		if ( ! $rule_id ) {
			wp_send_json_error( 'Invalid rule ID' );
		}
		
		global $wpdb;
		
		$rule = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wc_product_addon_rules WHERE rule_id = %d",
			$rule_id
		) );
		
		if ( ! $rule ) {
			wp_send_json_error( 'Rule not found' );
		}
		
		// Decode JSON fields
		$rule->conditions = json_decode( $rule->conditions, true );
		$rule->actions = json_decode( $rule->actions, true );
		
		wp_send_json_success( $rule );
	}
	
	/**
	 * AJAX handler to duplicate a rule
	 */
	public function ajax_duplicate_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$rule_id = intval( $_POST['rule_id'] );
		
		if ( ! $rule_id ) {
			wp_send_json_error( 'Invalid rule ID' );
		}
		
		global $wpdb;
		
		// Get the original rule
		$rule = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wc_product_addon_rules WHERE rule_id = %d",
			$rule_id
		), ARRAY_A );
		
		if ( ! $rule ) {
			wp_send_json_error( 'Rule not found' );
		}
		
		// Remove the ID and update timestamps
		unset( $rule['rule_id'] );
		$rule['rule_name'] = $rule['rule_name'] . ' (Copy)';
		$rule['created_at'] = current_time( 'mysql' );
		$rule['updated_at'] = current_time( 'mysql' );
		$rule['priority'] = $this->get_next_priority();
		
		$result = $wpdb->insert(
			"{$wpdb->prefix}wc_product_addon_rules",
			$rule
		);
		
		if ( false === $result ) {
			wp_send_json_error( 'Failed to duplicate rule' );
		}
		
		wp_send_json_success( array( 'rule_id' => $wpdb->insert_id ) );
	}
	
	/**
	 * AJAX handler to toggle rule status
	 */
	public function ajax_toggle_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$rule_id = intval( $_POST['rule_id'] );
		$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === 'true' ? 1 : 0;
		
		if ( ! $rule_id ) {
			wp_send_json_error( 'Invalid rule ID' );
		}
		
		global $wpdb;
		
		$result = $wpdb->update(
			"{$wpdb->prefix}wc_product_addon_rules",
			array( 
				'enabled' => $enabled,
				'updated_at' => current_time( 'mysql' )
			),
			array( 'rule_id' => $rule_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		
		if ( false === $result ) {
			wp_send_json_error( 'Failed to update rule status' );
		}
		
		wp_send_json_success();
	}
	
	/**
	 * AJAX handler to delete a rule
	 */
	public function ajax_delete_rule() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$rule_id = intval( $_POST['rule_id'] );
		
		if ( ! $rule_id ) {
			wp_send_json_error( 'Invalid rule ID' );
		}
		
		global $wpdb;
		
		// Delete associated conditions and actions first
		$wpdb->delete( "{$wpdb->prefix}wc_product_addon_conditions", array( 'rule_id' => $rule_id ), array( '%d' ) );
		$wpdb->delete( "{$wpdb->prefix}wc_product_addon_actions", array( 'rule_id' => $rule_id ), array( '%d' ) );
		
		// Delete the rule
		$result = $wpdb->delete(
			"{$wpdb->prefix}wc_product_addon_rules",
			array( 'rule_id' => $rule_id ),
			array( '%d' )
		);
		
		if ( false === $result ) {
			wp_send_json_error( 'Failed to delete rule' );
		}
		
		wp_send_json_success();
	}
	
	/**
	 * AJAX handler to update rule priorities
	 */
	public function ajax_update_rule_priorities() {
		check_ajax_referer( 'wc_pao_conditional_logic', 'security' );
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$priorities = json_decode( stripslashes( $_POST['priorities'] ), true );
		
		if ( empty( $priorities ) ) {
			wp_send_json_error( 'Invalid priority data' );
		}
		
		global $wpdb;
		
		foreach ( $priorities as $priority_data ) {
			$wpdb->update(
				"{$wpdb->prefix}wc_product_addon_rules",
				array( 
					'priority' => intval( $priority_data['priority'] ),
					'updated_at' => current_time( 'mysql' )
				),
				array( 'rule_id' => intval( $priority_data['rule_id'] ) ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}
		
		wp_send_json_success();
	}
	
	/**
	 * Get the next available priority
	 */
	private function get_next_priority() {
		global $wpdb;
		
		$max_priority = $wpdb->get_var(
			"SELECT MAX(priority) FROM {$wpdb->prefix}wc_product_addon_rules"
		);
		
		return intval( $max_priority ) + 1;
	}
	
	/**
	 * AJAX handler to get available addons
	 */
	public function ajax_get_addons() {
		// Set proper headers
		header( 'Content-Type: application/json' );
		
		// Check nonce for security
		if ( ! check_ajax_referer( 'wc_pao_conditional_logic', 'security', false ) ) {
			wp_send_json_error( 'Invalid security token' );
		}
		
		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$context = isset( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : 'all';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		
		$response_data = array();
		
		// Get global addons
		if ( $context === 'all' || $context === 'global_only' ) {
			$global_addons = $this->get_global_addons();
			if ( ! empty( $global_addons ) ) {
				$response_data['global'] = array(
					'label' => __( 'Global Add-ons', 'woocommerce-product-addons-extra-digital' ),
					'addons' => $global_addons
				);
			}
		}
		
		// Get product-specific addons
		if ( ( $context === 'all' || $context === 'specific_product' ) && $product_id ) {
			$product_addons = $this->get_product_addons( $product_id );
			if ( ! empty( $product_addons ) ) {
				$product = wc_get_product( $product_id );
				$product_name = $product ? $product->get_name() : __( 'Product', 'woocommerce-product-addons-extra-digital' );
				$response_data['product_' . $product_id] = array(
					'label' => sprintf( __( '%s Add-ons', 'woocommerce-product-addons-extra-digital' ), $product_name ),
					'addons' => $product_addons
				);
			}
		}
		
		// If context is 'all' and no specific product, get addons from all products
		if ( $context === 'all' && ! $product_id ) {
			// Get a sample of products with addons
			$products_with_addons = get_posts( array(
				'post_type' => 'product',
				'posts_per_page' => 50,
				'meta_query' => array(
					array(
						'key' => '_product_addons',
						'compare' => 'EXISTS'
					)
				)
			) );
			
			foreach ( $products_with_addons as $product_post ) {
				$product_addons = $this->get_product_addons( $product_post->ID );
				if ( ! empty( $product_addons ) ) {
					$response_data['product_' . $product_post->ID] = array(
						'label' => sprintf( __( '%s Add-ons', 'woocommerce-product-addons-extra-digital' ), $product_post->post_title ),
						'addons' => $product_addons
					);
				}
			}
		}
		
		wp_send_json_success( $response_data );
	}
	
	/**
	 * AJAX handler to get addon options
	 */
	public function ajax_get_addon_options() {
		// Set proper headers
		header( 'Content-Type: application/json' );
		
		// Check nonce for security
		if ( ! check_ajax_referer( 'wc_pao_conditional_logic', 'security', false ) ) {
			wp_send_json_error( 'Invalid security token' );
		}
		
		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$addon_id = isset( $_POST['addon_id'] ) ? sanitize_text_field( $_POST['addon_id'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		
		if ( empty( $addon_id ) ) {
			wp_send_json_error( 'No addon ID provided' );
		}
		
		$options = $this->get_addon_options_by_id( $addon_id, $product_id );
		
		wp_send_json_success( $options );
	}
	
	/**
	 * Get global addons
	 */
	private function get_global_addons() {
		$addons = array();
		
		// Get global addon groups
		$global_groups = get_posts( array(
			'post_type' => 'global_product_addon',
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_key' => '_priority',
			'orderby' => 'meta_value_num',
			'order' => 'ASC'
		) );
		
		foreach ( $global_groups as $group ) {
			$group_addons = get_post_meta( $group->ID, '_product_addons', true );
			
			if ( is_array( $group_addons ) ) {
				foreach ( $group_addons as $addon ) {
					if ( isset( $addon['name'] ) && ! empty( $addon['name'] ) ) {
						$addons[] = array(
							'id' => 'global_' . $group->ID . '_' . sanitize_title( $addon['name'] ),
							'name' => $addon['name'],
							'type' => isset( $addon['type'] ) ? $addon['type'] : 'text',
							'group_id' => $group->ID,
							'group_name' => $group->post_title
						);
					}
				}
			}
		}
		
		return $addons;
	}
	
	/**
	 * Get product-specific addons
	 */
	private function get_product_addons( $product_id ) {
		$addons = array();
		
		if ( ! $product_id ) {
			return $addons;
		}
		
		$product_addons = get_post_meta( $product_id, '_product_addons', true );
		
		if ( is_array( $product_addons ) ) {
			foreach ( $product_addons as $addon ) {
				if ( isset( $addon['name'] ) && ! empty( $addon['name'] ) ) {
					$addons[] = array(
						'id' => 'product_' . $product_id . '_' . sanitize_title( $addon['name'] ),
						'name' => $addon['name'],
						'type' => isset( $addon['type'] ) ? $addon['type'] : 'text',
						'product_id' => $product_id
					);
				}
			}
		}
		
		return $addons;
	}
	
	/**
	 * Get addon options by ID
	 */
	private function get_addon_options_by_id( $addon_id, $product_id ) {
		$options = array();
		
		// Parse addon ID to determine source
		if ( strpos( $addon_id, 'global_' ) === 0 ) {
			// Global addon
			$parts = explode( '_', $addon_id );
			if ( count( $parts ) >= 3 ) {
				$group_id = intval( $parts[1] );
				$addon_name = str_replace( '_', ' ', implode( '_', array_slice( $parts, 2 ) ) );
				
				$group_addons = get_post_meta( $group_id, '_product_addons', true );
				
				if ( is_array( $group_addons ) ) {
					foreach ( $group_addons as $addon ) {
						if ( isset( $addon['name'] ) && sanitize_title( $addon['name'] ) === sanitize_title( $addon_name ) ) {
							if ( isset( $addon['options'] ) && is_array( $addon['options'] ) ) {
								foreach ( $addon['options'] as $option ) {
									if ( isset( $option['label'] ) ) {
										$options[] = array(
											'value' => sanitize_title( $option['label'] ),
											'label' => $option['label']
										);
									}
								}
							}
							break;
						}
					}
				}
			}
		} elseif ( strpos( $addon_id, 'product_' ) === 0 ) {
			// Product-specific addon
			$parts = explode( '_', $addon_id );
			if ( count( $parts ) >= 3 ) {
				$product_id = intval( $parts[1] );
				$addon_name = str_replace( '_', ' ', implode( '_', array_slice( $parts, 2 ) ) );
				
				$product_addons = get_post_meta( $product_id, '_product_addons', true );
				
				if ( is_array( $product_addons ) ) {
					foreach ( $product_addons as $addon ) {
						if ( isset( $addon['name'] ) && sanitize_title( $addon['name'] ) === sanitize_title( $addon_name ) ) {
							if ( isset( $addon['options'] ) && is_array( $addon['options'] ) ) {
								foreach ( $addon['options'] as $option ) {
									if ( isset( $option['label'] ) ) {
										$options[] = array(
											'value' => sanitize_title( $option['label'] ),
											'label' => $option['label']
										);
									}
								}
							}
							break;
						}
					}
				}
			}
		}
		
		return $options;
	}
	
	/**
	 * Register AJAX handlers on init hook
	 */
	public function register_ajax_handlers() {
		error_log( 'WC_Product_Addons_Conditional_Logic: register_ajax_handlers() called' );
		error_log( 'WC_Product_Addons_Conditional_Logic: Current user ID: ' . get_current_user_id() );
		error_log( 'WC_Product_Addons_Conditional_Logic: Can manage WooCommerce: ' . ( current_user_can( 'manage_woocommerce' ) ? 'yes' : 'no' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		add_action( 'wp_ajax_nopriv_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		
		// Admin AJAX handlers
		add_action( 'wp_ajax_wc_pao_get_rules', array( $this, 'ajax_get_rules' ) );
		add_action( 'wp_ajax_wc_pao_save_conditional_rule', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_wc_pao_get_rule', array( $this, 'ajax_get_rule' ) );
		add_action( 'wp_ajax_wc_pao_duplicate_rule', array( $this, 'ajax_duplicate_rule' ) );
		add_action( 'wp_ajax_wc_pao_toggle_rule', array( $this, 'ajax_toggle_rule' ) );
		add_action( 'wp_ajax_wc_pao_delete_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_wc_pao_update_rule_priorities', array( $this, 'ajax_update_rule_priorities' ) );
		add_action( 'wp_ajax_wc_pao_get_addons', array( $this, 'ajax_get_addons' ) );
		add_action( 'wp_ajax_wc_pao_get_addon_options', array( $this, 'ajax_get_addon_options' ) );
		
		// Simple test handler
		add_action( 'wp_ajax_test_simple_ajax', array( $this, 'test_simple_ajax' ) );
		
		error_log( 'WC_Product_Addons_Conditional_Logic: AJAX handlers registered on init hook' );
	}

	/**
	 * Simple test AJAX handler
	 */
	public function test_simple_ajax() {
		// Set proper headers
		header( 'Content-Type: application/json' );
		
		// Basic response
		$response = array(
			'success' => true,
			'data' => array(
				'message' => 'Simple AJAX test working!',
				'user_can_manage_woocommerce' => current_user_can( 'manage_woocommerce' ),
				'current_user_id' => get_current_user_id(),
				'timestamp' => time()
			)
		);
		
		echo json_encode( $response );
		wp_die(); // Important: prevent WordPress from adding extra output
	}
}

// Note: This class is initialized in the main plugin file after all dependencies are loaded