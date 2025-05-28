<?php
/**
 * Conditional Logic Admin Page
 *
 * @package WC_Product_Addons/Admin/Views
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Initialize conditional logic system
$conditional_logic = WC_Product_Addons_Conditional_Logic::instance();
$groups = WC_Product_Addons_Groups::get_all_global_groups();
$products = wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) );

// Handle form submissions
if ( isset( $_POST['save_conditional_rules'] ) && wp_verify_nonce( $_POST['conditional_logic_nonce'], 'save_conditional_rules' ) ) {
	$rules = isset( $_POST['conditional_rules'] ) ? $_POST['conditional_rules'] : array();
	
	// Save rules logic would go here
	echo '<div class="notice notice-success"><p>' . __( 'Conditional logic rules saved successfully!', 'woocommerce-product-addons-extra-digital' ) . '</p></div>';
}
?>

<div class="wrap woocommerce">
	<h1><?php esc_html_e( 'Conditional Logic Rules', 'woocommerce-product-addons-extra-digital' ); ?></h1>
	
	<div class="conditional-logic-admin-header">
		<p class="description">
			<?php esc_html_e( 'Create conditional logic rules to show/hide addons, modify prices, and change requirements based on user selections. Rules can cascade - one rule can trigger another rule.', 'woocommerce-product-addons-extra-digital' ); ?>
		</p>
		
		<div class="conditional-logic-tabs">
			<nav class="nav-tab-wrapper">
				<a href="#global-rules" class="nav-tab nav-tab-active"><?php esc_html_e( 'Global Rules', 'woocommerce-product-addons-extra-digital' ); ?></a>
				<a href="#product-rules" class="nav-tab"><?php esc_html_e( 'Product Rules', 'woocommerce-product-addons-extra-digital' ); ?></a>
				<a href="#category-rules" class="nav-tab"><?php esc_html_e( 'Category Rules', 'woocommerce-product-addons-extra-digital' ); ?></a>
				<a href="#rule-builder" class="nav-tab"><?php esc_html_e( 'Rule Builder', 'woocommerce-product-addons-extra-digital' ); ?></a>
			</nav>
		</div>
	</div>

	<form method="post" action="">
		<?php wp_nonce_field( 'save_conditional_rules', 'conditional_logic_nonce' ); ?>
		
		<!-- Global Rules Tab -->
		<div id="global-rules" class="conditional-logic-tab-content">
			<div class="conditional-logic-section">
				<h2><?php esc_html_e( 'Global Conditional Rules', 'woocommerce-product-addons-extra-digital' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'These rules apply to all products that use global addon groups.', 'woocommerce-product-addons-extra-digital' ); ?>
				</p>
				
				<div class="global-rules-container">
					<div class="rules-list" id="global-rules-list">
						<!-- Rules will be loaded here via JavaScript -->
					</div>
					
					<button type="button" class="button button-secondary add-rule" data-context="global">
						<?php esc_html_e( 'Add Global Rule', 'woocommerce-product-addons-extra-digital' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Product Rules Tab -->
		<div id="product-rules" class="conditional-logic-tab-content" style="display: none;">
			<div class="conditional-logic-section">
				<h2><?php esc_html_e( 'Product-Specific Rules', 'woocommerce-product-addons-extra-digital' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Create rules that apply only to specific products.', 'woocommerce-product-addons-extra-digital' ); ?>
				</p>
				
				<div class="product-selector">
					<label for="product-select"><?php esc_html_e( 'Select Product:', 'woocommerce-product-addons-extra-digital' ); ?></label>
					<select id="product-select" class="wc-product-search" style="width: 300px;">
						<option value=""><?php esc_html_e( 'Search for a product...', 'woocommerce-product-addons-extra-digital' ); ?></option>
					</select>
				</div>
				
				<div class="product-rules-container" id="product-rules-container">
					<!-- Product-specific rules will be loaded here -->
				</div>
			</div>
		</div>

		<!-- Category Rules Tab -->
		<div id="category-rules" class="conditional-logic-tab-content" style="display: none;">
			<div class="conditional-logic-section">
				<h2><?php esc_html_e( 'Category-Based Rules', 'woocommerce-product-addons-extra-digital' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Create rules that apply to all products in specific categories.', 'woocommerce-product-addons-extra-digital' ); ?>
				</p>
				
				<div class="category-selector">
					<label for="category-select"><?php esc_html_e( 'Select Category:', 'woocommerce-product-addons-extra-digital' ); ?></label>
					<?php
					wp_dropdown_categories( array(
						'taxonomy'          => 'product_cat',
						'name'              => 'category-select',
						'id'                => 'category-select',
						'show_option_none'  => __( 'Select a category...', 'woocommerce-product-addons-extra-digital' ),
						'option_none_value' => '',
						'hide_empty'        => false,
					) );
					?>
				</div>
				
				<div class="category-rules-container" id="category-rules-container">
					<!-- Category-specific rules will be loaded here -->
				</div>
			</div>
		</div>

		<!-- Rule Builder Tab -->
		<div id="rule-builder" class="conditional-logic-tab-content" style="display: none;">
			<div class="conditional-logic-section">
				<h2><?php esc_html_e( 'Visual Rule Builder', 'woocommerce-product-addons-extra-digital' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Use the visual interface to build complex conditional logic rules with drag-and-drop functionality.', 'woocommerce-product-addons-extra-digital' ); ?>
				</p>
				
				<div class="rule-builder-interface">
					<div class="rule-builder-sidebar">
						<h3><?php esc_html_e( 'Conditions', 'woocommerce-product-addons-extra-digital' ); ?></h3>
						<div class="condition-types">
							<div class="condition-type" data-type="field">
								<strong><?php esc_html_e( 'Field Value', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Check addon field values', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="condition-type" data-type="product">
								<strong><?php esc_html_e( 'Product', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Product-based conditions', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="condition-type" data-type="cart">
								<strong><?php esc_html_e( 'Cart', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Shopping cart conditions', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="condition-type" data-type="user">
								<strong><?php esc_html_e( 'User', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'User-based conditions', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="condition-type" data-type="date">
								<strong><?php esc_html_e( 'Date/Time', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Date and time conditions', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="condition-type" data-type="rule">
								<strong><?php esc_html_e( 'Other Rule State', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Cascade based on other rules', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
						</div>
						
						<h3><?php esc_html_e( 'Actions', 'woocommerce-product-addons-extra-digital' ); ?></h3>
						<div class="action-types">
							<div class="action-type" data-type="visibility">
								<strong><?php esc_html_e( 'Show/Hide', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Control addon visibility', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="action-type" data-type="price">
								<strong><?php esc_html_e( 'Price Modification', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Modify addon prices', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="action-type" data-type="requirement">
								<strong><?php esc_html_e( 'Requirements', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Change required status', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
							<div class="action-type" data-type="modifier">
								<strong><?php esc_html_e( 'Text/Options', 'woocommerce-product-addons-extra-digital' ); ?></strong>
								<p><?php esc_html_e( 'Modify text and options', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
						</div>
					</div>
					
					<div class="rule-builder-canvas">
						<div class="canvas-header">
							<h3><?php esc_html_e( 'Rule Canvas', 'woocommerce-product-addons-extra-digital' ); ?></h3>
							<button type="button" class="button button-secondary clear-canvas">
								<?php esc_html_e( 'Clear Canvas', 'woocommerce-product-addons-extra-digital' ); ?>
							</button>
						</div>
						<div class="canvas-content" id="rule-canvas">
							<div class="canvas-drop-zone">
								<p><?php esc_html_e( 'Drag conditions and actions here to build your rule', 'woocommerce-product-addons-extra-digital' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="conditional-logic-actions">
			<button type="submit" name="save_conditional_rules" class="button button-primary">
				<?php esc_html_e( 'Save All Rules', 'woocommerce-product-addons-extra-digital' ); ?>
			</button>
			
			<button type="button" class="button button-secondary test-rules">
				<?php esc_html_e( 'Test Rules', 'woocommerce-product-addons-extra-digital' ); ?>
			</button>
			
			<button type="button" class="button button-secondary export-rules">
				<?php esc_html_e( 'Export Rules', 'woocommerce-product-addons-extra-digital' ); ?>
			</button>
			
			<button type="button" class="button button-secondary import-rules">
				<?php esc_html_e( 'Import Rules', 'woocommerce-product-addons-extra-digital' ); ?>
			</button>
		</div>
	</form>
</div>

<!-- Rule Template for JavaScript -->
<script type="text/template" id="rule-template">
	<div class="conditional-rule" data-rule-id="{{rule_id}}">
		<div class="rule-header">
			<h4><?php esc_html_e( 'Rule', 'woocommerce-product-addons-extra-digital' ); ?> #{{rule_id}}</h4>
			<div class="rule-actions">
				<span class="rule-toggle"><?php esc_html_e( 'Enabled', 'woocommerce-product-addons-extra-digital' ); ?></span>
				<button type="button" class="button-link rule-duplicate"><?php esc_html_e( 'Duplicate', 'woocommerce-product-addons-extra-digital' ); ?></button>
				<button type="button" class="button-link rule-delete"><?php esc_html_e( 'Delete', 'woocommerce-product-addons-extra-digital' ); ?></button>
			</div>
		</div>
		
		<div class="rule-content">
			<div class="rule-section">
				<label><?php esc_html_e( 'Priority', 'woocommerce-product-addons-extra-digital' ); ?></label>
				<input type="number" name="conditional_rules[{{rule_id}}][priority]" value="10" min="1" max="100" />
				<p class="description"><?php esc_html_e( 'Lower numbers execute first. Use for cascading rules.', 'woocommerce-product-addons-extra-digital' ); ?></p>
			</div>
			
			<div class="rule-section">
				<label><?php esc_html_e( 'Target Addon', 'woocommerce-product-addons-extra-digital' ); ?></label>
				<select name="conditional_rules[{{rule_id}}][target_addon]" class="addon-selector">
					<option value=""><?php esc_html_e( 'Select addon...', 'woocommerce-product-addons-extra-digital' ); ?></option>
				</select>
			</div>
			
			<div class="rule-section conditions-section">
				<h5><?php esc_html_e( 'Conditions', 'woocommerce-product-addons-extra-digital' ); ?></h5>
				<div class="conditions-container">
					<!-- Conditions will be added here -->
				</div>
				<button type="button" class="button button-secondary add-condition">
					<?php esc_html_e( 'Add Condition', 'woocommerce-product-addons-extra-digital' ); ?>
				</button>
			</div>
			
			<div class="rule-section actions-section">
				<h5><?php esc_html_e( 'Actions', 'woocommerce-product-addons-extra-digital' ); ?></h5>
				<div class="actions-container">
					<!-- Actions will be added here -->
				</div>
				<button type="button" class="button button-secondary add-action">
					<?php esc_html_e( 'Add Action', 'woocommerce-product-addons-extra-digital' ); ?>
				</button>
			</div>
		</div>
	</div>
</script>

<style>
.conditional-logic-admin-header {
	margin: 20px 0;
}

.conditional-logic-tabs .nav-tab-wrapper {
	margin-bottom: 20px;
}

.conditional-logic-tab-content {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	border-top: none;
}

.conditional-logic-section {
	margin-bottom: 30px;
}

.conditional-rule {
	background: #f9f9f9;
	border: 1px solid #ddd;
	margin-bottom: 20px;
	border-radius: 4px;
}

.rule-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: #f1f1f1;
	padding: 10px 15px;
	border-bottom: 1px solid #ddd;
}

.rule-content {
	padding: 15px;
}

.rule-section {
	margin-bottom: 20px;
}

.rule-section label {
	display: block;
	font-weight: 600;
	margin-bottom: 5px;
}

.rule-builder-interface {
	display: flex;
	gap: 20px;
	min-height: 500px;
}

.rule-builder-sidebar {
	width: 300px;
	background: #f9f9f9;
	padding: 15px;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.rule-builder-canvas {
	flex: 1;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.canvas-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 15px;
	border-bottom: 1px solid #ddd;
	background: #f9f9f9;
}

.canvas-content {
	padding: 20px;
	min-height: 400px;
}

.canvas-drop-zone {
	border: 2px dashed #ddd;
	border-radius: 4px;
	padding: 40px;
	text-align: center;
	color: #666;
	min-height: 300px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.condition-type, .action-type {
	background: #fff;
	border: 1px solid #ddd;
	padding: 10px;
	margin-bottom: 10px;
	border-radius: 4px;
	cursor: move;
}

.condition-type:hover, .action-type:hover {
	border-color: #0073aa;
	background: #f0f8ff;
}

.conditional-logic-actions {
	background: #f9f9f9;
	padding: 15px;
	border-top: 1px solid #ddd;
	margin-top: 20px;
}

.conditional-logic-actions .button {
	margin-right: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		var target = $(this).attr('href');
		
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		
		$('.conditional-logic-tab-content').hide();
		$(target).show();
	});
	
	// Initialize conditional logic admin interface
	if (typeof WC_Product_Addons_Conditional_Logic_Admin !== 'undefined') {
		WC_Product_Addons_Conditional_Logic_Admin.init();
	}
});
</script>