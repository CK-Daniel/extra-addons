<?php
/**
 * Admin View: Conditional Logic Panel
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$addon_index = isset( $loop ) ? $loop : 0;
$conditional_logic = isset( $addon['conditional_logic'] ) ? $addon['conditional_logic'] : array();
$enabled = ! empty( $conditional_logic['enabled'] );
$condition_groups = isset( $conditional_logic['condition_groups'] ) ? $conditional_logic['condition_groups'] : array();
?>

<div class="conditional-logic-section">
	<div class="conditional-logic-header">
		<h4><?php esc_html_e( 'Conditional Logic', 'woocommerce-product-addons-extra-digital' ); ?></h4>
		<label>
			<input type="checkbox" 
				   class="enable-conditional-logic" 
				   name="product_addon[<?php echo esc_attr( $addon_index ); ?>][conditional_logic][enabled]" 
				   value="1" 
				   <?php checked( $enabled, true ); ?> />
			<?php esc_html_e( 'Enable conditional logic for this addon', 'woocommerce-product-addons-extra-digital' ); ?>
		</label>
		<span class="conditional-logic-help" title="<?php esc_attr_e( 'Use conditional logic to show/hide this addon or modify its behavior based on other selections', 'woocommerce-product-addons-extra-digital' ); ?>">?</span>
	</div>

	<div class="conditional-logic-container" data-addon-index="<?php echo esc_attr( $addon_index ); ?>" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
		<div class="condition-groups">
			<?php
			if ( ! empty( $condition_groups ) ) {
				foreach ( $condition_groups as $group_index => $group ) {
					wc_product_addons_render_condition_group( $addon_index, $group_index, $group );
				}
			}
			?>
		</div>
		
		<button type="button" class="button add-condition-group" data-addon-index="<?php echo esc_attr( $addon_index ); ?>">
			<?php esc_html_e( 'Add Condition Group', 'woocommerce-product-addons-extra-digital' ); ?>
		</button>
		
		<div class="conditional-logic-message info" style="display:none;">
			<p><?php esc_html_e( 'Multiple condition groups work with OR logic - if any group matches, the actions will be applied.', 'woocommerce-product-addons-extra-digital' ); ?></p>
		</div>
	</div>
</div>

<?php
/**
 * Render a condition group
 */
