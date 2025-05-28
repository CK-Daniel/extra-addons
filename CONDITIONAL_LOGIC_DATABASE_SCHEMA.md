# Conditional Logic - Database Schema & Data Structure

## Enhanced Addon Data Structure

```php
// Complete addon structure with conditional logic
array(
    'name' => 'Color Selection',
    'title_format' => 'standard', // standard|dynamic|formula
    'description' => 'Choose your preferred color',
    'type' => 'multiple_choice',
    'display' => 'select',
    'position' => 0,
    'required' => 0, // Can be overridden by conditions
    'restrictions' => 0,
    'restrictions_type' => 'any_text',
    'adjust_price' => 1,
    'price_type' => 'flat_fee',
    'price' => '',
    'min' => 0,
    'max' => 0,
    
    // Options array
    'options' => array(
        array(
            'label' => 'Red',
            'price' => '10',
            'image' => '',
            'price_type' => 'flat_fee',
            'value' => 'red', // Internal value for conditions
            'stock' => null, // Individual option stock
            'sku' => '', // Option SKU
            'weight' => 0, // Additional weight
            'conditional_modifiers' => array() // Per-option conditions
        )
    ),
    
    // NEW: Conditional Logic Configuration
    'conditional_logic' => array(
        'version' => '1.0', // Schema version for migrations
        'enabled' => true,
        'debug_mode' => false, // Show condition evaluation in frontend
        
        // Condition Groups (OR between groups, AND within groups)
        'condition_groups' => array(
            array(
                'id' => 'group_1',
                'name' => 'Premium Color Conditions',
                'enabled' => true,
                'priority' => 10, // Higher priority evaluates first
                'match_type' => 'all', // all|any|custom
                
                // Conditions within this group
                'conditions' => array(
                    array(
                        'id' => 'cond_1',
                        'type' => 'field', // field|product|cart|user|date|external
                        'target' => array(
                            'addon' => 'material_type',
                            'field' => 'selected_value'
                        ),
                        'operator' => 'equals', // equals|not_equals|contains|greater_than|etc
                        'value' => 'premium_leather',
                        'case_sensitive' => false
                    ),
                    array(
                        'id' => 'cond_2',
                        'type' => 'cart',
                        'target' => 'total',
                        'operator' => 'greater_than',
                        'value' => 100
                    )
                ),
                
                // Actions to perform when conditions match
                'actions' => array(
                    array(
                        'id' => 'action_1',
                        'type' => 'modify_price',
                        'priority' => 10,
                        'config' => array(
                            'target' => 'self', // self|other|all|category
                            'target_addon' => null, // For 'other' target
                            'modification_type' => 'cascade',
                            'modification' => array(
                                'method' => 'percentage_add',
                                'value' => 25,
                                'compound' => true,
                                'round' => true,
                                'round_precision' => 2,
                                'min_price' => 0,
                                'max_price' => null
                            ),
                            'apply_to_options' => 'all', // all|specific|except
                            'specific_options' => array(),
                            'except_options' => array('basic_red', 'basic_blue'),
                            'message' => array(
                                'text' => 'Premium pricing applied (+25%)',
                                'type' => 'info', // info|warning|success
                                'position' => 'inline' // inline|tooltip|modal
                            )
                        )
                    ),
                    array(
                        'id' => 'action_2',
                        'type' => 'modify_options',
                        'config' => array(
                            'add_options' => array(
                                array(
                                    'label' => 'Exclusive Gold',
                                    'value' => 'exclusive_gold',
                                    'price' => '50',
                                    'price_type' => 'flat_fee',
                                    'position' => 0 // Insert at beginning
                                )
                            ),
                            'remove_options' => array('basic_red', 'basic_blue'),
                            'disable_options' => array(),
                            'modify_existing' => array(
                                'premium_red' => array(
                                    'label' => 'Premium Red (Limited Edition)',
                                    'price_modifier' => '+10'
                                )
                            )
                        )
                    ),
                    array(
                        'id' => 'action_3',
                        'type' => 'visibility',
                        'config' => array(
                            'action' => 'show', // show|hide|fade_in|slide_down
                            'animation_duration' => 300,
                            'scroll_to' => true
                        )
                    ),
                    array(
                        'id' => 'action_4',
                        'type' => 'requirement',
                        'config' => array(
                            'required' => true,
                            'validation_message' => 'Premium material requires color selection'
                        )
                    )
                )
            )
        ),
        
        // Global price cascade rules
        'price_cascade_rules' => array(
            array(
                'id' => 'cascade_1',
                'name' => 'Material-based pricing',
                'trigger' => array(
                    'addon' => 'material_type',
                    'values' => array('premium_leather', 'exotic_wood')
                ),
                'effects' => array(
                    array(
                        'scope' => 'all_addons',
                        'exclude' => array('material_type'),
                        'modification' => array(
                            'premium_leather' => array(
                                'type' => 'percentage_add',
                                'value' => 30
                            ),
                            'exotic_wood' => array(
                                'type' => 'percentage_add',
                                'value' => 45
                            )
                        )
                    )
                )
            )
        ),
        
        // Formula definitions
        'formulas' => array(
            'custom_area_pricing' => array(
                'expression' => '(width * height * material_factor) + base_price',
                'variables' => array(
                    'width' => 'addon:dimensions.width',
                    'height' => 'addon:dimensions.height',
                    'material_factor' => 'addon:material_type.price_factor',
                    'base_price' => 'product:price'
                ),
                'constraints' => array(
                    'min' => 0,
                    'max' => 10000
                )
            )
        ),
        
        // Time-based rules
        'time_rules' => array(
            array(
                'id' => 'time_1',
                'type' => 'recurring',
                'schedule' => array(
                    'days' => array('saturday', 'sunday'),
                    'time_ranges' => array(),
                    'timezone' => 'store' // store|user
                ),
                'actions' => array(
                    array(
                        'type' => 'modify_price',
                        'modification' => array(
                            'type' => 'percentage_add',
                            'value' => 15
                        )
                    )
                )
            ),
            array(
                'id' => 'time_2',
                'type' => 'date_range',
                'start' => '2024-12-20',
                'end' => '2024-12-31',
                'actions' => array(
                    array(
                        'type' => 'show_message',
                        'message' => 'Holiday pricing in effect'
                    )
                )
            )
        ),
        
        // Dependencies and relationships
        'dependencies' => array(
            array(
                'type' => 'requires',
                'source' => 'color_selection',
                'target' => 'color_protection',
                'message' => 'Color protection recommended with color selection'
            ),
            array(
                'type' => 'excludes',
                'options' => array('option_a', 'option_b'),
                'message' => 'These options cannot be selected together'
            )
        ),
        
        // Performance settings
        'performance' => array(
            'cache_ttl' => 3600, // Cache evaluated conditions for 1 hour
            'evaluate_on' => array('change', 'load'), // When to evaluate
            'debounce_ms' => 300, // Debounce rapid changes
            'batch_updates' => true // Batch DOM updates
        )
    ),
    
    // NEW: Dynamic pricing configuration
    'dynamic_pricing' => array(
        'enabled' => true,
        'base_price_source' => 'fixed', // fixed|product|formula
        'price_modifiers' => array(
            array(
                'id' => 'mod_1',
                'type' => 'quantity_breaks',
                'breaks' => array(
                    array('min' => 1, 'max' => 9, 'modifier' => 0),
                    array('min' => 10, 'max' => 49, 'modifier' => -10),
                    array('min' => 50, 'max' => null, 'modifier' => -20)
                )
            ),
            array(
                'id' => 'mod_2',
                'type' => 'user_role',
                'roles' => array(
                    'wholesale' => -15,
                    'vip' => -10,
                    'subscriber' => -5
                )
            )
        )
    ),
    
    // NEW: Display configuration
    'display_config' => array(
        'layout' => 'default', // default|grid|accordion|wizard
        'columns' => 0, // 0 = auto
        'show_prices' => true,
        'price_format' => '+{price}', // How to display price
        'image_position' => 'left', // left|right|top|tooltip
        'help_text_position' => 'below', // below|tooltip|modal
        'mobile_layout' => 'stack' // stack|carousel|accordion
    ),
    
    // NEW: Analytics tracking
    'analytics' => array(
        'track_selections' => true,
        'track_abandonment' => true,
        'conversion_goals' => array(
            'premium_selection' => array('premium_red', 'premium_blue', 'exclusive_gold')
        )
    )
)
```

