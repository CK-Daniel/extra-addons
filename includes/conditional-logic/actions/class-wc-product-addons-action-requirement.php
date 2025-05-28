<?php
/**
 * Requirement Action Class
 *
 * Handles requirement and validation modifications for addons.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requirement Action Class
 *
 * @class    WC_Product_Addons_Action_Requirement
 * @extends  WC_Product_Addons_Action
 * @version  4.0.0
 */
class WC_Product_Addons_Action_Requirement extends WC_Product_Addons_Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'requirement';
	}

	/**
	 * Execute the requirement action
	 *
	 * @param mixed $target  Target addon
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 * @return mixed Modified target
	 */
	public function execute( $target, $action, $context ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;

		// Modify required status
		if ( isset( $config['required'] ) ) {
			$target['required'] = $config['required'] === 'true' || $config['required'] === true || $config['required'] === 1;
		}

		// Modify validation rules
		if ( isset( $config['validation'] ) ) {
			$target = $this->apply_validation_rules( $target, $config['validation'], $context );
		}

		// Modify restrictions
		if ( isset( $config['restrictions'] ) ) {
			$target = $this->apply_restrictions( $target, $config['restrictions'], $context );
		}

		// Modify error messages
		if ( isset( $config['error_messages'] ) ) {
			if ( ! isset( $target['error_messages'] ) ) {
				$target['error_messages'] = array();
			}
			$target['error_messages'] = array_merge( $target['error_messages'], $config['error_messages'] );
		}

		// Log the action
		$this->log_execution( $action, null, $target, $context );

		return $target;
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

		// Set required status
		if ( isset( $config['required'] ) ) {
			$results['required'] = $config['required'] === 'true' || $config['required'] === true || $config['required'] === 1;
		}

		// Store validation rules
		if ( isset( $config['validation'] ) ) {
			$results['validation'] = $config['validation'];
		}

		// Store restrictions
		if ( isset( $config['restrictions'] ) ) {
			$results['restrictions'] = $config['restrictions'];
		}

		// Store error messages
		if ( isset( $config['error_messages'] ) ) {
			$results['error_messages'] = $config['error_messages'];
		}

		// Add requirement message
		$message = $this->get_message_config( $action );
		if ( $message ) {
			$results['requirement_message'] = $message;
		}
	}

	/**
	 * Apply validation rules to addon
	 *
	 * @param array $addon      Addon data
	 * @param array $validation Validation rules
	 * @param array $context    Evaluation context
	 * @return array Modified addon
	 */
	private function apply_validation_rules( $addon, $validation, $context ) {
		// Set restriction type
		if ( isset( $validation['type'] ) ) {
			$addon['restrictions'] = 1;
			$addon['restrictions_type'] = $validation['type'];
		}

		// Minimum length/value
		if ( isset( $validation['min'] ) ) {
			$addon['min'] = $this->parse_dynamic_value( $validation['min'], $context );
		}

		// Maximum length/value
		if ( isset( $validation['max'] ) ) {
			$addon['max'] = $this->parse_dynamic_value( $validation['max'], $context );
		}

		// Pattern validation
		if ( isset( $validation['pattern'] ) ) {
			$addon['pattern'] = $validation['pattern'];
		}

		// Custom validation function
		if ( isset( $validation['custom'] ) ) {
			$addon['custom_validation'] = $validation['custom'];
		}

		// Allowed values
		if ( isset( $validation['allowed_values'] ) ) {
			$addon['allowed_values'] = is_array( $validation['allowed_values'] ) 
				? $validation['allowed_values'] 
				: explode( ',', $validation['allowed_values'] );
		}

		// Forbidden values
		if ( isset( $validation['forbidden_values'] ) ) {
			$addon['forbidden_values'] = is_array( $validation['forbidden_values'] ) 
				? $validation['forbidden_values'] 
				: explode( ',', $validation['forbidden_values'] );
		}

		// File type restrictions (for file upload addons)
		if ( isset( $validation['allowed_file_types'] ) && $addon['type'] === 'file_upload' ) {
			$addon['allowed_file_types'] = is_array( $validation['allowed_file_types'] ) 
				? $validation['allowed_file_types'] 
				: explode( ',', $validation['allowed_file_types'] );
		}

		// File size restrictions
		if ( isset( $validation['max_file_size'] ) && $addon['type'] === 'file_upload' ) {
			$addon['max_file_size'] = $validation['max_file_size'];
		}

		return $addon;
	}

	/**
	 * Apply restrictions to addon
	 *
	 * @param array $addon        Addon data
	 * @param array $restrictions Restriction rules
	 * @param array $context      Evaluation context
	 * @return array Modified addon
	 */
	private function apply_restrictions( $addon, $restrictions, $context ) {
		// Quantity restrictions
		if ( isset( $restrictions['min_quantity'] ) ) {
			$addon['min_quantity'] = $this->parse_dynamic_value( $restrictions['min_quantity'], $context );
		}

		if ( isset( $restrictions['max_quantity'] ) ) {
			$addon['max_quantity'] = $this->parse_dynamic_value( $restrictions['max_quantity'], $context );
		}

		// Selection restrictions (for multiple choice)
		if ( isset( $restrictions['min_selections'] ) ) {
			$addon['min_selections'] = intval( $restrictions['min_selections'] );
		}

		if ( isset( $restrictions['max_selections'] ) ) {
			$addon['max_selections'] = intval( $restrictions['max_selections'] );
		}

		// Unique value requirement
		if ( isset( $restrictions['unique_in_cart'] ) ) {
			$addon['unique_in_cart'] = (bool) $restrictions['unique_in_cart'];
		}

		// Dependencies on other fields
		if ( isset( $restrictions['depends_on'] ) ) {
			$addon['depends_on'] = $restrictions['depends_on'];
		}

		// Mutually exclusive with other fields
		if ( isset( $restrictions['exclusive_with'] ) ) {
			$addon['exclusive_with'] = is_array( $restrictions['exclusive_with'] ) 
				? $restrictions['exclusive_with'] 
				: array( $restrictions['exclusive_with'] );
		}

		return $addon;
	}

	/**
	 * Parse dynamic value that might contain variables or expressions
	 *
	 * @param mixed $value   Value to parse
	 * @param array $context Evaluation context
	 * @return mixed Parsed value
	 */
	private function parse_dynamic_value( $value, $context ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		// Check if it's a dynamic expression
		if ( strpos( $value, '{' ) !== false && strpos( $value, '}' ) !== false ) {
			// Parse variables
			$value = $this->parse_dynamic_text( $value, $context );
		}

		// Check if it's a calculation
		if ( preg_match( '/^[\d\+\-\*\/\(\)\s]+$/', $value ) ) {
			eval( '$result = ' . $value . ';' );
			if ( is_numeric( $result ) ) {
				return $result;
			}
		}

		return $value;
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'required' => array(
				'type'    => 'select',
				'label'   => __( 'Required Status', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'true'        => __( 'Required', 'woocommerce-product-addons-extra-digital' ),
					'false'       => __( 'Optional', 'woocommerce-product-addons-extra-digital' ),
					'conditional' => __( 'Conditionally Required', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'validation_type' => array(
				'type'    => 'select',
				'label'   => __( 'Validation Type', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'any_text'       => __( 'Any Text', 'woocommerce-product-addons-extra-digital' ),
					'only_letters'   => __( 'Only Letters', 'woocommerce-product-addons-extra-digital' ),
					'only_numbers'   => __( 'Only Numbers', 'woocommerce-product-addons-extra-digital' ),
					'only_letters_numbers' => __( 'Only Letters and Numbers', 'woocommerce-product-addons-extra-digital' ),
					'email'          => __( 'Email Address', 'woocommerce-product-addons-extra-digital' ),
					'phone'          => __( 'Phone Number', 'woocommerce-product-addons-extra-digital' ),
					'url'            => __( 'URL', 'woocommerce-product-addons-extra-digital' ),
					'custom_pattern' => __( 'Custom Pattern', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'pattern' => array(
				'type'        => 'text',
				'label'       => __( 'Validation Pattern (Regex)', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'e.g., ^[A-Z]{3}[0-9]{4}$', 'woocommerce-product-addons-extra-digital' ),
				'dependency'  => array(
					'field'  => 'validation_type',
					'values' => array( 'custom_pattern' ),
					'action' => 'show',
				),
			),
			'min_value' => array(
				'type'        => 'text',
				'label'       => __( 'Minimum Value/Length', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'e.g., 5 or {other_field_value}', 'woocommerce-product-addons-extra-digital' ),
			),
			'max_value' => array(
				'type'        => 'text',
				'label'       => __( 'Maximum Value/Length', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'e.g., 100 or {other_field_value} * 2', 'woocommerce-product-addons-extra-digital' ),
			),
			'min_selections' => array(
				'type'  => 'number',
				'label' => __( 'Minimum Selections', 'woocommerce-product-addons-extra-digital' ),
				'min'   => 0,
			),
			'max_selections' => array(
				'type'  => 'number',
				'label' => __( 'Maximum Selections', 'woocommerce-product-addons-extra-digital' ),
				'min'   => 1,
			),
			'allowed_values' => array(
				'type'        => 'textarea',
				'label'       => __( 'Allowed Values', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Enter allowed values, one per line', 'woocommerce-product-addons-extra-digital' ),
			),
			'forbidden_values' => array(
				'type'        => 'textarea',
				'label'       => __( 'Forbidden Values', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Enter forbidden values, one per line', 'woocommerce-product-addons-extra-digital' ),
			),
			'unique_in_cart' => array(
				'type'    => 'checkbox',
				'label'   => __( 'Value must be unique in cart', 'woocommerce-product-addons-extra-digital' ),
				'default' => false,
			),
			'error_message_required' => array(
				'type'        => 'text',
				'label'       => __( 'Required Error Message', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'This field is required', 'woocommerce-product-addons-extra-digital' ),
			),
			'error_message_validation' => array(
				'type'        => 'text',
				'label'       => __( 'Validation Error Message', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Please enter a valid value', 'woocommerce-product-addons-extra-digital' ),
			),
			'error_message_min' => array(
				'type'        => 'text',
				'label'       => __( 'Minimum Error Message', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Value must be at least {min}', 'woocommerce-product-addons-extra-digital' ),
			),
			'error_message_max' => array(
				'type'        => 'text',
				'label'       => __( 'Maximum Error Message', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Value must not exceed {max}', 'woocommerce-product-addons-extra-digital' ),
			),
		);
	}

	/**
	 * Get display label for the action
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Change Requirements', 'woocommerce-product-addons-extra-digital' );
	}

	/**
	 * Validate action configuration
	 *
	 * @param array $action Action configuration
	 * @return bool
	 */
	protected function validate_specific( $action ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;

		// At least one modification should be present
		if ( ! isset( $config['required'] ) && 
			 ! isset( $config['validation'] ) && 
			 ! isset( $config['restrictions'] ) && 
			 ! isset( $config['error_messages'] ) ) {
			return false;
		}

		return true;
	}
}