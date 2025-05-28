<?php
/**
 * Modifier Action Class
 *
 * Handles modifications to addon properties like labels, descriptions, and options.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modifier Action Class
 *
 * @class    WC_Product_Addons_Action_Modifier
 * @extends  WC_Product_Addons_Action
 * @version  4.0.0
 */
class WC_Product_Addons_Action_Modifier extends WC_Product_Addons_Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'modifier';
	}

	/**
	 * Execute the modifier action
	 *
	 * @param mixed $target  Target addon
	 * @param array $action  Action configuration
	 * @param array $context Evaluation context
	 * @return mixed Modified target
	 */
	public function execute( $target, $action, $context ) {
		$config = isset( $action['config'] ) ? $action['config'] : $action;

		// Modify text properties
		if ( isset( $config['text_modifications'] ) ) {
			$target = $this->apply_text_modifications( $target, $config['text_modifications'], $context );
		}

		// Modify options
		if ( isset( $config['option_modifications'] ) ) {
			$target = $this->apply_option_modifications( $target, $config['option_modifications'], $context );
		}

		// Modify display properties
		if ( isset( $config['display_modifications'] ) ) {
			$target = $this->apply_display_modifications( $target, $config['display_modifications'], $context );
		}

		// Add/remove CSS classes
		if ( isset( $config['css_modifications'] ) ) {
			$target = $this->apply_css_modifications( $target, $config['css_modifications'], $context );
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

		if ( ! isset( $results['modifications'] ) ) {
			$results['modifications'] = array();
		}

		// Store text modifications
		if ( isset( $config['text_modifications'] ) ) {
			$results['modifications']['text'] = $this->prepare_text_modifications( $config['text_modifications'], $context );
		}

		// Store option modifications
		if ( isset( $config['option_modifications'] ) ) {
			$results['modifications']['options'] = $config['option_modifications'];
		}

		// Store display modifications
		if ( isset( $config['display_modifications'] ) ) {
			$results['modifications']['display'] = $config['display_modifications'];
		}

		// Store CSS modifications
		if ( isset( $config['css_modifications'] ) ) {
			$results['modifications']['css'] = $config['css_modifications'];
		}
	}

	/**
	 * Apply text modifications to addon
	 *
	 * @param array $addon         Addon data
	 * @param array $modifications Text modifications
	 * @param array $context       Evaluation context
	 * @return array Modified addon
	 */
	private function apply_text_modifications( $addon, $modifications, $context ) {
		// Modify label
		if ( isset( $modifications['label'] ) ) {
			$label_mod = $modifications['label'];
			
			if ( isset( $label_mod['type'] ) ) {
				switch ( $label_mod['type'] ) {
					case 'replace':
						$addon['name'] = $this->parse_dynamic_text( $label_mod['value'], $context );
						break;
					
					case 'append':
						$addon['name'] .= ' ' . $this->parse_dynamic_text( $label_mod['value'], $context );
						break;
					
					case 'prepend':
						$addon['name'] = $this->parse_dynamic_text( $label_mod['value'], $context ) . ' ' . $addon['name'];
						break;
				}
			}
		}

		// Modify description
		if ( isset( $modifications['description'] ) ) {
			if ( ! isset( $addon['description'] ) ) {
				$addon['description'] = '';
			}
			
			$desc_mod = $modifications['description'];
			
			if ( isset( $desc_mod['type'] ) ) {
				switch ( $desc_mod['type'] ) {
					case 'replace':
						$addon['description'] = $this->parse_dynamic_text( $desc_mod['value'], $context );
						break;
					
					case 'append':
						$addon['description'] .= ' ' . $this->parse_dynamic_text( $desc_mod['value'], $context );
						break;
					
					case 'prepend':
						$addon['description'] = $this->parse_dynamic_text( $desc_mod['value'], $context ) . ' ' . $addon['description'];
						break;
				}
			}
		}

		// Modify placeholder
		if ( isset( $modifications['placeholder'] ) && in_array( $addon['type'], array( 'custom_text', 'custom_textarea', 'custom_email' ) ) ) {
			$addon['placeholder'] = $this->parse_dynamic_text( $modifications['placeholder'], $context );
		}

		// Modify help text
		if ( isset( $modifications['help_text'] ) ) {
			$addon['help_text'] = $this->parse_dynamic_text( $modifications['help_text'], $context );
		}

		return $addon;
	}

	/**
	 * Apply option modifications to addon
	 *
	 * @param array $addon         Addon data
	 * @param array $modifications Option modifications
	 * @param array $context       Evaluation context
	 * @return array Modified addon
	 */
	private function apply_option_modifications( $addon, $modifications, $context ) {
		if ( ! isset( $addon['options'] ) || ! is_array( $addon['options'] ) ) {
			return $addon;
		}

		// Add new options
		if ( isset( $modifications['add_options'] ) && is_array( $modifications['add_options'] ) ) {
			foreach ( $modifications['add_options'] as $new_option ) {
				// Parse dynamic values
				if ( isset( $new_option['label'] ) ) {
					$new_option['label'] = $this->parse_dynamic_text( $new_option['label'], $context );
				}
				
				// Add at specific position or at end
				if ( isset( $new_option['position'] ) && is_numeric( $new_option['position'] ) ) {
					array_splice( $addon['options'], intval( $new_option['position'] ), 0, array( $new_option ) );
				} else {
					$addon['options'][] = $new_option;
				}
			}
		}

		// Remove options
		if ( isset( $modifications['remove_options'] ) && is_array( $modifications['remove_options'] ) ) {
			$addon['options'] = array_filter( $addon['options'], function( $option ) use ( $modifications ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				return ! in_array( $option_value, $modifications['remove_options'] );
			} );
			
			// Re-index array
			$addon['options'] = array_values( $addon['options'] );
		}

		// Modify existing options
		if ( isset( $modifications['modify_options'] ) && is_array( $modifications['modify_options'] ) ) {
			foreach ( $addon['options'] as &$option ) {
				$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
				
				if ( isset( $modifications['modify_options'][ $option_value ] ) ) {
					$mods = $modifications['modify_options'][ $option_value ];
					
					// Modify label
					if ( isset( $mods['label'] ) ) {
						$option['label'] = $this->parse_dynamic_text( $mods['label'], $context );
					}
					
					// Modify price
					if ( isset( $mods['price'] ) ) {
						$option['price'] = $mods['price'];
					}
					
					// Modify price type
					if ( isset( $mods['price_type'] ) ) {
						$option['price_type'] = $mods['price_type'];
					}
					
					// Add/modify image
					if ( isset( $mods['image'] ) ) {
						$option['image'] = $mods['image'];
					}
				}
			}
		}

		// Group options
		if ( isset( $modifications['group_options'] ) && is_array( $modifications['group_options'] ) ) {
			$grouped = array();
			$ungrouped = array();
			
			// First, group specified options
			foreach ( $modifications['group_options'] as $group_name => $option_values ) {
				$group = array(
					'group_label' => $group_name,
					'options' => array(),
				);
				
				foreach ( $addon['options'] as $option ) {
					$option_value = isset( $option['value'] ) ? $option['value'] : $option['label'];
					
					if ( in_array( $option_value, $option_values ) ) {
						$group['options'][] = $option;
					} else {
						$ungrouped[] = $option;
					}
				}
				
				if ( ! empty( $group['options'] ) ) {
					$grouped[] = $group;
				}
			}
			
			// Add ungrouped options at the end
			if ( ! empty( $ungrouped ) ) {
				$grouped[] = array(
					'group_label' => __( 'Other Options', 'woocommerce-product-addons-extra-digital' ),
					'options' => $ungrouped,
				);
			}
			
			$addon['option_groups'] = $grouped;
		}

		return $addon;
	}

	/**
	 * Apply display modifications to addon
	 *
	 * @param array $addon         Addon data
	 * @param array $modifications Display modifications
	 * @param array $context       Evaluation context
	 * @return array Modified addon
	 */
	private function apply_display_modifications( $addon, $modifications, $context ) {
		// Change display type
		if ( isset( $modifications['display'] ) ) {
			$addon['display'] = $modifications['display'];
		}

		// Change layout
		if ( isset( $modifications['layout'] ) ) {
			$addon['layout'] = $modifications['layout'];
		}

		// Set columns for grid layout
		if ( isset( $modifications['columns'] ) ) {
			$addon['columns'] = intval( $modifications['columns'] );
		}

		// Image display settings
		if ( isset( $modifications['image_size'] ) ) {
			$addon['image_size'] = $modifications['image_size'];
		}

		// Show/hide prices
		if ( isset( $modifications['show_prices'] ) ) {
			$addon['show_prices'] = (bool) $modifications['show_prices'];
		}

		// Price format
		if ( isset( $modifications['price_format'] ) ) {
			$addon['price_format'] = $modifications['price_format'];
		}

		return $addon;
	}

	/**
	 * Apply CSS modifications to addon
	 *
	 * @param array $addon         Addon data
	 * @param array $modifications CSS modifications
	 * @param array $context       Evaluation context
	 * @return array Modified addon
	 */
	private function apply_css_modifications( $addon, $modifications, $context ) {
		if ( ! isset( $addon['css_classes'] ) ) {
			$addon['css_classes'] = array();
		}

		// Add classes
		if ( isset( $modifications['add_classes'] ) && is_array( $modifications['add_classes'] ) ) {
			$addon['css_classes'] = array_merge( $addon['css_classes'], $modifications['add_classes'] );
		}

		// Remove classes
		if ( isset( $modifications['remove_classes'] ) && is_array( $modifications['remove_classes'] ) ) {
			$addon['css_classes'] = array_diff( $addon['css_classes'], $modifications['remove_classes'] );
		}

		// Inline styles
		if ( isset( $modifications['inline_styles'] ) && is_array( $modifications['inline_styles'] ) ) {
			if ( ! isset( $addon['inline_styles'] ) ) {
				$addon['inline_styles'] = array();
			}
			$addon['inline_styles'] = array_merge( $addon['inline_styles'], $modifications['inline_styles'] );
		}

		// Ensure unique classes
		$addon['css_classes'] = array_unique( $addon['css_classes'] );

		return $addon;
	}

	/**
	 * Prepare text modifications for frontend
	 *
	 * @param array $modifications Text modifications
	 * @param array $context       Evaluation context
	 * @return array Prepared modifications
	 */
	private function prepare_text_modifications( $modifications, $context ) {
		$prepared = array();

		foreach ( $modifications as $field => $mod ) {
			if ( is_array( $mod ) && isset( $mod['value'] ) ) {
				$prepared[ $field ] = array(
					'type'  => isset( $mod['type'] ) ? $mod['type'] : 'replace',
					'value' => $this->parse_dynamic_text( $mod['value'], $context ),
				);
			} else {
				$prepared[ $field ] = $this->parse_dynamic_text( $mod, $context );
			}
		}

		return $prepared;
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'modification_type' => array(
				'type'    => 'select',
				'label'   => __( 'Modification Type', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'text'    => __( 'Text Properties', 'woocommerce-product-addons-extra-digital' ),
					'options' => __( 'Options', 'woocommerce-product-addons-extra-digital' ),
					'display' => __( 'Display Settings', 'woocommerce-product-addons-extra-digital' ),
					'css'     => __( 'CSS Styling', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			
			// Text modification fields
			'label_type' => array(
				'type'        => 'select',
				'label'       => __( 'Label Modification', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(
					'replace' => __( 'Replace', 'woocommerce-product-addons-extra-digital' ),
					'append'  => __( 'Append', 'woocommerce-product-addons-extra-digital' ),
					'prepend' => __( 'Prepend', 'woocommerce-product-addons-extra-digital' ),
				),
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'text' ),
					'action' => 'show',
				),
			),
			'label_value' => array(
				'type'        => 'text',
				'label'       => __( 'Label Text', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Dynamic text with {variables}', 'woocommerce-product-addons-extra-digital' ),
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'text' ),
					'action' => 'show',
				),
			),
			'description_value' => array(
				'type'        => 'textarea',
				'label'       => __( 'Description', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'Dynamic description with {variables}', 'woocommerce-product-addons-extra-digital' ),
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'text' ),
					'action' => 'show',
				),
			),
			
			// Option modification fields
			'add_options' => array(
				'type'        => 'repeater',
				'label'       => __( 'Add Options', 'woocommerce-product-addons-extra-digital' ),
				'fields'      => array(
					'label' => array(
						'type'  => 'text',
						'label' => __( 'Option Label', 'woocommerce-product-addons-extra-digital' ),
					),
					'value' => array(
						'type'  => 'text',
						'label' => __( 'Option Value', 'woocommerce-product-addons-extra-digital' ),
					),
					'price' => array(
						'type'  => 'number',
						'label' => __( 'Price', 'woocommerce-product-addons-extra-digital' ),
						'step'  => '0.01',
					),
				),
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'options' ),
					'action' => 'show',
				),
			),
			'remove_options' => array(
				'type'        => 'multiselect',
				'label'       => __( 'Remove Options', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(), // Populated dynamically
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'options' ),
					'action' => 'show',
				),
			),
			
			// Display modification fields
			'display_type' => array(
				'type'        => 'select',
				'label'       => __( 'Display Type', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(
					'select'      => __( 'Dropdown', 'woocommerce-product-addons-extra-digital' ),
					'radiobutton' => __( 'Radio Buttons', 'woocommerce-product-addons-extra-digital' ),
					'images'      => __( 'Image Swatches', 'woocommerce-product-addons-extra-digital' ),
				),
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'display' ),
					'action' => 'show',
				),
			),
			'layout' => array(
				'type'        => 'select',
				'label'       => __( 'Layout', 'woocommerce-product-addons-extra-digital' ),
				'options'     => array(
					'default' => __( 'Default', 'woocommerce-product-addons-extra-digital' ),
					'grid'    => __( 'Grid', 'woocommerce-product-addons-extra-digital' ),
					'list'    => __( 'List', 'woocommerce-product-addons-extra-digital' ),
				),
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'display' ),
					'action' => 'show',
				),
			),
			
			// CSS modification fields
			'add_classes' => array(
				'type'        => 'text',
				'label'       => __( 'Add CSS Classes', 'woocommerce-product-addons-extra-digital' ),
				'placeholder' => __( 'class1 class2 class3', 'woocommerce-product-addons-extra-digital' ),
				'dependency'  => array(
					'field'  => 'modification_type',
					'values' => array( 'css' ),
					'action' => 'show',
				),
			),
		);
	}

	/**
	 * Get display label for the action
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Modify Properties', 'woocommerce-product-addons-extra-digital' );
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
		if ( ! isset( $config['text_modifications'] ) && 
			 ! isset( $config['option_modifications'] ) && 
			 ! isset( $config['display_modifications'] ) && 
			 ! isset( $config['css_modifications'] ) ) {
			return false;
		}

		return true;
	}
}