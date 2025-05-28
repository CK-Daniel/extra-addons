<?php
/**
 * Visibility Action Class
 *
 * Handles visibility modifications for addons and options.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visibility Action Class
 *
 * @class    WC_Product_Addons_Action_Visibility
 * @extends  WC_Product_Addons_Action
 * @version  4.0.0
 */
class WC_Product_Addons_Action_Visibility extends WC_Product_Addons_Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'visibility';
	}

	/**
	 * Execute the visibility action
	 *
	 * @param mixed $target  Target element (addon or options)
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 * @return mixed Modified target
	 */
	public function execute( $target, $action, $context ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;
		$visibility_action = isset( $config['action'] ) ? $config['action'] : 'show';

		// For addon visibility
		if ( isset( $target['type'] ) && isset( $target['name'] ) ) {
			// This is an addon
			switch ( $visibility_action ) {
				case 'show':
					$target['hidden'] = false;
					break;

				case 'hide':
					$target['hidden'] = true;
					break;

				case 'toggle':
					$target['hidden'] = ! empty( $target['hidden'] ) ? false : true;
					break;
			}

			// Handle option visibility within the addon
			if ( isset( $config['options_visibility'] ) && isset( $target['options'] ) ) {
				$target = $this->modify_options_visibility( $target, $config['options_visibility'], $context );
			}
		}

		// For options array
		if ( is_array( $target ) && ! isset( $target['type'] ) ) {
			// This is an options array
			$target = $this->modify_options_visibility( array( 'options' => $target ), $config, $context );
			return $target['options'];
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
		$visibility_action = isset( $config['action'] ) ? $config['action'] : 'show';

		// Set addon visibility
		switch ( $visibility_action ) {
			case 'show':
				$results['visible'] = true;
				break;

			case 'hide':
				$results['visible'] = false;
				break;

			case 'toggle':
				$results['visible'] = ! isset( $results['visible'] ) || ! $results['visible'];
				break;
		}

		// Store animation settings if specified
		if ( isset( $config['animation'] ) ) {
			$results['animation'] = array(
				'type'     => $config['animation'],
				'duration' => isset( $config['animation_duration'] ) ? $config['animation_duration'] : 300,
			);
		}

		// Store option visibility modifications
		if ( isset( $config['show_options'] ) || isset( $config['hide_options'] ) || isset( $config['disable_options'] ) ) {
			if ( ! isset( $results['option_modifiers'] ) ) {
				$results['option_modifiers'] = array();
			}

			if ( isset( $config['show_options'] ) ) {
				$results['option_modifiers']['show'] = $config['show_options'];
			}

			if ( isset( $config['hide_options'] ) ) {
				$results['option_modifiers']['hide'] = $config['hide_options'];
			}

			if ( isset( $config['disable_options'] ) ) {
				$results['option_modifiers']['disable'] = $config['disable_options'];
			}
		}

		// Add any display message
		$message = $this->get_message_config( $action );
		if ( $message ) {
			$results['visibility_message'] = $message;
		}
	}

	/**
	 * Modify options visibility within an addon
	 *
	 * @param array $addon   Addon data with options
	 * @param array $config  Visibility configuration
	 * @param array $context Evaluation context
	 * @return array Modified addon
	 */
	private function modify_options_visibility( $addon, $config, $context ) {
		if ( ! isset( $addon['options'] ) || ! is_array( $addon['options'] ) ) {
			return $addon;
		}

		// Show specific options
		if ( isset( $config['show_options'] ) && is_array( $config['show_options'] ) ) {
			foreach ( $addon['options'] as &$option ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				
				if ( in_array( $option_value, $config['show_options'] ) ) {
					$option['hidden'] = false;
					unset( $option['disabled'] );
				}
			}
		}

		// Hide specific options
		if ( isset( $config['hide_options'] ) && is_array( $config['hide_options'] ) ) {
			foreach ( $addon['options'] as &$option ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				
				if ( in_array( $option_value, $config['hide_options'] ) ) {
					$option['hidden'] = true;
				}
			}
		}

		// Disable specific options
		if ( isset( $config['disable_options'] ) && is_array( $config['disable_options'] ) ) {
			foreach ( $addon['options'] as &$option ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				
				if ( in_array( $option_value, $config['disable_options'] ) ) {
					$option['disabled'] = true;
				}
			}
		}

		// Enable specific options
		if ( isset( $config['enable_options'] ) && is_array( $config['enable_options'] ) ) {
			foreach ( $addon['options'] as &$option ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				
				if ( in_array( $option_value, $config['enable_options'] ) ) {
					unset( $option['disabled'] );
				}
			}
		}

		// Show only specific options (hide all others)
		if ( isset( $config['show_only_options'] ) && is_array( $config['show_only_options'] ) ) {
			foreach ( $addon['options'] as &$option ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				
				if ( ! in_array( $option_value, $config['show_only_options'] ) ) {
					$option['hidden'] = true;
				} else {
					$option['hidden'] = false;
				}
			}
		}

		// Reorder options if specified
		if ( isset( $config['reorder_options'] ) && is_array( $config['reorder_options'] ) ) {
			$addon['options'] = $this->reorder_options( $addon['options'], $config['reorder_options'] );
		}

		return $addon;
	}

	/**
	 * Reorder options based on specified order
	 *
	 * @param array $options Current options
	 * @param array $order   Desired order of option values
	 * @return array Reordered options
	 */
	private function reorder_options( $options, $order ) {
		$ordered = array();
		$remaining = array();

		// First, add options in the specified order
		foreach ( $order as $value ) {
			foreach ( $options as $option ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				if ( $option_value === $value ) {
					$ordered[] = $option;
					break;
				}
			}
		}

		// Then add any remaining options
		foreach ( $options as $option ) {
			$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
			$found = false;
			
			foreach ( $ordered as $ordered_option ) {
				$ordered_value = isset( $ordered_option['value'] ) ? $ordered_option['value'] : $ordered_option['label'];
				if ( $option_value === $ordered_value ) {
					$found = true;
					break;
				}
			}
			
			if ( ! $found ) {
				$remaining[] = $option;
			}
		}

		return array_merge( $ordered, $remaining );
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'action' => array(
				'type'    => 'select',
				'label'   => __( 'Visibility Action', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'show'   => __( 'Show', 'woocommerce-product-addons-extra-digital' ),
					'hide'   => __( 'Hide', 'woocommerce-product-addons-extra-digital' ),
					'toggle' => __( 'Toggle', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'target' => array(
				'type'    => 'select',
				'label'   => __( 'Apply To', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'addon'   => __( 'Entire Addon', 'woocommerce-product-addons-extra-digital' ),
					'options' => __( 'Specific Options', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'animation' => array(
				'type'    => 'select',
				'label'   => __( 'Animation', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'none'       => __( 'None', 'woocommerce-product-addons-extra-digital' ),
					'fade'       => __( 'Fade', 'woocommerce-product-addons-extra-digital' ),
					'slide'      => __( 'Slide', 'woocommerce-product-addons-extra-digital' ),
					'slide_fade' => __( 'Slide + Fade', 'woocommerce-product-addons-extra-digital' ),
				),
				'default' => 'fade',
			),
			'animation_duration' => array(
				'type'    => 'number',
				'label'   => __( 'Animation Duration (ms)', 'woocommerce-product-addons-extra-digital' ),
				'default' => 300,
				'min'     => 0,
				'max'     => 2000,
				'step'    => 50,
			),
			'show_options' => array(
				'type'        => 'multiselect',
				'label'       => __( 'Show Options', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Populated dynamically
				'dependency'  => array(
					'field'  => 'target',
					'values' => array( 'options' ),
					'action' => 'show',
				),
			),
			'hide_options' => array(
				'type'        => 'multiselect',
				'label'       => __( 'Hide Options', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Populated dynamically
				'dependency'  => array(
					'field'  => 'target',
					'values' => array( 'options' ),
					'action' => 'show',
				),
			),
			'disable_options' => array(
				'type'        => 'multiselect',
				'label'       => __( 'Disable Options', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Populated dynamically
				'dependency'  => array(
					'field'  => 'target',
					'values' => array( 'options' ),
					'action' => 'show',
				),
			),
			'reorder_options' => array(
				'type'        => 'sortable',
				'label'       => __( 'Reorder Options', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Populated dynamically
				'dependency'  => array(
					'field'  => 'target',
					'values' => array( 'options' ),
					'action' => 'show',
				),
			),
			'scroll_to' => array(
				'type'    => 'checkbox',
				'label'   => __( 'Scroll to addon when shown', 'woocommerce-product-addons-extra-digital' ),
				'default' => false,
			),
			'highlight' => array(
				'type'    => 'checkbox',
				'label'   => __( 'Highlight addon when shown', 'woocommerce-product-addons-extra-digital' ),
				'default' => false,
			),
			'highlight_color' => array(
				'type'        => 'color',
				'label'       => __( 'Highlight Color', 'woocommerce-product-addons-extra-digital' ),
				'default'     => '#ffe082',
				'dependency'  => array(
					'field'  => 'highlight',
					'values' => array( true ),
					'action' => 'show',
				),
			),
			'message' => array(
				'type'        => 'text',
				'label'       => __( 'Display Message', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'e.g., This option is now available', 'woocommerce-product-addons-extra-digital' ),
			),
		);
	}

	/**
	 * Get display label for the action
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Change Visibility', 'woocommerce-product-addons-extra-digital' );
	}

	/**
	 * Validate action configuration
	 *
	 * @param array $action Action configuration
	 * @return bool
	 */
	protected function validate_specific( $action ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;

		// Must have an action
		if ( ! isset( $config['action'] ) ) {
			return false;
		}

		// Valid actions
		$valid_actions = array( 'show', 'hide', 'toggle' );
		if ( ! in_array( $config['action'], $valid_actions ) ) {
			return false;
		}

		return true;
	}
}