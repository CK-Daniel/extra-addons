<?php
/**
 * WooCommerce Product Add-ons Rule Builder
 *
 * Provides a consistent interface for building conditional logic rules
 * with precise addon and option targeting.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule Builder Class
 *
 * @class   WC_Product_Addons_Rule_Builder
 * @version 4.1.0
 */
class WC_Product_Addons_Rule_Builder {

	/**
	 * Build a condition for addon selection
	 *
	 * @param string $addon_identifier Addon identifier (e.g., 'test_global_163')
	 * @param string $option_key       Option key (e.g., 'tester123')
	 * @param string $state           State ('selected' or 'not_selected')
	 * @return array Condition configuration
	 */
	public static function addon_selected_condition( $addon_identifier, $option_key, $state = 'selected' ) {
		return array(
			'type'   => 'addon_selected',
			'config' => array(
				'condition_addon'  => $addon_identifier,
				'condition_option' => $option_key, // Use the key, not the value
				'condition_state'  => $state,
			),
		);
	}

	/**
	 * Build an action to hide/show addon
	 *
	 * @param string $type             Action type ('hide_addon' or 'show_addon')
	 * @param string $addon_identifier Target addon identifier
	 * @return array Action configuration
	 */
	public static function addon_visibility_action( $type, $addon_identifier ) {
		return array(
			'type'   => $type,
			'config' => array(
				'action_addon' => $addon_identifier,
			),
		);
	}

	/**
	 * Build an action to hide/show option
	 *
	 * @param string $type             Action type ('hide_option' or 'show_option')
	 * @param string $addon_identifier Target addon identifier
	 * @param string $option_key       Option key to hide/show
	 * @return array Action configuration
	 */
	public static function option_visibility_action( $type, $addon_identifier, $option_key ) {
		return array(
			'type'   => $type,
			'config' => array(
				'action_addon'  => $addon_identifier,
				'action_option' => $option_key, // Use the key, not the value
			),
		);
	}

	/**
	 * Build a complete rule
	 *
	 * @param string $name       Rule name
	 * @param array  $conditions Array of conditions
	 * @param array  $actions    Array of actions
	 * @param array  $options    Additional options
	 * @return array Rule configuration
	 */
	public static function build_rule( $name, $conditions, $actions, $options = array() ) {
		$defaults = array(
			'rule_type' => 'custom',
			'priority'  => 10,
			'enabled'   => true,
		);

		$options = wp_parse_args( $options, $defaults );

		return array(
			'rule_name'  => $name,
			'conditions' => $conditions,
			'actions'    => $actions,
			'rule_type'  => $options['rule_type'],
			'priority'   => $options['priority'],
			'enabled'    => $options['enabled'],
		);
	}

	/**
	 * Convert addon data to rule-compatible format
	 *
	 * @param array  $addon      Addon data
	 * @param int    $product_id Product ID
	 * @param string $scope      Scope (product/global/category)
	 * @return array Addon rule data
	 */
	public static function prepare_addon_for_rules( $addon, $product_id = 0, $scope = 'product' ) {
		$identifier = WC_Product_Addons_Addon_Identifier::generate_identifier( $addon, $product_id, $scope );
		
		$rule_data = array(
			'identifier' => $identifier,
			'name'       => isset( $addon['name'] ) ? $addon['name'] : '',
			'type'       => isset( $addon['type'] ) ? $addon['type'] : '',
			'scope'      => $scope,
			'options'    => array(),
		);

		// Process options
		if ( isset( $addon['options'] ) && is_array( $addon['options'] ) ) {
			foreach ( $addon['options'] as $index => $option ) {
				$label = isset( $option['label'] ) ? $option['label'] : '';
				$key   = sanitize_title( $label );
				
				$rule_data['options'][] = array(
					'key'   => $key,
					'label' => $label,
					'value' => $key . '-' . ( $index + 1 ), // Match the frontend pattern
					'index' => $index + 1,
				);
			}
		}

		return $rule_data;
	}

	/**
	 * Validate rule structure
	 *
	 * @param array $rule Rule configuration
	 * @return array Validation result with 'valid' and 'errors' keys
	 */
	public static function validate_rule( $rule ) {
		$errors = array();
		
		// Check required fields
		if ( empty( $rule['rule_name'] ) ) {
			$errors[] = __( 'Rule name is required', 'woocommerce-product-addons-extra-digital' );
		}

		if ( empty( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
			$errors[] = __( 'At least one condition is required', 'woocommerce-product-addons-extra-digital' );
		}

		if ( empty( $rule['actions'] ) || ! is_array( $rule['actions'] ) ) {
			$errors[] = __( 'At least one action is required', 'woocommerce-product-addons-extra-digital' );
		}

		// Validate conditions
		foreach ( $rule['conditions'] as $condition ) {
			if ( empty( $condition['type'] ) ) {
				$errors[] = __( 'Condition type is required', 'woocommerce-product-addons-extra-digital' );
			}

			if ( $condition['type'] === 'addon_selected' ) {
				if ( empty( $condition['config']['condition_addon'] ) ) {
					$errors[] = __( 'Condition addon is required for addon_selected type', 'woocommerce-product-addons-extra-digital' );
				}
				if ( empty( $condition['config']['condition_option'] ) ) {
					$errors[] = __( 'Condition option is required for addon_selected type', 'woocommerce-product-addons-extra-digital' );
				}
			}
		}

		// Validate actions
		foreach ( $rule['actions'] as $action ) {
			if ( empty( $action['type'] ) ) {
				$errors[] = __( 'Action type is required', 'woocommerce-product-addons-extra-digital' );
			}

			if ( in_array( $action['type'], array( 'hide_addon', 'show_addon', 'hide_option', 'show_option' ) ) ) {
				if ( empty( $action['config']['action_addon'] ) ) {
					$errors[] = __( 'Action addon is required for visibility actions', 'woocommerce-product-addons-extra-digital' );
				}
			}

			if ( in_array( $action['type'], array( 'hide_option', 'show_option' ) ) ) {
				if ( empty( $action['config']['action_option'] ) ) {
					$errors[] = __( 'Action option is required for option visibility actions', 'woocommerce-product-addons-extra-digital' );
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Get example rule structure
	 *
	 * @return array Example rule
	 */
	public static function get_example_rule() {
		return array(
			'rule_name'  => 'Hide Option When Test Selected',
			'conditions' => array(
				self::addon_selected_condition( 'test_global_163', 'test', 'selected' ),
			),
			'actions' => array(
				self::option_visibility_action( 'hide_option', 'example1_global_173', 'tester123' ),
			),
			'rule_type' => 'global',
			'priority'  => 10,
			'enabled'   => true,
		);
	}
}