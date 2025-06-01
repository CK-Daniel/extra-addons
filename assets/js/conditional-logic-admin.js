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
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initializeSelect2();
            this.loadExistingRules();
            this.loadAddonsData();
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
                self.addCondition();
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
                var ruleId = $(this).closest('.rule-item').data('rule-id');
                self.editRule(ruleId);
            });
            
            $(document).on('click', '.duplicate-rule', function(e) {
                e.preventDefault();
                var ruleId = $(this).closest('.rule-item').data('rule-id');
                self.duplicateRule(ruleId);
            });
            
            $(document).on('click', '.toggle-rule', function(e) {
                e.preventDefault();
                var ruleId = $(this).closest('.rule-item').data('rule-id');
                self.toggleRule(ruleId);
            });
            
            $(document).on('click', '.delete-rule', function(e) {
                e.preventDefault();
                if (confirm(wc_product_addons_params.i18n_confirm_delete_rule)) {
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
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_get_all_addons',
                    security: wc_product_addons_params.get_addons_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.addonsCache = response.data;
                    }
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
        
        // Action configuration templates
        getAddonVisibilityActionConfig: function() {
            var html = '<select class="addon-select" name="action_addon">';
            html += '<option value="">' + wc_product_addons_params.i18n_select_addon + '</option>';
            
            // Add addons from cache
            $.each(this.addonsCache, function(key, addon) {
                html += '<option value="' + key + '">' + addon.name + '</option>';
            });
            
            html += '</select>';
            
            return html;
        },
        
        getSetPriceActionConfig: function() {
            var html = '<select class="addon-select" name="action_addon">';
            html += '<option value="">' + wc_product_addons_params.i18n_select_addon + '</option>';
            
            // Add addons from cache
            $.each(this.addonsCache, function(key, addon) {
                html += '<option value="' + key + '">' + addon.name + '</option>';
            });
            
            html += '</select>';
            html += '<input type="number" class="price-input" name="action_price" placeholder="' + wc_product_addons_params.i18n_new_price + '" step="0.01">';
            
            return html;
        },
        
        getAdjustPriceActionConfig: function() {
            var html = '<select class="addon-select" name="action_addon">';
            html += '<option value="">' + wc_product_addons_params.i18n_select_addon + '</option>';
            
            // Add addons from cache
            $.each(this.addonsCache, function(key, addon) {
                html += '<option value="' + key + '">' + addon.name + '</option>';
            });
            
            html += '</select>';
            html += '<select class="adjustment-type" name="action_adjustment_type">';
            html += '<option value="increase_fixed">' + wc_product_addons_params.i18n_increase_by + '</option>';
            html += '<option value="decrease_fixed">' + wc_product_addons_params.i18n_decrease_by + '</option>';
            html += '<option value="increase_percent">' + wc_product_addons_params.i18n_increase_percent + '</option>';
            html += '<option value="decrease_percent">' + wc_product_addons_params.i18n_decrease_percent + '</option>';
            html += '</select>';
            html += '<input type="number" class="value-input" name="action_value" placeholder="' + wc_product_addons_params.i18n_amount + '" step="0.01">';
            
            return html;
        },
        
        // Save rule
        saveRule: function() {
            var self = this;
            var ruleData = this.collectRuleData();
            
            if (!this.validateRule(ruleData)) {
                return;
            }
            
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
                    if (response.success) {
                        self.showNotice(wc_product_addons_params.i18n_rule_saved, 'success');
                        self.resetRuleBuilder();
                        self.loadExistingRules();
                    } else {
                        self.showNotice(response.data.message || wc_product_addons_params.i18n_error_saving, 'error');
                    }
                },
                error: function() {
                    self.showNotice(wc_product_addons_params.i18n_error_saving, 'error');
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
                condition_logic: $('#condition-logic').val(),
                conditions: [],
                actions: []
            };
            
            // Get scope targets
            if (data.scope === 'category') {
                data.scope_targets = $('.wc-category-search').val() || [];
            } else if (data.scope === 'product') {
                data.scope_targets = $('.wc-product-search').val() || [];
            }
            
            // Collect conditions
            $('.condition-item').each(function() {
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
                    data.conditions.push(condition);
                }
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
                    }
                }
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
            var self = this;
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_get_rule',
                    security: wc_product_addons_params.get_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    if (response.success) {
                        self.populateRuleBuilder(response.data);
                        self.editingRuleId = ruleId;
                        $('html, body').animate({
                            scrollTop: $('.rule-builder').offset().top - 50
                        }, 500);
                    }
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
            var self = this;
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_duplicate_rule',
                    security: wc_product_addons_params.duplicate_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(wc_product_addons_params.i18n_rule_duplicated, 'success');
                        self.loadExistingRules();
                    }
                }
            });
        },
        
        // Toggle rule
        toggleRule: function(ruleId) {
            var self = this;
            var $rule = $('.rule-item[data-rule-id="' + ruleId + '"]');
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_toggle_rule',
                    security: wc_product_addons_params.toggle_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        if (response.data.status === 'active') {
                            $rule.find('.rule-status').removeClass('inactive').addClass('active').text(wc_product_addons_params.i18n_active);
                            $rule.find('.toggle-rule .dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                        } else {
                            $rule.find('.rule-status').removeClass('active').addClass('inactive').text(wc_product_addons_params.i18n_inactive);
                            $rule.find('.toggle-rule .dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                        }
                    }
                }
            });
        },
        
        // Delete rule
        deleteRule: function(ruleId) {
            var self = this;
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_product_addons_delete_rule',
                    security: wc_product_addons_params.delete_rule_nonce,
                    rule_id: ruleId
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(wc_product_addons_params.i18n_rule_deleted, 'success');
                        $('.rule-item[data-rule-id="' + ruleId + '"]').fadeOut(300, function() {
                            $(this).remove();
                            if ($('.rule-item').length === 0) {
                                $('#rules-list').html('<div class="no-rules-message"><p>' + wc_product_addons_params.i18n_no_rules + '</p></div>');
                            }
                        });
                    }
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