## Database Tables Structure

### Table: wp_wc_product_addon_rules (New)
```sql
CREATE TABLE wp_wc_product_addon_rules (
    rule_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_name VARCHAR(255) NOT NULL,
    rule_type ENUM('product', 'global', 'category', 'tag') DEFAULT 'product',
    scope_id BIGINT(20) UNSIGNED DEFAULT NULL, -- Product ID, Category ID, etc.
    conditions LONGTEXT NOT NULL, -- JSON encoded conditions
    actions LONGTEXT NOT NULL, -- JSON encoded actions
    priority INT(11) DEFAULT 10,
    enabled TINYINT(1) DEFAULT 1,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    usage_count BIGINT(20) DEFAULT 0,
    created_by BIGINT(20) UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id),
    KEY idx_rule_type_scope (rule_type, scope_id),
    KEY idx_enabled_priority (enabled, priority),
    KEY idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: wp_wc_product_addon_rule_usage (New)
```sql
CREATE TABLE wp_wc_product_addon_rule_usage (
    usage_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id BIGINT(20) UNSIGNED NOT NULL,
    order_id BIGINT(20) UNSIGNED DEFAULT NULL,
    user_id BIGINT(20) UNSIGNED DEFAULT NULL,
    session_id VARCHAR(255) DEFAULT NULL,
    product_id BIGINT(20) UNSIGNED NOT NULL,
    addon_name VARCHAR(255) NOT NULL,
    original_price DECIMAL(10,2) DEFAULT NULL,
    modified_price DECIMAL(10,2) DEFAULT NULL,
    modification_details LONGTEXT, -- JSON encoded
    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usage_id),
    KEY idx_rule_id (rule_id),
    KEY idx_order_id (order_id),
    KEY idx_user_id (user_id),
    KEY idx_product_addon (product_id, addon_name),
    KEY idx_used_at (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: wp_wc_product_addon_formulas (New)
```sql
CREATE TABLE wp_wc_product_addon_formulas (
    formula_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    formula_name VARCHAR(255) NOT NULL,
    formula_expression TEXT NOT NULL,
    variables JSON NOT NULL,
    validation_rules JSON DEFAULT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT NULL,
    is_global TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (formula_id),
    UNIQUE KEY idx_formula_name (formula_name),
    KEY idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Optimized Data Storage

### JSON Structure for Complex Conditions
```json
{
    "version": "1.0",
    "conditions": [
        {
            "id": "cond_123",
            "group": "AND",
            "rules": [
                {
                    "field": "addon:material_type",
                    "operator": "in",
                    "value": ["leather", "suede"]
                },
                {
                    "field": "cart:total",
                    "operator": ">=",
                    "value": 200
                }
            ]
        }
    ],
    "actions": [
        {
            "type": "cascade_price",
            "targets": {
                "include": "all",
                "exclude": ["material_type"]
            },
            "modification": {
                "type": "percentage",
                "value": 25,
                "compound": true
            }
        }
    ]
}
```

## Migration Strategy

```php
// Upgrade routine for existing installations
class WC_Product_Addons_Conditional_Logic_Migration {
    
    public function migrate_to_conditional_logic() {
        global $wpdb;
        
        // Get all products with addons
        $products = $wpdb->get_results(
            "SELECT post_id, meta_value 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_product_addons'"
        );
        
        foreach ($products as $product) {
            $addons = maybe_unserialize($product->meta_value);
            
            if (is_array($addons)) {
                foreach ($addons as &$addon) {
                    // Add conditional logic structure
                    if (!isset($addon['conditional_logic'])) {
                        $addon['conditional_logic'] = array(
                            'version' => '1.0',
                            'enabled' => false,
                            'condition_groups' => array()
                        );
                    }
                    
                    // Add dynamic pricing structure
                    if (!isset($addon['dynamic_pricing'])) {
                        $addon['dynamic_pricing'] = array(
                            'enabled' => false,
                            'price_modifiers' => array()
                        );
                    }
                }
                
                // Update the addon data
                update_post_meta($product->post_id, '_product_addons', $addons);
            }
        }
        
        // Create new tables
        $this->create_conditional_logic_tables();
        
        // Update version
        update_option('wc_product_addons_conditional_logic_version', '1.0');
    }
}
```

This database schema provides a robust foundation for storing and managing all conditional logic configurations while maintaining performance and flexibility.