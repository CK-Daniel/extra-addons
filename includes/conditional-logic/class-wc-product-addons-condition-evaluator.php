<?php
/**
 * WooCommerce Product Add-ons Condition Evaluator
 *
 * Handles the evaluation of conditional logic rules.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Condition Evaluator Class
 *
 * @class   WC_Product_Addons_Condition_Evaluator
 * @version 4.0.0
 */
class WC_Product_Addons_Condition_Evaluator {

	/**
	 * Operators mapping
	 *
	 * @var array
	 */
	protected static $operators = array(
		// Comparison operators
		'equals'              => '==',
		'not_equals'          => '!=',
		'greater_than'        => '>',
		'less_than'           => '<',
		'greater_than_equals' => '>=',
		'less_than_equals'    => '<=',
		
		// String operators
		'contains'            => 'contains',
		'not_contains'        => 'not_contains',
		'starts_with'         => 'starts_with',
		'ends_with'           => 'ends_with',
		'is_empty'            => 'is_empty',
		'is_not_empty'        => 'is_not_empty',
		
		// Array operators
		'in'                  => 'in_array',
		'not_in'              => 'not_in_array',
		'all_in'              => 'all_in_array',
		'any_in'              => 'any_in_array',
		
		// Special operators
		'between'             => 'between',
		'not_between'         => 'not_between',
		'matches'             => 'regex_match',
		'not_matches'         => 'not_regex_match',
	);

	/**
	 * Evaluate a condition group
	 *
	 * @param array $conditions Conditions to evaluate
	 * @param array $context    Evaluation context
	 * @param string $logic     Logic type (all, any, custom)
	 * @return bool
	 */
	public static function evaluate_group( $conditions, $context, $logic = 'all' ) {
		if ( empty( $conditions ) ) {
			return true;
		}

		$results = array();

		foreach ( $conditions as $condition ) {
			$results[] = self::evaluate_single( $condition, $context );
		}

		switch ( $logic ) {
			case 'all':
				return ! in_array( false, $results, true );
			
			case 'any':
				return in_array( true, $results, true );
			
			case 'custom':
				// TODO: Implement custom logic expressions
				return true;
			
			default:
				return true;
		}
	}

	/**
	 * Evaluate a single condition
	 *
	 * @param array $condition Condition to evaluate
	 * @param array $context   Evaluation context
	 * @return bool
	 */
	public static function evaluate_single( $condition, $context ) {
		$value = self::get_value_from_context( $condition['target'], $context );
		$compare_value = isset( $condition['value'] ) ? $condition['value'] : null;
		$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'equals';

		return self::compare( $value, $compare_value, $operator );
	}

	/**
	 * Get value from context based on target path
	 *
	 * @param array|string $target  Target specification
	 * @param array        $context Evaluation context
	 * @return mixed
	 */
	protected static function get_value_from_context( $target, $context ) {
		if ( is_string( $target ) ) {
			// Simple string path like "user.role"
			$parts = explode( '.', $target );
			$value = $context;

			foreach ( $parts as $part ) {
				if ( is_array( $value ) && isset( $value[ $part ] ) ) {
					$value = $value[ $part ];
				} elseif ( is_object( $value ) ) {
					if ( method_exists( $value, 'get_' . $part ) ) {
						$method = 'get_' . $part;
						$value = $value->$method();
					} elseif ( property_exists( $value, $part ) ) {
						$value = $value->$part;
					} else {
						return null;
					}
				} else {
					return null;
				}
			}

			return $value;
		}

		if ( is_array( $target ) && isset( $target['type'] ) ) {
			// Complex target specification
			switch ( $target['type'] ) {
				case 'field':
					return self::get_field_value( $target, $context );
				
				case 'product':
					return self::get_product_value( $target, $context );
				
				case 'cart':
					return self::get_cart_value( $target, $context );
				
				case 'user':
					return self::get_user_value( $target, $context );
				
				default:
					return null;
			}
		}

		return null;
	}

	/**
	 * Get field value from context
	 *
	 * @param array $target  Target specification
	 * @param array $context Evaluation context
	 * @return mixed
	 */
	protected static function get_field_value( $target, $context ) {
		if ( ! isset( $context['selections'] ) || ! isset( $target['addon'] ) ) {
			return null;
		}

		$addon_name = $target['addon'];
		$field_property = isset( $target['field'] ) ? $target['field'] : 'selected_value';

		if ( isset( $context['selections'][ $addon_name ] ) ) {
			$selection = $context['selections'][ $addon_name ];

			switch ( $field_property ) {
				case 'selected_value':
					return is_array( $selection ) ? $selection['value'] : $selection;
				
				case 'selected_label':
					return is_array( $selection ) ? $selection['label'] : $selection;
				
				case 'selected_price':
					return is_array( $selection ) ? $selection['price'] : 0;
				
				case 'is_selected':
					return ! empty( $selection );
				
				default:
					return isset( $selection[ $field_property ] ) ? $selection[ $field_property ] : null;
			}
		}

		return null;
	}

