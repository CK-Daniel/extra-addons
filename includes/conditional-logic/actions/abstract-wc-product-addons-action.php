<?php
/**
 * Abstract Action Class
 *
 * Base class for all action types in the conditional logic system.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Action Class
 *
 * @abstract
 * @class    WC_Product_Addons_Action
 * @version  4.0.0
 */
abstract class WC_Product_Addons_Action {

	/**
	 * Action type identifier
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * Get action type
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Execute the action
	 *
	 * @param mixed $target  Target to apply action to
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 * @return mixed Modified target
	 */
	abstract public function execute( $target, $action, $context );

	/**
	 * Apply action to results array (for AJAX responses)
	 *
	 * @param array $results Results array to modify
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 */
	abstract public function apply_to_results( &$results, $action, $context );

	/**
	 * Validate action configuration
	 *
	 * @param array $action Action configuration
	 * @return bool
	 */
	public function validate( $action ) {
		// Check if required fields exist
		if ( ! isset( $action['type'] ) || $action['type'] !== $this->type ) {
			return false;
		}

		// Type-specific validation
		return $this->validate_specific( $action );
	}

	/**
	 * Type-specific validation
	 *
	 * @param array $action Action configuration
	 * @return bool
	 */
	protected function validate_specific( $action ) {
		return true;
	}

	/**
	 * Get display label for the action
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
	abstract public function get_config_fields();

	/**
	 * Get target options for admin UI
	 *
	 * @return array
	 */
	protected function get_target_options() {
		return array(
			'self'       => __( 'This Addon', 'woocommerce-product-addons-extra-digital' ),
			'other'      => __( 'Specific Addon', 'woocommerce-product-addons-extra-digital' ),
			'all'        => __( 'All Addons', 'woocommerce-product-addons-extra-digital' ),
			'category'   => __( 'Addon Category', 'woocommerce-product-addons-extra-digital' ),
			'except'     => __( 'All Except', 'woocommerce-product-addons-extra-digital' ),
		);
	}

	/**
	 * Check if action should apply to addon
	 *
	 * @param array  $addon   Addon data
	 * @param array  $action  Action configuration
	 * @param string $current Current addon name (for self reference)
	 * @return bool
	 */
	protected function should_apply_to_addon( $addon, $action, $current = '' ) {
		$target = isset( $action['target'] ) ? $action['target'] : 'self';

		switch ( $target ) {
			case 'self':
				return $addon['name'] === $current;

			case 'other':
				if ( isset( $action['target_addon'] ) ) {
					return $addon['name'] === $action['target_addon'];
				}
				return false;

			case 'all':
				return true;

			case 'category':
				if ( isset( $action['target_category'] ) && isset( $addon['category'] ) ) {
					return $addon['category'] === $action['target_category'];
				}
				return false;

			case 'except':
				if ( isset( $action['except_addons'] ) && is_array( $action['except_addons'] ) ) {
					return ! in_array( $addon['name'], $action['except_addons'] );
				}
				return true;

			default:
				return false;
		}
	}

	/**
	 * Get message configuration
	 *
	 * @param array $action Action configuration
	 * @return array|null
	 */
	protected function get_message_config( $action ) {
		if ( ! isset( $action['message'] ) ) {
			return null;
		}

		// Handle simple string message
		if ( is_string( $action['message'] ) ) {
			return array(
				'text'     => $action['message'],
				'type'     => 'info',
				'position' => 'inline',
			);
		}

		// Handle complex message configuration
		if ( is_array( $action['message'] ) ) {
			return wp_parse_args( $action['message'], array(
				'text'     => '',
				'type'     => 'info', // info, warning, success, error
				'position' => 'inline', // inline, tooltip, modal
			) );
		}

		return null;
	}

	/**
	 * Parse dynamic variables in text
	 *
	 * @param string $text    Text containing variables
	 * @param array  $context Evaluation context
	 * @return string Parsed text
	 */
	protected function parse_dynamic_text( $text, $context ) {
		// Replace common variables
		$replacements = array(
			'{product_name}'  => isset( $context['product'] ) ? $context['product']->get_name() : '',
			'{product_price}' => isset( $context['product'] ) ? wc_price( $context['product']->get_price() ) : '',
			'{user_name}'     => isset( $context['user'] ) ? $context['user']->display_name : '',
			'{cart_total}'    => isset( $context['cart'] ) ? wc_price( $context['cart']->get_cart_contents_total() ) : '',
			'{date}'          => date_i18n( get_option( 'date_format' ) ),
			'{time}'          => date_i18n( get_option( 'time_format' ) ),
		);

		// Allow filtering of replacements
		$replacements = apply_filters( 'woocommerce_product_addons_action_text_replacements', $replacements, $text, $context );

		// Perform replacements
		foreach ( $replacements as $variable => $value ) {
			$text = str_replace( $variable, $value, $text );
		}

		// Handle custom variables from context
		if ( preg_match_all( '/\{([a-zA-Z0-9_\.]+)\}/', $text, $matches ) ) {
			foreach ( $matches[1] as $key => $variable ) {
				$value = $this->get_variable_value( $variable, $context );
				if ( $value !== null ) {
					$text = str_replace( $matches[0][ $key ], $value, $text );
				}
			}
		}

		return $text;
	}

	/**
	 * Get variable value from context
	 *
	 * @param string $variable Variable path (e.g., "selections.addon_name.value")
	 * @param array  $context  Evaluation context
	 * @return mixed|null
	 */
	protected function get_variable_value( $variable, $context ) {
		$parts = explode( '.', $variable );
		$value = $context;

		foreach ( $parts as $part ) {
			if ( is_array( $value ) && isset( $value[ $part ] ) ) {
				$value = $value[ $part ];
			} elseif ( is_object( $value ) && property_exists( $value, $part ) ) {
				$value = $value->$part;
			} else {
				return null;
			}
		}

		return $value;
	}

	/**
	 * Log action execution
	 *
	 * @param array $action     Action configuration
	 * @param mixed $target     Target that was modified
	 * @param mixed $result     Result of the action
	 * @param array $context    Evaluation context
	 */
	protected function log_execution( $action, $target, $result, $context ) {
		if ( ! apply_filters( 'woocommerce_product_addons_log_action_execution', false ) ) {
			return;
		}

		$log_data = array(
			'action_type' => $this->type,
			'action'      => $action,
			'target'      => $target,
			'result'      => $result,
			'timestamp'   => current_time( 'mysql' ),
			'user_id'     => get_current_user_id(),
		);

		do_action( 'woocommerce_product_addons_action_executed', $log_data, $context );
	}
}