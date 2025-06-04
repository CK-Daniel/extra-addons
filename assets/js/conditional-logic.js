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
	 * Conditional Logic Engine
	 */
	var WC_Product_Addons_Conditional_Logic = {
		
		/**
		 * Initialize
		 */
		init: function() {
			console.log('🔧 Initializing WC Product Addons Conditional Logic...');
			
			this.form = $('form.cart');
			this.addons = $('.product-addon, .wc-pao-addon');
			this.cache = {};
			this.debounceTimer = null;
			this.evaluationQueue = [];
			
			console.log('🔍 Found elements:');
			console.log('  - Cart form:', this.form.length);
			console.log('  - Product addons:', this.addons.length);
			
			// Try alternative selectors if standard ones don't work
			if (this.addons.length === 0) {
				console.log('🔍 Trying alternative addon selectors...');
				this.addons = $('.addon, .product-addon-field, .form-row');
				console.log('  - Alternative addons found:', this.addons.length);
			}
			
			if (this.form.length === 0 || this.addons.length === 0) {
				console.warn('⚠️ Missing required elements - conditional logic disabled');
				return;
			}

			console.log('✅ Required elements found, proceeding with initialization...');
			this.bindEvents();
			this.initializeState();
			
			// Wait a bit for DOM to be fully ready, then evaluate
			setTimeout(() => {
				this.evaluateAllConditions();
			}, 100);
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
		},

		/**
		 * Initialize state
		 */
		initializeState: function() {
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
				
				// Try multiple ways to get the addon name based on the HTML structure
				var name = null;
				
				// Method 1: Check data-addon-name on the container
				name = addon.data('addon-name');
				
				// Method 2: Check data-addon-name on label inside
				if (!name) {
					name = addon.find('label[data-addon-name]').data('addon-name');
				}
				
				// Method 3: Check wc-pao-addon-name class text
				if (!name) {
					var labelText = addon.find('.wc-pao-addon-name').text();
					if (labelText) {
						// Clean up the label text (remove required asterisk, etc.)
						name = labelText.replace(/\s*\*\s*$/, '').replace(/\s+$/, '').trim();
					}
				}
				
				// Method 4: Extract from CSS classes (e.g., wc-pao-addon-test)
				if (!name) {
					var classes = addon.attr('class') || '';
					var match = classes.match(/wc-pao-addon-([a-zA-Z0-9_-]+)(?:\s|$)/);
					if (match && match[1] && match[1] !== 'container') {
						name = match[1];
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
				
				console.log('🔍 Addon detection methods:', {
					'data-addon-name (container)': addon.data('addon-name'),
					'data-addon-name (label)': addon.find('label[data-addon-name]').data('addon-name'),
					'label text': addon.find('.wc-pao-addon-name').text().trim(),
					'css classes': addon.attr('class'),
					'field name': addon.find('input, select, textarea').first().attr('name'),
					'final name': name
				});
				
				if (name) {
					console.log('  ✅ Found addon:', name);
					this.originalAddons[name] = {
						element: addon,
						data: addon.data(),
						required: addon.find('input, select, textarea').prop('required'),
						prices: this.extractPrices(addon)
					};
				} else {
					console.warn('  ❌ Addon found but no name detected:', addon[0]);
				}
			}.bind(this));
			
			console.log('📋 Total addons cataloged:', Object.keys(this.originalAddons).length);
			console.log('📋 Addon names found:', Object.keys(this.originalAddons));
		},

		/**
		 * Handle field change
		 */
		handleFieldChange: function(field) {
			var addon = field.closest('.wc-pao-addon, .product-addon');
			
			// Use our improved name detection
			var addonName = this.getAddonNameFromElement(addon);
			
			console.log('🔄 Field changed in addon:', addonName, 'value:', this.getFieldValue(field));
			
			if (addonName) {
				// Update state
				this.updateSelection(addonName, field);
				
				// Debounce evaluation
				this.debounceEvaluation();
			}
		},

		/**
		 * Get addon name from element
		 */
		getAddonNameFromElement: function(addon) {
			if (!addon || addon.length === 0) return null;
			
			// Try multiple methods (same as in cataloging)
			var name = addon.data('addon-name') || 
				addon.find('label[data-addon-name]').data('addon-name') ||
				addon.find('.wc-pao-addon-name').text().replace(/\s*\*\s*$/, '').trim();
				
			// Try CSS class matching
			if (!name) {
				var classes = addon.attr('class') || '';
				var match = classes.match(/wc-pao-addon-([a-zA-Z0-9_-]+)(?:\s|$)/);
				if (match && match[1] && match[1] !== 'container') {
					name = match[1];
				}
			}
			
			// Try field name extraction
			if (!name) {
				var field = addon.find('input, select, textarea').first();
				if (field.length) {
					var fieldName = field.attr('name') || field.attr('id') || '';
					var match = fieldName.match(/addon-\d+-([^-]+)-/);
					if (match && match[1]) {
						name = match[1];
					}
				}
			}
			
			return name;
		},

		/**
		 * Handle field input (for text fields)
		 */
		handleFieldInput: function(field) {
			var addon = field.closest('.wc-pao-addon, .product-addon');
			var addonName = this.getAddonNameFromElement(addon);
			
			if (addonName) {
				// Update state
				this.updateSelection(addonName, field);
				
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
		updateSelection: function(addonName, field) {
			var value = this.getFieldValue(field);
			var label = this.getFieldLabel(field);
			var price = this.getFieldPrice(field);

			if (value !== null) {
				this.state.selections[addonName] = {
					value: value,
					label: label,
					price: price,
					element: field
				};
			} else {
				delete this.state.selections[addonName];
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
			delay = delay || 300;
			
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

			// Show loading state
			this.showLoading();

			// Prepare addon data for evaluation using our cataloged addons
			var addonData = [];
			
			for (var addonName in this.originalAddons) {
				var addonInfo = this.originalAddons[addonName];
				var addon = addonInfo.element;
				
				console.log('🔧 Preparing data for addon:', addonName);
				
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

				addonData.push({
					name: addonName,
					id: addonName, // Use the cleaned name as ID
					options: options,
					current_value: self.state.selections[addonName] ? self.state.selections[addonName].value : null
				});
			}

			console.log('📊 Addon data prepared for evaluation:', addonData);

			// Make AJAX request to evaluate rules from database
			$.ajax({
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
				success: function(response) {
					console.log('✅ Rule evaluation response:', response);
					if (response.success) {
						self.applyRuleResults(response.data);
					} else {
						console.error('❌ Rule evaluation failed:', response.data);
					}
					self.hideLoading();
				},
				error: function(xhr, status, error) {
					console.error('💥 AJAX error during rule evaluation:', xhr, status, error);
					self.hideLoading();
				}
			});
		},

		/**
		 * Apply results from rule evaluation
		 */
		applyRuleResults: function(results) {
			var self = this;
			console.log('🎯 Applying rule results:', results);

			if (!results || !results.actions) {
				console.log('⚠️ No actions to apply');
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
						self.handlePriceChange(action.target_addon, action.target_option, action.new_price, action);
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
		 * Handle addon visibility changes
		 */
		handleAddonVisibility: function(addonName, visible, action) {
			console.log(visible ? '👁️ Showing addon:' : '🙈 Hiding addon:', addonName);
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
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
			
			var addon = this.getAddonByName(addonName);
			if (!addon || addon.length === 0) {
				console.warn('⚠️ Addon not found:', addonName);
				return;
			}

			// Find the specific option and update its price
			var targetElements = [];
			if (optionValue) {
				targetElements = addon.find('option[value="' + optionValue + '"], input[value="' + optionValue + '"]');
			} else {
				targetElements = addon.find('option, input[type="radio"], input[type="checkbox"]');
			}

			targetElements.each(function() {
				var element = $(this);
				element.data('price', newPrice);
				self.updatePriceDisplay(element, newPrice);
			});
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
		 * Get addon by name
		 */
		getAddonByName: function(name) {
			console.log('🔍 Looking for addon by name:', name);
			
			// First try our cataloged addons
			if (this.originalAddons && this.originalAddons[name]) {
				console.log('  ✅ Found in catalog:', name);
				return this.originalAddons[name].element;
			}
			
			// Fallback to searching through DOM
			var found = null;
			this.addons.each(function() {
				var addon = $(this);
				
				// Try multiple name detection methods
				var addonName = addon.data('addon-name') || 
					addon.find('label[data-addon-name]').data('addon-name') ||
					addon.find('.wc-pao-addon-name').text().replace(/\s*\*\s*$/, '').trim();
					
				// Also try CSS class matching
				if (!addonName) {
					var classes = addon.attr('class') || '';
					var match = classes.match(/wc-pao-addon-([a-zA-Z0-9_-]+)(?:\s|$)/);
					if (match && match[1] && match[1] !== 'container') {
						addonName = match[1];
					}
				}
				
				console.log('  🔍 Checking addon:', addonName, 'against target:', name);
				
				if (addonName === name || addonName === name.toLowerCase() || addonName === name.replace(/[^a-zA-Z0-9]/g, '')) {
					found = addon;
					console.log('  ✅ Found match:', addonName);
					return false; // Break the loop
				}
			});
			
			if (!found) {
				console.warn('  ❌ Addon not found:', name);
				console.log('  📋 Available addons:', Object.keys(this.originalAddons || {}));
			}
			
			return found;
		},

		/**
		 * Set addon visibility
		 */
		setAddonVisibility: function(addon, visible, animation) {
			animation = animation || { type: 'fade', duration: 300 };

			if (visible) {
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
			}
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
			$.each(optionValues, function(index, value) {
				addon.find('option[value="' + value + '"]').show();
				addon.find('input[value="' + value + '"]').closest('.addon-option').show();
			});
		},

		/**
		 * Hide specific options
		 */
		hideOptions: function(addon, optionValues) {
			$.each(optionValues, function(index, value) {
				addon.find('option[value="' + value + '"]').hide();
				addon.find('input[value="' + value + '"]').closest('.addon-option').hide();
			});
		},

		/**
		 * Disable specific options
		 */
		disableOptions: function(addon, optionValues) {
			$.each(optionValues, function(index, value) {
				addon.find('option[value="' + value + '"]').prop('disabled', true);
				addon.find('input[value="' + value + '"]').prop('disabled', true);
			});
		},

		/**
		 * Enable specific options
		 */
		enableOptions: function(addon, optionValues) {
			$.each(optionValues, function(index, value) {
				addon.find('option[value="' + value + '"]').prop('disabled', false);
				addon.find('input[value="' + value + '"]').prop('disabled', false);
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

			addon.find('option, input[type="radio"], input[type="checkbox"]').each(function() {
				var element = $(this);
				prices[element.val()] = parseFloat(element.data('price')) || 0;
			});

			return prices;
		},

		/**
		 * Get product data
		 */
		getProductData: function() {
			return {
				id: $('input[name="add-to-cart"]').val() || $('.single_add_to_cart_button').val(),
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
			// Use WooCommerce price format
			if (typeof accounting !== 'undefined') {
				return accounting.formatMoney(price, {
					symbol: woocommerce_addons_params.currency_symbol,
					decimal: woocommerce_addons_params.decimal_separator,
					thousand: woocommerce_addons_params.thousand_separator,
					precision: woocommerce_addons_params.decimals,
					format: woocommerce_addons_params.price_format
				});
			}
			
			return woocommerce_addons_params.currency_symbol + price.toFixed(2);
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

	// Try on document ready
	$(document).ready(function() {
		console.log('📄 Document ready - attempting initialization...');
		if (!initializeConditionalLogic()) {
			// If initialization failed, try again after a delay
			console.log('⏰ Retrying initialization in 500ms...');
			setTimeout(function() {
				initializeConditionalLogic();
			}, 500);
		}
	});

	// Also try when window is fully loaded
	$(window).on('load', function() {
		console.log('🌐 Window loaded - checking if conditional logic is initialized...');
		if (!WC_Product_Addons_Conditional_Logic.form || WC_Product_Addons_Conditional_Logic.form.length === 0) {
			console.log('⏰ Retrying initialization after window load...');
			initializeConditionalLogic();
		}
	});

	// Export for global access
	window.WC_Product_Addons_Conditional_Logic = WC_Product_Addons_Conditional_Logic;

})(jQuery);