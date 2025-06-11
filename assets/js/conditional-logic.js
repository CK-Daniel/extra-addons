/**
 * WooCommerce Product Add-ons Extra Digital - Conditional Logic Engine
 *
 * Frontend JavaScript for handling conditional logic evaluation and actions.
 *
 * @package WooCommerce Product Add-ons
 * @since   4.0.0
 */

(function($) {
	'use strict';

	/**
	 * Option Matcher - Precise option matching system
	 */
	var OptionMatcher = {
		/**
		 * Find options by rule value
		 */
		findOptions: function(addon, ruleValue) {
			var matches = [];
			
			// Strategy 1: Exact value match
			var exactMatch = addon.find('option[value="' + ruleValue + '"]');
			if (exactMatch.length > 0) {
				matches.push({ element: exactMatch, matchType: 'exact-value' });
			}
			
			// Strategy 2: Match by data-option-key (sanitized label)
			var keyMatch = addon.find('option[data-option-key="' + ruleValue + '"]');
			if (keyMatch.length > 0) {
				matches.push({ element: keyMatch, matchType: 'option-key' });
			}
			
			// Strategy 3: Match by data-label (original label)
			var labelMatch = addon.find('option[data-label="' + ruleValue + '"]');
			if (labelMatch.length > 0) {
				matches.push({ element: labelMatch, matchType: 'label' });
			}
			
			// Strategy 4: Match value with -N pattern
			var patternMatch = addon.find('option[value^="' + ruleValue + '-"]');
			if (patternMatch.length > 0) {
				matches.push({ element: patternMatch, matchType: 'value-pattern' });
			}
			
			// Remove duplicates
			var seen = [];
			var unique = [];
			matches.forEach(function(match) {
				match.element.each(function() {
					var val = $(this).val();
					if (seen.indexOf(val) === -1) {
						seen.push(val);
						unique.push($(this));
					}
				});
			});
			
			return unique;
		},
		
		/**
		 * Check if option value matches rule value
		 */
		matchesRule: function(optionElement, ruleValue) {
			var $option = $(optionElement);
			
			// Check exact value
			if ($option.val() === ruleValue) return true;
			
			// Check data attributes
			if ($option.data('option-key') === ruleValue) return true;
			if ($option.data('label') === ruleValue) return true;
			
			// Check value pattern
			var optionValue = $option.val();
			if (optionValue && optionValue.indexOf(ruleValue + '-') === 0) return true;
			
			return false;
		}
	};

	/**
	 * Conditional Logic Engine
	 */
	var WC_Product_Addons_Conditional_Logic = {
		
		/**
		 * Initialize
		 */
		init: function() {
			console.log('🔧 Initializing WC Product Addons Conditional Logic...');
			
			// Check if we have the required global variable
			if (typeof wc_product_addons_conditional_logic === 'undefined') {
				console.error('❌ wc_product_addons_conditional_logic global variable not found');
				return;
			}
			
			this.form = $('form.cart');
			this.cache = {};
			this.debounceTimer = null;
			this.evaluationQueue = [];
			
			// Find addons - try multiple selectors to support both new and legacy templates
			this.addons = $('.wc-pao-addon-container[data-addon-identifier]');
			
			// Fall back to legacy selectors if needed
			if (this.addons.length === 0) {
				console.log('🔍 No enhanced addons found, trying legacy selectors...');
				// For legacy template, look for the specific structure
				this.addons = $('.product-addon');
				if (this.addons.length === 0) {
					// Try alternative selectors
					this.addons = $('.wc-pao-addon, .wc-pao-addon-container');
				}
			}
			
			console.log('🔍 Found elements:');
			console.log('  - Cart form:', this.form.length);
			console.log('  - Product addons:', this.addons.length);
			
			if (this.form.length === 0 || this.addons.length === 0) {
				console.warn('⚠️ Missing required elements - conditional logic disabled');
				return;
			}
			
			// Read conditional logic data
			this.readConditionalLogicData();
			
			// Log addon information
			this.logAddonInfo();

			console.log('✅ Required elements found, proceeding with initialization...');
			this.bindEvents();
			this.initializeState();
			
			// Mark form as initialized to prevent duplicate initialization
			this.form.data('conditional-logic-initialized', true);
			
			// Wait a bit for DOM to be fully ready, then evaluate
			// Only run once to prevent multiple evaluations
			if (!this.initialEvaluationDone) {
				this.initialEvaluationDone = true;
				setTimeout(() => {
					this.evaluateAllConditions();
				}, 100);
			}
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			var self = this;
			
			console.log('🔗 Binding events to form elements...');

			// Listen for addon field changes - use broader selectors
			this.form.on('change', '.wc-pao-addon input, .wc-pao-addon select, .wc-pao-addon textarea, .product-addon input, .product-addon select, .product-addon textarea', function() {
				console.log('📝 Change event detected on:', $(this)[0]);
				self.handleFieldChange($(this));
			});

			// Listen for input events on text fields
			this.form.on('input', '.wc-pao-addon input[type="text"], .wc-pao-addon textarea, .product-addon input[type="text"], .product-addon textarea', function() {
				console.log('⌨️ Input event detected on:', $(this)[0]);
				self.handleFieldInput($(this));
			});

			// Listen for addon update event
			this.form.on('woocommerce-product-addons-update', function() {
				self.evaluateAllConditions();
			});

			// Listen for variation changes
			this.form.on('found_variation', function(event, variation) {
				self.handleVariationChange(variation);
			});

			// Listen for quantity changes
			this.form.on('change', 'input.qty', function() {
				self.handleQuantityChange($(this).val());
			});
			
			// Intercept form submission to add conditional prices
			this.form.on('submit', function(e) {
				self.addConditionalPricesToForm();
			});
		},

		/**
		 * Read conditional logic data from embedded JSON
		 */
		readConditionalLogicData: function() {
			var self = this;
			this.conditionalData = {};
			
			$('script.wc-pao-conditional-data').each(function() {
				var script = $(this);
				var identifier = script.data('addon-identifier');
				
				try {
					var data = JSON.parse(script.html());
					self.conditionalData[identifier] = data;
					console.log('📋 Loaded conditional data for:', identifier, data);
				} catch (e) {
					console.error('Failed to parse conditional data:', e);
				}
			});
		},

		/**
		 * Log addon information for debugging
		 */
		logAddonInfo: function() {
			var self = this;
			console.log('📊 Addon Information:');
			
			this.addons.each(function(index) {
				var addon = $(this);
				var info = {
					index: index,
					identifier: addon.data('addon-identifier'),
					fieldName: addon.data('addon-field-name'),
					name: addon.data('addon-name'),
					type: addon.data('addon-type'),
					scope: addon.data('addon-scope'),
					globalId: addon.data('addon-global-id'),
					required: addon.data('addon-required') === '1',
					hasConditionalData: false
				};
				
				// Check if we have conditional data for this addon
				if (info.identifier && self.conditionalData[info.identifier]) {
					info.hasConditionalData = true;
					info.conditionalRules = self.conditionalData[info.identifier].rule_targets;
				}
				
				console.log(`  [${index}]`, info);
			});
		},

		/**
		 * Initialize state
		 */
		initializeState: function() {
			var self = this;
			this.state = {
				selections: {},
				product: this.getProductData(),
				cart: this.getCartData(),
				user: this.getUserData(),
				quantity: parseInt($('input.qty').val()) || 1
			};

			// Store original addon data
			this.originalAddons = {};
			console.log('📝 Cataloging addons...');
			this.addons.each(function() {
				var addon = $(this);
				
				// Safety check
				if (!addon || addon.length === 0) {
					console.warn('  ⚠️ Invalid addon element in loop');
					return; // Continue to next iteration
				}
				
				// Try multiple ways to get the addon name based on the HTML structure
				var name = null;
				
				// Method 1: Check data-addon-name on the container
				name = addon.data('addon-name');
				
				// Method 2: Check data-addon-name on label inside
				if (!name) {
					var label = addon.find('label[data-addon-name]');
					if (label.length) {
						name = label.data('addon-name');
					}
				}
				
				// Method 3: Check wc-pao-addon-name class text OR legacy addon-name class
				if (!name) {
					var labelText = addon.find('.wc-pao-addon-name, .addon-name').text();
					if (labelText) {
						// Clean up the label text (remove required asterisk, etc.)
						name = labelText.replace(/\s*\*\s*$/, '').replace(/\([^)]*\)$/, '').replace(/\s+$/, '').trim();
					}
				}
				
				// Method 4: Extract from CSS classes (e.g., wc-pao-addon-test or product-addon-test)
				if (!name) {
					var classes = addon.attr('class') || '';
					// Try new format first
					var match = classes.match(/wc-pao-addon-([a-zA-Z0-9_-]+)(?:\s|$)/);
					if (match && match[1] && match[1] !== 'container') {
						name = match[1];
					} else {
						// Try legacy format
						match = classes.match(/product-addon-([a-zA-Z0-9_-]+)(?:\s|$)/);
						if (match && match[1]) {
							name = match[1];
						}
					}
				}
				
				// Method 5: Get from input/select name attribute
				if (!name) {
					var field = addon.find('input, select, textarea').first();
					if (field.length) {
						var fieldName = field.attr('name') || field.attr('id') || '';
						// Extract from addon-ID-name-index pattern
						var match = fieldName.match(/addon-\d+-([^-]+)-/);
						if (match && match[1]) {
							name = match[1];
						}
					}
				}
				
				// Method 6: Get from the field ID pattern (e.g., addon-140-test-0)
				if (!name) {
					var field = addon.find('input, select, textarea').first();
					if (field.length) {
						var fieldId = field.attr('id') || '';
						// Extract from addon-productid-name-index pattern
						var match = fieldId.match(/addon-\d+-([^-]+)-\d+/);
						if (match && match[1]) {
							name = match[1];
						}
					}
				}
				
				// Only log detailed debug info if debug mode is enabled
				if (wc_product_addons_conditional_logic.debug) {
					console.log('🔍 Addon detection methods:', {
						'data-addon-name (container)': addon.data('addon-name'),
						'data-addon-name (label)': addon.find('label[data-addon-name]').data('addon-name'),
						'label text': addon.find('.wc-pao-addon-name').text().trim(),
						'css classes': addon.attr('class'),
						'field name': addon.find('input, select, textarea').first().attr('name'),
						'addon html': (function() {
							try {
								if (addon && addon.length > 0 && addon[0] && addon[0].outerHTML) {
									return addon[0].outerHTML.substring(0, 200) + '...';
								}
								return 'No HTML available';
							} catch (e) {
								return 'Error getting HTML: ' + e.message;
							}
						})(),
						'final name': name
					});
				}
				
				if (name) {
					console.log('  ✅ Found addon:', name);
					// Also store by identifier for better lookup
					var identifier = self.getAddonIdentifier(addon);
					var storeKey = identifier || name;
					
					self.originalAddons[storeKey] = {
						element: addon,
						name: name,
						identifier: identifier,
						data: addon.data() || {},
						required: addon.find('input, select, textarea').first().prop('required') || false,
						prices: self.extractPrices(addon) || {}
					};
					
					// If identifier is different from name, also store by name
					if (identifier && identifier !== name) {
						self.originalAddons[name] = self.originalAddons[storeKey];
					}
				} else {
					console.warn('  ❌ Addon found but no name detected:', addon.get(0));
				}
			});
			
			console.log('📋 Total addons cataloged:', Object.keys(this.originalAddons).length);
			console.log('📋 Addon names found:', Object.keys(this.originalAddons));
		},

		/**
		 * Handle field change
		 */
		handleFieldChange: function(field) {
			var addon = field.closest('.wc-pao-addon, .product-addon, .wc-pao-addon-container');
			
			// Use consistent identifier
			var addonId = this.getAddonIdentifier(addon);
			var addonName = this.getAddonNameFromElement(addon);
			
			console.log('🔄 Field changed in addon:', addonName, 'ID:', addonId, 'value:', this.getFieldValue(field));
			
			if (addonId) {
				// Update state using identifier
				this.updateSelection(addonId, field);
				
				// Store name mapping for display
				if (addonName && addonName !== addonId) {
					this.state.addonNames = this.state.addonNames || {};
					this.state.addonNames[addonId] = addonName;
				}
				
				// Debounce evaluation
				this.debounceEvaluation();
			}
		},

		/**
		 * Get addon identifier from element
		 */
		getAddonIdentifier: function(addon) {
			if (!addon || addon.length === 0) return null;
			
			// Priority 1: Use data-addon-identifier (our enhanced attribute)
			var identifier = addon.attr('data-addon-identifier') || addon.data('addon-identifier');
			if (identifier) {
				return identifier;
			}
			
			// Priority 2: Use data-addon-field-name
			var fieldName = addon.attr('data-addon-field-name') || addon.data('addon-field-name');
			if (fieldName) {
				return fieldName;
			}
			
			// Priority 3: Use data-addon-id (legacy)
			var addonId = addon.attr('data-addon-id') || addon.data('addon-id');
			if (addonId) {
				return addonId;
			}
			
			// Priority 4: Fall back to name detection
			return this.getAddonNameFromElement(addon);
		},

		/**
		 * Get addon name from element (for display/fallback)
		 */
		getAddonNameFromElement: function(addon) {
			if (!addon || addon.length === 0) return null;
			
			// Priority 1: Use data-addon-name
			var dataName = addon.attr('data-addon-name');
			if (dataName) {
				return dataName;
			}
			
			// Priority 2: Get from heading/label (including legacy .addon-name)
			var heading = addon.find('.wc-pao-addon-name, .addon-name, legend:first, h3:first, h4:first').first();
			if (heading.length > 0) {
				var name = heading.text().trim().replace(/\s*\*\s*$/, '').trim();
				if (name) {
					return name;
				}
			}
			
			// Priority 3: Extract from field name
			var field = addon.find('input[name*="addon-"], select[name*="addon-"], textarea[name*="addon-"]').first();
			if (field.length > 0) {
				var fieldName = field.attr('name');
				var match = fieldName.match(/addon-\d+-([^\[]+)/);
				if (match && match[1]) {
					return match[1].replace(/-/g, ' ').replace(/_/g, ' ');
				}
			}
			
			// Priority 4: CSS class matching
			var classes = addon.attr('class') || '';
			// Try new format first
			var classMatch = classes.match(/wc-pao-addon-([a-zA-Z0-9_-]+)(?:\s|$)/);
			if (classMatch && classMatch[1] && classMatch[1] !== 'container') {
				return classMatch[1];
			}
			// Try legacy format
			classMatch = classes.match(/product-addon-([a-zA-Z0-9_-]+)(?:\s|$)/);
			if (classMatch && classMatch[1]) {
				return classMatch[1];
			}
			
			return 'unknown_addon';
		},

		/**
		 * Handle field input (for text fields)
		 */
		handleFieldInput: function(field) {
			var addon = field.closest('.wc-pao-addon, .product-addon, .wc-pao-addon-container');
			var addonId = this.getAddonIdentifier(addon);
			
			if (addonId) {
				// Update state
				this.updateSelection(addonId, field);
				
				// Debounce evaluation with longer delay for typing
				this.debounceEvaluation(500);
			}
		},

		/**
		 * Handle variation change
		 */
		handleVariationChange: function(variation) {
			this.state.product.variation = variation;
			this.evaluateAllConditions();
		},

		/**
		 * Handle quantity change
		 */
		handleQuantityChange: function(quantity) {
			this.state.quantity = parseInt(quantity) || 1;
			this.evaluateAllConditions();
		},

		/**
		 * Update selection in state
		 */
		updateSelection: function(addonId, field) {
			var value = this.getFieldValue(field);
			var label = this.getFieldLabel(field);
			var price = this.getFieldPrice(field);
			var addon = field.closest('.wc-pao-addon, .product-addon, .wc-pao-addon-container');
			var addonName = this.getAddonNameFromElement(addon);

			if (value !== null) {
				this.state.selections[addonId] = {
					value: value,
					label: label,
					price: price,
					element: field,
					name: addonName, // Include the addon name for flexible matching
					identifier: addonId,
					scope: addon.data('addon-scope') || 'product',
					globalId: addon.data('addon-global-id')
				};
				console.log('📝 Updated selection:', addonId, this.state.selections[addonId]);
			} else {
				delete this.state.selections[addonId];
				console.log('🗑️ Removed selection:', addonId);
			}
		},

		/**
		 * Get field value
		 */
		getFieldValue: function(field) {
			var type = field.attr('type');
			
			if (type === 'checkbox') {
				if (field.is(':checked')) {
					return field.val();
				}
				return null;
			} else if (type === 'radio') {
				if (field.is(':checked')) {
					return field.val();
				}
				return null;
			} else {
				var val = field.val();
				return val !== '' ? val : null;
			}
		},

		/**
		 * Get field label
		 */
		getFieldLabel: function(field) {
			var label = '';
			
			if (field.is('select')) {
				label = field.find('option:selected').text();
			} else if (field.attr('type') === 'radio' || field.attr('type') === 'checkbox') {
				label = field.closest('label').text() || field.next('label').text();
			} else {
				label = field.val();
			}
			
			return label.trim();
		},

		/**
		 * Get field price
		 */
		getFieldPrice: function(field) {
			var price = 0;
			
			if (field.is('select')) {
				price = parseFloat(field.find('option:selected').data('price')) || 0;
			} else {
				price = parseFloat(field.data('price')) || 0;
			}
			
			return price;
		},

		/**
		 * Debounce evaluation
		 */
		debounceEvaluation: function(delay) {
			clearTimeout(this.debounceTimer);
			// Reduce delay to 50ms for immediate response
			delay = delay || 50;
			
			this.debounceTimer = setTimeout(function() {
				this.evaluateAllConditions();
			}.bind(this), delay);
		},

		/**
		 * Evaluate all conditions
		 */
		evaluateAllConditions: function() {
			var self = this;
			
			console.log('🔄 Starting conditional logic evaluation...');
			console.log('Current state:', this.state);

			// Skip loading state for immediate response
			// this.showLoading();

			// Prepare addon data for evaluation
			var addonData = [];
			
			this.addons.each(function() {
				var addon = $(this);
				var addonId = self.getAddonIdentifier(addon);
				var addonName = self.getAddonNameFromElement(addon);
				
				console.log('🔧 Preparing data for addon:', addonName, 'ID:', addonId);
				
				// Collect addon options for rule evaluation
				var options = [];
				addon.find('input, select, textarea').each(function() {
					var field = $(this);
					
					if (field.is('select')) {
						field.find('option').each(function() {
							var option = $(this);
							if (option.val()) { // Skip empty option
								options.push({
									value: option.val(),
									label: option.text().trim(),
									selected: option.is(':selected')
								});
							}
						});
					} else if (field.attr('type') === 'radio' || field.attr('type') === 'checkbox') {
						if (field.val()) { // Skip empty values
							options.push({
								value: field.val(),
								label: self.getFieldLabel(field),
								selected: field.is(':checked')
							});
						}
					}
				});

				console.log('  📋 Options found:', options);

				// Build comprehensive addon data
				var addonInfo = {
					name: addonName,
					id: addonId, // Use consistent identifier
					identifier: addonId,
					display_name: addonName,
					field_name: addon.data('addon-field-name') || addonId,
					type: addon.data('addon-type'),
					scope: addon.data('addon-scope') || 'product',
					global_id: addon.data('addon-global-id'),
					database_id: addon.data('addon-database-id'),
					options: options,
					current_value: self.state.selections[addonId] ? self.state.selections[addonId].value : null
				};
				
				// Log the addon being prepared
				console.log('📦 Addon prepared:', {
					identifier: addonId,
					name: addonName,
					scope: addonInfo.scope,
					globalId: addonInfo.global_id,
					currentValue: addonInfo.current_value,
					hasSelection: !!self.state.selections[addonId]
				});
				
				// Add any conditional logic metadata
				if (self.conditionalData && self.conditionalData[addonId]) {
					addonInfo.conditional_metadata = self.conditionalData[addonId];
				}
				
				addonData.push(addonInfo);
			});

			console.log('📊 Addon data prepared for evaluation:', addonData);
			console.log('📤 Current selections being sent:', this.state.selections);

			// Use queue manager if available, otherwise fallback to regular AJAX
			var ajaxMethod = $.ajax;
			if ($.wcPaoAjaxQueue && $.wcPaoAjaxQueue.request) {
				ajaxMethod = function(options) {
					return $.wcPaoAjaxQueue.request(options);
				};
			}
			
			// Make AJAX request to evaluate rules from database
			ajaxMethod({
				url: wc_product_addons_conditional_logic.ajax_url,
				type: 'POST',
				data: {
					action: 'wc_product_addons_evaluate_rules',
					security: wc_product_addons_conditional_logic.nonce,
					product_id: this.state.product.id,
					addon_data: JSON.stringify(addonData),
					selections: JSON.stringify(this.state.selections),
					user_data: JSON.stringify(this.state.user),
					cart_data: JSON.stringify(this.state.cart)
				},
				cancelPrevious: true, // Cancel any pending evaluation requests
				success: function(response) {
					console.log('✅ Rule evaluation response:', response);
					if (response.success) {
						// Check if response includes sequence metadata
						if (response.data && response.data._sequence) {
							console.log('Response sequence:', response.data._sequence);
						}
						self.applyRuleResults(response.data);
					} else {
						// Handle outdated request silently
						if (response.data && response.data.code === 'outdated_request') {
							console.log('⏭️ Skipping outdated request response');
							return; // Don't hide loading or show error
						}
						console.error('❌ Rule evaluation failed:', response.data);
						self.showError(response.data.message || 'Rule evaluation failed');
					}
					// self.hideLoading();
				},
				error: function(xhr, status, error) {
					// Ignore aborted requests (from cancellation)
					if (xhr.statusText !== 'abort') {
						console.error('💥 AJAX error during rule evaluation:', xhr, status, error);
						self.showError('Network error during rule evaluation');
					}
					// self.hideLoading();
				}
			});
		},

		/**
		 * Apply results from rule evaluation
		 */
		applyRuleResults: function(results) {
			var self = this;
			console.log('🎯 Applying rule results:', results);
			
			// Check if we have no actions to apply
			if (!results.actions || results.actions.length === 0) {
				console.log('📋 No actions to apply from rules');
				if (results.message) {
					console.log('ℹ️ Server message:', results.message);
				}
				return;
			}
			
			// Log all loaded rules for debugging
			if (results.rules && results.rules.length > 0) {
				console.log('📋 All loaded rules:', results.rules);
				results.rules.forEach(function(rule, index) {
					console.log(`📝 Rule ${index + 1}: "${rule.name}" - Type: ${rule.rule_type}, Enabled: ${rule.enabled}`);
					console.log('   Conditions:', rule.conditions);
					rule.conditions.forEach(function(condition) {
						if (condition.type === 'addon_selected' && condition.config) {
							console.log('     🎯 Looking for addon:', condition.config.condition_addon, 
								'with value:', condition.config.condition_option);
						}
					});
					console.log('   Actions:', rule.actions);
					rule.actions.forEach(function(action) {
						if (action.config && action.config.action_addon) {
							console.log('     🎬 Action targets addon:', action.config.action_addon);
						}
					});
				});
			}

			// IMPORTANT: First reset all modifications to default state
			this.resetAllModifications();

			if (!results || !results.actions) {
				console.log('⚠️ No actions to apply - all elements reset to default');
				return;
			}

			// Apply each action
			$.each(results.actions, function(index, action) {
				console.log('🔧 Applying action:', action);
				
				switch(action.type) {
					case 'show_addon':
						self.handleAddonVisibility(action.target_addon, true, action);
						break;
					case 'hide_addon':
						self.handleAddonVisibility(action.target_addon, false, action);
						break;
					case 'show_option':
						self.handleOptionVisibility(action.target_addon, action.target_option, true, action);
						break;
					case 'hide_option':
						self.handleOptionVisibility(action.target_addon, action.target_option, false, action);
						break;
					case 'set_price':
						// Handle both new_price and original config for backward compatibility
						var newPrice = action.new_price || (action.original_config && action.original_config.action_price) || 0;
						self.handlePriceChange(action.target_addon, action.target_option, newPrice, action);
						break;
					case 'adjust_price':
						self.handlePriceAdjustment(action.target_addon, action.target_option, action.adjustment, action);
						break;
					case 'make_required':
						self.handleRequirementChange(action.target_addon, true, action);
						break;
					case 'make_optional':
						self.handleRequirementChange(action.target_addon, false, action);
						break;
					case 'set_label':
						self.handleLabelChange(action.target_addon, action.new_label, action);
						break;
					case 'set_description':
						self.handleDescriptionChange(action.target_addon, action.new_description, action);
						break;
					default:
						console.warn('⚠️ Unknown action type:', action.type);
				}
			});

			// Trigger update event
			this.form.trigger('woocommerce-product-addons-rules-applied', [results]);
			console.log('✨ All rule actions applied');
		},

		/**
		 * Reset all modifications to default state
		 */
		resetAllModifications: function() {
			console.log('🔄 Resetting all modifications to default state');
			var self = this;
			
			// Reset all addon visibility
			this.addons.each(function() {
				var addon = $(this);
				// Show all addons that were hidden by conditional logic
				if (addon.hasClass('conditional-logic-hidden')) {
					self.setAddonVisibility(addon, true);
				}
			});
			
			// Reset all option visibility
			$('.addon-option.conditional-logic-hidden, option.conditional-logic-hidden').each(function() {
				var option = $(this);
				option.removeClass('conditional-logic-hidden');
				option.show();
				if (option.is('option')) {
					option.prop('disabled', false);
				} else {
					option.find('input').prop('disabled', false);
				}
			});
			
			// Reset all prices to original values
			var processedAddons = {};
			for (var key in this.originalAddons) {
				var addonData = this.originalAddons[key];
				if (addonData && addonData.element) {
					var elementId = addonData.element.attr('id') || addonData.identifier || addonData.name;
					if (!processedAddons[elementId]) {
						processedAddons[elementId] = true;
						this.resetAddonPrices(addonData.element, addonData.prices);
					}
				}
			}
			
			// Reset all required states
			processedAddons = {};
			for (var key in this.originalAddons) {
				var addonData = this.originalAddons[key];
				if (addonData && addonData.element) {
					var elementId = addonData.element.attr('id') || addonData.identifier || addonData.name;
					if (!processedAddons[elementId]) {
						processedAddons[elementId] = true;
						this.setAddonRequired(addonData.element, addonData.required);
					}
				}
			}
		},

		/**
		 * Reset addon prices to original values
		 */
		resetAddonPrices: function(addon, originalPrices) {
			if (!originalPrices) return;
			
			var self = this;
			addon.find('option, input[type="radio"], input[type="checkbox"]').each(function() {
				var element = $(this);
				var value = element.val();
				if (value && originalPrices[value] !== undefined) {
					var originalPrice = originalPrices[value];
					element.data('price', originalPrice);
					element.data('raw-price', originalPrice);
					element.attr('data-price', originalPrice);
					element.attr('data-raw-price', originalPrice);
					self.updatePriceDisplay(element, originalPrice);
				}
			});
		},

		/**
		 * Handle addon visibility changes
		 */
		handleAddonVisibility: function(addonName, visible, action) {
			console.log(visible ? '👁️ Showing addon:' : '🙈 Hiding addon:', addonName);
			
			// Try original target first if available
			if (action && action.original_target) {
				console.log('  Original target:', action.original_target);
			}
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				
				// Log available addons for debugging
				var self = this;
				console.log('Available addons:');
				this.addons.each(function() {
					var el = $(this);
					var id = self.getAddonIdentifier(el);
					var name = self.getAddonNameFromElement(el);
					console.log('  - ID:', id, 'Name:', name);
				});
				return;
			}

			this.setAddonVisibility(addon, visible);
		},

		/**
		 * Handle option visibility changes
		 */
		handleOptionVisibility: function(addonName, optionValue, visible, action) {
			console.log(visible ? '👁️ Showing option:' : '🙈 Hiding option:', addonName + ' -> ' + optionValue);
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				return;
			}

			if (visible) {
				this.showOptions(addon, [optionValue]);
			} else {
				this.hideOptions(addon, [optionValue]);
			}
		},

		/**
		 * Handle price changes
		 */
		handlePriceChange: function(addonName, optionValue, newPrice, action) {
			console.log('💰 Setting price for:', addonName + ' -> ' + optionValue + ' = ' + newPrice);
			
			var self = this;
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				return;
			}

			// Find the specific option and update its price
			var targetElements = [];
			if (optionValue) {
				// Use OptionMatcher for precise matching
				var options = OptionMatcher.findOptions(addon, optionValue);
				if (options.length > 0) {
					targetElements = $(options);
				} else {
					// Fallback to direct value match
					targetElements = addon.find('option[value="' + optionValue + '"], input[value="' + optionValue + '"]');
				}
			} else {
				targetElements = addon.find('option, input[type="radio"], input[type="checkbox"]');
			}

			targetElements.each(function() {
				var element = $(this);
				element.data('price', newPrice);
				element.data('raw-price', newPrice);
				element.attr('data-price', newPrice);
				element.attr('data-raw-price', newPrice);
				// Store conditional price for form submission
				element.attr('data-conditional-price', newPrice);
				self.updatePriceDisplay(element, newPrice);
			});
			
			// Trigger addon update event
			addon.trigger('woocommerce-product-addon-price-updated');
			this.form.trigger('woocommerce-product-addons-update');
		},

		/**
		 * Handle price adjustments
		 */
		handlePriceAdjustment: function(addonName, optionValue, adjustment, action) {
			console.log('📈 Adjusting price for:', addonName + ' -> ' + optionValue, adjustment);
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				return;
			}

			// Apply price adjustment logic here
			// This would implement the specific adjustment calculation
		},

		/**
		 * Handle requirement changes
		 */
		handleRequirementChange: function(addonName, required, action) {
			console.log(required ? '⚠️ Making required:' : '✅ Making optional:', addonName);
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				return;
			}

			this.setAddonRequired(addon, required);
		},

		/**
		 * Handle label changes
		 */
		handleLabelChange: function(addonName, newLabel, action) {
			console.log('🏷️ Changing label for:', addonName + ' -> ' + newLabel);
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				return;
			}

			addon.find('.addon-name, .addon-label').first().text(newLabel);
		},

		/**
		 * Handle description changes
		 */
		handleDescriptionChange: function(addonName, newDescription, action) {
			console.log('📝 Changing description for:', addonName + ' -> ' + newDescription);
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				return;
			}

			var descElement = addon.find('.addon-description');
			if (descElement.length === 0) {
				addon.find('.addon-name').after('<div class="addon-description"></div>');
				descElement = addon.find('.addon-description');
			}
			descElement.html(newDescription);
		},

		/**
		 * Apply results from condition evaluation (legacy support)
		 */
		applyResults: function(results) {
			console.log('📋 Applying legacy results format');
			// Keep for backward compatibility
			this.applyRuleResults(results);
		},

		/**
		 * Get addon by identifier or name
		 */
		getAddonByName: function(identifier) {
			console.log('🔍 Looking for addon by identifier:', identifier);
			
			var self = this;
			var found = null;
			
			// First try direct data attribute lookup for performance
			found = $('[data-addon-identifier="' + identifier + '"]').first();
			if (found.length > 0) {
				console.log('  ✅ Found by data-addon-identifier:', identifier);
				return found;
			}
			
			// Try other data attributes
			found = $('[data-addon-field-name="' + identifier + '"]').first();
			if (found.length > 0) {
				console.log('  ✅ Found by data-addon-field-name:', identifier);
				return found;
			}
			
			// Try global ID match
			found = $('[data-addon-global-id="' + identifier + '"]').first();
			if (found.length > 0) {
				console.log('  ✅ Found by data-addon-global-id:', identifier);
				return found;
			}
			
			// Search through all addons with flexible matching
			this.addons.each(function() {
				var addon = $(this);
				var addonId = self.getAddonIdentifier(addon);
				var addonName = self.getAddonNameFromElement(addon);
				var globalId = addon.data('addon-global-id');
				
				// Check various matching strategies
				if (addonId === identifier || 
					addonName === identifier ||
					globalId === identifier ||
					self.normalizeAddonName(addonName) === self.normalizeAddonName(identifier) ||
					self.matchesAddonPattern(identifier, addonId, addonName, globalId)) {
					found = addon;
					console.log('  ✅ Found by flexible match:', {
						identifier: addonId,
						name: addonName,
						globalId: globalId
					});
					return false; // Break the loop
				}
			});
			
			if (!found || found.length === 0) {
				console.warn('  ❌ Addon not found:', identifier);
				// Log available addons for debugging
				self.logAvailableAddons();
			}
			
			return found;
		},

		/**
		 * Match addon pattern for flexible identification
		 */
		matchesAddonPattern: function(searchTerm, addonId, addonName, globalId) {
			// Extract base names (remove scope and ID suffixes)
			var searchBase = searchTerm.replace(/_(?:global|product|category)_\d+$/, '');
			var idBase = addonId ? addonId.replace(/_(?:global|product|category)_\d+$/, '') : '';
			var nameBase = addonName ? addonName.replace(/_(?:global|product|category)_\d+$/, '') : '';
			
			console.log('  🔍 Pattern matching:', {
				searchTerm: searchTerm,
				searchBase: searchBase,
				addonId: addonId,
				idBase: idBase,
				addonName: addonName,
				nameBase: nameBase
			});
			
			// Check if bases match (case-insensitive)
			if ((idBase && searchBase.toLowerCase() === idBase.toLowerCase()) || 
				(nameBase && searchBase.toLowerCase() === nameBase.toLowerCase())) {
				console.log('    ✅ Base name match found');
				return true;
			}
			
			// Also try extracting pure base (before scope indicator)
			var searchPure = searchTerm.replace(/^(.*?)_(?:product|global|category).*$/, '$1');
			var idPure = addonId ? addonId.replace(/^(.*?)_(?:product|global|category).*$/, '$1') : '';
			var namePure = addonName ? addonName.replace(/^(.*?)_(?:product|global|category).*$/, '$1') : '';
			
			if (searchPure && ((idPure && searchPure.toLowerCase() === idPure.toLowerCase()) ||
				(namePure && searchPure.toLowerCase() === namePure.toLowerCase()))) {
				console.log('    ✅ Pure base name match found');
				return true;
			}
			
			// Check if search term is contained in any identifier
			if ((addonId && addonId.indexOf(searchTerm) !== -1) ||
				(addonName && addonName.indexOf(searchTerm) !== -1) ||
				(globalId && globalId.toString().indexOf(searchTerm) !== -1)) {
				console.log('    ✅ Contains match found');
				return true;
			}
			
			// Check normalized names
			if (this.normalizeAddonName(searchBase) === this.normalizeAddonName(addonName) ||
				this.normalizeAddonName(searchBase) === this.normalizeAddonName(idBase)) {
				console.log('    ✅ Normalized name match found');
				return true;
			}
			
			return false;
		},

		/**
		 * Log available addons for debugging
		 */
		logAvailableAddons: function() {
			var self = this;
			console.log('📋 Available addons:');
			this.addons.each(function(index) {
				var addon = $(this);
				console.log(`  [${index}]`, {
					identifier: addon.data('addon-identifier'),
					fieldName: addon.data('addon-field-name'),
					name: addon.data('addon-name'),
					globalId: addon.data('addon-global-id'),
					scope: addon.data('addon-scope')
				});
			});
		},

		/**
		 * Normalize addon name for comparison
		 */
		normalizeAddonName: function(name) {
			if (!name) return '';
			return name.toLowerCase()
				.replace(/[^a-z0-9]+/g, '_')
				.replace(/^_+|_+$/g, '');
		},

		/**
		 * Set addon visibility
		 */
		setAddonVisibility: function(addon, visible, animation) {
			animation = animation || { type: 'fade', duration: 300 };
			
			console.log('🎬 Setting addon visibility:', {
				addon: addon.data('addon-identifier') || addon.data('addon-name'),
				visible: visible,
				currentlyVisible: addon.is(':visible'),
				animation: animation
			});

			if (visible) {
				// Remove any display:none style first
				if (addon.css('display') === 'none') {
					addon.css('display', '');
				}
				
				switch (animation.type) {
					case 'slide':
						addon.slideDown(animation.duration);
						break;
					case 'slide_fade':
						addon.css('opacity', 0).slideDown(animation.duration).animate({ opacity: 1 }, animation.duration);
						break;
					default:
						addon.fadeIn(animation.duration);
				}

				// Enable inputs
				addon.find('input, select, textarea').prop('disabled', false);
				
				// Remove hidden class if present
				addon.removeClass('wc-pao-addon-hidden conditional-logic-hidden');
			} else {
				switch (animation.type) {
					case 'slide':
						addon.slideUp(animation.duration);
						break;
					case 'slide_fade':
						addon.animate({ opacity: 0 }, animation.duration / 2).slideUp(animation.duration / 2);
						break;
					default:
						addon.fadeOut(animation.duration);
				}

				// Disable inputs to prevent validation
				addon.find('input, select, textarea').prop('disabled', true);
				
				// Add hidden class
				addon.addClass('wc-pao-addon-hidden conditional-logic-hidden');
			}
			
			// Trigger event
			addon.trigger('wc-pao-addon-visibility-changed', [visible]);
		},

		/**
		 * Set addon required status
		 */
		setAddonRequired: function(addon, required) {
			var inputs = addon.find('input, select, textarea');
			
			inputs.prop('required', required);
			
			// Update visual indicators
			if (required) {
				addon.addClass('required-addon');
				addon.find('.addon-name').append('<span class="required">*</span>');
			} else {
				addon.removeClass('required-addon');
				addon.find('.addon-name .required').remove();
			}
		},

		/**
		 * Apply price modifications
		 */
		applyPriceModifications: function(addon, modifiers) {
			var self = this;

			$.each(modifiers, function(index, modifier) {
				if (modifier.target === 'self' || modifier.target === 'all') {
					self.modifyAddonPrices(addon, modifier);
				}
			});
		},

		/**
		 * Modify addon prices
		 */
		modifyAddonPrices: function(addon, modifier) {
			var self = this;
			var originalPrices = this.originalAddons[addon.data('addon-name')].prices;

			// Modify option prices
			addon.find('option, input[type="radio"], input[type="checkbox"]').each(function() {
				var element = $(this);
				var originalPrice = originalPrices[element.val()] || 0;
				var newPrice = self.calculateModifiedPrice(originalPrice, modifier.modification);

				// Update data attribute
				element.data('price', newPrice);

				// Update visible price text
				self.updatePriceDisplay(element, newPrice);
			});

			// Trigger addon update
			addon.trigger('woocommerce-product-addon-prices-updated');
		},

		/**
		 * Calculate modified price
		 */
		calculateModifiedPrice: function(originalPrice, modification) {
			if (!modification) return originalPrice;

			var method = modification.method || modification.type;
			var value = parseFloat(modification.value) || 0;

			switch (method) {
				case 'add':
					return originalPrice + value;
				case 'subtract':
					return originalPrice - value;
				case 'multiply':
					return originalPrice * value;
				case 'divide':
					return value !== 0 ? originalPrice / value : originalPrice;
				case 'set':
					return value;
				case 'percentage_add':
					return originalPrice * (1 + value / 100);
				case 'percentage_subtract':
					return originalPrice * (1 - value / 100);
				default:
					return originalPrice;
			}
		},

		/**
		 * Update price display
		 */
		updatePriceDisplay: function(element, price) {
			var priceHtml = '';
			
			if (price > 0) {
				priceHtml = ' (+' + this.formatPrice(price) + ')';
			} else if (price < 0) {
				priceHtml = ' (' + this.formatPrice(price) + ')';
			}

			// Update option text
			if (element.is('option')) {
				var text = element.text();
				text = text.replace(/\s*\([+-]?[^)]+\)\s*$/, ''); // Remove old price
				element.text(text + priceHtml);
			} else {
				// Update label for radio/checkbox
				var label = element.next('label');
				if (label.length === 0) {
					label = element.closest('label');
				}
				
				if (label.length > 0) {
					var labelHtml = label.html();
					labelHtml = labelHtml.replace(/<span class="addon-price">.*?<\/span>/, '');
					label.html(labelHtml + '<span class="addon-price">' + priceHtml + '</span>');
				}
			}
		},

		/**
		 * Apply option modifications
		 */
		applyOptionModifications: function(addon, modifiers) {
			// Show options
			if (modifiers.show) {
				this.showOptions(addon, modifiers.show);
			}

			// Hide options
			if (modifiers.hide) {
				this.hideOptions(addon, modifiers.hide);
			}

			// Disable options
			if (modifiers.disable) {
				this.disableOptions(addon, modifiers.disable);
			}

			// Enable options
			if (modifiers.enable) {
				this.enableOptions(addon, modifiers.enable);
			}
		},

		/**
		 * Show specific options
		 */
		showOptions: function(addon, optionValues) {
			var self = this;
			$.each(optionValues, function(index, value) {
				console.log('  🔍 Trying to show option with value:', value);
				
				// Use precise option matcher
				var options = OptionMatcher.findOptions(addon, value);
				
				if (options.length > 0) {
					console.log('    ✅ Found ' + options.length + ' matching option(s)');
					options.forEach(function($option) {
						console.log('      Showing option:', $option.val(), '(' + $option.text() + ')');
						$option.show();
						$option.prop('disabled', false);
					});
				} else {
					console.log('    ❌ No matching options found');
				}
				
				// For radio/checkbox inputs
				addon.find('input[value="' + value + '"]').closest('.addon-option').show();
				addon.find('input[value^="' + value + '-"]').closest('.addon-option').show();
				addon.find('input[data-option-key="' + value + '"]').closest('.addon-option').show();
				
				// Check if select uses select2 and trigger update
				var select = addon.find('select');
				if (select.length > 0 && select.hasClass('select2-hidden-accessible')) {
					select.trigger('change.select2');
				}
			});
		},

		/**
		 * Hide specific options
		 */
		hideOptions: function(addon, optionValues) {
			var self = this;
			$.each(optionValues, function(index, value) {
				console.log('  🔍 Trying to hide option with value:', value);
				
				// Use precise option matcher
				var options = OptionMatcher.findOptions(addon, value);
				
				if (options.length > 0) {
					console.log('    ✅ Found ' + options.length + ' matching option(s)');
					options.forEach(function($option) {
						console.log('      Hiding option:', $option.val(), '(' + $option.text() + ')');
						$option.addClass('conditional-logic-hidden');
						$option.hide();
						$option.prop('disabled', true);
						
						// If currently selected, deselect it
						if ($option.is(':selected')) {
							var select = $option.closest('select');
							select.val('').trigger('change');
						}
					});
				} else {
					console.log('    ❌ No matching options found');
				}
				
				// For radio/checkbox inputs
				addon.find('input[value="' + value + '"]').closest('.addon-option').addClass('conditional-logic-hidden').hide();
				addon.find('input[value^="' + value + '-"]').closest('.addon-option').addClass('conditional-logic-hidden').hide();
				addon.find('input[data-option-key="' + value + '"]').closest('.addon-option').addClass('conditional-logic-hidden').hide();
				
				// Disable the inputs themselves
				addon.find('input[value="' + value + '"], input[value^="' + value + '-"], input[data-option-key="' + value + '"]')
					.prop('disabled', true)
					.prop('checked', false);
				
				// Check if select uses select2 and trigger update
				var select = addon.find('select');
				if (select.length > 0 && select.hasClass('select2-hidden-accessible')) {
					select.trigger('change.select2');
				}
			});
		},

		/**
		 * Disable specific options
		 */
		disableOptions: function(addon, optionValues) {
			$.each(optionValues, function(index, value) {
				// Direct value match
				addon.find('option[value="' + value + '"]').prop('disabled', true);
				addon.find('input[value="' + value + '"]').prop('disabled', true);
				
				// Try with -N suffix pattern
				addon.find('option[value^="' + value + '-"]').prop('disabled', true);
				addon.find('input[value^="' + value + '-"]').prop('disabled', true);
				
				// Try by data-label or data-option-key
				addon.find('option[data-label="' + value + '"], option[data-option-key="' + value + '"]').prop('disabled', true);
			});
		},

		/**
		 * Enable specific options
		 */
		enableOptions: function(addon, optionValues) {
			$.each(optionValues, function(index, value) {
				// Direct value match
				addon.find('option[value="' + value + '"]').prop('disabled', false);
				addon.find('input[value="' + value + '"]').prop('disabled', false);
				
				// Try with -N suffix pattern
				addon.find('option[value^="' + value + '-"]').prop('disabled', false);
				addon.find('input[value^="' + value + '-"]').prop('disabled', false);
				
				// Try by data-label or data-option-key
				addon.find('option[data-label="' + value + '"], option[data-option-key="' + value + '"]').prop('disabled', false);
			});
		},

		/**
		 * Apply modifications (text, display, etc.)
		 */
		applyModifications: function(addon, modifications) {
			// Text modifications
			if (modifications.text) {
				this.applyTextModifications(addon, modifications.text);
			}

			// Display modifications
			if (modifications.display) {
				this.applyDisplayModifications(addon, modifications.display);
			}

			// CSS modifications
			if (modifications.css) {
				this.applyCSSModifications(addon, modifications.css);
			}
		},

		/**
		 * Apply text modifications
		 */
		applyTextModifications: function(addon, textMods) {
			if (textMods.label) {
				addon.find('.addon-name').text(textMods.label.value || textMods.label);
			}

			if (textMods.description) {
				var desc = addon.find('.addon-description');
				if (desc.length === 0) {
					addon.find('.addon-name').after('<div class="addon-description"></div>');
					desc = addon.find('.addon-description');
				}
				desc.html(textMods.description.value || textMods.description);
			}
		},

		/**
		 * Show message
		 */
		showMessage: function(addon, messageConfig) {
			var message = $('<div class="addon-conditional-message"></div>')
				.addClass('message-' + messageConfig.type)
				.text(messageConfig.text);

			if (messageConfig.position === 'inline') {
				addon.prepend(message);
			} else if (messageConfig.position === 'tooltip') {
				// TODO: Implement tooltip
			}

			// Auto-hide after 5 seconds
			setTimeout(function() {
				message.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Extract prices from addon
		 */
		extractPrices: function(addon) {
			var prices = {};
			
			try {
				addon.find('option, input[type="radio"], input[type="checkbox"]').each(function() {
					var element = $(this);
					var val = element.val();
					if (val) {
						// Try multiple attributes to get the price
						var price = parseFloat(element.data('price')) || 
								   parseFloat(element.data('raw-price')) ||
								   parseFloat(element.attr('data-price')) ||
								   parseFloat(element.attr('data-raw-price')) || 0;
						prices[val] = price;
					}
				});
			} catch (e) {
				console.warn('Error extracting prices:', e);
			}

			return prices;
		},

		/**
		 * Get product data
		 */
		getProductData: function() {
			// Try multiple ways to get the product ID
			var productId = null;
			
			// Method 1: Standard add-to-cart input
			productId = $('input[name="add-to-cart"]').val();
			
			// Method 2: Single add to cart button value
			if (!productId) {
				productId = $('.single_add_to_cart_button').val();
			}
			
			// Method 3: From form action or data attributes
			if (!productId) {
				var form = $('form.cart');
				if (form.length) {
					// Try data-product_id attribute
					productId = form.data('product_id');
					
					// Try to extract from form action URL
					if (!productId) {
						var action = form.attr('action');
						if (action) {
							var match = action.match(/add-to-cart=(\d+)/);
							if (match) {
								productId = match[1];
							}
						}
					}
				}
			}
			
			// Method 4: Check variation form
			if (!productId) {
				productId = $('.variations_form').data('product_id');
			}
			
			// Method 5: Global JS variable (often set by WooCommerce)
			if (!productId && typeof wc_product_addons_params !== 'undefined' && wc_product_addons_params.post_id) {
				productId = wc_product_addons_params.post_id;
			}
			
			// Method 6: Try to find product ID from the page URL
			if (!productId) {
				var urlMatch = window.location.href.match(/[?&]p=(\d+)/);
				if (!urlMatch) {
					urlMatch = window.location.href.match(/product\/[^\/]+\/?(?:\?.*)?$/);
					if (urlMatch) {
						// Try to extract from post ID in body class
						var bodyClasses = $('body').attr('class');
						if (bodyClasses) {
							var postIdMatch = bodyClasses.match(/postid-(\d+)/);
							if (postIdMatch) {
								productId = postIdMatch[1];
							}
						}
					}
				} else {
					productId = urlMatch[1];
				}
			}
			
			// Method 7: Try data attributes on addon containers
			if (!productId) {
				var addonContainer = $('.wc-pao-addon-container').first();
				if (addonContainer.length) {
					productId = addonContainer.data('product-id') || addonContainer.closest('form').find('[name="product_id"]').val();
				}
			}
			
			// Method 8: Last resort - hardcode for testing (should be removed in production)
			if (!productId) {
				console.warn('⚠️ Could not detect product ID, using test ID 140');
				productId = '140'; // Test product ID
			}
			
			console.log('🏷️ Detected product ID:', productId);
			
			return {
				id: productId,
				price: parseFloat($('.single_variation_wrap .woocommerce-variation-price .amount').text().replace(/[^0-9.-]+/g, '')) || 
					   parseFloat($('.summary .price .amount').first().text().replace(/[^0-9.-]+/g, ''))
			};
		},

		/**
		 * Get cart data
		 */
		getCartData: function() {
			// This would be populated from server-side data
			return window.wc_product_addons_cart_data || {};
		},

		/**
		 * Get user data
		 */
		getUserData: function() {
			// This would be populated from server-side data
			return window.wc_product_addons_user_data || {};
		},

		/**
		 * Format price
		 */
		formatPrice: function(price) {
			// Try woocommerce-product-addons params first (most reliable)
			if (typeof woocommerce_product_addons_params !== 'undefined') {
				var params = woocommerce_product_addons_params;
				
				// Use accounting.js if available
				if (typeof accounting !== 'undefined') {
					return accounting.formatMoney(price, {
						symbol: params.currency_format_symbol || '₪',
						decimal: params.currency_format_decimal_sep || '.',
						thousand: params.currency_format_thousand_sep || ',',
						precision: params.currency_format_num_decimals || 2,
						format: params.currency_format || '%s%v'
					});
				}
				
				// Manual formatting
				var formatted = price.toFixed(params.currency_format_num_decimals || 2);
				return params.currency_format_symbol + formatted;
			}
			
			// Fallback: try woocommerce_addons_params (older versions)
			if (typeof woocommerce_addons_params !== 'undefined') {
				return accounting.formatMoney(price, {
					symbol: woocommerce_addons_params.currency_symbol || '₪',
					decimal: woocommerce_addons_params.decimal_separator || '.',
					thousand: woocommerce_addons_params.thousand_separator || ',',
					precision: woocommerce_addons_params.decimals || 2,
					format: woocommerce_addons_params.price_format || '%s%v'
				});
			}
			
			// Last resort: try to detect from existing prices on page
			var existingPrice = $('.amount').first().text();
			var currencyMatch = existingPrice.match(/^([^\d\s.,]+)/);
			if (currencyMatch && currencyMatch[1]) {
				return currencyMatch[1] + price.toFixed(2);
			}
			
			// Default fallback
			return '₪' + price.toFixed(2);
		},

		/**
		 * Show loading state
		 */
		showLoading: function() {
			this.form.addClass('processing');
		},

		/**
		 * Hide loading state
		 */
		hideLoading: function() {
			this.form.removeClass('processing');
		},

		/**
		 * Show error message
		 */
		showError: function(message) {
			console.error('Conditional Logic Error:', message);
			// You can implement a user-friendly error display here
			// For now, just log it
		},
		
		/**
		 * Add conditional prices to form before submission
		 */
		addConditionalPricesToForm: function() {
			var self = this;
			
			// Remove any existing conditional price inputs
			this.form.find('input[name^="conditional_price_"]').remove();
			
			// Get all current price modifications
			var priceModifications = {};
			
			// Check each addon for price modifications
			this.addons.each(function() {
				var addon = $(this);
				var identifier = self.getAddonIdentifier(addon);
				
				if (!identifier) return;
				
				// Check for modified prices on options
				addon.find('option[data-conditional-price], input[data-conditional-price]').each(function() {
					var element = $(this);
					var optionValue = element.val();
					var conditionalPrice = element.attr('data-conditional-price');
					
					if (optionValue && conditionalPrice !== undefined) {
						var fieldName = element.closest('select, input').attr('name');
						if (fieldName) {
							priceModifications[fieldName + '_' + optionValue] = conditionalPrice;
						}
					}
				});
			});
			
			// Add hidden inputs for each price modification
			$.each(priceModifications, function(key, price) {
				$('<input>').attr({
					type: 'hidden',
					name: 'conditional_price_' + key,
					value: price
				}).appendTo(self.form);
			});
			
			console.log('💰 Added conditional prices to form:', priceModifications);
		}
	};

	// Try multiple initialization methods to ensure it works
	function initializeConditionalLogic() {
		console.log('🚀 WC Product Addons Conditional Logic script loaded');
		console.log('Available parameters:', window.wc_product_addons_conditional_logic);
		
		// Check if we have the necessary elements
		var $form = $('form.cart');
		var $addons = $('.product-addon, .wc-pao-addon, .addon, .product-addon-field');
		
		console.log('Form found:', $form.length > 0);
		console.log('Addons found:', $addons.length);
		console.log('Current URL:', window.location.href);
		console.log('Page type checks:', {
			hasCartForm: $form.length > 0,
			hasAddons: $addons.length > 0,
			hasWooCommerce: typeof woocommerce_params !== 'undefined',
			hasJQuery: typeof jQuery !== 'undefined'
		});
		
		if ($form.length === 0) {
			console.warn('⚠️ No cart form found - conditional logic will not work');
			return false;
		}
		
		if ($addons.length === 0) {
			console.warn('⚠️ No product addons found - conditional logic will not work');
			console.log('🔍 Checking DOM for any addon-like elements...');
			console.log('All form elements:', $('form.cart').find('input, select, textarea').length);
			return false;
		}
		
		console.log('✅ Initializing conditional logic...');
		WC_Product_Addons_Conditional_Logic.init();
		return true;
	}

	// Track initialization state
	var isInitialized = false;
	
	// Try on document ready
	$(document).ready(function() {
		console.log('📄 Document ready - attempting initialization...');
		
		// Prevent multiple initializations
		if (isInitialized) {
			console.log('⚠️ Already initialized, skipping...');
			return;
		}
		
		if (!initializeConditionalLogic()) {
			// If initialization failed, try again after a delay
			console.log('⏰ Retrying initialization in 500ms...');
			setTimeout(function() {
				if (!isInitialized) {
					initializeConditionalLogic();
				}
			}, 500);
		} else {
			isInitialized = true;
		}
	});

	// Also try when window is fully loaded
	$(window).on('load', function() {
		console.log('🌐 Window loaded - checking if conditional logic is initialized...');
		if (!isInitialized && (!WC_Product_Addons_Conditional_Logic.form || WC_Product_Addons_Conditional_Logic.form.length === 0)) {
			console.log('⏰ Retrying initialization after window load...');
			if (initializeConditionalLogic()) {
				isInitialized = true;
			}
		}
	});

	// Export for global access
	window.WC_Product_Addons_Conditional_Logic = WC_Product_Addons_Conditional_Logic;

})(jQuery);