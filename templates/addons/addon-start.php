<?php
/**
 * The Template for displaying start of field.
 *
 * @version 3.0.0
 */
global $product;

$price_display          = '';
$title_format           = ! empty( $addon['title_format'] ) ? $addon['title_format'] : '';
$addon_type             = ! empty( $addon['type'] ) ? $addon['type'] : '';
$addon_price            = ! empty( $addon['price'] ) ? $addon['price'] : '';
$addon_price_type       = ! empty( $addon['price_type'] ) ? $addon['price_type'] : '';
$adjust_price           = ! empty( $addon['adjust_price'] ) ? $addon['adjust_price'] : '';
$required               = ! empty( $addon['required'] ) ? $addon['required'] : '';
$has_per_person_pricing = ( isset( $addon['wc_booking_person_qty_multiplier'] ) && 1 === $addon['wc_booking_person_qty_multiplier'] ) ? true : false;
$has_per_block_pricing  = ( ( isset( $addon['wc_booking_block_qty_multiplier'] ) && 1 === $addon['wc_booking_block_qty_multiplier'] ) || ( isset( $addon['wc_accommodation_booking_block_qty_multiplier'] ) && 1 === $addon['wc_accommodation_booking_block_qty_multiplier'] ) ) ? true : false;
$product_title          = WC_Product_Addons_Helper::is_wc_gte( '3.0' ) ? $product->get_name() : $product->post_title;

// Generate unified addon identifier
if ( class_exists( 'WC_Product_Addons_Addon_Identifier' ) ) {
	$product_id = $product->get_id();
	$scope = isset( $addon['global_addon_id'] ) ? 'global' : 'product';
	$addon_identifier = WC_Product_Addons_Addon_Identifier::generate_identifier( $addon, $product_id, $scope );
	$field_name = WC_Product_Addons_Addon_Identifier::get_field_name( $addon );
	
	// Generate multiple identifiers for better matching across scopes
	$identifiers = array();
	
	// 1. Base identifier (current product context)
	$identifiers['primary'] = $addon_identifier;
	
	// 2. Global reference identifier if this is from a global addon
	if ( isset( $addon['global_addon_id'] ) && isset( $addon['id'] ) ) {
		$identifiers['global'] = sanitize_title( $name ) . '_global_' . $addon['id'];
	}
	
	// 3. Category reference identifier (for category rules)
	$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
	if ( ! empty( $product_categories ) ) {
		$identifiers['category'] = sanitize_title( $name ) . '_category_' . $product_categories[0];
	}
	
	// 4. Product-specific identifier (always include for product rules)
	$identifiers['product'] = sanitize_title( $name ) . '_product_' . $product_id;
	
	// 5. Base name only (for most flexible matching)
	$identifiers['base'] = sanitize_title( $name );
	
} else {
	// Fallback
	$addon_identifier = isset( $addon['field_name'] ) ? $addon['field_name'] : sanitize_title( $name );
	$field_name = $addon_identifier;
}

if ( 'checkbox' !== $addon_type && 'multiple_choice' !== $addon_type && 'custom_price' !== $addon_type ) {
	$price_prefix = 0 < $addon_price ? '+' : '';
	$price_type   = $addon_price_type;
	$adjust_price = $adjust_price;
	$price_raw    = apply_filters( 'woocommerce_product_addons_price_raw', $addon_price, $addon );
	$required     = '1' == $required;

	if ( 'percentage_based' === $price_type ) {
		$price_display = apply_filters( 'woocommerce_product_addons_price',
			'1' == $adjust_price && $price_raw ? '(' . $price_prefix . $price_raw . '%)' : '',
			$addon,
			0,
			$addon_type
		);
	} else {
		$price_display = apply_filters( 'woocommerce_product_addons_price',
			'1' == $adjust_price && $price_raw ? '(' . $price_prefix . wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) ) . ')' : '',
			$addon,
			0,
			$addon_type
		);
	}
}



?>

