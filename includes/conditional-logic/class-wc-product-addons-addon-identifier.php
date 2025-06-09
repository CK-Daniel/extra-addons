<?php
/**
 * WooCommerce Product Add-ons Addon Identifier
 *
 * Provides consistent addon identification across frontend and backend
 * to ensure proper state synchronization.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Addon Identifier Class
 *
 * @class   WC_Product_Addons_Addon_Identifier
 * @version 4.1.0
 */
class WC_Product_Addons_Addon_Identifier {

	/**
	 * Generate a unique identifier for an addon
	 *
	 * @param array  $addon      Addon data
	 * @param int    $product_id Product ID
	 * @param string $scope      Scope (product, global, category)
	 * @return string Unique identifier
	 */
	public static function generate_identifier( $addon, $product_id = 0, $scope = 'product' ) {
		$components = array();
		
		// Base name
		$name = isset( $addon['name'] ) ? sanitize_title( $addon['name'] ) : '';
		if ( empty( $name ) && isset( $addon['field_name'] ) ) {
			$name = sanitize_title( $addon['field_name'] );
		}
		
		if ( ! empty( $name ) ) {
			$components[] = $name;
		}
		
		// Add scope
		$components[] = $scope;
		
		// Add ID based on scope
		if ( $scope === 'product' && $product_id > 0 ) {
			$components[] = $product_id;
		} elseif ( isset( $addon['id'] ) ) {
			$components[] = $addon['id'];
		}
		
		// Create identifier
		$identifier = implode( '_', array_filter( $components ) );
		
		// Ensure uniqueness with hash if needed
		if ( empty( $identifier ) || strlen( $identifier ) < 3 ) {
			$identifier = 'addon_' . substr( md5( json_encode( $addon ) ), 0, 8 );
		}
		
		return $identifier;
	}

	/**
	 * Parse an addon identifier
	 *
	 * @param string $identifier Addon identifier
	 * @return array Parsed components
	 */
	public static function parse_identifier( $identifier ) {
		$parts = explode( '_', $identifier );
		$result = array(
			'name'       => '',
			'scope'      => 'product',
			'scope_id'   => 0,
			'identifier' => $identifier,
		);
		
		if ( count( $parts ) >= 2 ) {
			// Last part might be ID
			$last_part = end( $parts );
			if ( is_numeric( $last_part ) ) {
				$result['scope_id'] = intval( $last_part );
				array_pop( $parts );
			}
			
			// Second to last might be scope
			if ( count( $parts ) >= 2 ) {
				$scope_part = end( $parts );
				if ( in_array( $scope_part, array( 'product', 'global', 'category', 'tag' ), true ) ) {
					$result['scope'] = $scope_part;
					array_pop( $parts );
				}
			}
			
			// Remaining parts are the name
			$result['name'] = implode( '_', $parts );
		} else {
			$result['name'] = $identifier;
		}
		
		return $result;
	}

	/**
	 * Get addon by identifier
	 *
	 * @param string $identifier Addon identifier
	 * @param array  $addons     Array of addons to search
	 * @return array|null Found addon or null
	 */
	public static function find_addon_by_identifier( $identifier, $addons ) {
		foreach ( $addons as $addon ) {
			$addon_id = self::get_addon_identifier( $addon );
			if ( $addon_id === $identifier ) {
				return $addon;
			}
			
			// Also check alternative matches
			$parsed = self::parse_identifier( $identifier );
			$addon_name = isset( $addon['name'] ) ? sanitize_title( $addon['name'] ) : '';
			
			if ( $parsed['name'] === $addon_name ) {
				return $addon;
			}
		}
		
		return null;
	}

	/**
	 * Get addon identifier from various sources
	 *
	 * @param mixed $addon Addon data (array or object)
	 * @return string Addon identifier
	 */
	public static function get_addon_identifier( $addon ) {
		// If already has identifier
		if ( is_array( $addon ) && isset( $addon['identifier'] ) ) {
			return $addon['identifier'];
		}
		
		// Generate from addon data
		if ( is_array( $addon ) ) {
			$product_id = isset( $addon['product_id'] ) ? $addon['product_id'] : 0;
			$scope = isset( $addon['scope'] ) ? $addon['scope'] : 'product';
			return self::generate_identifier( $addon, $product_id, $scope );
		}
		
		// Handle object
		if ( is_object( $addon ) ) {
			$addon_array = (array) $addon;
			return self::get_addon_identifier( $addon_array );
		}
		
		return '';
	}

