/**
 * WooCommerce Product Add-ons - Conditional Logic Admin JavaScript
 * 
 * Handles all admin functionality for conditional logic rules
 */
(function($) {
    'use strict';

    var WC_PAO_Conditional_Logic_Admin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.loadExistingRules();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                self.switchTab($(this).attr('href').substring(1));
            });

            // Rule scope change
            $('input[name="rule_scope"]').on('change', function() {
                self.handleScopeChange($(this).val());
            });

            // Add-on context change
            $('input[name="addon_context"]').on('change', function() {
                self.handleContextChange($(this).val());
            });

            // Add condition/action buttons
            $('.add-condition').on('click', function() {
                self.addCondition();
            });

            $('.add-condition-group').on('click', function() {
                self.addConditionGroup();
            });

            $('.add-action').on('click', function() {
                self.addAction();
            });

            // Save rule
            $('.save-rule').on('click', function() {
                self.saveRule();
            });

            // Cancel rule
            $('.cancel-rule').on('click', function() {
                self.cancelRule();
            });

            // Filter tabs
            $('.tab-button').on('click', function() {
                $('.tab-button').removeClass('active');
                $(this).addClass('active');
                self.filterRules($(this).data('filter'));
            });

            // Delegated events for dynamic content
            $(document).on('change', '.condition-type', function() {
                self.handleConditionTypeChange($(this));
            });

            $(document).on('change', '.action-type', function() {
                self.handleActionTypeChange($(this));
            });

            $(document).on('click', '.remove-condition', function() {
                self.removeCondition($(this));
            });

            $(document).on('click', '.remove-action', function() {
                self.removeAction($(this));
            });

            $(document).on('click', '.remove-group', function() {
                self.removeConditionGroup($(this));
            });

            $(document).on('click', '.add-condition-to-group', function() {
                self.addConditionToGroup($(this).closest('.condition-group'));
            });

            // Rule item actions
            $(document).on('click', '.edit-rule', function(e) {
                e.preventDefault();
                self.editRule($(this).closest('.rule-item').data('rule-id'));
            });

            $(document).on('click', '.duplicate-rule', function(e) {
                e.preventDefault();
                self.duplicateRule($(this).closest('.rule-item').data('rule-id'));
            });

            $(document).on('click', '.toggle-rule', function(e) {
                e.preventDefault();
                self.toggleRule($(this).closest('.rule-item'));
            });

            $(document).on('click', '.delete-rule', function(e) {
                e.preventDefault();
                self.deleteRule($(this).closest('.rule-item'));
            });

            // Initialize Select2 for product/category search
            this.initializeSelect2();
        },

        /**
         * Initialize Select2
         */
        initializeSelect2: function() {
            if ($.fn.selectWoo) {
                $('.wc-product-search').selectWoo({
                    ajax: {
                        url: wc_product_addons_params.ajax_url || ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'woocommerce_json_search_products',
                                term: params.term,
                                security: wc_product_addons_params.search_products_nonce
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
                            return { results: results };
                        }
                    },
                    minimumInputLength: 3
                });

                $('.wc-category-search').selectWoo({
                    ajax: {
                        url: wc_product_addons_params.ajax_url || ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'woocommerce_json_search_categories',
                                term: params.term,
                                security: wc_product_addons_params.search_categories_nonce
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
                            return { results: results };
                        }
                    },
                    minimumInputLength: 3
                });
            }
        },

        /**
         * Initialize sortable for rules
         */
        initSortable: function() {
            var self = this;
            
            $('#rules-list').sortable({
                handle: '.drag-handle',
                placeholder: 'ui-sortable-placeholder',
                items: '.rule-item',
                axis: 'y',
                opacity: 0.8,
                update: function(event, ui) {
                    self.updateRulePriorities();
                }
            });
        },

        /**
         * Switch tab
         */
        switchTab: function(tabId) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('#' + tabId + '-tab').addClass('nav-tab-active');
            
            $('.tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        },

        /**
         * Handle scope change
         */
        handleScopeChange: function(scope) {
            $('.scope-target').hide();
            if (scope === 'category') {
                $('#category-target').show();
            } else if (scope === 'product') {
                $('#product-target').show();
            }
        },

        /**
         * Handle context change
         */
        handleContextChange: function(context) {
            if (context === 'specific_product') {
                $('#product-selector').show();
            } else {
                $('#product-selector').hide();
            }
        },

        /**
         * Add condition
         */
        addCondition: function() {
            var template = $('#condition-template').html();
            var $condition = $(template);
            
            if ($('#condition-groups-container .condition-group').length === 0) {
                this.addConditionGroup();
                $('#condition-groups-container .condition-group:first .conditions-in-group').append($condition);
            } else {
                $('#condition-groups-container .condition-group:last .conditions-in-group').append($condition);
            }
            
            this.updateConditionNumbers();
        },

        /**
         * Add condition group
         */
        addConditionGroup: function() {
            var template = $('#condition-group-template').html();
            var $group = $(template);
            
            $('#condition-groups-container').append($group);
            
            // Hide relationship selector for last group
            this.updateGroupRelationships();
        },

        /**
         * Add condition to specific group
         */
        addConditionToGroup: function($group) {
            var template = $('#condition-template').html();
            var $condition = $(template);
            
            $group.find('.conditions-in-group').append($condition);
            this.updateConditionNumbers();
        },

        /**
         * Add action
         */
        addAction: function() {
            var template = $('#action-template').html();
            var $action = $(template);
            
            $('#actions-container').append($action);
            this.updateActionNumbers();
        },

        /**
         * Remove condition
         */
        removeCondition: function($button) {
            var $condition = $button.closest('.condition-item');
            var $group = $condition.closest('.condition-group');
            
            $condition.fadeOut(200, function() {
                $(this).remove();
                
                // Remove group if empty
                if ($group.find('.condition-item').length === 0) {
                    $group.fadeOut(200, function() {
                        $(this).remove();
                        self.updateGroupRelationships();
                    });
                }
                
                self.updateConditionNumbers();
            });
        },

        /**
         * Remove condition group
         */
        removeConditionGroup: function($button) {
            var $group = $button.closest('.condition-group');
            
            $group.fadeOut(200, function() {
                $(this).remove();
                self.updateGroupRelationships();
                self.updateConditionNumbers();
            });
        },

        /**
         * Remove action
         */
        removeAction: function($button) {
            var $action = $button.closest('.action-item');
            
            $action.fadeOut(200, function() {
                $(this).remove();
                self.updateActionNumbers();
            });
        },

        /**
         * Update group relationships visibility
         */
        updateGroupRelationships: function() {
            var $groups = $('.condition-group');
            $groups.find('.group-relationship').show();
            $groups.last().find('.group-relationship').hide();
        },

        /**
         * Update condition numbers
         */
        updateConditionNumbers: function() {
            $('.condition-item').each(function(index) {
                $(this).attr('data-condition-id', index + 1);
            });
        },

        /**
         * Update action numbers
         */
        updateActionNumbers: function() {
            $('.action-item').each(function(index) {
                $(this).attr('data-action-id', index + 1);
            });
        },

        /**
         * Handle condition type change
         */
        handleConditionTypeChange: function($select) {
            var type = $select.val();
            var $config = $select.siblings('.condition-config');
            
            $config.empty();
            
            if (!type) return;
            
            var html = '';
            
            switch (type) {
                case 'addon_field':
                case 'addon_selected':
                    html = this.getAddonFieldConfig();
                    break;
                case 'product_price':
                case 'cart_total':
                    html = this.getPriceConfig();
                    break;
                case 'product_stock':
                case 'cart_quantity':
                    html = this.getQuantityConfig();
                    break;
                case 'product_category':
                    html = this.getCategoryConfig();
                    break;
                case 'user_role':
                    html = this.getUserRoleConfig();
                    break;
                case 'user_logged_in':
                    html = this.getLoggedInConfig();
                    break;
                case 'current_date':
                    html = this.getDateConfig();
                    break;
                case 'current_time':
                    html = this.getTimeConfig();
                    break;
                case 'day_of_week':
                    html = this.getDayOfWeekConfig();
                    break;
            }
            
            $config.html(html);
            
            // Initialize any select2 fields
            $config.find('.addon-select').each(function() {
                self.initializeAddonSelect($(this));
            });
        },

        /**
         * Handle action type change
         */
        handleActionTypeChange: function($select) {
            var type = $select.val();
            var $config = $select.siblings('.action-config');
            
            $config.empty();
            
            if (!type) return;
            
            var html = '';
            
            switch (type) {
                case 'show_addon':
                case 'hide_addon':
                case 'make_required':
                case 'make_optional':
                    html = this.getAddonTargetConfig();
                    break;
                case 'show_option':
                case 'hide_option':
                    html = this.getOptionTargetConfig();
                    break;
                case 'set_price':
                case 'adjust_price':
                    html = this.getPriceActionConfig(type);
                    break;
                case 'set_label':
                case 'set_description':
                    html = this.getTextActionConfig(type);
                    break;
            }
            
            $config.html(html);
            
            // Initialize any select2 fields
            $config.find('.addon-select').each(function() {
                self.initializeAddonSelect($(this));
            });
        },

        /**
         * Save rule
         */
        saveRule: function() {
            var self = this;
            var data = this.collectRuleData();
            
            if (!this.validateRuleData(data)) {
                return;
            }
            
            $('.save-rule').prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pao_save_conditional_rule',
                    rule_data: JSON.stringify(data),
                    security: wc_product_addons_params.save_rule_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Rule saved successfully!', 'success');
                        self.resetRuleForm();
                        self.loadExistingRules();
                        self.switchTab('existing-rules');
                    } else {
                        self.showNotice(response.data || 'Error saving rule', 'error');
                    }
                },
                error: function() {
                    self.showNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $('.save-rule').prop('disabled', false).text('Save Rule');
                }
            });
        },

        /**
         * Edit rule
         */
        editRule: function(ruleId) {
            var self = this;
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pao_get_rule',
                    rule_id: ruleId,
                    security: wc_product_addons_params.get_rule_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.loadRuleIntoForm(response.data);
                        self.switchTab('new-rule');
                        
                        // Scroll to top
                        $('html, body').animate({
                            scrollTop: $('.wc-addons-conditional-logic-wrap').offset().top - 50
                        }, 300);
                    } else {
                        self.showNotice('Error loading rule', 'error');
                    }
                }
            });
        },

        /**
         * Duplicate rule
         */
        duplicateRule: function(ruleId) {
            var self = this;
            
            if (!confirm('Are you sure you want to duplicate this rule?')) {
                return;
            }
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pao_duplicate_rule',
                    rule_id: ruleId,
                    security: wc_product_addons_params.duplicate_rule_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Rule duplicated successfully!', 'success');
                        self.loadExistingRules();
                    } else {
                        self.showNotice('Error duplicating rule', 'error');
                    }
                }
            });
        },

        /**
         * Toggle rule status
         */
        toggleRule: function($ruleItem) {
            var self = this;
            var ruleId = $ruleItem.data('rule-id');
            var $button = $ruleItem.find('.toggle-rule');
            var $status = $ruleItem.find('.rule-status');
            var isActive = $status.hasClass('active');
            
            // Update UI immediately
            if (isActive) {
                $status.removeClass('active').addClass('inactive').text('Inactive');
                $button.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $status.removeClass('inactive').addClass('active').text('Active');
                $button.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pao_toggle_rule',
                    rule_id: ruleId,
                    enabled: !isActive,
                    security: wc_product_addons_params.toggle_rule_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Rule status updated', 'success');
                    } else {
                        // Revert UI on error
                        if (isActive) {
                            $status.removeClass('inactive').addClass('active').text('Active');
                            $button.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                        } else {
                            $status.removeClass('active').addClass('inactive').text('Inactive');
                            $button.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                        }
                        self.showNotice('Error updating rule status', 'error');
                    }
                }
            });
        },

        /**
         * Delete rule
         */
        deleteRule: function($ruleItem) {
            var self = this;
            var ruleId = $ruleItem.data('rule-id');
            
            if (!confirm('Are you sure you want to delete this rule? This action cannot be undone.')) {
                return;
            }
            
            // Add deleting class for animation
            $ruleItem.addClass('is-deleting');
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pao_delete_rule',
                    rule_id: ruleId,
                    security: wc_product_addons_params.delete_rule_nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Wait for animation to complete
                        setTimeout(function() {
                            $ruleItem.remove();
                            self.showNotice('Rule deleted successfully', 'success');
                            
                            // Show empty message if no rules left
                            if ($('#rules-list .rule-item').length === 0) {
                                $('#rules-list').html('<div class="no-rules-message"><p>No rules found. Create your first rule in the "Create New Rule" tab!</p></div>');
                            }
                        }, 300);
                    } else {
                        $ruleItem.removeClass('is-deleting');
                        self.showNotice('Error deleting rule', 'error');
                    }
                },
                error: function() {
                    $ruleItem.removeClass('is-deleting');
                    self.showNotice('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Filter rules by type
         */
        filterRules: function(filter) {
            if (filter === 'all') {
                $('.rule-item').show();
            } else {
                $('.rule-item').hide();
                $('.rule-item[data-scope="' + filter + '"]').show();
            }
        },

        /**
         * Update rule priorities after reordering
         */
        updateRulePriorities: function() {
            var self = this;
            var priorities = [];
            
            $('#rules-list .rule-item').each(function(index) {
                var ruleId = $(this).data('rule-id');
                var priority = $('#rules-list .rule-item').length - index;
                
                priorities.push({
                    rule_id: ruleId,
                    priority: priority
                });
                
                // Update UI
                $(this).find('.rule-priority').text('#' + priority);
            });
            
            // Save new priorities
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pao_update_rule_priorities',
                    priorities: JSON.stringify(priorities),
                    security: wc_product_addons_params.update_priorities_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Rule priorities updated', 'success');
                    } else {
                        self.showNotice('Error updating priorities', 'error');
                        self.loadExistingRules(); // Reload to revert
                    }
                }
            });
        },

        /**
         * Load existing rules
         */
        loadExistingRules: function() {
            var self = this;
            
            $('#rules-list').html('<div class="loading-spinner">Loading rules...</div>');
            
            $.ajax({
                url: wc_product_addons_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pao_get_rules',
                    security: wc_product_addons_params.get_rules_nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        
                        $.each(response.data, function(index, rule) {
                            html += self.buildRuleItem(rule);
                        });
                        
                        $('#rules-list').html(html);
                        
                        // Reinitialize sortable
                        self.initSortable();
                    } else {
                        $('#rules-list').html('<div class="no-rules-message"><p>No rules found. Create your first rule in the "Create New Rule" tab!</p></div>');
                    }
                }
            });
        },

        /**
         * Build rule item HTML
         */
        buildRuleItem: function(rule) {
            var template = $('#rule-item-template').html();
            
            // Replace placeholders
            template = template.replace(/{rule_id}/g, rule.rule_id);
            template = template.replace(/{rule_name}/g, this.escapeHtml(rule.rule_name));
            template = template.replace(/{priority}/g, rule.priority);
            template = template.replace(/{scope}/g, rule.rule_type);
            template = template.replace(/{scope_label}/g, this.getScopeLabel(rule.rule_type));
            template = template.replace(/{status}/g, rule.enabled ? 'active' : 'inactive');
            template = template.replace(/{status_label}/g, rule.enabled ? 'Active' : 'Inactive');
            template = template.replace(/{toggle_icon}/g, rule.enabled ? 'visibility' : 'hidden');
            template = template.replace(/{conditions_summary}/g, this.getConditionsSummary(rule.conditions));
            template = template.replace(/{actions_summary}/g, this.getActionsSummary(rule.actions));
            
            return template;
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.woocommerce').prepend($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Helper functions
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        getScopeLabel: function(scope) {
            switch (scope) {
                case 'global':
                    return 'Global';
                case 'category':
                    return 'Category';
                case 'product':
                    return 'Product';
                default:
                    return scope;
            }
        },

        getConditionsSummary: function(conditions) {
            if (!conditions || conditions.length === 0) {
                return 'No conditions';
            }
            
            var summary = [];
            try {
                var parsed = typeof conditions === 'string' ? JSON.parse(conditions) : conditions;
                // Create a simple summary
                if (parsed.length > 0) {
                    summary.push(parsed.length + ' condition' + (parsed.length > 1 ? 's' : ''));
                }
            } catch (e) {
                summary.push('Invalid conditions');
            }
            
            return summary.join(', ');
        },

        getActionsSummary: function(actions) {
            if (!actions || actions.length === 0) {
                return 'No actions';
            }
            
            var summary = [];
            try {
                var parsed = typeof actions === 'string' ? JSON.parse(actions) : actions;
                // Create a simple summary
                if (parsed.length > 0) {
                    summary.push(parsed.length + ' action' + (parsed.length > 1 ? 's' : ''));
                }
            } catch (e) {
                summary.push('Invalid actions');
            }
            
            return summary.join(', ');
        },

        /**
         * Additional helper methods for configuration
         */
        getAddonFieldConfig: function() {
            return '<select class="addon-select"><option value="">Select add-on...</option></select>' +
                   '<select class="operator"><option value="equals">equals</option><option value="not_equals">not equals</option><option value="contains">contains</option></select>' +
                   '<input type="text" class="field-value" placeholder="Value">';
        },

        getPriceConfig: function() {
            return '<select class="operator"><option value="greater_than">greater than</option><option value="less_than">less than</option><option value="equals">equals</option></select>' +
                   '<input type="number" class="price-value" placeholder="0.00" step="0.01">';
        },

        getAddonTargetConfig: function() {
            return '<select class="target-level"><option value="addon">Entire Add-on</option></select>' +
                   '<select class="addon-select"><option value="">Select add-on...</option></select>';
        },

        getOptionTargetConfig: function() {
            return '<select class="target-level"><option value="option">Specific Option</option></select>' +
                   '<select class="addon-select"><option value="">Select add-on...</option></select>' +
                   '<select class="option-select" style="display:none;"><option value="">Select option...</option></select>';
        },
        
        getQuantityConfig: function() {
            return '<select class="operator"><option value="greater_than">greater than</option><option value="less_than">less than</option><option value="equals">equals</option></select>' +
                   '<input type="number" class="quantity-value" placeholder="0" min="0">';
        },
        
        getCategoryConfig: function() {
            return '<select class="category-select" multiple><option value="">Select categories...</option></select>';
        },
        
        getUserRoleConfig: function() {
            return '<select class="operator"><option value="is">is</option><option value="is_not">is not</option></select>' +
                   '<select class="role-select"><option value="guest">Guest</option><option value="customer">Customer</option><option value="subscriber">Subscriber</option><option value="administrator">Administrator</option></select>';
        },
        
        getLoggedInConfig: function() {
            return '<select class="logged-in-value"><option value="yes">Logged In</option><option value="no">Not Logged In</option></select>';
        },
        
        getDateConfig: function() {
            return '<select class="operator"><option value="before">before</option><option value="after">after</option><option value="on">on</option></select>' +
                   '<input type="date" class="date-value">';
        },
        
        getTimeConfig: function() {
            return '<select class="operator"><option value="before">before</option><option value="after">after</option><option value="between">between</option></select>' +
                   '<input type="time" class="time-value">' +
                   '<input type="time" class="time-value-end" style="display:none;" placeholder="End time">';
        },
        
        getDayOfWeekConfig: function() {
            return '<select class="operator"><option value="is">is</option><option value="is_not">is not</option></select>' +
                   '<select class="day-value" multiple><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="0">Sunday</option></select>';
        },
        
        getPriceActionConfig: function(type) {
            if (type === 'set_price') {
                return '<select class="target-level"><option value="addon">Entire Add-on</option><option value="option">Specific Option</option></select>' +
                       '<select class="addon-select"><option value="">Select add-on...</option></select>' +
                       '<select class="option-select" style="display:none;"><option value="">Select option...</option></select>' +
                       '<input type="number" class="price-value" placeholder="0.00" step="0.01">';
            } else {
                return '<select class="target-level"><option value="addon">Entire Add-on</option><option value="option">Specific Option</option></select>' +
                       '<select class="addon-select"><option value="">Select add-on...</option></select>' +
                       '<select class="option-select" style="display:none;"><option value="">Select option...</option></select>' +
                       '<select class="adjustment-type"><option value="add">Add</option><option value="subtract">Subtract</option><option value="multiply">Multiply by</option><option value="percentage">Percentage</option></select>' +
                       '<input type="number" class="adjustment-value" placeholder="0.00" step="0.01">';
            }
        },
        
        getTextActionConfig: function(type) {
            var label = type === 'set_label' ? 'New Label' : 'New Description';
            return '<select class="target-level"><option value="addon">Entire Add-on</option></select>' +
                   '<select class="addon-select"><option value="">Select add-on...</option></select>' +
                   '<textarea class="text-value" placeholder="' + label + '" rows="3" style="width: 100%;"></textarea>';
        },
        
        cancelRule: function() {
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                this.resetRuleForm();
                this.switchTab('existing-rules');
            }
        },
        
        loadRuleIntoForm: function(rule) {
            // Reset form first
            this.resetRuleForm();
            
            // Set basic fields
            $('#rule-name').val(rule.rule_name);
            $('input[name="rule_scope"][value="' + rule.rule_type + '"]').prop('checked', true).trigger('change');
            
            // Set rule ID for updating
            $('#rule-name').data('rule-id', rule.rule_id);
            
            // Load conditions
            if (rule.conditions && rule.conditions.length > 0) {
                // Implementation to recreate condition UI from data
                // This would need to parse the conditions and create the appropriate UI elements
            }
            
            // Load actions
            if (rule.actions && rule.actions.length > 0) {
                // Implementation to recreate action UI from data
                // This would need to parse the actions and create the appropriate UI elements
            }
            
            // Change button text
            $('.save-rule').text('Update Rule');
        },

        collectRuleData: function() {
            var data = {
                rule_name: $('#rule-name').val(),
                rule_scope: $('input[name="rule_scope"]:checked').val(),
                conditions: this.collectConditions(),
                actions: this.collectActions()
            };
            
            // Add scope-specific data
            if (data.rule_scope === 'category') {
                data.scope_categories = $('.wc-category-search').val();
            } else if (data.rule_scope === 'product') {
                data.scope_products = $('.wc-product-search').val();
            }
            
            return data;
        },
        
        collectConditions: function() {
            var conditions = [];
            var self = this;
            
            $('.condition-group').each(function() {
                var $group = $(this);
                var groupConditions = [];
                
                $group.find('.condition-item').each(function() {
                    var $condition = $(this);
                    var type = $condition.find('.condition-type').val();
                    
                    if (type) {
                        var condition = {
                            type: type,
                            config: {}
                        };
                        
                        // Collect condition configuration based on type
                        $condition.find('.condition-config input, .condition-config select').each(function() {
                            var $input = $(this);
                            var className = $input.attr('class');
                            if (className) {
                                // Extract meaningful name from class
                                var name = className.split(' ')[0].replace('-', '_');
                                condition.config[name] = $input.val();
                            }
                        });
                        
                        groupConditions.push(condition);
                    }
                });
                
                if (groupConditions.length > 0) {
                    conditions.push({
                        match_type: $group.find('.group-logic').val() || 'AND',
                        conditions: groupConditions,
                        relationship: $group.find('.group-relationship-selector').val() || 'AND'
                    });
                }
            });
            
            return conditions;
        },
        
        collectActions: function() {
            var actions = [];
            var self = this;
            
            $('.action-item').each(function() {
                var $action = $(this);
                var type = $action.find('.action-type').val();
                
                if (type) {
                    var action = {
                        type: type,
                        config: {}
                    };
                    
                    // Collect action configuration based on type
                    $action.find('.action-config input, .action-config select, .action-config textarea').each(function() {
                        var $input = $(this);
                        var className = $input.attr('class');
                        if (className) {
                            // Extract meaningful name from class
                            var name = className.split(' ')[0].replace('-', '_');
                            action.config[name] = $input.val();
                        }
                    });
                    
                    actions.push(action);
                }
            });
            
            return actions;
        },

        validateRuleData: function(data) {
            if (!data.rule_name) {
                this.showNotice('Please enter a rule name', 'error');
                return false;
            }
            
            if (data.conditions.length === 0) {
                this.showNotice('Please add at least one condition', 'error');
                return false;
            }
            
            if (data.actions.length === 0) {
                this.showNotice('Please add at least one action', 'error');
                return false;
            }
            
            return true;
        },

        resetRuleForm: function() {
            $('#rule-name').val('');
            $('input[name="rule_scope"][value="global"]').prop('checked', true).trigger('change');
            $('#condition-groups-container').empty();
            $('#actions-container').empty();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Check if localization data is available
        if (typeof wc_product_addons_params === 'undefined') {
            console.error('WC Product Addons Conditional Logic: Localization data not found. Script may not be properly enqueued.');
            return;
        }
        
        WC_PAO_Conditional_Logic_Admin.init();
    });

})(jQuery);