function wc_product_addons_render_condition_group( $addon_index, $group_index, $group ) {
	$conditions = isset( $group['conditions'] ) ? $group['conditions'] : array();
	$actions = isset( $group['actions'] ) ? $group['actions'] : array();
	?>
	<div class="condition-group" data-group-index="<?php echo esc_attr( $group_index ); ?>">
		<div class="group-header">
			<span class="group-handle dashicons dashicons-move"></span>
			<h4><?php printf( esc_html__( 'Rule %d', 'woocommerce-product-addons-extra-digital' ), $group_index + 1 ); ?></h4>
			<button type="button" class="remove-condition-group dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Remove rule', 'woocommerce-product-addons-extra-digital' ); ?>"></button>
		</div>
		
		<div class="group-content">
			<!-- Conditions -->
			<div class="conditions-section">
				<h5><?php esc_html_e( 'IF', 'woocommerce-product-addons-extra-digital' ); ?></h5>
				<div class="conditions-list">
					<?php
					if ( ! empty( $conditions ) ) {
						foreach ( $conditions as $condition_index => $condition ) {
							wc_product_addons_render_condition( $addon_index, $group_index, $condition_index, $condition );
						}
					}
					?>
				</div>
				<button type="button" class="button add-condition">
					<?php esc_html_e( 'Add Condition', 'woocommerce-product-addons-extra-digital' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'All conditions must be met (AND logic)', 'woocommerce-product-addons-extra-digital' ); ?></p>
			</div>
			
			<!-- Actions -->
			<div class="actions-section">
				<h5><?php esc_html_e( 'THEN', 'woocommerce-product-addons-extra-digital' ); ?></h5>
				<div class="actions-list">
					<?php
					if ( ! empty( $actions ) ) {
						foreach ( $actions as $action_index => $action ) {
							wc_product_addons_render_action( $addon_index, $group_index, $action_index, $action );
						}
					}
					?>
				</div>
				<button type="button" class="button add-action">
					<?php esc_html_e( 'Add Action', 'woocommerce-product-addons-extra-digital' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render a condition
 */
function wc_product_addons_render_condition( $addon_index, $group_index, $condition_index, $condition ) {
	$type = isset( $condition['type'] ) ? $condition['type'] : 'field';
	$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'equals';
	$value = isset( $condition['value'] ) ? $condition['value'] : '';
	?>
	<div class="condition-item">
		<span class="condition-handle dashicons dashicons-move"></span>
		
		<select name="product_addon[<?php echo esc_attr( $addon_index ); ?>][conditional_logic][condition_groups][<?php echo esc_attr( $group_index ); ?>][conditions][<?php echo esc_attr( $condition_index ); ?>][type]" class="condition-type">
			<option value="field" <?php selected( $type, 'field' ); ?>><?php esc_html_e( 'Field Value', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="product" <?php selected( $type, 'product' ); ?>><?php esc_html_e( 'Product Property', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="cart" <?php selected( $type, 'cart' ); ?>><?php esc_html_e( 'Cart Property', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="user" <?php selected( $type, 'user' ); ?>><?php esc_html_e( 'User Property', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="date" <?php selected( $type, 'date' ); ?>><?php esc_html_e( 'Date & Time', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="rule" <?php selected( $type, 'rule' ); ?>><?php esc_html_e( 'Other Rule State', 'woocommerce-product-addons-extra-digital' ); ?></option>
		</select>
		
		<div class="condition-fields">
			<!-- Field-specific inputs will be populated by JavaScript -->
			<?php wc_product_addons_render_condition_fields( $addon_index, $group_index, $condition_index, $condition ); ?>
		</div>
		
		<button type="button" class="remove-condition dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Remove condition', 'woocommerce-product-addons-extra-digital' ); ?>"></button>
	</div>
	<?php
}

/**
 * Render condition fields based on type
 */
function wc_product_addons_render_condition_fields( $addon_index, $group_index, $condition_index, $condition ) {
	$type = isset( $condition['type'] ) ? $condition['type'] : 'field';
	$base_name = "product_addon[{$addon_index}][conditional_logic][condition_groups][{$group_index}][conditions][{$condition_index}]";
	
	// Common fields
	?>
	<div class="field-group field-operator">
		<select name="<?php echo esc_attr( $base_name ); ?>[operator]" class="condition-operator">
			<?php
			$operators = wc_product_addons_get_operators_for_type( $type );
			foreach ( $operators as $op_value => $op_label ) {
				?>
				<option value="<?php echo esc_attr( $op_value ); ?>" <?php selected( isset( $condition['operator'] ) ? $condition['operator'] : '', $op_value ); ?>>
					<?php echo esc_html( $op_label ); ?>
				</option>
				<?php
			}
			?>
		</select>
	</div>
	
	<div class="field-group field-value">
		<input type="text" 
			   name="<?php echo esc_attr( $base_name ); ?>[value]" 
			   class="condition-value" 
			   value="<?php echo esc_attr( isset( $condition['value'] ) ? $condition['value'] : '' ); ?>" 
			   placeholder="<?php esc_attr_e( 'Value', 'woocommerce-product-addons-extra-digital' ); ?>" />
	</div>
	<?php
}

/**
 * Render an action
 */
function wc_product_addons_render_action( $addon_index, $group_index, $action_index, $action ) {
	$type = isset( $action['type'] ) ? $action['type'] : 'visibility';
	?>
	<div class="action-item">
		<span class="action-handle dashicons dashicons-move"></span>
		
		<select name="product_addon[<?php echo esc_attr( $addon_index ); ?>][conditional_logic][condition_groups][<?php echo esc_attr( $group_index ); ?>][actions][<?php echo esc_attr( $action_index ); ?>][type]" class="action-type">
			<option value="visibility" <?php selected( $type, 'visibility' ); ?>><?php esc_html_e( 'Change Visibility', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="price" <?php selected( $type, 'price' ); ?>><?php esc_html_e( 'Modify Price', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="requirement" <?php selected( $type, 'requirement' ); ?>><?php esc_html_e( 'Change Requirements', 'woocommerce-product-addons-extra-digital' ); ?></option>
			<option value="modifier" <?php selected( $type, 'modifier' ); ?>><?php esc_html_e( 'Modify Properties', 'woocommerce-product-addons-extra-digital' ); ?></option>
		</select>
		
		<div class="action-fields">
			<!-- Action-specific inputs will be populated by JavaScript -->
			<?php wc_product_addons_render_action_fields( $addon_index, $group_index, $action_index, $action ); ?>
		</div>
		
		<button type="button" class="remove-action dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Remove action', 'woocommerce-product-addons-extra-digital' ); ?>"></button>
	</div>
	<?php
}

/**
 * Render action fields based on type
 */
function wc_product_addons_render_action_fields( $addon_index, $group_index, $action_index, $action ) {
	$type = isset( $action['type'] ) ? $action['type'] : 'visibility';
	$config = isset( $action['config'] ) ? $action['config'] : array();
	$base_name = "product_addon[{$addon_index}][conditional_logic][condition_groups][{$group_index}][actions][{$action_index}][config]";
	
	switch ( $type ) {
		case 'visibility':
			?>
			<div class="field-group field-visibility-action">
				<select name="<?php echo esc_attr( $base_name ); ?>[action]">
					<option value="show" <?php selected( isset( $config['action'] ) ? $config['action'] : '', 'show' ); ?>><?php esc_html_e( 'Show', 'woocommerce-product-addons-extra-digital' ); ?></option>
					<option value="hide" <?php selected( isset( $config['action'] ) ? $config['action'] : '', 'hide' ); ?>><?php esc_html_e( 'Hide', 'woocommerce-product-addons-extra-digital' ); ?></option>
				</select>
			</div>
			<?php
			break;
			
		case 'price':
			?>
			<div class="field-group field-price-method">
				<select name="<?php echo esc_attr( $base_name ); ?>[modification][method]">
					<option value="add"><?php esc_html_e( 'Add Amount', 'woocommerce-product-addons-extra-digital' ); ?></option>
					<option value="subtract"><?php esc_html_e( 'Subtract Amount', 'woocommerce-product-addons-extra-digital' ); ?></option>
					<option value="percentage_add"><?php esc_html_e( 'Add Percentage', 'woocommerce-product-addons-extra-digital' ); ?></option>
					<option value="percentage_subtract"><?php esc_html_e( 'Subtract Percentage', 'woocommerce-product-addons-extra-digital' ); ?></option>
					<option value="set"><?php esc_html_e( 'Set To', 'woocommerce-product-addons-extra-digital' ); ?></option>
				</select>
			</div>
			<div class="field-group field-price-value">
				<input type="number" 
					   step="0.01" 
					   name="<?php echo esc_attr( $base_name ); ?>[modification][value]" 
					   placeholder="<?php esc_attr_e( 'Value', 'woocommerce-product-addons-extra-digital' ); ?>" />
			</div>
			<?php
			break;
	}
}

/**
 * Get operators for condition type
 */
function wc_product_addons_get_operators_for_type( $type ) {
	$operators = array(
		'field' => array(
			'equals' => __( 'equals', 'woocommerce-product-addons-extra-digital' ),
			'not_equals' => __( 'not equals', 'woocommerce-product-addons-extra-digital' ),
			'contains' => __( 'contains', 'woocommerce-product-addons-extra-digital' ),
			'not_contains' => __( 'does not contain', 'woocommerce-product-addons-extra-digital' ),
			'greater_than' => __( 'greater than', 'woocommerce-product-addons-extra-digital' ),
			'less_than' => __( 'less than', 'woocommerce-product-addons-extra-digital' ),
			'is_empty' => __( 'is empty', 'woocommerce-product-addons-extra-digital' ),
			'is_not_empty' => __( 'is not empty', 'woocommerce-product-addons-extra-digital' ),
		),
		'product' => array(
			'equals' => __( 'equals', 'woocommerce-product-addons-extra-digital' ),
			'not_equals' => __( 'not equals', 'woocommerce-product-addons-extra-digital' ),
			'greater_than' => __( 'greater than', 'woocommerce-product-addons-extra-digital' ),
			'less_than' => __( 'less than', 'woocommerce-product-addons-extra-digital' ),
			'in' => __( 'is in', 'woocommerce-product-addons-extra-digital' ),
			'not_in' => __( 'is not in', 'woocommerce-product-addons-extra-digital' ),
		),
		// Add more operator sets for other types
	);
	
	return isset( $operators[ $type ] ) ? $operators[ $type ] : $operators['field'];
}