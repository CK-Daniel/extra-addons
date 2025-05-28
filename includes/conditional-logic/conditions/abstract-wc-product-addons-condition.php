<?php
/**
 * Abstract Condition Class
 *
 * Base class for all condition types in the conditional logic system.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Condition Class
 *
 * @abstract
 * @class    WC_Product_Addons_Condition
 * @version  4.0.0
 */
abstract class WC_Product_Addons_Condition {

	/**
	 * Condition type identifier
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * Supported operators for this condition type
	 *
	 * @var array
	 */
	protected $supported_operators = array(
		'equals',
		'not_equals',
	);

	/**
	 * Get condition type
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get supported operators
	 *
	 * @return array
	 */
	public function get_supported_operators() {
		return $this->supported_operators;
	}

	/**
	 * Evaluate the condition
	 *
	 * @param array $condition Condition configuration
	 * @param array $context   Evaluation context
	 * @return bool
	 */
	abstract public function evaluate( $condition, $context );

	/**
	 * Get the value to compare
	 *
	 * @param array $condition Condition configuration
	 * @param array $context   Evaluation context
	 * @return mixed
	 */
	abstract protected function get_value( $condition, $context );

	/**
	 * Perform comparison
	 *
	 * @param mixed  $value         Value to compare
	 * @param mixed  $compare_value Value to compare against
	 * @param string $operator      Comparison operator
	 * @return bool
	 */
	protected function compare( $value, $compare_value, $operator ) {
		switch ( $operator ) {
			case 'equals':
				return $this->equals( $value, $compare_value );

			case 'not_equals':
				return ! $this->equals( $value, $compare_value );

			case 'greater_than':
				return $this->numeric_compare( $value, $compare_value, '>' );

			case 'less_than':
				return $this->numeric_compare( $value, $compare_value, '<' );

			case 'greater_than_equals':
				return $this->numeric_compare( $value, $compare_value, '>=' );

			case 'less_than_equals':
				return $this->numeric_compare( $value, $compare_value, '<=' );

			case 'contains':
				return $this->string_contains( $value, $compare_value );

			case 'not_contains':
				return ! $this->string_contains( $value, $compare_value );

			case 'starts_with':
				return $this->string_starts_with( $value, $compare_value );

			case 'ends_with':
				return $this->string_ends_with( $value, $compare_value );

			case 'in':
				return $this->in_array( $value, $compare_value );

			case 'not_in':
				return ! $this->in_array( $value, $compare_value );

			case 'is_empty':
				return empty( $value );

			case 'is_not_empty':
				return ! empty( $value );

			case 'between':
				return $this->between( $value, $compare_value );

			case 'not_between':
				return ! $this->between( $value, $compare_value );

			default:
				return apply_filters( 
					'woocommerce_product_addons_condition_compare_' . $this->type, 
					false, 
					$value, 
					$compare_value, 
					$operator,
					$this
				);
		}
	}

	/**
	 * Check if values are equal
	 *
	 * @param mixed $value1 First value
	 * @param mixed $value2 Second value
	 * @return bool
	 */
	protected function equals( $value1, $value2 ) {
		// Handle array comparison
		if ( is_array( $value1 ) || is_array( $value2 ) ) {
			if ( ! is_array( $value1 ) ) {
				$value1 = array( $value1 );
			}
			if ( ! is_array( $value2 ) ) {
				$value2 = array( $value2 );
			}
			
			sort( $value1 );
			sort( $value2 );
			
			return $value1 === $value2;
		}

		// Standard comparison
		return $value1 == $value2;
	}

	/**
	 * Numeric comparison
	 *
	 * @param mixed  $value1   First value
	 * @param mixed  $value2   Second value
	 * @param string $operator Comparison operator
	 * @return bool
	 */
	protected function numeric_compare( $value1, $value2, $operator ) {
		if ( ! is_numeric( $value1 ) || ! is_numeric( $value2 ) ) {
			return false;
		}

		$value1 = floatval( $value1 );
		$value2 = floatval( $value2 );

		switch ( $operator ) {
			case '>':
				return $value1 > $value2;
			case '<':
				return $value1 < $value2;
			case '>=':
				return $value1 >= $value2;
			case '<=':
				return $value1 <= $value2;
			default:
				return false;
		}
	}

	/**
	 * Check if string contains substring
	 *
	 * @param string $haystack String to search in
	 * @param string $needle   String to search for
	 * @return bool
	 */
	protected function string_contains( $haystack, $needle ) {
		return strpos( (string) $haystack, (string) $needle ) !== false;
	}

	/**
	 * Check if string starts with substring
	 *
	 * @param string $haystack String to check
	 * @param string $needle   String to look for
	 * @return bool
	 */
	protected function string_starts_with( $haystack, $needle ) {
		return strpos( (string) $haystack, (string) $needle ) === 0;
	}