	/**
	 * Normalize addon name for comparison
	 *
	 * @param string $name Addon name
	 * @return string Normalized name
	 */
	public static function normalize_name( $name ) {
		// Remove special characters and convert to lowercase
		$normalized = strtolower( $name );
		$normalized = preg_replace( '/[^a-z0-9]+/', '_', $normalized );
		$normalized = trim( $normalized, '_' );
		
		return $normalized;
	}

	/**
	 * Match addon names flexibly
	 *
	 * @param string $name1 First addon name
	 * @param string $name2 Second addon name
	 * @return bool Whether names match
	 */
	public static function names_match( $name1, $name2 ) {
		// Exact match
		if ( $name1 === $name2 ) {
			return true;
		}
		
		// Normalized match
		$norm1 = self::normalize_name( $name1 );
		$norm2 = self::normalize_name( $name2 );
		
		if ( $norm1 === $norm2 ) {
			return true;
		}
		
		// Check if one contains the other (for scope suffixes)
		if ( strpos( $name1, $name2 ) !== false || strpos( $name2, $name1 ) !== false ) {
			return true;
		}
		
		// Check without scope suffixes
		$patterns = array( '/_product_\d+$/', '/_global_\d+$/', '/_category_\d+$/' );
		foreach ( $patterns as $pattern ) {
			$clean1 = preg_replace( $pattern, '', $name1 );
			$clean2 = preg_replace( $pattern, '', $name2 );
			if ( $clean1 === $clean2 ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Get field name from addon
	 *
	 * @param array $addon Addon data
	 * @return string Field name
	 */
	public static function get_field_name( $addon ) {
		// Priority order for field name
		$candidates = array(
			'field_name',
			'field-name',
			'name',
			'label',
			'title',
		);
		
		foreach ( $candidates as $key ) {
			if ( isset( $addon[ $key ] ) && ! empty( $addon[ $key ] ) ) {
				return $addon[ $key ];
			}
		}
		
		// Generate from type if available
		if ( isset( $addon['type'] ) ) {
			return 'addon_' . $addon['type'] . '_' . uniqid();
		}
		
		return 'addon_' . uniqid();
	}

	/**
	 * Get HTML data attributes for addon container
	 *
	 * @param array $addon      Addon data
	 * @param int   $product_id Product ID
	 * @return string HTML data attributes
	 */
	public static function get_data_attributes( $addon, $product_id = 0 ) {
		$identifier = self::generate_identifier( $addon, $product_id );
		$field_name = self::get_field_name( $addon );
		$normalized_name = self::normalize_name( isset( $addon['name'] ) ? $addon['name'] : '' );
		
		$attributes = array(
			'data-addon-identifier' => esc_attr( $identifier ),
			'data-addon-field-name' => esc_attr( $field_name ),
			'data-addon-name' => esc_attr( $normalized_name ),
			'data-addon-type' => esc_attr( isset( $addon['type'] ) ? $addon['type'] : '' ),
		);
		
		if ( isset( $addon['required'] ) && $addon['required'] ) {
			$attributes['data-addon-required'] = '1';
		}
		
		$html = '';
		foreach ( $attributes as $key => $value ) {
			$html .= sprintf( ' %s="%s"', $key, $value );
		}
		
		return $html;
	}

	/**
	 * Extract addon identifier from HTML element
	 *
	 * @param string $element_html HTML element or jQuery selector
	 * @return string Addon identifier
	 */
	public static function extract_from_element( $element_html ) {
		// Try to extract data-addon-identifier
		if ( preg_match( '/data-addon-identifier=["\']([^"\']+)["\']/', $element_html, $matches ) ) {
			return $matches[1];
		}
		
		// Try to extract from ID or class
		if ( preg_match( '/id=["\']addon-([^"\']+)["\']/', $element_html, $matches ) ) {
			return $matches[1];
		}
		
		if ( preg_match( '/class=["\'][^"\']*addon-([^\s"\']+)/', $element_html, $matches ) ) {
			return $matches[1];
		}
		
		return '';
	}
}