<div class="wc-pao-addon-container container-<?=$addon['display']?> <?php echo $required ? 'wc-pao-required-addon' : ''; ?> wc-pao-addon wc-pao-addon-<?php echo sanitize_title( $name ); ?>" 
     data-product-name="<?php echo esc_attr( $product_title ); ?>"
     data-addon-identifier="<?php echo esc_attr( $addon_identifier ); ?>"
     data-addon-field-name="<?php echo esc_attr( $field_name ); ?>"
     data-addon-id="<?php echo esc_attr( $addon['field_name'] ?? sanitize_title( $name ) ); ?>"
     data-addon-name="<?php echo esc_attr( $name ); ?>"
     data-addon-base-name="<?php echo esc_attr( strtolower( preg_replace( '/[^a-z0-9]+/i', '_', $name ) ) ); ?>"
     data-addon-type="<?php echo esc_attr( $addon_type ); ?>"
     data-addon-required="<?php echo $required ? '1' : '0'; ?>"
     <?php if ( isset( $addon['global_addon_id'] ) ) : ?>
     data-addon-global-id="<?php echo esc_attr( $addon['global_addon_id'] ); ?>"
     data-addon-scope="global"
     <?php else : ?>
     data-addon-scope="product"
     <?php endif; ?>
     <?php if ( isset( $addon['id'] ) ) : ?>
     data-addon-database-id="<?php echo esc_attr( $addon['id'] ); ?>"
     <?php endif; ?>
     <?php if ( isset( $identifiers ) && is_array( $identifiers ) ) : ?>
     <?php foreach ( $identifiers as $scope_type => $scope_identifier ) : ?>
     data-addon-<?php echo esc_attr( $scope_type ); ?>-identifier="<?php echo esc_attr( $scope_identifier ); ?>"
     <?php endforeach; ?>
     <?php elseif ( isset( $global_identifier ) ) : ?>
     data-addon-global-identifier="<?php echo esc_attr( $global_identifier ); ?>"
     <?php endif; ?>>

	<?php 
	do_action( 'wc_product_addon_start', $addon );
	
	// Inject conditional logic data
	do_action( 'wc_product_addon_conditional_logic_data', $addon, $name );
	?>

	<?php
	if ( $name ) {
		if ( 'heading' === $addon_type ) {
		?>
			<h2 class="wc-pao-addon-heading"><?php echo wptexturize( $name ); ?></h2>
		<?php
		} else {
			switch ( $title_format ) {
				case 'heading':
					?>
					<h2 class="wc-pao-addon-name" data-addon-name="<?php echo esc_attr( wptexturize( $name ) ); ?>" data-has-per-person-pricing="<?php echo esc_attr( $has_per_person_pricing ); ?>" data-has-per-block-pricing="<?php echo esc_attr( $has_per_block_pricing ); ?>"><?php echo wptexturize( $name ); ?> <?php echo $required ? '<em class="required" title="' . __( 'Required field', 'woocommerce-product-addons-extra-digital' ) . '">*</em>&nbsp;' : ''; ?><?php echo wp_kses_post( $price_display ); ?></h2>
					<?php
					break;
				case 'hide':
					?>
					<label class="wc-pao-addon-name" data-addon-name="<?php echo esc_attr( wptexturize( $name ) ); ?>" data-has-per-person-pricing="<?php echo esc_attr( $has_per_person_pricing ); ?>" data-has-per-block-pricing="<?php echo esc_attr( $has_per_block_pricing ); ?>" style="display:none;"></label>
					<?php
					break;
				case 'label':
				default:
					?>
					<?php if($addon['display'] == "images") : ?>
					<div class="images-addon-title"><?=wptexturize( $name )?></div>
					<?php endif; ?>
					<label for="<?php echo 'addon-' . esc_attr( wptexturize( $addon['field_name'] ) ); ?>" class="wc-pao-addon-name" data-addon-name="<?php echo esc_attr( wptexturize( $name ) ); ?>" data-has-per-person-pricing="<?php echo esc_attr( $has_per_person_pricing ); ?>" data-has-per-block-pricing="<?php echo esc_attr( $has_per_block_pricing ); ?>"><?php echo wptexturize( $name ); ?> <?php echo $required ? '<em class="required" title="' . __( 'Required field', 'woocommerce-product-addons-extra-digital' ) . '">*</em>&nbsp;' : ''; ?><?php echo wp_kses_post( $price_display ); ?></label>
					<?php
					break;
			}
		}
	}
	?>
	<?php
	if ( $display_description ) {
		?>
		<?php echo '<div class="wc-pao-addon-description">' . wpautop( wptexturize( $description ) ) . '</div>'; ?>
	<?php }; ?>

	<?php do_action( 'wc_product_addon_options', $addon ); ?>
