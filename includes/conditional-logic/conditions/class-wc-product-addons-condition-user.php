<?php
/**
 * User Condition Class
 *
 * Handles conditions based on user properties.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Condition Class
 *
 * @class    WC_Product_Addons_Condition_User
 * @extends  WC_Product_Addons_Condition
 * @version  4.0.0
 */
class WC_Product_Addons_Condition_User extends WC_Product_Addons_Condition {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->type = 'user';
		$this->supported_operators = array(
			'equals',
			'not_equals',
			'greater_than',
			'less_than',
			'greater_than_equals',
			'less_than_equals',
			'contains',
			'not_contains',
			'in',
			'not_in',
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
		// Get user from context or current user
		if ( isset( $context['user'] ) && is_object( $context['user'] ) ) {
			$user = $context['user'];
		} else {
			$user = wp_get_current_user();
		}

		$property = isset( $condition['property'] ) ? $condition['property'] : 'role';

		switch ( $property ) {
			case 'id':
				return $user->ID;

			case 'role':
				return $user->roles;

			case 'capability':
				if ( isset( $condition['capability_name'] ) ) {
					return $user->has_cap( $condition['capability_name'] );
				}
				return false;

			case 'username':
				return $user->user_login;

			case 'email':
				return $user->user_email;

			case 'display_name':
				return $user->display_name;

			case 'is_logged_in':
				return $user->ID > 0;

			case 'registration_date':
				return $user->user_registered;

			case 'order_count':
				return $this->get_customer_order_count( $user->ID );

			case 'total_spent':
				return $this->get_customer_total_spent( $user->ID );

			case 'average_order_value':
				$order_count = $this->get_customer_order_count( $user->ID );
				if ( $order_count > 0 ) {
					return $this->get_customer_total_spent( $user->ID ) / $order_count;
				}
				return 0;

			case 'last_order_date':
				return $this->get_customer_last_order_date( $user->ID );

			case 'is_paying_customer':
				return get_user_meta( $user->ID, 'paying_customer', true ) === '1';

			case 'billing_country':
				return get_user_meta( $user->ID, 'billing_country', true );

			case 'billing_state':
				return get_user_meta( $user->ID, 'billing_state', true );

			case 'billing_city':
				return get_user_meta( $user->ID, 'billing_city', true );

			case 'billing_postcode':
				return get_user_meta( $user->ID, 'billing_postcode', true );

			case 'shipping_country':
				return get_user_meta( $user->ID, 'shipping_country', true );

			case 'shipping_state':
				return get_user_meta( $user->ID, 'shipping_state', true );

			case 'shipping_city':
				return get_user_meta( $user->ID, 'shipping_city', true );

			case 'shipping_postcode':
				return get_user_meta( $user->ID, 'shipping_postcode', true );

			case 'meta':
				if ( isset( $condition['meta_key'] ) ) {
					return get_user_meta( $user->ID, $condition['meta_key'], true );
				}
				return null;

			case 'purchased_products':
				return $this->get_customer_purchased_products( $user->ID );

			case 'purchased_categories':
				return $this->get_customer_purchased_categories( $user->ID );

			default:
				return null;
		}
	}

	/**
	 * Get customer order count
	 *
	 * @param int $user_id User ID
	 * @return int
	 */
	private function get_customer_order_count( $user_id ) {
		if ( function_exists( 'wc_get_customer_order_count' ) ) {
			return wc_get_customer_order_count( $user_id );
		}

		// Fallback
		$count = get_user_meta( $user_id, '_order_count', true );
		return $count ? intval( $count ) : 0;
	}

	/**
	 * Get customer total spent
	 *
	 * @param int $user_id User ID
	 * @return float
	 */
	private function get_customer_total_spent( $user_id ) {
		if ( function_exists( 'wc_get_customer_total_spent' ) ) {
			return floatval( wc_get_customer_total_spent( $user_id ) );
		}

		// Fallback
		$spent = get_user_meta( $user_id, '_money_spent', true );
		return $spent ? floatval( $spent ) : 0;
	}

	/**
	 * Get customer last order date
	 *
	 * @param int $user_id User ID
	 * @return string|null
	 */
	private function get_customer_last_order_date( $user_id ) {
		if ( ! $user_id ) {
			return null;
		}

		$orders = wc_get_orders( array(
			'customer' => $user_id,
			'limit'    => 1,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'ids',
		) );

		if ( ! empty( $orders ) ) {
			$order = wc_get_order( $orders[0] );
			if ( $order ) {
				return $order->get_date_created()->date( 'Y-m-d H:i:s' );
			}
		}

		return null;
	}

	/**
	 * Get products purchased by customer
	 *
	 * @param int $user_id User ID
	 * @return array
	 */
	private function get_customer_purchased_products( $user_id ) {
		if ( ! $user_id ) {
			return array();
		}

		global $wpdb;

		$product_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT woim.meta_value 
			FROM {$wpdb->prefix}woocommerce_order_items woi
			JOIN {$wpdb->prefix}woocommerce_order_itemmeta woim ON woi.order_item_id = woim.order_item_id
			JOIN {$wpdb->posts} p ON woi.order_id = p.ID
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ('wc-completed', 'wc-processing')
			AND woim.meta_key IN ('_product_id', '_variation_id')
			AND p.post_author = %d
		", $user_id ) );

		return array_map( 'intval', $product_ids );
	}

