/**
 * WooCommerce Product Add-ons Conditional Logic Admin
 * Simplified and improved conditional logic interface
 */

(function($) {
    'use strict';

    var WC_Product_Addons_Conditional_Logic_Admin = {
        
        // Current rule being edited
        currentRule: null,
        editingRuleId: null,
        
        // Cache for addons data
        addonsCache: {},
        addonsData: {},
        currentContext: 'all',
        currentProductId: 0,
        
        // Condition group counter
        conditionGroupCounter: 0,
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initializeSelect2();
            this.initializeTabs();
            this.loadExistingRules();
            this.loadAddonsData();
            this.initializeRuleBuilder();
        },
        
        // Bind all events
        bindEvents: function() {
            var self = this;
            
            // Rule scope selector
            $('input[name="rule_scope"]').on('change', function() {
                self.handleScopeChange($(this).val());
            });
            
            // Add condition/action buttons
            $(document).on('click', '.add-condition', function(e) {
                e.preventDefault();
                self.addConditionGroup();
            });
            
            $(document).on('click', '.add-condition-group', function(e) {
                e.preventDefault();
                self.addConditionGroup();
            });
            
            $(document).on('click', '.add-condition-to-group', function(e) {
                e.preventDefault();
                var groupId = $(this).closest('.condition-group').data('group-id');
                self.addConditionToGroup(groupId);
            });
            
            $(document).on('click', '.remove-group', function(e) {
                e.preventDefault();
                var groupId = $(this).closest('.condition-group').data('group-id');
                self.removeConditionGroup(groupId);
            });
            
            $(document).on('click', '.add-action', function(e) {
                e.preventDefault();
                self.addAction();
            });
            
            // Remove condition/action buttons
            $(document).on('click', '.remove-condition', function(e) {
                e.preventDefault();
                $(this).closest('.condition-item').fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            $(document).on('click', '.remove-action', function(e) {
                e.preventDefault();
                $(this).closest('.action-item').fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            // Condition type change
            $(document).on('change', '.condition-type', function() {
                self.updateConditionConfig($(this));
            });
            
            // Action type change
            $(document).on('change', '.action-type', function() {
                self.updateActionConfig($(this));
            });
            
            // Handle addon selection to populate options
            $(document).on('change', '.addon-select', function() {
                self.updateAddonOptions($(this));
            });
            
            // Addon context change
            $('input[name="addon_context"]').on('change', function() {
                self.handleAddonContextChange($(this).val());
            });
            
            // Product context selection
            $(document).on('change', '.wc-product-search-context', function() {
                self.currentProductId = $(this).val();
                self.loadAddonsData();
            });
            
            // Target level change (addon vs option)
            $(document).on('change', '.target-level', function() {
                self.updateTargetLevelDisplay($(this));
            });
            
            // Save rule button
            $('.save-rule').on('click', function(e) {
                e.preventDefault();
                self.saveRule();
            });
            
            // Cancel rule button
            $('.cancel-rule').on('click', function(e) {
                e.preventDefault();
                self.resetRuleBuilder();
            });
            
            // Filter tabs
            $('.tab-button').on('click', function() {
                var filter = $(this).data('filter');
                $('.tab-button').removeClass('active');
                $(this).addClass('active');
                self.filterRules(filter);
            });
            
            // Rule actions
            $(document).on('click', '.edit-rule', function(e) {
                e.preventDefault();
                console.log('Edit rule clicked');
                var ruleId = $(this).closest('.rule-item').data('rule-id');
                console.log('Rule ID:', ruleId);
                if (ruleId) {
                    self.editRule(ruleId);
                }
            });
            
            $(document).on('click', '.duplicate-rule', function(e) {
                e.preventDefault();
                console.log('Duplicate rule clicked');
                var ruleId = $(this).closest('.rule-item').data('rule-id');
                console.log('Rule ID:', ruleId);
                if (ruleId) {
                    self.duplicateRule(ruleId);
                }
            });
            
            $(document).on('click', '.toggle-rule', function(e) {
                e.preventDefault();
                console.log('Toggle rule clicked');
                var ruleId = $(this).closest('.rule-item').data('rule-id');
                console.log('Rule ID:', ruleId);
                if (ruleId) {
                    self.toggleRule(ruleId);
                }
            });
            
            $(document).on('click', '.delete-rule', function(e) {
                e.preventDefault();
                var confirmMessage = (typeof wc_product_addons_params !== 'undefined' && wc_product_addons_params.i18n_confirm_delete_rule) 
                    ? wc_product_addons_params.i18n_confirm_delete_rule 
                    : 'Are you sure you want to delete this rule?';
                if (confirm(confirmMessage)) {
                    var ruleId = $(this).closest('.rule-item').data('rule-id');
                    self.deleteRule(ruleId);
                }
            });
        },
        
        // Initialize Select2
        initializeSelect2: function() {
            // Category search
            $('.wc-category-search').select2({
                ajax: {
                    url: wc_product_addons_params.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'wc_product_addons_search_categories',
                            security: wc_product_addons_params.search_categories_nonce,
                            term: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2
            });
            
            // Product search
            $('.wc-product-search').select2({
                ajax: {
                    url: wc_product_addons_params.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'woocommerce_json_search_products',
                            security: wc_product_addons_params.search_products_nonce,
                            term: params.term,
                            exclude_type: 'variable'
                        };
                    },
                    processResults: function(data) {
                        var results = [];
                        $.each(data, function(id, text) {
                            results.push({
                                id: id,
                                text: text
                            });
                        });
                        return {
                            results: results
                        };
                    },
                    cache: true
                },
                minimumInputLength: 3
            });
            
            // Product search for context
            $('.wc-product-search-context').select2({
                ajax: {
                    url: wc_product_addons_params.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'woocommerce_json_search_products',
                            security: wc_product_addons_params.search_products_nonce,
                            term: params.term,
                            exclude_type: 'variable'
                        };
                    },
                    processResults: function(data) {
                        var results = [];
                        $.each(data, function(id, text) {
                            results.push({
                                id: id,
                                text: text
                            });
                        });
                        return {
                            results: results
                        };
                    },
                    cache: true
                },
                minimumInputLength: 3
            });
        },
        
        // Initialize tabs
        initializeTabs: function() {
            var self = this;
            
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                // Update tab appearance
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show/hide tab content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
                
                // Load rules when switching to existing rules tab
                if (target === '#existing-rules') {
                    self.loadExistingRules();
                }
            });
        },
        
        // Add condition group
        addConditionGroup: function() {
            var groupId = 'group-' + (++this.conditionGroupCounter);
            var template = $('#condition-group-template').html();
            var $group = $(template);
            
            $group.attr('data-group-id', groupId);
            $('#condition-groups-container').append($group);
            
            // Add first condition to the group
            this.addConditionToGroup(groupId);
            
            return groupId;
        },
        
        // Add condition to specific group
        addConditionToGroup: function(groupId) {
            var template = $('#condition-template').html();
            var $condition = $(template);
            var conditionId = 'condition-' + Date.now();
            
            $condition.attr('data-condition-id', conditionId);
            $('[data-group-id="' + groupId + '"] .conditions-in-group').append($condition);
            
            return conditionId;
        },
        
        // Remove condition group
        removeConditionGroup: function(groupId) {
            $('[data-group-id="' + groupId + '"]').fadeOut(200, function() {
                $(this).remove();
            });
        },
        
        // Initialize rule builder with default state
        initializeRuleBuilder: function() {
            // Add initial condition group if none exist
            if ($('.condition-group').length === 0) {
                this.addConditionGroup();
            }
        },
        
        // Handle addon context change
        handleAddonContextChange: function(context) {
            this.currentContext = context;
            
            if (context === 'specific_product') {
                $('#product-selector').show();
            } else {
                $('#product-selector').hide();
                this.currentProductId = 0;
            }
            
            // Reload addons data with new context
            this.loadAddonsData();
        },
        
        // Handle scope change
        handleScopeChange: function(scope) {
            $('.scope-target').hide();
            
            if (scope === 'category') {
                $('#category-target').show();
            } else if (scope === 'product') {
                $('#product-target').show();
            }
        },
        
        // Load addons data
        loadAddonsData: function() {
            var self = this;
            var includeProducts = this.currentContext !== 'global_only';
            var productId = this.currentContext === 'specific_product' ? this.currentProductId : 0;
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'GET',
                data: {
                    action: 'wc_product_addons_get_all_addons',
                    security: wc_product_addons_params.get_addons_nonce,
                    include_products: includeProducts,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        self.addonsData = response.data;
                        self.addonsCache = response.data.organized || {};
                        self.updateAddonSelects();
                    }
                }
            });
        },
        
        // Update all addon select elements with new data
        updateAddonSelects: function() {
            var self = this;
            
            $('.addon-select').each(function() {
                var $select = $(this);
                var currentValue = $select.val();
                
                // Clear and rebuild options
                $select.empty();
                $select.append('<option value="">' + wc_product_addons_params.i18n_select_addon + '</option>');
                
                // Add global addons group
                if (self.addonsData.global && Object.keys(self.addonsData.global).length > 0) {
                    var $globalGroup = $('<optgroup label="🌍 Global Add-ons"></optgroup>');
                    $.each(self.addonsData.global, function(key, addon) {
                        $globalGroup.append('<option value="' + key + '">' + addon.name + ' (' + addon.group_name + ')</option>');
                    });
                    $select.append($globalGroup);
                }
                
                // Add product-specific addons groups
                if (self.addonsData.products && Object.keys(self.addonsData.products).length > 0) {
                    $.each(self.addonsData.products, function(productId, productData) {
                        if (productData.addons && Object.keys(productData.addons).length > 0) {
                            var $productGroup = $('<optgroup label="📦 ' + productData.product_name + ' (Product-specific)"></optgroup>');
                            $.each(productData.addons, function(key, addon) {
                                $productGroup.append('<option value="' + key + '">' + addon.name + ' (' + addon.product_name + ')</option>');
                            });
                            $select.append($productGroup);
                        }
                    });
                }
                
                // Restore previous value if it still exists
                if (currentValue && $select.find('option[value="' + currentValue + '"]').length) {
                    $select.val(currentValue);
                }
            });
        },
        
        // Add new condition
        addCondition: function() {
            var template = $('#condition-template').html();
            var conditionId = 'condition_' + Date.now();
            template = template.replace(/data-condition-id=""/g, 'data-condition-id="' + conditionId + '"');
            
            $('#conditions-container').append(template);
        },
        
        // Add new action
        addAction: function() {
            var template = $('#action-template').html();
            var actionId = 'action_' + Date.now();
            template = template.replace(/data-action-id=""/g, 'data-action-id="' + actionId + '"');
            
            $('#actions-container').append(template);
        },
        
        // Update condition configuration based on type
        updateConditionConfig: function($select) {
            var type = $select.val();
            var $config = $select.closest('.condition-row').find('.condition-config');
            var html = '';
            
            switch(type) {
                case 'addon_field':
                    html = this.getAddonFieldConditionConfig();
                    break;
                case 'addon_selected':
                    html = this.getAddonSelectedConditionConfig();
                    break;
                case 'product_price':
                    html = this.getProductPriceConditionConfig();
                    break;
                case 'product_stock':
                    html = this.getProductStockConditionConfig();
                    break;
                case 'product_category':
                    html = this.getProductCategoryConditionConfig();
                    break;
                case 'cart_total':
                    html = this.getCartTotalConditionConfig();
                    break;
                case 'cart_quantity':
                    html = this.getCartQuantityConditionConfig();
                    break;
                case 'user_role':
                    html = this.getUserRoleConditionConfig();
                    break;
                case 'user_logged_in':
                    html = this.getUserLoggedInConditionConfig();
                    break;
                case 'current_date':
                    html = this.getCurrentDateConditionConfig();
                    break;
                case 'current_time':
                    html = this.getCurrentTimeConditionConfig();
                    break;
                case 'day_of_week':
                    html = this.getDayOfWeekConditionConfig();
                    break;
            }
            
            $config.html(html);
            
            // Initialize any select2 fields
            $config.find('.addon-select, .option-select').select2({
                placeholder: wc_product_addons_params.i18n_select_addon,
                allowClear: true
            });
            
            // Populate the addon select with current addon data
            this.updateAddonSelects();
        },
        
        // Update action configuration based on type
        updateActionConfig: function($select) {
            var type = $select.val();
            var $config = $select.closest('.action-row').find('.action-config');
            var html = '';
            
            switch(type) {
                case 'show_addon':
                case 'hide_addon':
                    html = this.getAddonVisibilityActionConfig();
                    break;
                case 'show_option':
                case 'hide_option':
                    html = this.getOptionVisibilityActionConfig();
                    break;
                case 'set_price':
                    html = this.getSetPriceActionConfig();
                    break;
                case 'adjust_price':
                    html = this.getAdjustPriceActionConfig();
                    break;
                case 'make_required':
                case 'make_optional':
                    html = this.getRequirementActionConfig();
                    break;
                case 'set_label':
                    html = this.getSetLabelActionConfig();
                    break;
                case 'set_description':
                    html = this.getSetDescriptionActionConfig();
                    break;
            }
            
            $config.html(html);
            
            // Initialize any select2 fields
            $config.find('.addon-select, .option-select').select2({
                placeholder: wc_product_addons_params.i18n_select_addon,
                allowClear: true
            });
            
            // Initialize target level display if target level selector exists
            var $targetLevel = $config.find('.target-level');
            if ($targetLevel.length) {
                // Set default to "addon" and trigger change to set initial state
                $targetLevel.val('addon').trigger('change');
            }
            
            // Populate the addon select with current addon data
            this.updateAddonSelects();
            
            // Handle option select visibility based on action type
            var actionType = $select.val();
            $config.find('.option-select').each(function() {
                var $optionSelect = $(this);
                var $addonSelect = $optionSelect.siblings('.addon-select');
                
                // For option-specific actions, show the option select even without addon selection
                if (actionType === 'show_option' || actionType === 'hide_option') {
                    $optionSelect.show();
                } else if (!$addonSelect.val()) {
                    // For other actions, only show if addon is selected
                    $optionSelect.hide();
                }
            });
        },
        
        // Condition configuration templates
        getAddonFieldConditionConfig: function() {
            var html = '<select class="addon-select" name="condition_addon">';
            html += '<option value="">' + wc_product_addons_params.i18n_select_addon + '</option>';
            
            // Add addons from cache
            $.each(this.addonsCache, function(key, addon) {
                html += '<option value="' + key + '">' + addon.name + '</option>';
            });
            
            html += '</select>';
            html += '<select class="operator-select" name="condition_operator">';
            html += '<option value="equals">' + wc_product_addons_params.i18n_equals + '</option>';
            html += '<option value="not_equals">' + wc_product_addons_params.i18n_not_equals + '</option>';
            html += '<option value="contains">' + wc_product_addons_params.i18n_contains + '</option>';
            html += '<option value="not_contains">' + wc_product_addons_params.i18n_not_contains + '</option>';
            html += '<option value="empty">' + wc_product_addons_params.i18n_is_empty + '</option>';
            html += '<option value="not_empty">' + wc_product_addons_params.i18n_is_not_empty + '</option>';
            html += '</select>';
            html += '<input type="text" class="value-input" name="condition_value" placeholder="' + wc_product_addons_params.i18n_value + '">';
            
            return html;
        },
        
        getAddonSelectedConditionConfig: function() {
            var html = '<select class="addon-select" name="condition_addon">';
            html += '<option value="">' + wc_product_addons_params.i18n_select_addon + '</option>';
            
            // Add addons from cache
            $.each(this.addonsCache, function(key, addon) {
                html += '<option value="' + key + '">' + addon.name + '</option>';
            });
            
            html += '</select>';
            html += '<select class="option-select" name="condition_option">';
            html += '<option value="">' + wc_product_addons_params.i18n_select_option + '</option>';
            html += '</select>';
            html += '<select class="state-select" name="condition_state">';
            html += '<option value="selected">' + wc_product_addons_params.i18n_is_selected + '</option>';
            html += '<option value="not_selected">' + wc_product_addons_params.i18n_is_not_selected + '</option>';
            html += '</select>';
            
            return html;
        },
        
        getProductPriceConditionConfig: function() {
            var html = '<select class="operator-select" name="condition_operator">';
            html += '<option value="equals">' + wc_product_addons_params.i18n_equals + '</option>';
            html += '<option value="not_equals">' + wc_product_addons_params.i18n_not_equals + '</option>';
            html += '<option value="greater_than">' + wc_product_addons_params.i18n_greater_than + '</option>';
            html += '<option value="less_than">' + wc_product_addons_params.i18n_less_than + '</option>';
            html += '<option value="greater_equals">' + wc_product_addons_params.i18n_greater_equals + '</option>';
            html += '<option value="less_equals">' + wc_product_addons_params.i18n_less_equals + '</option>';
            html += '</select>';
            html += '<input type="number" class="value-input" name="condition_value" placeholder="' + wc_product_addons_params.i18n_price + '" step="0.01">';
            
            return html;
        },
        
        // ... (continue with other configuration methods)
        
        // Build organized addon select HTML
        buildAddonSelectHtml: function(includeOptions, includeTargetLevel) {
            var html = '';
            
            // Add target level selector if requested - FIRST
            if (includeTargetLevel) {
                html += '<select class="target-level" name="action_target_level">';
                html += '<option value="addon">' + wc_product_addons_params.i18n_entire_addon + '</option>';
                html += '<option value="option">' + wc_product_addons_params.i18n_specific_option + '</option>';
                html += '</select>';
            }
            
            html += '<select class="addon-select" name="action_addon">';
            html += '<option value="">' + wc_product_addons_params.i18n_select_addon + '</option>';
            
            // Add global addons group
            if (this.addonsData.global && Object.keys(this.addonsData.global).length > 0) {
                html += '<optgroup label="🌍 Global Add-ons">';
                $.each(this.addonsData.global, function(key, addon) {
                    html += '<option value="' + key + '">' + addon.name + ' (' + addon.group_name + ')</option>';
                });
                html += '</optgroup>';
            }
            
            // Add product-specific addons groups
            if (this.addonsData.products && Object.keys(this.addonsData.products).length > 0) {
                $.each(this.addonsData.products, function(productId, productData) {
                    if (productData.addons && Object.keys(productData.addons).length > 0) {
                        html += '<optgroup label="📦 ' + productData.product_name + ' (Product-specific)">';
                        $.each(productData.addons, function(key, addon) {
                            html += '<option value="' + key + '">' + addon.name + ' (' + addon.product_name + ')</option>';
                        });
                        html += '</optgroup>';
                    }
                });
            }
            
            html += '</select>';
            
            if (includeOptions) {
                html += '<select class="option-select" name="action_option" style="display: none;">';
                html += '<option value="">' + wc_product_addons_params.i18n_select_option + '</option>';
                html += '</select>';
            }
            
            return html;
        },
        
        // Action configuration templates
        getAddonVisibilityActionConfig: function() {
            return this.buildAddonSelectHtml(true, true);
        },
        
        getOptionVisibilityActionConfig: function() {
            var html = this.buildAddonSelectHtml(true, false);
            // Force the option select to be visible for option-specific actions
            html = html.replace('style="display:none;"', 'style=""');
            return html;
        },
        
        getSetPriceActionConfig: function() {
            var html = this.buildAddonSelectHtml(true, true);
            html += '<input type="number" class="price-input" name="action_price" placeholder="' + wc_product_addons_params.i18n_new_price + '" step="0.01">';
            return html;
        },
        
        getAdjustPriceActionConfig: function() {
            var html = this.buildAddonSelectHtml(true, true);
            html += '<select class="adjustment-type" name="action_adjustment_type">';
            html += '<option value="increase_fixed">' + wc_product_addons_params.i18n_increase_by + '</option>';
            html += '<option value="decrease_fixed">' + wc_product_addons_params.i18n_decrease_by + '</option>';
            html += '<option value="increase_percent">' + wc_product_addons_params.i18n_increase_percent + '</option>';
            html += '<option value="decrease_percent">' + wc_product_addons_params.i18n_decrease_percent + '</option>';
            html += '</select>';
            html += '<input type="number" class="value-input" name="action_value" placeholder="' + wc_product_addons_params.i18n_amount + '" step="0.01">';
            return html;
        },
        
        getRequirementActionConfig: function() {
            return this.buildAddonSelectHtml(true, true);
        },
        
        getSetLabelActionConfig: function() {
            var html = this.buildAddonSelectHtml(true, true);
            html += '<input type="text" class="text-input" name="action_label" placeholder="' + wc_product_addons_params.i18n_new_label + '">';
            return html;
        },
        
        getSetDescriptionActionConfig: function() {
            var html = this.buildAddonSelectHtml(true, true);
            html += '<textarea class="text-input" name="action_description" placeholder="' + wc_product_addons_params.i18n_new_description + '"></textarea>';
            return html;
        },
        
        // Update target level display (show/hide option selector)
        updateTargetLevelDisplay: function($targetSelect) {
            var targetLevel = $targetSelect.val();
            var $container = $targetSelect.closest('.action-config');
            var $optionSelect = $container.find('.option-select');
            
            if (targetLevel === 'option') {
                $optionSelect.show();
                // Trigger addon change to populate options if addon is already selected
                var $addonSelect = $container.find('.addon-select');
                if ($addonSelect.val()) {
                    this.updateAddonOptions($addonSelect);
                }
            } else {
                $optionSelect.hide().val(''); // Hide and clear the value
            }
        },
        
        // Update addon options when an addon is selected
        updateAddonOptions: function($select) {
            var addonId = $select.val();
            var $container = $select.closest('.action-config, .condition-config');
            var $optionSelect = $container.find('.option-select');
            
            if (!$optionSelect.length) {
                return;
            }
            
            // Clear existing options
            $optionSelect.empty().append('<option value="">' + wc_product_addons_params.i18n_select_option + '</option>');
            
            if (addonId && this.addonsCache[addonId] && this.addonsCache[addonId].options) {
                var addon = this.addonsCache[addonId];
                
                $.each(addon.options, function(index, option) {
                    $optionSelect.append('<option value="' + option.value + '">' + option.label + '</option>');
                });
                
                // Check if this is for an action that should show options, or if target level is set to "option"
                var $targetLevel = $container.find('.target-level');
                var $actionType = $container.closest('.action-item').find('.action-type');
                var actionType = $actionType.length ? $actionType.val() : '';
                
                if (actionType === 'show_option' || actionType === 'hide_option' || 
                    ($targetLevel.length && $targetLevel.val() === 'option')) {
                    $optionSelect.show();
                }
            } else {
                // Only hide if not a required option selection
                var $actionType = $container.closest('.action-item').find('.action-type');
                var actionType = $actionType.length ? $actionType.val() : '';
                
                if (actionType !== 'show_option' && actionType !== 'hide_option') {
                    $optionSelect.hide();
                }
            }
        },
        
        // Save rule
        saveRule: function() {
            var self = this;
            var ruleData = this.collectRuleData();
            
            if (!this.validateRule(ruleData)) {
                return;
            }
            
            // Add debug information to the page
            this.showDebugInfo('Rule Data Being Sent:', ruleData);
            
            // Show loading state
            $('.save-rule').addClass('loading').prop('disabled', true);
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_save_rule',
                    security: wc_product_addons_params.save_rule_nonce,
                    rule_id: this.editingRuleId,
                    rule_data: ruleData
                },
                success: function(response) {
                    self.showDebugInfo('AJAX Response:', response);
                    
                    if (response.success) {
                        self.showNotice(wc_product_addons_params.i18n_rule_saved, 'success');
                        self.resetRuleBuilder();
                        self.loadExistingRules();
                    } else {
                        var errorMessage = wc_product_addons_params.i18n_error_saving;
                        var debugInfo = '';
                        
                        if (response.data) {
                            if (response.data.message) {
                                errorMessage = response.data.message;
                            }
                            if (response.data.debug) {
                                debugInfo = ' Debug: ' + JSON.stringify(response.data.debug);
                            }
                            if (response.data.error_details) {
                                debugInfo += ' Details: ' + response.data.error_details;
                            }
                        }
                        
                        self.showNotice(errorMessage + debugInfo, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    var errorDetails = 'AJAX Error - Status: ' + status + ', Error: ' + error;
                    if (xhr.responseText) {
                        errorDetails += ', Response: ' + xhr.responseText.substring(0, 500);
                    }
                    
                    self.showDebugInfo('AJAX Error Details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        readyState: xhr.readyState,
                        statusText: xhr.statusText
                    });
                    
                    self.showNotice(wc_product_addons_params.i18n_error_saving + ' - ' + errorDetails, 'error');
                },
                complete: function() {
                    $('.save-rule').removeClass('loading').prop('disabled', false);
                }
            });
        },
        
        // Collect rule data from form
        collectRuleData: function() {
            var data = {
                name: $('#rule-name').val(),
                scope: $('input[name="rule_scope"]:checked').val(),
                scope_targets: [],
                condition_groups: [],
                actions: []
            };
            
            // Get scope targets
            if (data.scope === 'category') {
                data.scope_targets = $('.wc-category-search').val() || [];
            } else if (data.scope === 'product') {
                data.scope_targets = $('.wc-product-search').val() || [];
            }
            
            // Collect condition groups
            $('.condition-group').each(function() {
                var $group = $(this);
                var group = {
                    id: $group.data('group-id'),
                    logic: $group.find('.group-logic').val() || 'AND',
                    relationship: $group.find('.group-relationship-selector').val() || 'AND',
                    conditions: []
                };
                
                // Collect conditions within this group
                $group.find('.condition-item').each(function() {
                    var $item = $(this);
                    var condition = {
                        id: $item.data('condition-id'),
                        type: $item.find('.condition-type').val(),
                        config: {}
                    };
                    
                    // Collect all config inputs
                    $item.find('.condition-config').find('input, select').each(function() {
                        var $input = $(this);
                        var name = $input.attr('name');
                        if (name) {
                            condition.config[name] = $input.val();
                        }
                    });
                    
                    if (condition.type) {
                        group.conditions.push(condition);
                    }
                });
                
                if (group.conditions.length > 0) {
                    data.condition_groups.push(group);
                }
            });
            
            // For backward compatibility, flatten to simple conditions array
            data.conditions = [];
            data.condition_groups.forEach(function(group) {
                data.conditions = data.conditions.concat(group.conditions);
            });
            
            // Collect actions
            $('.action-item').each(function() {
                var $item = $(this);
                var action = {
                    id: $item.data('action-id'),
                    type: $item.find('.action-type').val(),
                    config: {}
                };
                
                // Collect all config inputs
                $item.find('.action-config').find('input, select').each(function() {
                    var $input = $(this);
                    var name = $input.attr('name');
                    if (name) {
                        action.config[name] = $input.val();
                    }
                });
                
                if (action.type) {
                    data.actions.push(action);
                }
            });
            
            return data;
        },
        
        // Validate rule
        validateRule: function(ruleData) {
            if (!ruleData.name) {
                this.showNotice(wc_product_addons_params.i18n_rule_name_required, 'error');
                return false;
            }
            
            if (ruleData.conditions.length === 0) {
                this.showNotice(wc_product_addons_params.i18n_at_least_one_condition, 'error');
                return false;
            }
            
            if (ruleData.actions.length === 0) {
                this.showNotice(wc_product_addons_params.i18n_at_least_one_action, 'error');
                return false;
            }
            
            if ((ruleData.scope === 'category' || ruleData.scope === 'product') && ruleData.scope_targets.length === 0) {
                this.showNotice(wc_product_addons_params.i18n_select_scope_target, 'error');
                return false;
            }
            
            return true;
        },
        
        // Show notice message
        showNotice: function(message, type) {
            // Remove existing notices
            $('.wc-pao-notice').remove();
            
            var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
            var notice = '<div class="notice ' + noticeClass + ' wc-pao-notice is-dismissible">' +
                        '<p>' + message + '</p>' +
                        '<button type="button" class="notice-dismiss">' +
                        '<span class="screen-reader-text">Dismiss this notice.</span>' +
                        '</button>' +
                        '</div>';
            
            $('.wc-addons-conditional-logic-wrap').prepend(notice);
            
            // Auto-remove success notices after 3 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $('.wc-pao-notice').fadeOut();
                }, 3000);
            }
        },
        
        // Show debug information on the page
        showDebugInfo: function(title, data) {
            // Remove existing debug info
            $('.wc-pao-debug').remove();
            
            var debugContent = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            var debugBox = '<div class="wc-pao-debug" style="background: #f0f0f0; border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 4px;">' +
                          '<h4 style="margin: 0 0 10px 0; color: #333;">' + title + '</h4>' +
                          '<div style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">' + debugContent + '</div>' +
                          '<button type="button" onclick="$(this).closest(\'.wc-pao-debug\').remove();" style="margin-top: 10px; padding: 5px 10px; background: #dc3232; color: white; border: none; border-radius: 3px; cursor: pointer;">Close Debug Info</button>' +
                          '</div>';
            
            $('.wc-addons-conditional-logic-wrap').prepend(debugBox);
        },
        
        // Reset rule builder to initial state
        resetRuleBuilder: function() {
            // Clear form inputs
            $('#rule-name').val('');
            $('input[name="rule_scope"][value="global"]').prop('checked', true);
            $('.wc-category-search').val(null).trigger('change');
            $('.wc-product-search').val(null).trigger('change');
            
            // Clear conditions and actions
            $('.condition-group').remove();
            $('.action-item').remove();
            
            // Reset state
            this.conditionGroupCounter = 0;
            this.editingRuleId = null;
            
            // Add initial condition group
            this.addConditionGroup();
        },
        
        // Load existing rules
        loadExistingRules: function() {
            var self = this;
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_get_rules',
                    security: wc_product_addons_params.get_rules_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayRules(response.data);
                        self.initializeDragAndDrop();
                    }
                }
            });
        },
        
        // Initialize drag and drop for rules
        initializeDragAndDrop: function() {
            var self = this;
            
            if (typeof $.fn.sortable !== 'undefined') {
                $('#rules-list').sortable({
                    handle: '.drag-handle',
                    placeholder: 'ui-sortable-placeholder',
                    helper: 'clone',
                    start: function(event, ui) {
                        ui.placeholder.height(ui.item.height());
                    },
                    update: function(event, ui) {
                        self.updateRulePriorities();
                    }
                });
            }
        },
        
        // Update rule priorities after drag and drop
        updateRulePriorities: function() {
            var self = this;
            var ruleIds = [];
            
            $('#rules-list .rule-item').each(function() {
                var ruleId = $(this).data('rule-id');
                if (ruleId) {
                    ruleIds.push(ruleId);
                }
            });
            
            if (ruleIds.length === 0) {
                return;
            }
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_update_rule_priorities',
                    security: wc_product_addons_params.update_priorities_nonce,
                    rule_ids: ruleIds
                },
                success: function(response) {
                    if (response.success) {
                        // Update priority numbers in the UI
                        self.updatePriorityDisplay();
                        self.showNotice(response.data.message, 'success');
                    } else {
                        self.showNotice(response.data.message || 'Error updating priorities', 'error');
                    }
                },
                error: function() {
                    self.showNotice('Error updating rule priorities', 'error');
                }
            });
        },
        
        // Update priority display numbers
        updatePriorityDisplay: function() {
            var totalRules = $('#rules-list .rule-item').length;
            
            $('#rules-list .rule-item').each(function(index) {
                var priority = totalRules - index;
                $(this).find('.rule-priority').text('#' + priority);
            });
        },
        
        // Display rules
        displayRules: function(rules) {
            var $rulesList = $('#rules-list');
            $rulesList.empty();
            
            if (rules.length === 0) {
                $rulesList.html('<div class="no-rules-message"><p>' + wc_product_addons_params.i18n_no_rules + '</p></div>');
                return;
            }
            
            var template = $('#rule-item-template').html();
            
            $.each(rules, function(index, rule) {
                var html = template
                    .replace(/{rule_id}/g, rule.id)
                    .replace(/{rule_name}/g, rule.name)
                    .replace(/{scope}/g, rule.scope)
                    .replace(/{scope_label}/g, rule.scope_label)
                    .replace(/{priority}/g, rule.priority || (rules.length - index))
                    .replace(/{status}/g, rule.status)
                    .replace(/{status_label}/g, rule.status_label)
                    .replace(/{toggle_icon}/g, rule.status === 'active' ? 'visibility' : 'hidden')
                    .replace(/{conditions_summary}/g, rule.conditions_summary)
                    .replace(/{actions_summary}/g, rule.actions_summary);
                
                $rulesList.append(html);
            });
        },
        
        // Filter rules
        filterRules: function(filter) {
            if (filter === 'all') {
                $('.rule-item').show();
            } else {
                $('.rule-item').hide();
                $('.rule-item[data-scope="' + filter + '"]').show();
            }
        },
        
        // Edit rule
        editRule: function(ruleId) {
            console.log('editRule called with ID:', ruleId);
            var self = this;
            
            // Check if wc_product_addons_params is available
            if (typeof wc_product_addons_params === 'undefined') {
                console.error('wc_product_addons_params is not defined');
                alert('Error: WordPress admin parameters not loaded. Please refresh the page.');
                return;
            }
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_get_rule',
                    security: wc_product_addons_params.get_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    console.log('Edit rule response:', response);
                    if (response.success) {
                        self.populateRuleBuilder(response.data);
                        self.editingRuleId = ruleId;
                        
                        // Switch to the create rule tab
                        $('.nav-tab[href="#create-rule"]').click();
                        
                        $('html, body').animate({
                            scrollTop: $('.rule-builder').offset().top - 50
                        }, 500);
                    } else {
                        console.error('Edit rule failed:', response);
                        alert('Error loading rule: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error editing rule:', xhr, status, error);
                    alert('Error loading rule: ' + error);
                }
            });
        },
        
        // Populate rule builder with existing rule data
        populateRuleBuilder: function(rule) {
            // Reset first
            this.resetRuleBuilder();
            
            // Set basic fields
            $('#rule-name').val(rule.name);
            $('input[name="rule_scope"][value="' + rule.scope + '"]').prop('checked', true).trigger('change');
            
            // Set scope targets
            if (rule.scope === 'category' && rule.scope_targets) {
                // Populate category select
                var $select = $('.wc-category-search');
                $.each(rule.scope_targets, function(id, name) {
                    $select.append(new Option(name, id, true, true));
                });
                $select.trigger('change');
            } else if (rule.scope === 'product' && rule.scope_targets) {
                // Populate product select
                var $select = $('.wc-product-search');
                $.each(rule.scope_targets, function(id, name) {
                    $select.append(new Option(name, id, true, true));
                });
                $select.trigger('change');
            }
            
            // Set condition logic
            $('#condition-logic').val(rule.condition_logic);
            
            // Add conditions
            var self = this;
            $.each(rule.conditions, function(index, condition) {
                self.addCondition();
                var $condition = $('.condition-item').last();
                $condition.find('.condition-type').val(condition.type).trigger('change');
                
                // Set config values
                setTimeout(function() {
                    $.each(condition.config, function(key, value) {
                        $condition.find('[name="' + key + '"]').val(value).trigger('change');
                    });
                }, 100);
            });
            
            // Add actions
            $.each(rule.actions, function(index, action) {
                self.addAction();
                var $action = $('.action-item').last();
                $action.find('.action-type').val(action.type).trigger('change');
                
                // Set config values
                setTimeout(function() {
                    $.each(action.config, function(key, value) {
                        $action.find('[name="' + key + '"]').val(value).trigger('change');
                    });
                }, 100);
            });
        },
        
        // Reset rule builder
        resetRuleBuilder: function() {
            this.editingRuleId = null;
            $('#rule-name').val('');
            $('input[name="rule_scope"][value="global"]').prop('checked', true).trigger('change');
            $('.wc-category-search, .wc-product-search').val(null).trigger('change');
            $('#condition-logic').val('AND');
            $('#conditions-container, #actions-container').empty();
        },
        
        // Duplicate rule
        duplicateRule: function(ruleId) {
            console.log('duplicateRule called with ID:', ruleId);
            var self = this;
            
            // Check if wc_product_addons_params is available
            if (typeof wc_product_addons_params === 'undefined') {
                console.error('wc_product_addons_params is not defined');
                alert('Error: WordPress admin parameters not loaded. Please refresh the page.');
                return;
            }
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_duplicate_rule',
                    security: wc_product_addons_params.duplicate_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    console.log('Duplicate rule response:', response);
                    if (response.success) {
                        var successMessage = (wc_product_addons_params.i18n_rule_duplicated) 
                            ? wc_product_addons_params.i18n_rule_duplicated 
                            : 'Rule duplicated successfully';
                        self.showNotice(successMessage, 'success');
                        self.loadExistingRules();
                    } else {
                        console.error('Duplicate rule failed:', response);
                        alert('Error duplicating rule: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error duplicating rule:', xhr, status, error);
                    alert('Error duplicating rule: ' + error);
                }
            });
        },
        
        // Toggle rule
        toggleRule: function(ruleId) {
            console.log('toggleRule called with ID:', ruleId);
            var self = this;
            var $rule = $('.rule-item[data-rule-id="' + ruleId + '"]');
            
            // Check if wc_product_addons_params is available
            if (typeof wc_product_addons_params === 'undefined') {
                console.error('wc_product_addons_params is not defined');
                alert('Error: WordPress admin parameters not loaded. Please refresh the page.');
                return;
            }
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_toggle_rule',
                    security: wc_product_addons_params.toggle_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    console.log('Toggle rule response:', response);
                    if (response.success) {
                        // Update UI
                        if (response.data.status === 'active') {
                            var activeText = (wc_product_addons_params.i18n_active) ? wc_product_addons_params.i18n_active : 'Active';
                            $rule.find('.rule-status').removeClass('inactive').addClass('active').text(activeText);
                            $rule.find('.toggle-rule .dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                        } else {
                            var inactiveText = (wc_product_addons_params.i18n_inactive) ? wc_product_addons_params.i18n_inactive : 'Inactive';
                            $rule.find('.rule-status').removeClass('active').addClass('inactive').text(inactiveText);
                            $rule.find('.toggle-rule .dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                        }
                    } else {
                        console.error('Toggle rule failed:', response);
                        alert('Error toggling rule: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error toggling rule:', xhr, status, error);
                    alert('Error toggling rule: ' + error);
                }
            });
        },
        
        // Delete rule
        deleteRule: function(ruleId) {
            console.log('deleteRule called with ID:', ruleId);
            var self = this;
            
            // Check if wc_product_addons_params is available
            if (typeof wc_product_addons_params === 'undefined') {
                console.error('wc_product_addons_params is not defined');
                alert('Error: WordPress admin parameters not loaded. Please refresh the page.');
                return;
            }
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_delete_rule',
                    security: wc_product_addons_params.delete_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    console.log('Delete rule response:', response);
                    if (response.success) {
                        var successMessage = (wc_product_addons_params.i18n_rule_deleted) 
                            ? wc_product_addons_params.i18n_rule_deleted 
                            : 'Rule deleted successfully';
                        self.showNotice(successMessage, 'success');
                        $('.rule-item[data-rule-id="' + ruleId + '"]').fadeOut(300, function() {
                            $(this).remove();
                            if ($('.rule-item').length === 0) {
                                var noRulesMessage = (wc_product_addons_params.i18n_no_rules) 
                                    ? wc_product_addons_params.i18n_no_rules 
                                    : 'No rules found';
                                $('#rules-list').html('<div class="no-rules-message"><p>' + noRulesMessage + '</p></div>');
                            }
                        });
                    } else {
                        console.error('Delete rule failed:', response);
                        alert('Error deleting rule: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error deleting rule:', xhr, status, error);
                    alert('Error deleting rule: ' + error);
                }
            });
        },
        
        // Show notice
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wc-addons-conditional-logic-wrap').before($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.wc-addons-conditional-logic-wrap').length) {
            WC_Product_Addons_Conditional_Logic_Admin.init();
        }
    });
    
    // Export for global access
    window.WC_Product_Addons_Conditional_Logic_Admin = WC_Product_Addons_Conditional_Logic_Admin;
    
})(jQuery);