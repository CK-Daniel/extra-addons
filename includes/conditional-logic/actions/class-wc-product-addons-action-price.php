<?php
/**
 * Price Action Class
 *
 * Handles price modification actions including cascade pricing.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Price Action Class
 *
 * @class    WC_Product_Addons_Action_Price
 * @extends  WC_Product_Addons_Action
 * @version  4.0.0
 */
class WC_Product_Addons_Action_Price extends WC_Product_Addons_Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'price';
	}

	/**
	 * Execute the price modification
	 *
	 * @param mixed $price   Original price
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 * @return float Modified price
	 */
	public function execute( $price, $action, $context ) {
		$original_price = floatval( $price );
		$modified_price = $original_price;

		// Get modification configuration
		$config = isset( $action['config'] ) ? $action['config'] : $action;
		$modification = isset( $config['modification'] ) ? $config['modification'] : null;

		if ( ! $modification ) {
			return $original_price;
		}

		// Apply modification based on type
		$method = isset( $modification['method'] ) ? $modification['method'] : 'add';
		$value = isset( $modification['value'] ) ? floatval( $modification['value'] ) : 0;

		switch ( $method ) {
			case 'add':
				$modified_price = $original_price + $value;
				break;

			case 'subtract':
				$modified_price = $original_price - $value;
				break;

			case 'multiply':
				$modified_price = $original_price * $value;
				break;

			case 'divide':
				if ( $value != 0 ) {
					$modified_price = $original_price / $value;
				}
				break;

			case 'set':
				$modified_price = $value;
				break;

			case 'percentage_add':
				$modified_price = $original_price * ( 1 + ( $value / 100 ) );
				break;

			case 'percentage_subtract':
				$modified_price = $original_price * ( 1 - ( $value / 100 ) );
				break;

			case 'formula':
				$modified_price = $this->evaluate_formula( $modification, $original_price, $context );
				break;

			case 'cascade':
				// Cascade pricing is handled differently - it modifies multiple addon prices
				$modified_price = $this->apply_cascade_pricing( $original_price, $modification, $context );
				break;

			case 'sync':
				$modified_price = $this->sync_price( $original_price, $modification, $context );
				break;

			case 'scale':
				$modified_price = $this->scale_price( $original_price, $modification, $context );
				break;

			case 'tiered':
				$modified_price = $this->apply_tiered_pricing( $original_price, $modification, $context );
				break;
		}

		// Apply constraints
		if ( isset( $modification['min_price'] ) && $modified_price < $modification['min_price'] ) {
			$modified_price = floatval( $modification['min_price'] );
		}

		if ( isset( $modification['max_price'] ) && $modified_price > $modification['max_price'] ) {
			$modified_price = floatval( $modification['max_price'] );
		}

		// Apply rounding if specified
		if ( isset( $modification['round'] ) && $modification['round'] ) {
			$precision = isset( $modification['round_precision'] ) ? intval( $modification['round_precision'] ) : 2;
			$modified_price = round( $modified_price, $precision );
		}

		// Log the modification
		$this->log_execution( $action, $original_price, $modified_price, $context );

		return $modified_price;
	}

	/**
	 * Apply action to results array for AJAX
	 *
	 * @param array $results Results array to modify
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 */
	public function apply_to_results( &$results, $action, $context ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;

		// Store price modifiers for frontend application
		if ( ! isset( $results['price_modifiers'] ) ) {
			$results['price_modifiers'] = array();
		}

		$results['price_modifiers'][] = array(
			'action_id'     => isset( $action['id'] ) ? $action['id'] : uniqid(),
			'target'        => isset( $config['target'] ) ? $config['target'] : 'self',
			'modification'  => isset( $config['modification'] ) ? $config['modification'] : null,
			'apply_to'      => isset( $config['apply_to_options'] ) ? $config['apply_to_options'] : 'all',
			'message'       => $this->get_message_config( $action ),
		);

		// For cascade pricing, store additional data
		if ( isset( $config['modification']['method'] ) && $config['modification']['method'] === 'cascade' ) {
			$results['cascade_rules'][] = $config;
		}
	}

	/**
	 * Evaluate price formula
	 *
	 * @param array  $modification Modification configuration
	 * @param float  $original_price Original price
	 * @param array  $context Evaluation context
	 * @return float
	 */
	private function evaluate_formula( $modification, $original_price, $context ) {
		if ( ! isset( $modification['formula'] ) ) {
			return $original_price;
		}

		$formula = $modification['formula'];
		$variables = isset( $modification['variables'] ) ? $modification['variables'] : array();

		// Add default variables
		$variables['base_price'] = $original_price;
		$variables['original_price'] = $original_price;

		// Add context variables
		if ( isset( $context['product'] ) ) {
			$variables['product_price'] = $context['product']->get_price();
			$variables['product_regular_price'] = $context['product']->get_regular_price();
		}

		if ( isset( $context['cart'] ) ) {
			$variables['cart_total'] = $context['cart']->get_cart_contents_total();
			$variables['cart_items'] = $context['cart']->get_cart_contents_count();
		}

		// Replace variables in formula
		foreach ( $variables as $var_name => $var_value ) {
			$formula = str_replace( $var_name, $var_value, $formula );
		}

		// Evaluate formula safely
		try {
			// Basic safety check - only allow numbers, operators, and functions
			if ( ! preg_match( '/^[0-9\+\-\*\/\(\)\.\s]+$/', $formula ) ) {
				// Contains functions or variables
				$result = $this->safe_eval_formula( $formula );
			} else {
				// Simple arithmetic
				eval( '$result = ' . $formula . ';' );
			}

			return is_numeric( $result ) ? floatval( $result ) : $original_price;
		} catch ( Exception $e ) {
			return $original_price;
		}
	}

	/**
	 * Safely evaluate formula with functions
	 *
	 * @param string $formula Formula to evaluate
	 * @return float
	 */
	private function safe_eval_formula( $formula ) {
		// Define allowed functions
		$allowed_functions = array(
			'min', 'max', 'round', 'floor', 'ceil', 'abs',
			'sqrt', 'pow', 'log', 'exp',
		);

		// Validate formula contains only allowed functions
		preg_match_all( '/([a-zA-Z_]+)\s*\(/', $formula, $matches );
		foreach ( $matches[1] as $function ) {
			if ( ! in_array( $function, $allowed_functions ) ) {
				throw new Exception( 'Unsafe function in formula: ' . $function );
			}
		}

		// Evaluate
		eval( '$result = ' . $formula . ';' );
		return $result;
	}

	/**
	 * Apply cascade pricing
	 *
	 * @param float  $original_price Original price
	 * @param array  $modification Modification configuration
	 * @param array  $context Evaluation context
	 * @return float
	 */
	private function apply_cascade_pricing( $original_price, $modification, $context ) {
		// Cascade pricing affects other addons, not the current one
		// This is handled in the main conditional logic controller
		// Here we just return the original price unless this addon is affected by cascade
		
		if ( isset( $context['cascade_modifications'] ) && isset( $context['current_addon'] ) ) {
			$addon_name = $context['current_addon']['name'];
			
			if ( isset( $context['cascade_modifications'][ $addon_name ] ) ) {
				$cascade_mod = $context['cascade_modifications'][ $addon_name ];
				return $this->execute( $original_price, array( 'modification' => $cascade_mod ), $context );
			}
		}

		return $original_price;
	}

	/**
	 * Sync price with another addon
	 *
	 * @param float  $original_price Original price
	 * @param array  $modification Modification configuration
	 * @param array  $context Evaluation context
	 * @return float
	 */
	private function sync_price( $original_price, $modification, $context ) {
		if ( ! isset( $modification['sync_with'] ) || ! isset( $context['addon_prices'] ) ) {
			return $original_price;
		}

		$sync_addon = $modification['sync_with'];
		$sync_type = isset( $modification['sync_type'] ) ? $modification['sync_type'] : 'match';

		if ( ! isset( $context['addon_prices'][ $sync_addon ] ) ) {
			return $original_price;
		}

		$sync_price = floatval( $context['addon_prices'][ $sync_addon ] );

		switch ( $sync_type ) {
			case 'match':
				return $sync_price;

			case 'percentage':
				$percentage = isset( $modification['sync_percentage'] ) ? floatval( $modification['sync_percentage'] ) : 100;
				return $sync_price * ( $percentage / 100 );

			case 'inverse':
				$total = isset( $modification['total_pool'] ) ? floatval( $modification['total_pool'] ) : 100;
				return max( 0, $total - $sync_price );

			case 'complement':
				// Price that complements to a round number
				$target = isset( $modification['complement_to'] ) ? floatval( $modification['complement_to'] ) : 100;
				return max( 0, $target - $sync_price );

			default:
				return $original_price;
		}
	}

	/**
	 * Scale price based on quantity or value
	 *
	 * @param float  $original_price Original price
	 * @param array  $modification Modification configuration
	 * @param array  $context Evaluation context
	 * @return float
	 */
	private function scale_price( $original_price, $modification, $context ) {
		$scale_base = isset( $modification['scale_base'] ) ? $modification['scale_base'] : 'quantity';
		$scale_type = isset( $modification['scale_type'] ) ? $modification['scale_type'] : 'linear';

		// Get the value to scale by
		$scale_value = 1;
		switch ( $scale_base ) {
			case 'quantity':
				$scale_value = isset( $context['quantity'] ) ? intval( $context['quantity'] ) : 1;
				break;

			case 'selection_count':
				$scale_value = isset( $context['selection_count'] ) ? intval( $context['selection_count'] ) : 1;
				break;

			case 'user_input':
				if ( isset( $modification['input_field'] ) && isset( $context['selections'][ $modification['input_field'] ] ) ) {
					$scale_value = floatval( $context['selections'][ $modification['input_field'] ] );
				}
				break;
		}

		// Apply scaling
		switch ( $scale_type ) {
			case 'linear':
				$factor = isset( $modification['scale_factor'] ) ? floatval( $modification['scale_factor'] ) : 1;
				return $original_price * $scale_value * $factor;

			case 'logarithmic':
				return $original_price * log( $scale_value + 1 );

			case 'exponential':
				$exponent = isset( $modification['exponent'] ) ? floatval( $modification['exponent'] ) : 2;
				return $original_price * pow( $scale_value, $exponent );

			case 'stepped':
				$steps = isset( $modification['steps'] ) ? $modification['steps'] : array();
				foreach ( $steps as $step ) {
					if ( $scale_value >= $step['min'] && ( ! isset( $step['max'] ) || $scale_value <= $step['max'] ) ) {
						return $original_price * floatval( $step['multiplier'] );
					}
				}
				return $original_price;

			default:
				return $original_price;
		}
	}

	/**
	 * Apply tiered pricing
	 *
	 * @param float  $original_price Original price
	 * @param array  $modification Modification configuration
	 * @param array  $context Evaluation context
	 * @return float
	 */
	private function apply_tiered_pricing( $original_price, $modification, $context ) {
		if ( ! isset( $modification['tiers'] ) || ! is_array( $modification['tiers'] ) ) {
			return $original_price;
		}

		$tier_base = isset( $modification['tier_base'] ) ? $modification['tier_base'] : 'quantity';
		$tier_value = 1;

		// Get the value to check against tiers
		switch ( $tier_base ) {
			case 'quantity':
				$tier_value = isset( $context['quantity'] ) ? intval( $context['quantity'] ) : 1;
				break;

			case 'cart_total':
				$tier_value = isset( $context['cart'] ) ? floatval( $context['cart']->get_cart_contents_total() ) : 0;
				break;

			case 'user_spent':
				$tier_value = isset( $context['user'] ) ? wc_get_customer_total_spent( $context['user']->ID ) : 0;
				break;
		}

		// Find applicable tier
		foreach ( $modification['tiers'] as $tier ) {
			$min = isset( $tier['min'] ) ? floatval( $tier['min'] ) : 0;
			$max = isset( $tier['max'] ) ? floatval( $tier['max'] ) : PHP_FLOAT_MAX;

			if ( $tier_value >= $min && $tier_value <= $max ) {
				if ( isset( $tier['price'] ) ) {
					return floatval( $tier['price'] );
				} elseif ( isset( $tier['modifier'] ) ) {
					return $this->execute( $original_price, array( 'modification' => $tier['modifier'] ), $context );
				}
			}
		}

		return $original_price;
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'target' => array(
				'type'    => 'select',
				'label'   => __( 'Apply To', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_target_options(),
			),
			'target_addon' => array(
				'type'        => 'select',
				'label'       => __( 'Target Addon', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Populated dynamically
				'dependency'  => array(
					'field'  => 'target',
					'values' => array( 'other' ),
					'action' => 'show',
				),
			),
			'modification_method' => array(
				'type'    => 'select',
				'label'   => __( 'Modification Method', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'add'                 => __( 'Add Amount', 'woocommerce-product-addons-extra-digital' ),
					'subtract'            => __( 'Subtract Amount', 'woocommerce-product-addons-extra-digital' ),
					'multiply'            => __( 'Multiply By', 'woocommerce-product-addons-extra-digital' ),
					'divide'              => __( 'Divide By', 'woocommerce-product-addons-extra-digital' ),
					'set'                 => __( 'Set To', 'woocommerce-product-addons-extra-digital' ),
					'percentage_add'      => __( 'Add Percentage', 'woocommerce-product-addons-extra-digital' ),
					'percentage_subtract' => __( 'Subtract Percentage', 'woocommerce-product-addons-extra-digital' ),
					'formula'             => __( 'Custom Formula', 'woocommerce-product-addons-extra-digital' ),
					'cascade'             => __( 'Cascade Pricing', 'woocommerce-product-addons-extra-digital' ),
					'sync'                => __( 'Sync With Other', 'woocommerce-product-addons-extra-digital' ),
					'scale'               => __( 'Scale Based On', 'woocommerce-product-addons-extra-digital' ),
					'tiered'              => __( 'Tiered Pricing', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'value' => array(
				'type'        => 'number',
				'label'       => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
				'step'        => '0.01',
				'dependency'  => array(
					'field'   => 'modification_method',
					'values'  => array( 'formula', 'cascade', 'sync', 'scale', 'tiered' ),
					'action'  => 'hide',
				),
			),
			'formula' => array(
				'type'        => 'textarea',
				'label'       => __( 'Price Formula', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'e.g., base_price * 1.2 + 10', 'woocommerce-product-addons-extra-digital' ),
				'dependency'  => array(
					'field'  => 'modification_method',
					'values' => array( 'formula' ),
					'action' => 'show',
				),
			),
			'apply_compound' => array(
				'type'    => 'checkbox',
				'label'   => __( 'Apply on already modified prices', 'woocommerce-product-addons-extra-digital' ),
				'default' => false,
			),
			'round_price' => array(
				'type'    => 'checkbox',
				'label'   => __( 'Round price', 'woocommerce-product-addons-extra-digital' ),
				'default' => true,
			),
			'min_price' => array(
				'type'  => 'number',
				'label' => __( 'Minimum Price', 'woocommerce-product-addons-extra-digital' ),
				'step'  => '0.01',
			),
			'max_price' => array(
				'type'  => 'number',
				'label' => __( 'Maximum Price', 'woocommerce-product-addons-extra-digital' ),
				'step'  => '0.01',
			),
			'message' => array(
				'type'        => 'text',
				'label'       => __( 'Display Message', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'e.g., Premium pricing applied', 'woocommerce-product-addons-extra-digital' ),
			),
		);
	}

	/**
	 * Get display label for the action
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Modify Price', 'woocommerce-product-addons-extra-digital' );
	}

	/**
	 * Validate action configuration
	 *
	 * @param array $action Action configuration
	 * @return bool
	 */
	protected function validate_specific( $action ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;

		// Must have modification configuration
		if ( ! isset( $config['modification'] ) ) {
			return false;
		}

		$modification = $config['modification'];

		// Must have a method
		if ( ! isset( $modification['method'] ) ) {
			return false;
		}

		// Formula method must have formula
		if ( $modification['method'] === 'formula' && ! isset( $modification['formula'] ) ) {
			return false;
		}

		// Sync method must have sync_with
		if ( $modification['method'] === 'sync' && ! isset( $modification['sync_with'] ) ) {
			return false;
		}

		return true;
	}
}