	/**
	 * Get categories of products purchased by customer
	 *
	 * @param int $user_id User ID
	 * @return array
	 */
	private function get_customer_purchased_categories( $user_id ) {
		$product_ids = $this->get_customer_purchased_products( $user_id );
		$category_ids = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$category_ids = array_merge( $category_ids, $product->get_category_ids() );
			}
		}

		return array_unique( $category_ids );
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
					'id'                   => __( 'User ID', 'woocommerce-product-addons-extra-digital' ),
					'role'                 => __( 'User Role', 'woocommerce-product-addons-extra-digital' ),
					'capability'           => __( 'User Capability', 'woocommerce-product-addons-extra-digital' ),
					'username'             => __( 'Username', 'woocommerce-product-addons-extra-digital' ),
					'email'                => __( 'Email', 'woocommerce-product-addons-extra-digital' ),
					'display_name'         => __( 'Display Name', 'woocommerce-product-addons-extra-digital' ),
					'is_logged_in'         => __( 'Is Logged In', 'woocommerce-product-addons-extra-digital' ),
					'registration_date'    => __( 'Registration Date', 'woocommerce-product-addons-extra-digital' ),
					'order_count'          => __( 'Order Count', 'woocommerce-product-addons-extra-digital' ),
					'total_spent'          => __( 'Total Spent', 'woocommerce-product-addons-extra-digital' ),
					'average_order_value'  => __( 'Average Order Value', 'woocommerce-product-addons-extra-digital' ),
					'last_order_date'      => __( 'Last Order Date', 'woocommerce-product-addons-extra-digital' ),
					'is_paying_customer'   => __( 'Is Paying Customer', 'woocommerce-product-addons-extra-digital' ),
					'billing_country'      => __( 'Billing Country', 'woocommerce-product-addons-extra-digital' ),
					'billing_state'        => __( 'Billing State', 'woocommerce-product-addons-extra-digital' ),
					'billing_city'         => __( 'Billing City', 'woocommerce-product-addons-extra-digital' ),
					'billing_postcode'     => __( 'Billing Postcode', 'woocommerce-product-addons-extra-digital' ),
					'shipping_country'     => __( 'Shipping Country', 'woocommerce-product-addons-extra-digital' ),
					'shipping_state'       => __( 'Shipping State', 'woocommerce-product-addons-extra-digital' ),
					'shipping_city'        => __( 'Shipping City', 'woocommerce-product-addons-extra-digital' ),
					'shipping_postcode'    => __( 'Shipping Postcode', 'woocommerce-product-addons-extra-digital' ),
					'purchased_products'   => __( 'Purchased Products', 'woocommerce-product-addons-extra-digital' ),
					'purchased_categories' => __( 'Purchased Categories', 'woocommerce-product-addons-extra-digital' ),
					'meta'                 => __( 'Custom Meta', 'woocommerce-product-addons-extra-digital' ),
				),
			),
			'operator' => array(
				'type'    => 'select',
				'label'   => __( 'Operator', 'woocommerce-product-addons-extra-digital' ),
				'options' => $this->get_operator_options(),
			),
		);

		// Add capability name field
		$fields['capability_name'] = array(
			'type'        => 'text',
			'label'       => __( 'Capability Name', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'e.g., manage_options', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'capability' ),
				'action' => 'show',
			),
		);

		// Add meta key field
		$fields['meta_key'] = array(
			'type'        => 'text',
			'label'       => __( 'Meta Key', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter user meta key', 'woocommerce-product-addons-extra-digital' ),
			'dependency'  => array(
				'field'  => 'property',
				'values' => array( 'meta' ),
				'action' => 'show',
			),
		);

		// Add value field with dynamic configuration
		$fields['value'] = array(
			'type'        => 'dynamic',
			'label'       => __( 'Value', 'woocommerce-product-addons-extra-digital' ),
			'placeholder' => __( 'Enter value to compare', 'woocommerce-product-addons-extra-digital' ),
			'config'      => array(
				'role' => array(
					'type'     => 'multiselect',
					'options'  => $this->get_user_roles(),
				),
				'is_logged_in' => array(
					'type'    => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'is_paying_customer' => array(
					'type'    => 'select',
					'options' => array(
						'1' => __( 'Yes', 'woocommerce-product-addons-extra-digital' ),
						'0' => __( 'No', 'woocommerce-product-addons-extra-digital' ),
					),
				),
				'billing_country' => array(
					'type'    => 'select',
					'options' => WC()->countries->get_countries(),
				),
				'shipping_country' => array(
					'type'    => 'select',
					'options' => WC()->countries->get_countries(),
				),
				'purchased_products' => array(
					'type'     => 'multiselect',
					'options'  => 'products',
					'class'    => 'wc-product-search',
				),
				'purchased_categories' => array(
					'type'     => 'multiselect',
					'options'  => 'product_categories',
				),
				'default' => array(
					'type' => 'text',
				),
			),
		);

		return $fields;
	}

	/**
	 * Get user roles for select field
	 *
	 * @return array
	 */
	private function get_user_roles() {
		global $wp_roles;

		$roles = array();
		foreach ( $wp_roles->roles as $key => $role ) {
			$roles[ $key ] = $role['name'];
		}

		return $roles;
	}

	/**
	 * Get display label for the condition
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'User Property', 'woocommerce-product-addons-extra-digital' );
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

		if ( $property === 'capability' && ! isset( $condition['capability_name'] ) ) {
			return false;
		}

		if ( $property === 'meta' && ! isset( $condition['meta_key'] ) ) {
			return false;
		}

		return true;
	}
}