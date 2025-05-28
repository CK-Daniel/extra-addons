<?php
/**
 * Product Condition Class
 *
 * Handles conditions based on product properties.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Condition Class
 *
 * @class    WC_Product_Addons_Condition_Product
 * @extends  WC_Product_Addons_Condition
 * @version  4.0.0
 */
class WC_Product_Addons_Condition_Product extends WC_Product_Addons_Condition {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'product';
		$this->supported_operators = array(
			'equals',
			'not_equals',
			'greater_than',
			'less_than',
			'greater_than_equals',
			'less_than_equals',
			'in',
			'not_in',
			'contains',
			'not_contains',
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
		if ( ! isset( $context['product'] ) || ! is_object( $context['product'] ) ) {
			return null;
		}

		$product = $context['product'];
		$property = isset( $condition['property'] ) ? $condition['property'] : 'id';

		switch ( $property ) {
			case 'id':
				return $product->get_id();

			case 'type':
				return $product->get_type();

			case 'price':
				return floatval( $product->get_price() );

			case 'regular_price':
				return floatval( $product->get_regular_price() );

			case 'sale_price':
				$sale_price = $product->get_sale_price();
				return $sale_price ? floatval( $sale_price ) : null;

			case 'stock_quantity':
				return $product->get_stock_quantity();

			case 'stock_status':
				return $product->get_stock_status();

			case 'categories':
				return $product->get_category_ids();

			case 'tags':
				return $product->get_tag_ids();

			case 'sku':
				return $product->get_sku();

			case 'weight':
				return floatval( $product->get_weight() );

			case 'length':
				return floatval( $product->get_length() );

			case 'width':
				return floatval( $product->get_width() );

			case 'height':
				return floatval( $product->get_height() );

			case 'is_on_sale':
				return $product->is_on_sale();

			case 'is_featured':
				return $product->is_featured();

			case 'is_virtual':
				return $product->is_virtual();

			case 'is_downloadable':
				return $product->is_downloadable();

			case 'total_sales':
				return intval( $product->get_total_sales() );

			case 'attribute':
				if ( isset( $condition['attribute_name'] ) ) {
					return $product->get_attribute( $condition['attribute_name'] );
				}
				return null;

			case 'meta':
				if ( isset( $condition['meta_key'] ) ) {
					return get_post_meta( $product->get_id(), $condition['meta_key'], true );
				}
				return null;

			default:
				// Try to call get_{property} method
				if ( method_exists( $product, 'get_' . $property ) ) {
					$method = 'get_' . $property;
					return $product->$method();
				}
				return null;
		}
	}

	/**
	 * Get configuration fields for admin UI
	 *
	 * @return array
	 */
	public function get_config_fields() {
		$fields = array(
			'property' => array(
				'type'    => 'select',
				'label'   => __( 'Property', 'woocommerce-product-addons-extra-digital' ),
				'options' => array(
					'id'              => __( 'Product ID', 'woocommerce-product-addons-extra-digital' ),
					'type'            => __( 'Product Type', 'woocommerce-product-addons-extra-digital' ),
					'price'           => __( 'Price', 'woocommerce-product-addons-extra-digital' ),
					'regular_price'   => __( 'Regular Price', 'woocommerce-product-addons-extra-digital' ),
					'sale_price'      => __( 'Sale Price', 'woocommerce-product-addons-extra-digital' ),
					'stock_quantity'  => __( 'Stock Quantity', 'woocommerce-product-addons-extra-digital' ),
					'stock_status'    => __( 'Stock Status', 'woocommerce-product-addons-extra-digital' ),
					'categories'      => __( 'Categories', 'woocommerce-product-addons-extra-digital' ),
					'tags'            => __( 'Tags', 'woocommerce-product-addons-extra-digital' ),
					'sku'             => __( 'SKU', 'woocommerce-product-addons-extra-digital' ),
					'weight'          => __( 'Weight', 'woocommerce-product-addons-extra-digital' ),
					'is_on_sale'      => __( 'Is On Sale', 'woocommerce-product-addons-extra-digital' ),
					'is_featured'     => __( 'Is Featured', 'woocommerce-product-addons-extra-digital' ),
					'is_virtual'      => __( 'Is Virtual', 'woocommerce-product-addons-extra-digital' ),
					'is_downloadable' => __( 'Is Downloadable', 'woocommerce-product-addons-extra-digital' ),
					'total_sales'     => __( 'Total Sales', 'woocommerce-product-addons-extra-digital' ),
					'attribute'       => __( 'Product Attribute', 'woocommerce-product-addons-extra-digital' ),
					'meta'            => __( 'Custom Meta', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'operator' => array(
				'type'    => 'select',
				'label'   => __( 'Operator', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_operator_options(),
			),
		);

		// Add attribute name field if attribute property is selected
		$fields['attribute_name'] = array(
			'type'        => 'text',
			'label'       => __( 'Attribute Name', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'e.g., pa_color', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'attribute' ),
				'action' => 'show',
			),
		);

		// Add meta key field if meta property is selected
		$fields['meta_key'] = array(
			'type'        => 'text',
			'label'       => __( 'Meta Key', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter meta key', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'meta' ),
				'action' => 'show',
			),
		);

		// Add value field with dynamic type based on property
		$fields['value'] = array(
			'type'        => 'dynamic',
			'label'       => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter value to compare', 'woocommerce-product-addons-extra-digital' ),
			'config'      => array(
				'type' => array(
					'type'  => 'select',
					'options' => array(
						'simple'   => __( 'Simple', 'woocommerce-product-addons-extra-digital' ),
						'variable' => __( 'Variable', 'woocommerce-product-addons-extra-digital' ),
						'grouped'  => __( 'Grouped', 'woocommerce-product-addons-extra-digital' ),
						'external' => __( 'External', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'stock_status' => array(
					'type'  => 'select',
					'options' => array(
						'instock'     => __( 'In Stock', 'woocommerce-product-addons-extra-digital' ),
						'outofstock'  => __( 'Out of Stock', 'woocommerce-product-addons-extra-digital' ),
						'onbackorder' => __( 'On Backorder', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'is_on_sale' => array(
					'type'  => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'is_featured' => array(
					'type'  => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'is_virtual' => array(
					'type'  => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'is_downloadable' => array(
					'type'  => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'categories' => array(
					'type'     => 'multiselect',
					'options'  => 'product_categories', // Will be populated dynamically
				),
				'tags' => array(
					'type'     => 'multiselect',
					'options'  => 'product_tags', // Will be populated dynamically
				),
				'default' => array(
					'type' => 'text',
				),
			),
		);

		return $fields;
	}

	/**
	 * Get display label for the condition
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Product Property', 'woocommerce-product-addons-extra-digital' );
	}

	/**
	 * Validate condition configuration
	 *
	 * @param array $condition Condition configuration
	 * @return bool
	 */
	protected function validate_specific( $condition ) {
		// Must have property
		if ( ! isset( $condition['property'] ) ) {
			return false;
		}

		// If property is attribute, must have attribute_name
		if ( $condition['property'] === 'attribute' && ! isset( $condition['attribute_name'] ) ) {
			return false;
		}

		// If property is meta, must have meta_key
		if ( $condition['property'] === 'meta' && ! isset( $condition['meta_key'] ) ) {
			return false;
		}

		return true;
	}
}