	/**
	 * Check if string ends with substring
	 *
	 * @param string $haystack String to check
	 * @param string $needle   String to look for
	 * @return bool
	 */
	protected function string_ends_with( $haystack, $needle ) {
		$length = strlen( (string) $needle );
		return $length === 0 || substr( (string) $haystack, -$length ) === (string) $needle;
	}

	/**
	 * Check if value is in array
	 *
	 * @param mixed $needle   Value to search for
	 * @param array $haystack Array to search in
	 * @return bool
	 */
	protected function in_array( $needle, $haystack ) {
		if ( ! is_array( $haystack ) ) {
			$haystack = array( $haystack );
		}

		// Handle array needle (check if any element is in haystack)
		if ( is_array( $needle ) ) {
			foreach ( $needle as $value ) {
				if ( in_array( $value, $haystack ) ) {
					return true;
				}
			}
			return false;
		}

		return in_array( $needle, $haystack );
	}

	/**
	 * Check if value is between two values
	 *
	 * @param mixed $value         Value to check
	 * @param array $range_values  Array with min and max values
	 * @return bool
	 */
	protected function between( $value, $range_values ) {
		if ( ! is_array( $range_values ) || count( $range_values ) < 2 ) {
			return false;
		}

		if ( ! is_numeric( $value ) ) {
			return false;
		}

		$min = floatval( $range_values[0] );
		$max = floatval( $range_values[1] );
		$value = floatval( $value );

		return $value >= $min && $value <= $max;
	}

	/**
	 * Validate condition configuration
	 *
	 * @param array $condition Condition configuration
	 * @return bool
	 */
	public function validate( $condition ) {
		// Check if operator is supported
		if ( isset( $condition['operator'] ) && ! in_array( $condition['operator'], $this->supported_operators ) ) {
			return false;
		}

		// Type-specific validation
		return $this->validate_specific( $condition );
	}

	/**
	 * Type-specific validation
	 *
	 * @param array $condition Condition configuration
	 * @return bool
	 */
	protected function validate_specific( $condition ) {
		return true;
	}

	/**
	 * Get display label for the condition
	 *
	 * @return string
	 */
	public function get_label() {
		return ucfirst( str_replace( '_', ' ', $this->type ) );
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'operator' => array(
				'type'    => 'select',
				'label'   => __( 'Operator', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_operator_options(),
			),
			'value' => array(
				'type'  => 'text',
				'label' => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
			),
		);
	}

	/**
	 * Get operator options for admin UI
	 *
	 * @return array
	 */
	protected function get_operator_options() {
		$options = array();

		foreach ( $this->supported_operators as $operator ) {
			$options[ $operator ] = $this->get_operator_label( $operator );
		}

		return $options;
	}

	/**
	 * Get human-readable label for operator
	 *
	 * @param string $operator Operator identifier
	 * @return string
	 */
	protected function get_operator_label( $operator ) {
		$labels = array(
			'equals'              => __( 'equals', 'woocommerce-product-addons-extra-digital' ),
			'not_equals'          => __( 'not equals', 'woocommerce-product-addons-extra-digital' ),
			'greater_than'        => __( 'greater than', 'woocommerce-product-addons-extra-digital' ),
			'less_than'           => __( 'less than', 'woocommerce-product-addons-extra-digital' ),
			'greater_than_equals' => __( 'greater than or equals', 'woocommerce-product-addons-extra-digital' ),
			'less_than_equals'    => __( 'less than or equals', 'woocommerce-product-addons-extra-digital' ),
			'contains'            => __( 'contains', 'woocommerce-product-addons-extra-digital' ),
			'not_contains'        => __( 'does not contain', 'woocommerce-product-addons-extra-digital' ),
			'starts_with'         => __( 'starts with', 'woocommerce-product-addons-extra-digital' ),
			'ends_with'           => __( 'ends with', 'woocommerce-product-addons-extra-digital' ),
			'in'                  => __( 'is in', 'woocommerce-product-addons-extra-digital' ),
			'not_in'              => __( 'is not in', 'woocommerce-product-addons-extra-digital' ),
			'is_empty'            => __( 'is empty', 'woocommerce-product-addons-extra-digital' ),
			'is_not_empty'        => __( 'is not empty', 'woocommerce-product-addons-extra-digital' ),
			'between'             => __( 'is between', 'woocommerce-product-addons-extra-digital' ),
			'not_between'         => __( 'is not between', 'woocommerce-product-addons-extra-digital' ),
		);

		return isset( $labels[ $operator ] ) ? $labels[ $operator ] : $operator;
	}
}