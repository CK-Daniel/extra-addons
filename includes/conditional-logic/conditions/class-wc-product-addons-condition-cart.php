<?php
/**
 * Cart Condition Class
 *
 * Handles conditions based on cart properties.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart Condition Class
 *
 * @class    WC_Product_Addons_Condition_Cart
 * @extends  WC_Product_Addons_Condition
 * @version  4.0.0
 */
class WC_Product_Addons_Condition_Cart extends WC_Product_Addons_Condition {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'cart';
		$this->supported_operators = array(
			'equals',
			'not_equals',
			'greater_than',
			'less_than',
			'greater_than_equals',
			'less_than_equals',
			'contains',
			'not_contains',
			'is_empty',
			'is_not_empty',
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
		if ( ! isset( $context['cart'] ) || ! is_object( $context['cart'] ) ) {
			// Try to get cart instance
			if ( function_exists( 'WC' ) && WC()->cart ) {
				$cart = WC()->cart;
			} else {
				return null;
			}
		} else {
			$cart = $context['cart'];
		}

		$property = isset( $condition['property'] ) ? $condition['property'] : 'total';

		switch ( $property ) {
			case 'total':
				return floatval( $cart->get_cart_contents_total() );

			case 'subtotal':
				return floatval( $cart->get_subtotal() );

			case 'subtotal_tax':
				return floatval( $cart->get_subtotal_tax() );

			case 'total_tax':
				return floatval( $cart->get_cart_contents_tax() );

			case 'shipping_total':
				return floatval( $cart->get_shipping_total() );

			case 'discount_total':
				return floatval( $cart->get_discount_total() );

			case 'item_count':
				return intval( $cart->get_cart_contents_count() );

			case 'unique_item_count':
				return count( $cart->get_cart() );

			case 'weight':
				return floatval( $cart->get_cart_contents_weight() );

			case 'coupons':
				return $cart->get_applied_coupons();

			case 'has_coupon':
				if ( isset( $condition['coupon_code'] ) ) {
					return $cart->has_discount( $condition['coupon_code'] );
				}
				return ! empty( $cart->get_applied_coupons() );

			case 'contains_product':
				if ( isset( $condition['product_id'] ) ) {
					return $this->cart_contains_product( $cart, $condition['product_id'] );
				}
				return false;

			case 'contains_category':
				if ( isset( $condition['category_id'] ) ) {
					return $this->cart_contains_category( $cart, $condition['category_id'] );
				}
				return false;

			case 'contains_tag':
				if ( isset( $condition['tag_id'] ) ) {
					return $this->cart_contains_tag( $cart, $condition['tag_id'] );
				}
				return false;

			case 'shipping_class':
				return $this->get_cart_shipping_classes( $cart );

			case 'shipping_method':
				$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
				return $chosen_methods ? $chosen_methods : array();

			case 'payment_method':
				return WC()->session->get( 'chosen_payment_method' );

			default:
				return null;
		}
	}

