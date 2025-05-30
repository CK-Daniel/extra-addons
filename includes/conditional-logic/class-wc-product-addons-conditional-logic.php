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
		$this->init();
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
		
		// AJAX handlers
		add_action( 'wp_ajax_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		add_action( 'wp_ajax_nopriv_wc_product_addons_evaluate_conditions', array( $this, 'ajax_evaluate_conditions' ) );
		
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'woocommerce_product_addons_panel_after_options', array( $this, 'render_conditional_logic_panel' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
		
		// Frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		
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
			'location'  => 'WC_Product_Addons_Condition_Location',
			'inventory' => 'WC_Product_Addons_Condition_Inventory',
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
		
		if ( $screen && in_array( $screen->id, array( 'product', 'edit-product' ) ) ) {
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
		if ( is_product() || is_shop() ) {
			wp_enqueue_script(
				'wc-product-addons-conditional-logic',
				WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/conditional-logic.js',
				array( 'jquery', 'woocommerce-addons' ),
				WC_PRODUCT_ADDONS_VERSION,
				true
			);
			
			wp_localize_script( 'wc-product-addons-conditional-logic', 'wc_product_addons_conditional_logic', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wc-product-addons-conditional-logic' ),
			) );
		}
	}
}

// Initialize the conditional logic system
WC_Product_Addons_Conditional_Logic::get_instance();