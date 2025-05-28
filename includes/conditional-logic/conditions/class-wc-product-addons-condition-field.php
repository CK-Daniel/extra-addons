<?php
/**
 * Field Condition Class
 *
 * Handles conditions based on other addon field selections.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field Condition Class
 *
 * @class    WC_Product_Addons_Condition_Field
 * @extends  WC_Product_Addons_Condition
 * @version  4.0.0
 */
class WC_Product_Addons_Condition_Field extends WC_Product_Addons_Condition {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'field';
		$this->supported_operators = array(
			'equals',
			'not_equals',
			'contains',
			'not_contains',
			'greater_than',
			'less_than',
			'greater_than_equals',
			'less_than_equals',
			'is_empty',
			'is_not_empty',
			'in',
			'not_in',
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

		// Special handling for is_empty/is_not_empty
		if ( in_array( $operator, array( 'is_empty', 'is_not_empty' ) ) ) {
			return $this->compare( $value, null, $operator );
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
		if ( ! isset( $condition['target'] ) || ! isset( $context['selections'] ) ) {
			return null;
		}

		$target = $condition['target'];
		
		// Extract addon name and property
		if ( is_array( $target ) ) {
			$addon_name = isset( $target['addon'] ) ? $target['addon'] : '';
			$property = isset( $target['property'] ) ? $target['property'] : 'value';
		} else {
			// Simple string format: "addon_name" or "addon_name.property"
			$parts = explode( '.', $target );
			$addon_name = $parts[0];
			$property = isset( $parts[1] ) ? $parts[1] : 'value';
		}

		// Get the selection for this addon
		if ( ! isset( $context['selections'][ $addon_name ] ) ) {
			return null;
		}

		$selection = $context['selections'][ $addon_name ];

		// Handle different selection formats
		if ( is_array( $selection ) ) {
			// Multiple selections or complex data
			if ( isset( $selection[0] ) && is_array( $selection[0] ) ) {
				// Array of selections
				return $this->extract_property_from_selections( $selection, $property );
			} else {
				// Single selection with properties
				return $this->extract_property_from_selection( $selection, $property );
			}
		} else {
			// Simple value
			return $property === 'value' ? $selection : null;
		}
	}

	/**
	 * Extract property from single selection
	 *
	 * @param array  $selection Selection data
	 * @param string $property  Property to extract
	 * @return mixed
	 */
	private function extract_property_from_selection( $selection, $property ) {
		switch ( $property ) {
			case 'value':
				return isset( $selection['value'] ) ? $selection['value'] : null;
			
			case 'label':
				return isset( $selection['label'] ) ? $selection['label'] : null;
			
			case 'price':
				return isset( $selection['price'] ) ? floatval( $selection['price'] ) : 0;
			
			case 'quantity':
				return isset( $selection['quantity'] ) ? intval( $selection['quantity'] ) : 1;
			
			case 'is_selected':
				return ! empty( $selection['value'] );
			
			default:
				return isset( $selection[ $property ] ) ? $selection[ $property ] : null;
		}
	}

	/**
	 * Extract property from multiple selections
	 *
	 * @param array  $selections Array of selections
	 * @param string $property   Property to extract
	 * @return mixed
	 */
	private function extract_property_from_selections( $selections, $property ) {
		$values = array();

		foreach ( $selections as $selection ) {
			$value = $this->extract_property_from_selection( $selection, $property );
			if ( $value !== null ) {
				$values[] = $value;
			}
		}

		// For price property, return sum
		if ( $property === 'price' ) {
			return array_sum( $values );
		}

		// For single value, return it directly
		if ( count( $values ) === 1 ) {
			return $values[0];
		}

		return $values;
	}

	/**
	 * Validate condition configuration
	 *
	 * @param array $condition Condition configuration
	 * @return bool
	 */
	protected function validate_specific( $condition ) {
		// Must have a target field
		if ( ! isset( $condition['target'] ) ) {
			return false;
		}

		// For comparison operators, must have a value
		if ( ! in_array( $condition['operator'], array( 'is_empty', 'is_not_empty' ) ) ) {
			if ( ! isset( $condition['value'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'target' => array(
				'type'        => 'select',
				'label'       => __( 'Field', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Will be populated dynamically
				'class'       => 'wc-pao-field-select',
				'data-source' => 'product-addons',
			),
			'property' => array(
				'type'    => 'select',
				'label'   => __( 'Property', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'value'       => __( 'Selected Value', 'woocommerce-product-addons-extra-digital' ),
					'label'       => __( 'Selected Label', 'woocommerce-product-addons-extra-digital' ),
					'price'       => __( 'Price', 'woocommerce-product-addons-extra-digital' ),
					'quantity'    => __( 'Quantity', 'woocommerce-product-addons-extra-digital' ),
					'is_selected' => __( 'Is Selected', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'operator' => array(
				'type'    => 'select',
				'label'   => __( 'Operator', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_operator_options(),
			),
			'value' => array(
				'type'        => 'text',
				'label'       => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Enter value to compare', 'woocommerce-product-addons-extra-digital' ),
				'dependency'  => array(
					'field'    => 'operator',
					'values'   => array( 'is_empty', 'is_not_empty' ),
					'action'   => 'hide',
				),
			),
		);
	}

	/**
	 * Get display label for the condition
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Field Value', 'woocommerce-product-addons-extra-digital' );
	}
}