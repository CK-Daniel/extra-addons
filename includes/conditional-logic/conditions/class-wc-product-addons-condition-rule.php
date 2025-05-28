<?php
/**
 * Rule Condition Class
 *
 * Handles conditions based on the state of other conditional logic rules.
 * This enables cascading rule dependencies.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule Condition Class
 *
 * @class    WC_Product_Addons_Condition_Rule
 * @extends  WC_Product_Addons_Condition
 * @version  4.0.0
 */
class WC_Product_Addons_Condition_Rule extends WC_Product_Addons_Condition {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'rule';
		$this->supported_operators = array(
			'equals',
			'not_equals',
			'is_visible',
			'is_hidden',
			'is_required',
			'is_not_required',
			'has_price_modified',
			'has_options_modified',
		);
	}

	/**
	 * Evaluate the condition
	 *
	 * @param array $condition Condition configuration
	 * @param array $context   Evaluation context
	 * @return bool
	 */
	public function evaluate( $condition, $context ) {
		$value = $this->get_value( $condition, $context );
		$compare_value = isset( $condition['value'] ) ? $condition['value'] : '';
		$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'equals';

		// Special handling for rule state operators
		if ( in_array( $operator, array( 'is_visible', 'is_hidden', 'is_required', 'is_not_required' ) ) ) {
			return $this->evaluate_rule_state( $condition, $context, $operator );
		}

		if ( in_array( $operator, array( 'has_price_modified', 'has_options_modified' ) ) ) {
			return $this->evaluate_rule_modifications( $condition, $context, $operator );
		}

		return $this->compare( $value, $compare_value, $operator );
	}

	/**
	 * Get the value to compare
	 *
	 * @param array $condition Condition configuration
	 * @param array $context   Evaluation context
	 * @return mixed
	 */
	protected function get_value( $condition, $context ) {
		if ( ! isset( $context['rule_results'] ) || ! isset( $condition['target_addon'] ) ) {
			return null;
		}

		$target_addon = $condition['target_addon'];
		$property = isset( $condition['property'] ) ? $condition['property'] : 'visible';

		if ( ! isset( $context['rule_results'][ $target_addon ] ) ) {
			return null;
		}

		$rule_results = $context['rule_results'][ $target_addon ];

		switch ( $property ) {
			case 'visible':
				return isset( $rule_results['visible'] ) ? $rule_results['visible'] : true;

			case 'required':
				return isset( $rule_results['required'] ) ? $rule_results['required'] : false;

			case 'price_modifiers_count':
				return isset( $rule_results['price_modifiers'] ) ? count( $rule_results['price_modifiers'] ) : 0;

			case 'option_modifiers_count':
				return isset( $rule_results['option_modifiers'] ) ? count( $rule_results['option_modifiers'] ) : 0;

			case 'has_modifications':
				return ! empty( $rule_results['modifications'] );

			case 'modification_count':
				return isset( $rule_results['modifications'] ) ? count( $rule_results['modifications'] ) : 0;

			default:
				if ( isset( $rule_results[ $property ] ) ) {
					return $rule_results[ $property ];
				}
				return null;
		}
	}

	/**
	 * Evaluate rule state conditions
	 *
	 * @param array  $condition Condition configuration
	 * @param array  $context   Evaluation context
	 * @param string $operator  Operator
	 * @return bool
	 */
	private function evaluate_rule_state( $condition, $context, $operator ) {
		if ( ! isset( $context['rule_results'] ) || ! isset( $condition['target_addon'] ) ) {
			return false;
		}

		$target_addon = $condition['target_addon'];
		$rule_results = $context['rule_results'][ $target_addon ];

		switch ( $operator ) {
			case 'is_visible':
				return isset( $rule_results['visible'] ) ? $rule_results['visible'] : true;

			case 'is_hidden':
				return isset( $rule_results['visible'] ) ? ! $rule_results['visible'] : false;

			case 'is_required':
				return isset( $rule_results['required'] ) ? $rule_results['required'] : false;

			case 'is_not_required':
				return isset( $rule_results['required'] ) ? ! $rule_results['required'] : true;

			default:
				return false;
		}
	}

	/**
	 * Evaluate rule modification conditions
	 *
	 * @param array  $condition Condition configuration
	 * @param array  $context   Evaluation context
	 * @param string $operator  Operator
	 * @return bool
	 */
	private function evaluate_rule_modifications( $condition, $context, $operator ) {
		if ( ! isset( $context['rule_results'] ) || ! isset( $condition['target_addon'] ) ) {
			return false;
		}

		$target_addon = $condition['target_addon'];
		$rule_results = $context['rule_results'][ $target_addon ];

		switch ( $operator ) {
			case 'has_price_modified':
				return ! empty( $rule_results['price_modifiers'] );

			case 'has_options_modified':
				return ! empty( $rule_results['option_modifiers'] );

			default:
				return false;
		}
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'target_addon' => array(
				'type'        => 'select',
				'label'       => __( 'Target Addon', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Will be populated dynamically with addon names
				'description' => __( 'Select the addon whose rule state you want to check', 'woocommerce-product-addons-extra-digital' ),
			),
			'property' => array(
				'type'    => 'select',
				'label'   => __( 'Rule Property', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'visible'                => __( 'Visibility State', 'woocommerce-product-addons-extra-digital' ),
					'required'               => __( 'Required State', 'woocommerce-product-addons-extra-digital' ),
					'price_modifiers_count'  => __( 'Number of Price Modifications', 'woocommerce-product-addons-extra-digital' ),
					'option_modifiers_count' => __( 'Number of Option Modifications', 'woocommerce-product-addons-extra-digital' ),
					'has_modifications'      => __( 'Has Any Modifications', 'woocommerce-product-addons-extra-digital' ),
					'modification_count'     => __( 'Total Modification Count', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'operator' => array(
				'type'    => 'select',
				'label'   => __( 'Operator', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_operator_options(),
			),
			'value' => array(
				'type'        => 'dynamic',
				'label'       => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Enter value to compare', 'woocommerce-product-addons-extra-digital' ),
				'config'      => array(
					'visible' => array(
						'type'    => 'select',
						'options' => array(
							'true'  => __( 'Visible', 'woocommerce-product-addons-extra-digital' ),
							'false' => __( 'Hidden', 'woocommerce-product-addons-extra-digital' ),
						),
					),
					'required' => array(
						'type'    => 'select',
						'options' => array(
							'true'  => __( 'Required', 'woocommerce-product-addons-extra-digital' ),
							'false' => __( 'Not Required', 'woocommerce-product-addons-extra-digital' ),
						),
					),
					'has_modifications' => array(
						'type'    => 'select',
						'options' => array(
							'true'  => __( 'Has Modifications', 'woocommerce-product-addons-extra-digital' ),
							'false' => __( 'No Modifications', 'woocommerce-product-addons-extra-digital' ),
						),
					),
					'default' => array(
						'type' => 'number',
					),
				),
				'dependency'  => array(
					'field'   => 'operator',
					'values'  => array( 'is_visible', 'is_hidden', 'is_required', 'is_not_required', 'has_price_modified', 'has_options_modified' ),
					'action'  => 'hide',
				),
			),
			'rule_dependency_note' => array(
				'type'        => 'note',
				'content'     => __( 'Rule dependencies create cascading logic where one rule\'s actions can trigger other rules. Use priority settings to control execution order.', 'woocommerce-product-addons-extra-digital' ),
				'class'       => 'conditional-logic-note',
			),
		);
	}

	/**
	 * Get display label for the condition
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Other Rule State', 'woocommerce-product-addons-extra-digital' );
	}

	/**
	 * Validate condition configuration
	 *
	 * @param array $condition Condition configuration
	 * @return bool
	 */
	protected function validate_specific( $condition ) {
		// Must have target addon
		if ( ! isset( $condition['target_addon'] ) ) {
			return false;
		}

		// Must have property
		if ( ! isset( $condition['property'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check for circular dependencies
	 *
	 * @param array $condition     Current condition
	 * @param array $all_rules     All conditional rules
	 * @param array $checked_rules Already checked rules (to prevent infinite recursion)
	 * @return bool True if circular dependency detected
	 */
	public function has_circular_dependency( $condition, $all_rules, $checked_rules = array() ) {
		if ( ! isset( $condition['target_addon'] ) ) {
			return false;
		}

		$target_addon = $condition['target_addon'];
		
		// If we've already checked this rule, we have a circular dependency
		if ( in_array( $target_addon, $checked_rules ) ) {
			return true;
		}

		$checked_rules[] = $target_addon;

		// Check if the target addon has rules that depend on other addons
		if ( isset( $all_rules[ $target_addon ] ) ) {
			foreach ( $all_rules[ $target_addon ] as $rule ) {
				if ( isset( $rule['conditions'] ) ) {
					foreach ( $rule['conditions'] as $rule_condition ) {
						if ( isset( $rule_condition['type'] ) && $rule_condition['type'] === 'rule' ) {
							if ( $this->has_circular_dependency( $rule_condition, $all_rules, $checked_rules ) ) {
								return true;
							}
						}
					}
				}
			}
		}

		return false;
	}
}