	/**
	 * Check if cart contains specific product
	 *
	 * @param WC_Cart $cart       Cart object
	 * @param int     $product_id Product ID
	 * @return bool
	 */
	private function cart_contains_product( $cart, $product_id ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( $cart_item['product_id'] == $product_id ) {
				return true;
			}
			// Check variation ID
			if ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] == $product_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if cart contains product from specific category
	 *
	 * @param WC_Cart $cart        Cart object
	 * @param int     $category_id Category ID
	 * @return bool
	 */
	private function cart_contains_category( $cart, $category_id ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product && in_array( $category_id, $product->get_category_ids() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if cart contains product with specific tag
	 *
	 * @param WC_Cart $cart   Cart object
	 * @param int     $tag_id Tag ID
	 * @return bool
	 */
	private function cart_contains_tag( $cart, $tag_id ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product && in_array( $tag_id, $product->get_tag_ids() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all shipping classes in cart
	 *
	 * @param WC_Cart $cart Cart object
	 * @return array
	 */
	private function get_cart_shipping_classes( $cart ) {
		$shipping_classes = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product ) {
				$class = $product->get_shipping_class();
				if ( $class && ! in_array( $class, $shipping_classes ) ) {
					$shipping_classes[] = $class;
				}
			}
		}
		return $shipping_classes;
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
					'total'              => __( 'Cart Total', 'woocommerce-product-addons-extra-digital' ),
					'subtotal'           => __( 'Cart Subtotal', 'woocommerce-product-addons-extra-digital' ),
					'subtotal_tax'       => __( 'Subtotal Tax', 'woocommerce-product-addons-extra-digital' ),
					'total_tax'          => __( 'Total Tax', 'woocommerce-product-addons-extra-digital' ),
					'shipping_total'     => __( 'Shipping Total', 'woocommerce-product-addons-extra-digital' ),
					'discount_total'     => __( 'Discount Total', 'woocommerce-product-addons-extra-digital' ),
					'item_count'         => __( 'Item Count', 'woocommerce-product-addons-extra-digital' ),
					'unique_item_count'  => __( 'Unique Item Count', 'woocommerce-product-addons-extra-digital' ),
					'weight'             => __( 'Total Weight', 'woocommerce-product-addons-extra-digital' ),
					'coupons'            => __( 'Applied Coupons', 'woocommerce-product-addons-extra-digital' ),
					'has_coupon'         => __( 'Has Specific Coupon', 'woocommerce-product-addons-extra-digital' ),
					'contains_product'   => __( 'Contains Product', 'woocommerce-product-addons-extra-digital' ),
					'contains_category'  => __( 'Contains Category', 'woocommerce-product-addons-extra-digital' ),
					'contains_tag'       => __( 'Contains Tag', 'woocommerce-product-addons-extra-digital' ),
					'shipping_class'     => __( 'Shipping Class', 'woocommerce-product-addons-extra-digital' ),
					'shipping_method'    => __( 'Shipping Method', 'woocommerce-product-addons-extra-digital' ),
					'payment_method'     => __( 'Payment Method', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'operator' => array(
				'type'    => 'select',
				'label'   => __( 'Operator', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_operator_options(),
			),
		);

		// Add product ID field for contains_product
		$fields['product_id'] = array(
			'type'        => 'select',
			'label'       => __( 'Product', 'woocommerce-product-addons-extra-digital' ),
			'options'     => 'products', // Will be populated dynamically
			'class'       => 'wc-product-search',
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'contains_product' ),
				'action' => 'show',
			),
		);

		// Add category ID field for contains_category
		$fields['category_id'] = array(
			'type'        => 'select',
			'label'       => __( 'Category', 'woocommerce-product-addons-extra-digital' ),
			'options'     => 'product_categories', // Will be populated dynamically
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'contains_category' ),
				'action' => 'show',
			),
		);

		// Add tag ID field for contains_tag
		$fields['tag_id'] = array(
			'type'        => 'select',
			'label'       => __( 'Tag', 'woocommerce-product-addons-extra-digital' ),
			'options'     => 'product_tags', // Will be populated dynamically
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'contains_tag' ),
				'action' => 'show',
			),
		);

		// Add coupon code field for has_coupon
		$fields['coupon_code'] = array(
			'type'        => 'text',
			'label'       => __( 'Coupon Code', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter coupon code', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'has_coupon' ),
				'action' => 'show',
			),
		);

		// Add value field
		$fields['value'] = array(
			'type'        => 'text',
			'label'       => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter value to compare', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'   => 'property',
				'values'  => array( 'contains_product', 'contains_category', 'contains_tag', 'has_coupon' ),
				'action'  => 'hide',
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
		return __( 'Cart Property', 'woocommerce-product-addons-extra-digital' );
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

		// Validate specific properties
		$property = $condition['property'];

		if ( $property === 'contains_product' && ! isset( $condition['product_id'] ) ) {
			return false;
		}

		if ( $property === 'contains_category' && ! isset( $condition['category_id'] ) ) {
			return false;
		}

		if ( $property === 'contains_tag' && ! isset( $condition['tag_id'] ) ) {
			return false;
		}

		return true;
	}
}