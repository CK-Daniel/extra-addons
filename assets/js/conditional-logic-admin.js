/**
 * WooCommerce Product Add-ons Extra Digital - Conditional Logic Admin
 *
 * Admin JavaScript for the conditional logic builder interface.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

(function($) {
	'use strict';

	/**
	 * Conditional Logic Builder
	 */
	var WC_Product_Addons_Conditional_Logic_Admin = {

		/**
		 * Initialize
		 */
		init: function() {
			this.container = $('#product_addons_data');
			this.template = this.getTemplate();
			
			if (this.container.length === 0) {
				return;
			}

			this.bindEvents();
			this.initializeExistingRules();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			var self = this;

			// Enable/disable conditional logic
			this.container.on('change', '.enable-conditional-logic', function() {
				self.toggleConditionalLogic($(this));
			});

			// Add condition group
			this.container.on('click', '.add-condition-group', function(e) {
				e.preventDefault();
				self.addConditionGroup($(this));
			});

			// Add condition
			this.container.on('click', '.add-condition', function(e) {
				e.preventDefault();
				self.addCondition($(this));
			});

			// Add action
			this.container.on('click', '.add-action', function(e) {
				e.preventDefault();
				self.addAction($(this));
			});

			// Remove condition group
			this.container.on('click', '.remove-condition-group', function(e) {
				e.preventDefault();
				self.removeConditionGroup($(this));
			});

			// Remove condition
			this.container.on('click', '.remove-condition', function(e) {
				e.preventDefault();
				self.removeCondition($(this));
			});

			// Remove action
			this.container.on('click', '.remove-action', function(e) {
				e.preventDefault();
				self.removeAction($(this));
			});

			// Change condition type
			this.container.on('change', '.condition-type', function() {
				self.updateConditionFields($(this));
			});

			// Change action type
			this.container.on('change', '.action-type', function() {
				self.updateActionFields($(this));
			});

			// Change property fields
			this.container.on('change', '.condition-property', function() {
				self.updatePropertyFields($(this));
			});

			// Make conditions sortable
			this.initializeSortable();
		},

		/**
		 * Initialize sortable
		 */
		initializeSortable: function() {
			$('.condition-groups').sortable({
				handle: '.group-handle',
				placeholder: 'sortable-placeholder',
				update: function() {
					// Trigger change event to mark form as dirty
					$(this).find('input').first().trigger('change');
				}
			});

			$('.conditions-list').sortable({
				handle: '.condition-handle',
				placeholder: 'sortable-placeholder'
			});

			$('.actions-list').sortable({
				handle: '.action-handle',
				placeholder: 'sortable-placeholder'
			});
		},

		/**
		 * Toggle conditional logic
		 */
		toggleConditionalLogic: function(checkbox) {
			var addon = checkbox.closest('.woocommerce_product_addon');
			var container = addon.find('.conditional-logic-container');

			if (checkbox.is(':checked')) {
				container.slideDown();
				if (container.find('.condition-group').length === 0) {
					this.addConditionGroup(container.find('.add-condition-group'));
				}
			} else {
				container.slideUp();
			}
		},

		/**
		 * Add condition group
		 */
		addConditionGroup: function(button) {
			var container = button.closest('.conditional-logic-container');
			var groups = container.find('.condition-groups');
			var template = this.getConditionGroupTemplate();
			var index = groups.find('.condition-group').length;

			// Replace placeholders
			template = template.replace(/\{group_index\}/g, index);
			template = template.replace(/\{addon_index\}/g, button.data('addon-index'));

			groups.append(template);

			// Initialize sortable for new group
			groups.find('.condition-group:last .conditions-list').sortable({
				handle: '.condition-handle',
				placeholder: 'sortable-placeholder'
			});

			groups.find('.condition-group:last .actions-list').sortable({
				handle: '.action-handle',
				placeholder: 'sortable-placeholder'
			});

			// Add first condition and action
			var newGroup = groups.find('.condition-group:last');
			this.addCondition(newGroup.find('.add-condition'));
			this.addAction(newGroup.find('.add-action'));
		},

		/**
		 * Add condition
		 */
		addCondition: function(button) {
			var group = button.closest('.condition-group');
			var list = group.find('.conditions-list');
			var template = this.getConditionTemplate();
			var index = list.find('.condition-item').length;
			var groupIndex = group.data('group-index');
			var addonIndex = button.closest('.conditional-logic-container').data('addon-index');

			// Replace placeholders
			template = template.replace(/\{condition_index\}/g, index);
			template = template.replace(/\{group_index\}/g, groupIndex);
			template = template.replace(/\{addon_index\}/g, addonIndex);

			list.append(template);

			// Update fields for default type
			var newCondition = list.find('.condition-item:last');
			this.updateConditionFields(newCondition.find('.condition-type'));
		},

		/**
		 * Add action
		 */
		addAction: function(button) {
			var group = button.closest('.condition-group');
			var list = group.find('.actions-list');
			var template = this.getActionTemplate();
			var index = list.find('.action-item').length;
			var groupIndex = group.data('group-index');
			var addonIndex = button.closest('.conditional-logic-container').data('addon-index');

			// Replace placeholders
			template = template.replace(/\{action_index\}/g, index);
			template = template.replace(/\{group_index\}/g, groupIndex);
			template = template.replace(/\{addon_index\}/g, addonIndex);

			list.append(template);

			// Update fields for default type
			var newAction = list.find('.action-item:last');
			this.updateActionFields(newAction.find('.action-type'));
		},

		/**
		 * Remove condition group
		 */
		removeConditionGroup: function(button) {
			var group = button.closest('.condition-group');
			
			if (confirm(wc_product_addons_conditional_logic.i18n.confirm_remove)) {
				group.fadeOut(function() {
					$(this).remove();
				});
			}
		},

		/**
		 * Remove condition
		 */
		removeCondition: function(button) {
			var condition = button.closest('.condition-item');
			var list = condition.closest('.conditions-list');
			
			// Don't remove if it's the last condition
			if (list.find('.condition-item').length > 1) {
				condition.fadeOut(function() {
					$(this).remove();
				});
			} else {
				alert('At least one condition is required.');
			}
		},

		/**
		 * Remove action
		 */
		removeAction: function(button) {
			var action = button.closest('.action-item');
			var list = action.closest('.actions-list');
			
			// Don't remove if it's the last action
			if (list.find('.action-item').length > 1) {
				action.fadeOut(function() {
					$(this).remove();
				});
			} else {
				alert('At least one action is required.');
			}
		},

		/**
		 * Update condition fields based on type
		 */
		updateConditionFields: function(select) {
			var condition = select.closest('.condition-item');
			var type = select.val();
			var fields = condition.find('.condition-fields');

			// Hide all field groups
			fields.find('.field-group').hide();

			// Show relevant fields
			switch (type) {
				case 'field':
					fields.find('.field-target, .field-operator, .field-value').show();
					this.populateFieldTargets(fields.find('.field-target select'));
					break;

				case 'product':
					fields.find('.field-property, .field-operator, .field-value').show();
					break;

				case 'cart':
					fields.find('.field-property, .field-operator, .field-value').show();
					break;

				case 'user':
					fields.find('.field-property, .field-operator, .field-value').show();
					break;

				case 'date':
					fields.find('.field-property, .field-operator, .field-value').show();
					break;

				case 'rule':
					fields.find('.field-target, .field-property, .field-operator, .field-value').show();
					this.populateRuleTargets(fields.find('.field-target select'));
					break;
			}

			// Update operator options
			this.updateOperatorOptions(condition, type);
		},

		/**
		 * Update action fields based on type
		 */
		updateActionFields: function(select) {
			var action = select.closest('.action-item');
			var type = select.val();
			var fields = action.find('.action-fields');

			// Hide all field groups
			fields.find('.field-group').hide();

			// Show relevant fields
			switch (type) {
				case 'visibility':
					fields.find('.field-visibility-action, .field-animation').show();
					break;

				case 'price':
					fields.find('.field-price-method, .field-price-value, .field-price-target').show();
					break;

				case 'requirement':
					fields.find('.field-required-status, .field-validation-type').show();
					break;

				case 'modifier':
					fields.find('.field-modification-type, .field-modification-value').show();
					break;
			}
		},

		/**
		 * Update property fields
		 */
		updatePropertyFields: function(select) {
			var condition = select.closest('.condition-item');
			var property = select.val();
			var valueField = condition.find('.field-value input, .field-value select');

			// Update value field type based on property
			this.updateValueFieldType(valueField, property);
		},

		/**
		 * Populate field targets
		 */
		populateFieldTargets: function(select) {
			var options = '<option value="">Select a field</option>';
			
			// Get all addons in the form
			$('.woocommerce_product_addon').each(function() {
				var addon = $(this);
				var name = addon.find('.addon_name').val();
				
				if (name) {
					options += '<option value="' + name + '">' + name + '</option>';
				}
			});

			select.html(options);
		},

		/**
		 * Populate rule targets (other addons for rule dependencies)
		 */
		populateRuleTargets: function(select) {
			var options = '<option value="">Select an addon</option>';
			var currentAddonName = select.closest('.woocommerce_product_addon').find('.addon_name').val();
			
			// Get all addons in the form except the current one
			$('.woocommerce_product_addon').each(function() {
				var addon = $(this);
				var name = addon.find('.addon_name').val();
				
				if (name && name !== currentAddonName) {
					options += '<option value="' + name + '">' + name + '</option>';
				}
			});

			select.html(options);
		},

		/**
		 * Update operator options based on condition type
		 */
		updateOperatorOptions: function(condition, type) {
			var operatorSelect = condition.find('.condition-operator');
			var options = '';

			// Define operators for each type
			var operators = {
				field: ['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'],
				product: ['equals', 'not_equals', 'greater_than', 'less_than', 'in', 'not_in'],
				cart: ['equals', 'not_equals', 'greater_than', 'less_than', 'contains', 'not_contains'],
				user: ['equals', 'not_equals', 'in', 'not_in', 'contains', 'not_contains'],
				date: ['equals', 'not_equals', 'greater_than', 'less_than', 'between', 'not_between'],
				rule: ['equals', 'not_equals', 'is_visible', 'is_hidden', 'is_required', 'is_not_required', 'has_price_modified', 'has_options_modified']
			};

			var operatorLabels = {
				equals: 'equals',
				not_equals: 'not equals',
				greater_than: 'greater than',
				less_than: 'less than',
				greater_than_equals: 'greater than or equals',
				less_than_equals: 'less than or equals',
				contains: 'contains',
				not_contains: 'does not contain',
				is_empty: 'is empty',
				is_not_empty: 'is not empty',
				in: 'is in',
				not_in: 'is not in',
				between: 'is between',
				not_between: 'is not between',
				is_visible: 'is visible',
				is_hidden: 'is hidden',
				is_required: 'is required',
				is_not_required: 'is not required',
				has_price_modified: 'has price modified',
				has_options_modified: 'has options modified'
			};

			// Build options
			var typeOperators = operators[type] || operators.field;
			$.each(typeOperators, function(index, operator) {
				options += '<option value="' + operator + '">' + operatorLabels[operator] + '</option>';
			});

			operatorSelect.html(options);
		},

		/**
		 * Update value field type
		 */
		updateValueFieldType: function(field, property) {
			// This would be expanded to handle different field types
			// based on the property selected
		},

		/**
		 * Initialize existing rules
		 */
		initializeExistingRules: function() {
			// Initialize sortable for existing rules
			this.initializeSortable();

			// Update fields for existing conditions and actions
			$('.condition-type').each(function() {
				WC_Product_Addons_Conditional_Logic_Admin.updateConditionFields($(this));
			});

			$('.action-type').each(function() {
				WC_Product_Addons_Conditional_Logic_Admin.updateActionFields($(this));
			});
		},

		/**
		 * Get template HTML
		 */
		getTemplate: function() {
			return $('#tmpl-addon-conditional-logic').html();
		},

		/**
		 * Get condition group template
		 */
		getConditionGroupTemplate: function() {
			return `
				<div class="condition-group" data-group-index="{group_index}">
					<div class="group-header">
						<span class="group-handle dashicons dashicons-move"></span>
						<h4>Condition Group {group_index}</h4>
						<button type="button" class="remove-condition-group dashicons dashicons-no-alt"></button>
					</div>
					<div class="group-content">
						<div class="conditions-section">
							<h5>${wc_product_addons_conditional_logic.i18n.if}</h5>
							<div class="conditions-list"></div>
							<button type="button" class="button add-condition">${wc_product_addons_conditional_logic.i18n.add_condition}</button>
						</div>
						<div class="actions-section">
							<h5>${wc_product_addons_conditional_logic.i18n.then}</h5>
							<div class="actions-list"></div>
							<button type="button" class="button add-action">${wc_product_addons_conditional_logic.i18n.add_action}</button>
						</div>
					</div>
				</div>
			`;
		},

		/**
		 * Get condition template
		 */
		getConditionTemplate: function() {
			return `
				<div class="condition-item">
					<span class="condition-handle dashicons dashicons-move"></span>
					<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][conditions][{condition_index}][type]" class="condition-type">
						<option value="field">Field Value</option>
						<option value="product">Product Property</option>
						<option value="cart">Cart Property</option>
						<option value="user">User Property</option>
						<option value="date">Date & Time</option>
						<option value="rule">Other Rule State</option>
					</select>
					<div class="condition-fields">
						<div class="field-group field-target" style="display:none;">
							<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][conditions][{condition_index}][target]" class="field-target-select">
								<option value="">Select field</option>
							</select>
						</div>
						<div class="field-group field-property" style="display:none;">
							<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][conditions][{condition_index}][property]" class="condition-property">
								<option value="">Select property</option>
							</select>
						</div>
						<div class="field-group field-operator">
							<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][conditions][{condition_index}][operator]" class="condition-operator">
								<option value="equals">equals</option>
								<option value="not_equals">not equals</option>
							</select>
						</div>
						<div class="field-group field-value">
							<input type="text" name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][conditions][{condition_index}][value]" class="condition-value" placeholder="Value">
						</div>
					</div>
					<button type="button" class="remove-condition dashicons dashicons-no-alt"></button>
				</div>
			`;
		},

		/**
		 * Get action template
		 */
		getActionTemplate: function() {
			return `
				<div class="action-item">
					<span class="action-handle dashicons dashicons-move"></span>
					<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][actions][{action_index}][type]" class="action-type">
						<option value="visibility">Change Visibility</option>
						<option value="price">Modify Price</option>
						<option value="requirement">Change Requirements</option>
						<option value="modifier">Modify Properties</option>
					</select>
					<div class="action-fields">
						<div class="field-group field-visibility-action" style="display:none;">
							<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][actions][{action_index}][config][action]">
								<option value="show">Show</option>
								<option value="hide">Hide</option>
							</select>
						</div>
						<div class="field-group field-animation" style="display:none;">
							<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][actions][{action_index}][config][animation]">
								<option value="none">No Animation</option>
								<option value="fade">Fade</option>
								<option value="slide">Slide</option>
							</select>
						</div>
						<div class="field-group field-price-method" style="display:none;">
							<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][actions][{action_index}][config][modification][method]">
								<option value="add">Add Amount</option>
								<option value="subtract">Subtract Amount</option>
								<option value="percentage_add">Add Percentage</option>
								<option value="percentage_subtract">Subtract Percentage</option>
								<option value="set">Set To</option>
							</select>
						</div>
						<div class="field-group field-price-value" style="display:none;">
							<input type="number" step="0.01" name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][actions][{action_index}][config][modification][value]" placeholder="Value">
						</div>
						<div class="field-group field-price-target" style="display:none;">
							<select name="product_addon[{addon_index}][conditional_logic][groups][{group_index}][actions][{action_index}][config][target]">
								<option value="self">This Addon</option>
								<option value="all">All Addons</option>
							</select>
						</div>
					</div>
					<button type="button" class="remove-action dashicons dashicons-no-alt"></button>
				</div>
			`;
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		WC_Product_Addons_Conditional_Logic_Admin.init();
	});

})(jQuery);