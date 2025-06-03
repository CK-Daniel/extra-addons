<?php
/**
 * Conditional Logic Admin Page - Simplified and Improved
 * 
 * @package WooCommerce Product Add-Ons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap woocommerce">
    <h1><?php esc_html_e( 'Product Add-Ons Conditional Logic', 'woocommerce-product-addons' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Create flexible rules to control add-on behavior based on user selections and conditions.', 'woocommerce-product-addons' ); ?></p>
    
    <div class="wc-addons-conditional-logic-wrap">
        <!-- Main Tabs -->
        <div class="main-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#new-rule" class="nav-tab nav-tab-active" id="new-rule-tab"><?php esc_html_e( 'Create New Rule', 'woocommerce-product-addons' ); ?></a>
                <a href="#existing-rules" class="nav-tab" id="existing-rules-tab"><?php esc_html_e( 'Manage Existing Rules', 'woocommerce-product-addons' ); ?></a>
            </nav>
        </div>
        
        <!-- New Rule Tab Content -->
        <div id="new-rule" class="tab-content active">
            <!-- Rule Scope Selector -->
            <div class="rule-scope-selector">
                <h2><?php esc_html_e( 'Rule Scope', 'woocommerce-product-addons' ); ?></h2>
            <div class="scope-options">
                <label>
                    <input type="radio" name="rule_scope" value="global" checked>
                    <span><?php esc_html_e( 'Global Rule', 'woocommerce-product-addons' ); ?></span>
                    <small><?php esc_html_e( 'Applies to all products', 'woocommerce-product-addons' ); ?></small>
                </label>
                <label>
                    <input type="radio" name="rule_scope" value="category">
                    <span><?php esc_html_e( 'Category Rule', 'woocommerce-product-addons' ); ?></span>
                    <small><?php esc_html_e( 'Applies to products in specific categories', 'woocommerce-product-addons' ); ?></small>
                </label>
                <label>
                    <input type="radio" name="rule_scope" value="product">
                    <span><?php esc_html_e( 'Product Rule', 'woocommerce-product-addons' ); ?></span>
                    <small><?php esc_html_e( 'Applies to specific products', 'woocommerce-product-addons' ); ?></small>
                </label>
            </div>
            
            <!-- Scope Target Selectors -->
            <div class="scope-target" id="category-target" style="display:none;">
                <label><?php esc_html_e( 'Select Categories:', 'woocommerce-product-addons' ); ?></label>
                <select class="wc-category-search" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for categories...', 'woocommerce-product-addons' ); ?>">
                </select>
            </div>
            
            <div class="scope-target" id="product-target" style="display:none;">
                <label><?php esc_html_e( 'Select Products:', 'woocommerce-product-addons' ); ?></label>
                <select class="wc-product-search" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for products...', 'woocommerce-product-addons' ); ?>">
                </select>
            </div>
        </div>

        <!-- Rule Builder -->
        <div class="rule-builder">
            <h2><?php esc_html_e( 'Rule Configuration', 'woocommerce-product-addons' ); ?></h2>
            
            <!-- Addon Context Selector - Affects entire rule page -->
            <div class="addon-context-selector global-context">
                <h3><?php esc_html_e( 'Add-on Context', 'woocommerce-product-addons' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Select which add-ons to show in all condition and action dropdowns on this page.', 'woocommerce-product-addons' ); ?></p>
                
                <div class="context-options">
                    <label>
                        <input type="radio" name="addon_context" value="all" checked>
                        <span><?php esc_html_e( 'All Add-ons', 'woocommerce-product-addons' ); ?></span>
                        <small><?php esc_html_e( 'Show global add-ons + add-ons from all products', 'woocommerce-product-addons' ); ?></small>
                    </label>
                    
                    <label>
                        <input type="radio" name="addon_context" value="global_only">
                        <span><?php esc_html_e( 'Global Add-ons Only', 'woocommerce-product-addons' ); ?></span>
                        <small><?php esc_html_e( 'Show only global add-on groups', 'woocommerce-product-addons' ); ?></small>
                    </label>
                    
                    <label>
                        <input type="radio" name="addon_context" value="specific_product">
                        <span><?php esc_html_e( 'Specific Product', 'woocommerce-product-addons' ); ?></span>
                        <small><?php esc_html_e( 'Show global add-ons + add-ons from a specific product', 'woocommerce-product-addons' ); ?></small>
                    </label>
                </div>
                
                <div class="product-selector" id="product-selector" style="display:none;">
                    <label><?php esc_html_e( 'Select Product:', 'woocommerce-product-addons' ); ?></label>
                    <select class="wc-product-search-context" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for a product...', 'woocommerce-product-addons' ); ?>">
                    </select>
                </div>
            </div>
            
            <!-- Rule Name -->
            <div class="rule-field">
                <label><?php esc_html_e( 'Rule Name:', 'woocommerce-product-addons' ); ?></label>
                <input type="text" id="rule-name" placeholder="<?php esc_attr_e( 'e.g., Hide shipping options for digital products', 'woocommerce-product-addons' ); ?>">
            </div>
            
            <!-- Conditions Section -->
            <div class="conditions-section">
                <h3><?php esc_html_e( 'IF (Conditions)', 'woocommerce-product-addons' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Define when this rule should apply. You can create condition groups with AND/OR logic.', 'woocommerce-product-addons' ); ?></p>
                
                <div class="condition-groups-container" id="condition-groups-container">
                    <!-- Condition groups will be added here dynamically -->
                </div>
                
                <div class="condition-controls">
                    <button type="button" class="button add-condition"><?php esc_html_e( '+ Add Condition', 'woocommerce-product-addons' ); ?></button>
                    <button type="button" class="button add-condition-group"><?php esc_html_e( '+ Add Condition Group', 'woocommerce-product-addons' ); ?></button>
                </div>
            </div>
            
            <!-- Actions Section -->
            <div class="actions-section">
                <h3><?php esc_html_e( 'THEN (Actions)', 'woocommerce-product-addons' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Define what should happen when conditions are met', 'woocommerce-product-addons' ); ?></p>
                
                <div class="actions-list" id="actions-container">
                    <!-- Actions will be added here dynamically -->
                </div>
                
                <button type="button" class="button add-action"><?php esc_html_e( '+ Add Action', 'woocommerce-product-addons' ); ?></button>
            </div>
            
            <!-- Save Rule Button -->
            <div class="rule-save-section">
                <button type="button" class="button button-primary save-rule"><?php esc_html_e( 'Save Rule', 'woocommerce-product-addons' ); ?></button>
                <button type="button" class="button cancel-rule"><?php esc_html_e( 'Cancel', 'woocommerce-product-addons' ); ?></button>
            </div>
        </div>
        </div>

        <!-- Existing Rules Tab Content -->
        <div id="existing-rules" class="tab-content">
            <div class="existing-rules">
                <h2><?php esc_html_e( 'Manage Existing Rules', 'woocommerce-product-addons' ); ?></h2>
                
                <!-- Filter Tabs -->
                <div class="rules-filter-tabs">
                    <button class="tab-button active" data-filter="all"><?php esc_html_e( 'All Rules', 'woocommerce-product-addons' ); ?></button>
                    <button class="tab-button" data-filter="global"><?php esc_html_e( 'Global', 'woocommerce-product-addons' ); ?></button>
                    <button class="tab-button" data-filter="category"><?php esc_html_e( 'Category', 'woocommerce-product-addons' ); ?></button>
                    <button class="tab-button" data-filter="product"><?php esc_html_e( 'Product', 'woocommerce-product-addons' ); ?></button>
                </div>
                
                <div class="rules-list" id="rules-list">
                    <!-- Rules will be loaded here via AJAX -->
                    <div class="no-rules-message">
                        <p><?php esc_html_e( 'No rules found. Create your first rule in the "Create New Rule" tab!', 'woocommerce-product-addons' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Templates -->
<script type="text/template" id="condition-template">
    <div class="condition-item" data-condition-id="">
        <div class="condition-row">
            <select class="condition-type">
                <option value=""><?php esc_html_e( 'Select condition type...', 'woocommerce-product-addons' ); ?></option>
                <optgroup label="<?php esc_attr_e( 'Add-on Fields', 'woocommerce-product-addons' ); ?>">
                    <option value="addon_field"><?php esc_html_e( 'Add-on Field Value', 'woocommerce-product-addons' ); ?></option>
                    <option value="addon_selected"><?php esc_html_e( 'Add-on Option Selected', 'woocommerce-product-addons' ); ?></option>
                </optgroup>
                <optgroup label="<?php esc_attr_e( 'Product', 'woocommerce-product-addons' ); ?>">
                    <option value="product_price"><?php esc_html_e( 'Product Price', 'woocommerce-product-addons' ); ?></option>
                    <option value="product_stock"><?php esc_html_e( 'Product Stock', 'woocommerce-product-addons' ); ?></option>
                    <option value="product_category"><?php esc_html_e( 'Product Category', 'woocommerce-product-addons' ); ?></option>
                </optgroup>
                <optgroup label="<?php esc_attr_e( 'Cart', 'woocommerce-product-addons' ); ?>">
                    <option value="cart_total"><?php esc_html_e( 'Cart Total', 'woocommerce-product-addons' ); ?></option>
                    <option value="cart_quantity"><?php esc_html_e( 'Cart Quantity', 'woocommerce-product-addons' ); ?></option>
                </optgroup>
                <optgroup label="<?php esc_attr_e( 'User', 'woocommerce-product-addons' ); ?>">
                    <option value="user_role"><?php esc_html_e( 'User Role', 'woocommerce-product-addons' ); ?></option>
                    <option value="user_logged_in"><?php esc_html_e( 'User Logged In', 'woocommerce-product-addons' ); ?></option>
                </optgroup>
                <optgroup label="<?php esc_attr_e( 'Date/Time', 'woocommerce-product-addons' ); ?>">
                    <option value="current_date"><?php esc_html_e( 'Current Date', 'woocommerce-product-addons' ); ?></option>
                    <option value="current_time"><?php esc_html_e( 'Current Time', 'woocommerce-product-addons' ); ?></option>
                    <option value="day_of_week"><?php esc_html_e( 'Day of Week', 'woocommerce-product-addons' ); ?></option>
                </optgroup>
            </select>
            
            <div class="condition-config">
                <!-- Dynamic configuration fields will be added here -->
            </div>
            
            <button type="button" class="remove-condition" title="<?php esc_attr_e( 'Remove condition', 'woocommerce-product-addons' ); ?>">×</button>
        </div>
    </div>
</script>

<script type="text/template" id="condition-group-template">
    <div class="condition-group" data-group-id="">
        <div class="condition-group-header">
            <span class="group-logic-indicator">IF</span>
            <select class="group-logic">
                <option value="AND"><?php esc_html_e( 'ALL conditions are met (AND)', 'woocommerce-product-addons' ); ?></option>
                <option value="OR"><?php esc_html_e( 'ANY condition is met (OR)', 'woocommerce-product-addons' ); ?></option>
            </select>
            <button type="button" class="remove-group" title="<?php esc_attr_e( 'Remove group', 'woocommerce-product-addons' ); ?>">×</button>
        </div>
        <div class="conditions-in-group">
            <!-- Individual conditions will be added here -->
        </div>
        <div class="group-controls">
            <button type="button" class="button add-condition-to-group"><?php esc_html_e( '+ Add Condition to Group', 'woocommerce-product-addons' ); ?></button>
        </div>
        <div class="group-relationship">
            <select class="group-relationship-selector">
                <option value="AND"><?php esc_html_e( 'AND', 'woocommerce-product-addons' ); ?></option>
                <option value="OR"><?php esc_html_e( 'OR', 'woocommerce-product-addons' ); ?></option>
            </select>
            <span class="relationship-label"><?php esc_html_e( 'with next group', 'woocommerce-product-addons' ); ?></span>
        </div>
    </div>
</script>

<script type="text/template" id="action-template">
    <div class="action-item" data-action-id="">
        <div class="action-row">
            <select class="action-type">
                <option value=""><?php esc_html_e( 'Select action type...', 'woocommerce-product-addons' ); ?></option>
                <option value="show_addon"><?php esc_html_e( 'Show Add-on', 'woocommerce-product-addons' ); ?></option>
                <option value="hide_addon"><?php esc_html_e( 'Hide Add-on', 'woocommerce-product-addons' ); ?></option>
                <option value="show_option"><?php esc_html_e( 'Show Add-on Option', 'woocommerce-product-addons' ); ?></option>
                <option value="hide_option"><?php esc_html_e( 'Hide Add-on Option', 'woocommerce-product-addons' ); ?></option>
                <option value="set_price"><?php esc_html_e( 'Set Add-on Price', 'woocommerce-product-addons' ); ?></option>
                <option value="adjust_price"><?php esc_html_e( 'Adjust Add-on Price', 'woocommerce-product-addons' ); ?></option>
                <option value="make_required"><?php esc_html_e( 'Make Add-on Required', 'woocommerce-product-addons' ); ?></option>
                <option value="make_optional"><?php esc_html_e( 'Make Add-on Optional', 'woocommerce-product-addons' ); ?></option>
                <option value="set_label"><?php esc_html_e( 'Change Add-on Label', 'woocommerce-product-addons' ); ?></option>
                <option value="set_description"><?php esc_html_e( 'Change Add-on Description', 'woocommerce-product-addons' ); ?></option>
            </select>
            
            <div class="action-config">
                <!-- Dynamic configuration fields will be added here -->
            </div>
            
            <button type="button" class="remove-action" title="<?php esc_attr_e( 'Remove action', 'woocommerce-product-addons' ); ?>">×</button>
        </div>
    </div>
</script>

<script type="text/template" id="rule-item-template">
    <div class="rule-item" data-rule-id="{rule_id}" data-scope="{scope}">
        <div class="drag-handle" title="<?php esc_attr_e( 'Drag to reorder rules (lower position = higher priority)', 'woocommerce-product-addons' ); ?>">
            <span class="dashicons dashicons-move"></span>
        </div>
        <div class="rule-content">
            <div class="rule-header">
                <div class="rule-header-main">
                    <h4 class="rule-title">{rule_name}</h4>
                    <div class="rule-meta">
                        <span class="rule-priority" title="<?php esc_attr_e( 'Rule Priority', 'woocommerce-product-addons' ); ?>">#{priority}</span>
                        <span class="rule-scope-badge {scope}">{scope_label}</span>
                        <span class="rule-status {status}">{status_label}</span>
                    </div>
                </div>
                <div class="rule-actions">
                    <button class="edit-rule" title="<?php esc_attr_e( 'Edit rule', 'woocommerce-product-addons' ); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button class="duplicate-rule" title="<?php esc_attr_e( 'Duplicate rule', 'woocommerce-product-addons' ); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                    <button class="toggle-rule" title="<?php esc_attr_e( 'Toggle rule', 'woocommerce-product-addons' ); ?>">
                        <span class="dashicons dashicons-{toggle_icon}"></span>
                    </button>
                    <button class="delete-rule" title="<?php esc_attr_e( 'Delete rule', 'woocommerce-product-addons' ); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="rule-summary">
                <div class="conditions-summary">
                    <strong><?php esc_html_e( 'IF:', 'woocommerce-product-addons' ); ?></strong>
                    <span>{conditions_summary}</span>
                </div>
                <div class="actions-summary">
                    <strong><?php esc_html_e( 'THEN:', 'woocommerce-product-addons' ); ?></strong>
                    <span>{actions_summary}</span>
                </div>
            </div>
        </div>
    </div>
</script>