	/**
	 * Get product value from context
	 *
	 * @param array $target  Target specification
	 * @param array $context Evaluation context
	 * @return mixed
	 */
	protected static function get_product_value( $target, $context ) {
		if ( ! isset( $context['product'] ) || ! is_object( $context['product'] ) ) {
			return null;
		}

		$product = $context['product'];
		$property = isset( $target['property'] ) ? $target['property'] : 'price';

		switch ( $property ) {
			case 'price':
				return $product->get_price();
			
			case 'regular_price':
				return $product->get_regular_price();
			
			case 'sale_price':
				return $product->get_sale_price();
			
			case 'stock_quantity':
				return $product->get_stock_quantity();
			
			case 'type':
				return $product->get_type();
			
			case 'category_ids':
				return $product->get_category_ids();
			
			case 'tag_ids':
				return $product->get_tag_ids();
			
			case 'is_on_sale':
				return $product->is_on_sale();
			
			case 'is_in_stock':
				return $product->is_in_stock();
			
			default:
				if ( method_exists( $product, 'get_' . $property ) ) {
					$method = 'get_' . $property;
					return $product->$method();
				}
				return null;
		}
	}

	/**
	 * Get cart value from context
	 *
	 * @param array $target  Target specification
	 * @param array $context Evaluation context
	 * @return mixed
	 */
	protected static function get_cart_value( $target, $context ) {
		if ( ! isset( $context['cart'] ) || ! is_object( $context['cart'] ) ) {
			return null;
		}

		$cart = $context['cart'];
		$property = isset( $target['property'] ) ? $target['property'] : 'total';

		switch ( $property ) {
			case 'total':
				return $cart->get_cart_contents_total();
			
			case 'subtotal':
				return $cart->get_subtotal();
			
			case 'item_count':
				return $cart->get_cart_contents_count();
			
			case 'weight':
				return $cart->get_cart_contents_weight();
			
			case 'coupon_codes':
				return $cart->get_applied_coupons();
			
			case 'contains_product':
				if ( isset( $target['product_id'] ) ) {
					foreach ( $cart->get_cart() as $cart_item ) {
						if ( $cart_item['product_id'] == $target['product_id'] ) {
							return true;
						}
					}
				}
				return false;
			
			default:
				return null;
		}
	}

	/**
	 * Get user value from context
	 *
	 * @param array $target  Target specification
	 * @param array $context Evaluation context
	 * @return mixed
	 */
	protected static function get_user_value( $target, $context ) {
		if ( ! isset( $context['user'] ) || ! is_object( $context['user'] ) ) {
			return null;
		}

		$user = $context['user'];
		$property = isset( $target['property'] ) ? $target['property'] : 'role';

		switch ( $property ) {
			case 'role':
				return $user->roles;
			
			case 'id':
				return $user->ID;
			
			case 'email':
				return $user->user_email;
			
			case 'is_logged_in':
				return $user->ID > 0;
			
			case 'order_count':
				return wc_get_customer_order_count( $user->ID );
			
			case 'total_spent':
				return wc_get_customer_total_spent( $user->ID );
			
			case 'meta':
				if ( isset( $target['meta_key'] ) ) {
					return get_user_meta( $user->ID, $target['meta_key'], true );
				}
				return null;
			
			default:
				if ( property_exists( $user, $property ) ) {
					return $user->$property;
				}
				return null;
		}
	}

	/**
	 * Compare values based on operator
	 *
	 * @param mixed  $value         Value to compare
	 * @param mixed  $compare_value Value to compare against
	 * @param string $operator      Comparison operator
	 * @return bool
	 */
	protected static function compare( $value, $compare_value, $operator ) {
		switch ( $operator ) {
			case 'equals':
				return $value == $compare_value;
			
			case 'not_equals':
				return $value != $compare_value;
			
			case 'greater_than':
				return is_numeric( $value ) && is_numeric( $compare_value ) && $value > $compare_value;
			
			case 'less_than':
				return is_numeric( $value ) && is_numeric( $compare_value ) && $value < $compare_value;
			
			case 'greater_than_equals':
				return is_numeric( $value ) && is_numeric( $compare_value ) && $value >= $compare_value;
			
			case 'less_than_equals':
				return is_numeric( $value ) && is_numeric( $compare_value ) && $value <= $compare_value;
			
			case 'contains':
				return strpos( (string) $value, (string) $compare_value ) !== false;
			
			case 'not_contains':
				return strpos( (string) $value, (string) $compare_value ) === false;
			
			case 'starts_with':
				return strpos( (string) $value, (string) $compare_value ) === 0;
			
			case 'ends_with':
				$length = strlen( (string) $compare_value );
				return $length === 0 || substr( (string) $value, -$length ) === (string) $compare_value;
			
			case 'is_empty':
				return empty( $value );
			
			case 'is_not_empty':
				return ! empty( $value );
			
			case 'in':
				if ( ! is_array( $compare_value ) ) {
					$compare_value = array( $compare_value );
				}
				return in_array( $value, $compare_value );
			
			case 'not_in':
				if ( ! is_array( $compare_value ) ) {
					$compare_value = array( $compare_value );
				}
				return ! in_array( $value, $compare_value );
			
			case 'between':
				if ( is_array( $compare_value ) && count( $compare_value ) >= 2 ) {
					return is_numeric( $value ) && $value >= $compare_value[0] && $value <= $compare_value[1];
				}
				return false;
			
			case 'not_between':
				if ( is_array( $compare_value ) && count( $compare_value ) >= 2 ) {
					return is_numeric( $value ) && ( $value < $compare_value[0] || $value > $compare_value[1] );
				}
				return false;
			
			case 'matches':
				return preg_match( '/' . $compare_value . '/', (string) $value ) === 1;
			
			case 'not_matches':
				return preg_match( '/' . $compare_value . '/', (string) $value ) === 0;
			
			default:
				return apply_filters( 'woocommerce_product_addons_condition_compare', false, $value, $compare_value, $operator );
		}
	}
}