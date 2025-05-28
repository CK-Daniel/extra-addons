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
	 * Conditional Logic Admin Manager
	 */
	window.WC_Product_Addons_Conditional_Logic_Admin = {
		
		ruleCounter: 0,
		currentTab: 'global-rules',
		rules: {},
		
		/**
		 * Initialize the conditional logic admin interface
		 */
		init: function() {
			console.log('Initializing Conditional Logic Admin...');
			
			// Verify we're on the right page
			if (!$('.conditional-logic-admin-header').length) {
				console.log('Not on conditional logic admin page');
				return;
			}
			
			this.bindEvents();
			this.initializeDragDrop();
			this.loadExistingRules();
			
			console.log('Conditional Logic Admin initialized');
		},
		
		/**
		 * Bind all event handlers
		 */
		bindEvents: function() {
			var self = this;
			
			// Tab switching
			$('.nav-tab').on('click', function(e) {
				e.preventDefault();
				self.switchTab($(this));
			});
			
			// Form submission
			$('form').on('submit', function(e) {
				e.preventDefault();
				self.saveRules();
			});
			
			// Add rule buttons
			$(document).on('click', '.add-rule', function(e) {
				e.preventDefault();
				self.addRule($(this).data('context'));
			});
			
			// Rule controls
			$(document).on('click', '.rule-delete', function(e) {
				e.preventDefault();
				self.deleteRule($(this).closest('.conditional-rule'));
			});
			
			$(document).on('click', '.rule-duplicate', function(e) {
				e.preventDefault();
				self.duplicateRule($(this).closest('.conditional-rule'));
			});
			
			$(document).on('click', '.rule-toggle', function(e) {
				e.preventDefault();
				self.toggleRule($(this));
			});
			
			// Add condition/action buttons
			$(document).on('click', '.add-condition', function(e) {
				e.preventDefault();
				self.addCondition($(this).closest('.conditional-rule'));
			});
			
			$(document).on('click', '.add-action', function(e) {
				e.preventDefault();
				self.addAction($(this).closest('.conditional-rule'));
			});
			
			// Remove condition/action
			$(document).on('click', '.remove-condition', function(e) {
				e.preventDefault();
				$(this).closest('.condition-item').fadeOut(300, function() {
					$(this).remove();
				});
			});
			
			$(document).on('click', '.remove-action', function(e) {
				e.preventDefault();
				$(this).closest('.action-item').fadeOut(300, function() {
					$(this).remove();
				});
			});
			
			// Condition type change
			$(document).on('change', '.condition-type', function() {
				self.updateConditionFields($(this));
			});
			
			// Action type change
			$(document).on('change', '.action-type', function() {
				self.updateActionFields($(this));
			});
			
			// Product/Category selection
			$('#product-select').on('change', function() {
				self.loadProductRules($(this).val());
			});
			
			$('#category-select').on('change', function() {
				self.loadCategoryRules($(this).val());
			});
			
			// Test, Export, Import buttons
			$('.test-rules').on('click', function(e) {
				e.preventDefault();
				self.testRules();
			});
			
			$('.export-rules').on('click', function(e) {
				e.preventDefault();
				self.exportRules();
			});
			
			$('.import-rules').on('click', function(e) {
				e.preventDefault();
				self.importRules();
			});
			
			// Clear canvas
			$('.clear-canvas').on('click', function(e) {
				e.preventDefault();
				self.clearCanvas();
			});
			
			// Initialize Select2 for product search
			if ($.fn.select2) {
				$('#product-select').select2({
					ajax: {
						url: wc_pao_conditional_logic_admin.ajax_url,
						dataType: 'json',
						delay: 250,
						data: function(params) {
							return {
								term: params.term,
								action: 'woocommerce_json_search_products',
								security: wc_pao_conditional_logic_admin.nonce
							};
						},
						processResults: function(data) {
							var results = [];
							if (data) {
								$.each(data, function(id, text) {
									results.push({
										id: id,
										text: text
									});
								});
							}
							return {
								results: results
							};
						},
						cache: true
					},
					minimumInputLength: 3
				});
			}
		},
		
		/**
		 * Initialize drag and drop functionality
		 */
		initializeDragDrop: function() {
			var self = this;
			
			// Check if jQuery UI is available
			if ($.fn.draggable) {
				// Make condition and action types draggable
				$('.condition-type, .action-type').draggable({
					helper: 'clone',
					revert: 'invalid',
					zIndex: 1000,
					start: function(event, ui) {
						$(ui.helper).addClass('dragging');
					}
				});
			}
			
			// Check if jQuery UI droppable is available
			if ($.fn.droppable) {
				// Make canvas droppable
				$('#rule-canvas').droppable({
					accept: '.condition-type, .action-type',
					hoverClass: 'drop-hover',
					drop: function(event, ui) {
						self.handleCanvasDrop(ui.draggable);
					}
				});
			}
			
			// Check if jQuery UI sortable is available
			if ($.fn.sortable) {
				// Make rules sortable
				$('.rules-list').sortable({
					handle: '.rule-header',
					placeholder: 'rule-placeholder',
					forcePlaceholderSize: true,
					update: function() {
						self.updateRulePriorities();
					}
				});
			}
		},
		
		/**
		 * Switch between tabs
		 */
		switchTab: function($tab) {
			var target = $tab.attr('href');
			
			// Update active tab
			$('.nav-tab').removeClass('nav-tab-active');
			$tab.addClass('nav-tab-active');
			
			// Show/hide content
			$('.conditional-logic-tab-content').hide();
			$(target).show();
			
			this.currentTab = target.substring(1);
		},
		
		/**
		 * Add a new rule
		 */
		addRule: function(context) {
			var ruleId = 'rule_' + Date.now();
			var template = $('#rule-template').html();
			
			// Replace placeholders
			template = template.replace(/{{rule_id}}/g, ruleId);
			
			// Add to appropriate container
			var $container;
			switch(context) {
				case 'global':
					$container = $('#global-rules-list');
					break;
				case 'product':
					$container = $('#product-rules-container .rules-list');
					break;
				case 'category':
					$container = $('#category-rules-container .rules-list');
					break;
			}
			
			if ($container && $container.length) {
				$container.append(template);
				
				// Initialize the new rule
				var $newRule = $container.find('[data-rule-id="' + ruleId + '"]');
				this.initializeRule($newRule);
				
				// Animate in
				$newRule.hide().slideDown(300);
			}
		},
		
		/**
		 * Initialize a rule element
		 */
		initializeRule: function($rule) {
			// Populate addon selector
			this.populateAddonSelector($rule.find('.addon-selector'));
			
			// Add default condition and action
			this.addCondition($rule);
			this.addAction($rule);
		},
		
		/**
		 * Delete a rule
		 */
		deleteRule: function($rule) {
			if (confirm(wc_pao_conditional_logic_admin.i18n.confirm_delete)) {
				$rule.slideUp(300, function() {
					$(this).remove();
				});
			}
		},
		
		/**
		 * Duplicate a rule
		 */
		duplicateRule: function($rule) {
			var $clone = $rule.clone();
			var newId = 'rule_' + Date.now();
			
			// Update IDs
			$clone.attr('data-rule-id', newId);
			$clone.find('[name]').each(function() {
				var name = $(this).attr('name');
				name = name.replace(/conditional_rules\[[^\]]+\]/, 'conditional_rules[' + newId + ']');
				$(this).attr('name', name);
			});
			
			// Insert after original
			$rule.after($clone);
			$clone.hide().slideDown(300);
		},
		
		/**
		 * Toggle rule enabled/disabled
		 */
		toggleRule: function($toggle) {
			var $rule = $toggle.closest('.conditional-rule');
			
			if ($rule.hasClass('disabled')) {
				$rule.removeClass('disabled');
				$toggle.text(wc_pao_conditional_logic_admin.i18n.enabled || 'Enabled');
			} else {
				$rule.addClass('disabled');
				$toggle.text(wc_pao_conditional_logic_admin.i18n.disabled || 'Disabled');
			}
		},
		
		/**
		 * Add a condition to a rule
		 */
		addCondition: function($rule) {
			var ruleId = $rule.data('rule-id');
			var conditionIndex = $rule.find('.condition-item').length;
			
			var template = `
				<div class="condition-item">
					<select name="conditional_rules[${ruleId}][conditions][${conditionIndex}][type]" class="condition-type">
						<option value="field">Field Value</option>
						<option value="product">Product Property</option>
						<option value="cart">Cart Property</option>
						<option value="user">User Property</option>
						<option value="date">Date/Time</option>
						<option value="rule">Other Rule</option>
					</select>
					<span class="condition-fields"></span>
					<button type="button" class="button-link remove-condition">Remove</button>
				</div>
			`;
			
			$rule.find('.conditions-container').append(template);
			
			// Update fields for default type
			var $newCondition = $rule.find('.condition-item').last();
			this.updateConditionFields($newCondition.find('.condition-type'));
		},
		
		/**
		 * Add an action to a rule
		 */
		addAction: function($rule) {
			var ruleId = $rule.data('rule-id');
			var actionIndex = $rule.find('.action-item').length;
			
			var template = `
				<div class="action-item">
					<select name="conditional_rules[${ruleId}][actions][${actionIndex}][type]" class="action-type">
						<option value="visibility">Show/Hide</option>
						<option value="price">Price Modification</option>
						<option value="requirement">Requirements</option>
						<option value="modifier">Text/Options</option>
					</select>
					<span class="action-fields"></span>
					<button type="button" class="button-link remove-action">Remove</button>
				</div>
			`;
			
			$rule.find('.actions-container').append(template);
			
			// Update fields for default type
			var $newAction = $rule.find('.action-item').last();
			this.updateActionFields($newAction.find('.action-type'));
		},
		
		/**
		 * Update condition fields based on type
		 */
		updateConditionFields: function($select) {
			var type = $select.val();
			var $container = $select.siblings('.condition-fields');
			var ruleId = $select.closest('.conditional-rule').data('rule-id');
			var condIndex = $select.closest('.condition-item').index();
			
			var fields = '';
			
			switch(type) {
				case 'field':
					fields = `
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][field]" class="field-select">
							<option value="">Select field...</option>
						</select>
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][operator]">
							<option value="equals">Equals</option>
							<option value="not_equals">Not Equals</option>
							<option value="contains">Contains</option>
							<option value="greater_than">Greater Than</option>
							<option value="less_than">Less Than</option>
						</select>
						<input type="text" name="conditional_rules[${ruleId}][conditions][${condIndex}][value]" placeholder="Value" />
					`;
					break;
					
				case 'product':
					fields = `
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][property]">
							<option value="price">Price</option>
							<option value="stock">Stock</option>
							<option value="category">Category</option>
							<option value="tag">Tag</option>
						</select>
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][operator]">
							<option value="equals">Equals</option>
							<option value="greater_than">Greater Than</option>
							<option value="less_than">Less Than</option>
							<option value="in">In</option>
						</select>
						<input type="text" name="conditional_rules[${ruleId}][conditions][${condIndex}][value]" placeholder="Value" />
					`;
					break;
					
				case 'cart':
					fields = `
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][property]">
							<option value="total">Cart Total</option>
							<option value="quantity">Total Quantity</option>
							<option value="items">Number of Items</option>
							<option value="coupon">Has Coupon</option>
						</select>
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][operator]">
							<option value="equals">Equals</option>
							<option value="greater_than">Greater Than</option>
							<option value="less_than">Less Than</option>
							<option value="contains">Contains</option>
						</select>
						<input type="text" name="conditional_rules[${ruleId}][conditions][${condIndex}][value]" placeholder="Value" />
					`;
					break;
					
				case 'user':
					fields = `
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][property]">
							<option value="role">User Role</option>
							<option value="logged_in">Is Logged In</option>
							<option value="customer">Is Customer</option>
							<option value="meta">User Meta</option>
						</select>
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][operator]">
							<option value="equals">Equals</option>
							<option value="not_equals">Not Equals</option>
							<option value="in">Is One Of</option>
						</select>
						<input type="text" name="conditional_rules[${ruleId}][conditions][${condIndex}][value]" placeholder="Value" />
					`;
					break;
					
				case 'date':
					fields = `
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][property]">
							<option value="current">Current Date/Time</option>
							<option value="day_of_week">Day of Week</option>
							<option value="time_of_day">Time of Day</option>
						</select>
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][operator]">
							<option value="equals">Equals</option>
							<option value="before">Before</option>
							<option value="after">After</option>
							<option value="between">Between</option>
						</select>
						<input type="text" name="conditional_rules[${ruleId}][conditions][${condIndex}][value]" placeholder="Value" />
					`;
					break;
					
				case 'rule':
					fields = `
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][target_rule]" class="rule-select">
							<option value="">Select rule...</option>
						</select>
						<select name="conditional_rules[${ruleId}][conditions][${condIndex}][property]">
							<option value="active">Is Active</option>
							<option value="triggered">Was Triggered</option>
						</select>
					`;
					break;
			}
			
			$container.html(fields);
			
			// Populate selectors if needed
			if (type === 'field') {
				this.populateFieldSelector($container.find('.field-select'));
			} else if (type === 'rule') {
				this.populateRuleSelector($container.find('.rule-select'));
			}
		},
		
		/**
		 * Update action fields based on type
		 */
		updateActionFields: function($select) {
			var type = $select.val();
			var $container = $select.siblings('.action-fields');
			var ruleId = $select.closest('.conditional-rule').data('rule-id');
			var actionIndex = $select.closest('.action-item').index();
			
			var fields = '';
			
			switch(type) {
				case 'visibility':
					fields = `
						<select name="conditional_rules[${ruleId}][actions][${actionIndex}][visibility]">
							<option value="show">Show</option>
							<option value="hide">Hide</option>
						</select>
						<select name="conditional_rules[${ruleId}][actions][${actionIndex}][animation]">
							<option value="none">No Animation</option>
							<option value="fade">Fade</option>
							<option value="slide">Slide</option>
						</select>
					`;
					break;
					
				case 'price':
					fields = `
						<select name="conditional_rules[${ruleId}][actions][${actionIndex}][price_method]">
							<option value="add">Add Amount</option>
							<option value="subtract">Subtract Amount</option>
							<option value="multiply">Multiply By</option>
							<option value="percentage">Add Percentage</option>
							<option value="set">Set To</option>
						</select>
						<input type="number" step="0.01" name="conditional_rules[${ruleId}][actions][${actionIndex}][price_value]" placeholder="Amount" />
					`;
					break;
					
				case 'requirement':
					fields = `
						<select name="conditional_rules[${ruleId}][actions][${actionIndex}][required]">
							<option value="required">Make Required</option>
							<option value="optional">Make Optional</option>
						</select>
					`;
					break;
					
				case 'modifier':
					fields = `
						<select name="conditional_rules[${ruleId}][actions][${actionIndex}][modify_type]">
							<option value="label">Change Label</option>
							<option value="description">Change Description</option>
							<option value="options">Modify Options</option>
						</select>
						<input type="text" name="conditional_rules[${ruleId}][actions][${actionIndex}][modify_value]" placeholder="New value" />
					`;
					break;
			}
			
			$container.html(fields);
		},
		
		/**
		 * Populate addon selector
		 */
		populateAddonSelector: function($select) {
			var context = this.currentTab.replace('-rules', '');
			var productId = $('#product-select').val();
			
			// Show loading state
			$select.html('<option value="">Loading addons...</option>');
			
			// Fetch addon list from server
			$.ajax({
				url: wc_pao_conditional_logic_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'wc_pao_get_addon_list',
					nonce: wc_pao_conditional_logic_admin.nonce,
					context: context,
					product_id: productId
				},
				success: function(response) {
					var options = '<option value="">Select addon...</option>';
					
					if (response.success && response.data.addons.length > 0) {
						$.each(response.data.addons, function(index, addon) {
							var label = addon.name;
							if (addon.group) {
								label = addon.group + ' - ' + label;
							}
							options += '<option value="' + addon.id + '" data-type="' + addon.type + '">' + label + '</option>';
						});
					} else {
						options = '<option value="">No addons found</option>';
					}
					
					$select.html(options);
				},
				error: function() {
					$select.html('<option value="">Error loading addons</option>');
				}
			});
		},
		
		/**
		 * Populate field selector
		 */
		populateFieldSelector: function($select) {
			// This would be populated with actual field data
			var options = '<option value="">Select field...</option>';
			options += '<option value="size">Size</option>';
			options += '<option value="color">Color</option>';
			options += '<option value="message">Message</option>';
			
			$select.html(options);
		},
		
		/**
		 * Populate rule selector
		 */
		populateRuleSelector: function($select) {
			var options = '<option value="">Select rule...</option>';
			
			// Get all rules except the current one
			var currentRuleId = $select.closest('.conditional-rule').data('rule-id');
			$('.conditional-rule').each(function() {
				var ruleId = $(this).data('rule-id');
				if (ruleId !== currentRuleId) {
					var ruleName = $(this).find('h4').text();
					options += '<option value="' + ruleId + '">' + ruleName + '</option>';
				}
			});
			
			$select.html(options);
		},
		
		/**
		 * Handle drop on canvas
		 */
		handleCanvasDrop: function($draggable) {
			var type = $draggable.data('type');
			var $canvas = $('#rule-canvas .canvas-drop-zone');
			
			// Create visual representation
			var $element = $('<div class="canvas-element ' + type + '-element">');
			$element.html($draggable.html());
			
			// Replace drop zone message if first element
			if ($canvas.find('.canvas-element').length === 0) {
				$canvas.html('');
			}
			
			$canvas.append($element);
			
			// Make element draggable within canvas
			$element.draggable({
				containment: '#rule-canvas',
				grid: [20, 20]
			});
		},
		
		/**
		 * Clear the rule builder canvas
		 */
		clearCanvas: function() {
			$('#rule-canvas .canvas-drop-zone').html('<p>Drag conditions and actions here to build your rule</p>');
		},
		
		/**
		 * Load existing rules
		 */
		loadExistingRules: function() {
			// This would load rules from the server
			console.log('Loading existing rules...');
		},
		
		/**
		 * Load product-specific rules
		 */
		loadProductRules: function(productId) {
			if (!productId) return;
			
			// This would load rules for the specific product
			console.log('Loading rules for product:', productId);
		},
		
		/**
		 * Load category-specific rules
		 */
		loadCategoryRules: function(categoryId) {
			if (!categoryId) return;
			
			// This would load rules for the specific category
			console.log('Loading rules for category:', categoryId);
		},
		
		/**
		 * Update rule priorities after sorting
		 */
		updateRulePriorities: function() {
			$('.conditional-rule').each(function(index) {
				$(this).find('[name*="[priority]"]').val(index + 1);
			});
		},
		
		/**
		 * Test rules
		 */
		testRules: function() {
			console.log('Testing rules...');
			
			// Collect all rules
			var rules = this.collectRules();
			
			// Send to server for testing
			$.ajax({
				url: wc_pao_conditional_logic_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'wc_pao_test_conditional_rules',
					nonce: wc_pao_conditional_logic_admin.nonce,
					rules: rules
				},
				success: function(response) {
					if (response.success) {
						alert('Rules tested successfully!');
					} else {
						alert('Error testing rules: ' + response.data);
					}
				}
			});
		},
		
		/**
		 * Export rules
		 */
		exportRules: function() {
			var rules = this.collectRules();
			var json = JSON.stringify(rules, null, 2);
			
			// Create download
			var blob = new Blob([json], { type: 'application/json' });
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = 'conditional-logic-rules.json';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
		},
		
		/**
		 * Import rules
		 */
		importRules: function() {
			var input = document.createElement('input');
			input.type = 'file';
			input.accept = '.json';
			
			input.onchange = function(e) {
				var file = e.target.files[0];
				if (!file) return;
				
				var reader = new FileReader();
				reader.onload = function(e) {
					try {
						var rules = JSON.parse(e.target.result);
						// Process imported rules
						console.log('Imported rules:', rules);
						alert('Rules imported successfully!');
					} catch (err) {
						alert('Error importing rules: Invalid JSON file');
					}
				};
				reader.readAsText(file);
			};
			
			input.click();
		},
		
		/**
		 * Collect all rules from the form
		 */
		collectRules: function() {
			var rules = {};
			
			$('.conditional-rule').each(function() {
				var ruleId = $(this).data('rule-id');
				var rule = {
					id: ruleId,
					enabled: !$(this).hasClass('disabled'),
					priority: $(this).find('[name*="[priority]"]').val(),
					target: $(this).find('.addon-selector').val(),
					conditions: [],
					actions: []
				};
				
				// Collect conditions
				$(this).find('.condition-item').each(function() {
					var condition = {};
					$(this).find('[name]').each(function() {
						var name = $(this).attr('name');
						var key = name.match(/\[(\w+)\]$/)[1];
						condition[key] = $(this).val();
					});
					rule.conditions.push(condition);
				});
				
				// Collect actions
				$(this).find('.action-item').each(function() {
					var action = {};
					$(this).find('[name]').each(function() {
						var name = $(this).attr('name');
						var key = name.match(/\[(\w+)\]$/)[1];
						action[key] = $(this).val();
					});
					rule.actions.push(action);
				});
				
				rules[ruleId] = rule;
			});
			
			return rules;
		},
		
		/**
		 * Save all rules
		 */
		saveRules: function() {
			var self = this;
			var rules = this.collectRules();
			
			// Show loading state
			$('.button-primary').prop('disabled', true).text('Saving...');
			
			$.ajax({
				url: wc_pao_conditional_logic_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'wc_pao_save_conditional_rules',
					nonce: wc_pao_conditional_logic_admin.nonce,
					rules: rules,
					context: this.currentTab.replace('-rules', '')
				},
				success: function(response) {
					if (response.success) {
						// Show success message
						var notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
						$('.wrap > h1').after(notice);
						
						// Auto-dismiss after 3 seconds
						setTimeout(function() {
							notice.fadeOut(function() {
								$(this).remove();
							});
						}, 3000);
					} else {
						alert('Error: ' + response.data.message);
					}
				},
				error: function() {
					alert('Error saving rules. Please try again.');
				},
				complete: function() {
					$('.button-primary').prop('disabled', false).text(wc_pao_conditional_logic_admin.i18n.save_all_rules || 'Save All Rules');
				}
			});
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		if (typeof WC_Product_Addons_Conditional_Logic_Admin !== 'undefined') {
			WC_Product_Addons_Conditional_Logic_Admin.init();
		}
	});

})(jQuery);