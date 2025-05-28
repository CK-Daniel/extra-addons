# Extended Conditional Logic Options - Complete Feature Set

## Price Modification Options

### 1. Price Calculation Methods
```javascript
{
    "price_action": {
        "type": "add|subtract|multiply|divide|set|formula|percentage_of_base|percentage_of_total",
        "value": "10",
        "apply_to": "self|other_addon|all_addons|specific_addons|product_base_price",
        "calculation_base": "original_price|current_price|product_price|cart_total|selected_options_total",
        "compound": true, // Apply on top of other modifications
        "priority": 1 // Order of application when multiple rules apply
    }
}
```

### 2. Advanced Price Actions

#### a) Cascade Pricing
```javascript
{
    "action": "cascade_price_change",
    "config": {
        "trigger": "Option X selected",
        "effects": [
            {
                "target": "all_addons",
                "change": "+15%",
                "exclude": ["addon_id_1", "addon_id_2"]
            },
            {
                "target": "specific_category",
                "category": "Premium Options",
                "change": "+25%"
            }
        ]
    }
}
```

#### b) Dynamic Price Scaling
```javascript
{
    "action": "scale_prices",
    "config": {
        "base_on": "quantity|selection_count|user_input_value",
        "scaling_formula": "logarithmic|linear|exponential|stepped",
        "min_price": 5,
        "max_price": 500,
        "breakpoints": [
            {"at": 10, "multiplier": 0.9},
            {"at": 50, "multiplier": 0.7},
            {"at": 100, "multiplier": 0.5}
        ]
    }
}
```

#### c) Price Synchronization
```javascript
{
    "action": "sync_price",
    "config": {
        "sync_with": "addon_name",
        "sync_type": "match|percentage|inverse|complement",
        "sync_formula": "target_price * 0.5"
    }
}
```

#### d) Time-Based Pricing
```javascript
{
    "action": "time_based_price",
    "config": {
        "schedule": [
            {"days": "weekday", "modifier": "base"},
            {"days": "weekend", "modifier": "+25%"},
            {"hours": "18-22", "modifier": "+15%"}, // Peak hours
            {"date_range": "2024-12-20 to 2024-12-26", "modifier": "+50%"} // Holiday
        ]
    }
}
```

## Field Modification Options

### 3. Label and Text Modifications
```javascript
{
    "action": "modify_text",
    "modifications": {
        "label": {
            "type": "replace|append|prepend|formula",
            "value": "New Label Text",
            "dynamic_values": {
                "{selected_option}": "addon.other_field.selected_label",
                "{price}": "addon.calculated_price",
                "{quantity}": "product.quantity"
            }
        },
        "description": "Dynamic description based on selection",
        "placeholder": "Enter {selected_option} details",
        "help_text": "This option adds {price} to your total"
    }
}
```

### 4. Option Modifications
```javascript
{
    "action": "modify_options",
    "modifications": {
        "add_options": [
            {"label": "New Option", "value": "new_opt", "price": "10"}
        ],
        "remove_options": ["option_value_1", "option_value_2"],
        "disable_options": ["option_value_3"],
        "reorder_options": ["opt_3", "opt_1", "opt_2"],
        "group_options": {
            "Basic": ["opt_1", "opt_2"],
            "Premium": ["opt_3", "opt_4"]
        },
        "transform_options": {
            "option_1": {
                "new_label": "Premium {original_label}",
                "new_price": "{original_price} * 1.5"
            }
        }
    }
}
```

### 5. Validation Rule Modifications
```javascript
{
    "action": "modify_validation",
    "rules": {
        "required": "conditional", // true|false|conditional
        "min_length": "{other_field_length} + 5",
        "max_length": 100,
        "pattern": "^[A-Z]{selected_length}[0-9]+$",
        "custom_validation": "validate_against_api",
        "error_messages": {
            "required": "This field is required when {condition}",
            "pattern": "Please enter {expected_format}"
        },
        "allowed_values": "dynamic_list_from_api",
        "forbidden_values": ["value1", "value2"],
        "unique_in_cart": true
    }
}
```

## Advanced Conditional Actions

### 6. Stock and Inventory Actions
```javascript
{
    "action": "modify_stock_behavior",
    "config": {
        "check_combined_stock": true,
        "reserve_stock": {
            "duration": "15_minutes",
            "show_timer": true
        },
        "stock_threshold_actions": [
            {"below": 10, "action": "add_scarcity_message"},
            {"below": 5, "action": "increase_price", "amount": "+10%"},
            {"below": 2, "action": "require_phone_number"}
        ]
    }
}
```

### 7. Bundle and Package Creation
```javascript
{
    "action": "create_bundle",
    "config": {
        "auto_select": ["addon_1", "addon_2"],
        "bundle_discount": "-20%",
        "bundle_name": "Complete Package",
        "show_savings": true,
        "lock_bundle": true, // Prevent deselection
        "replace_individual_prices": true
    }
}
```

### 8. Cross-Product Conditions
```javascript
{
    "action": "cross_product_modification",
    "config": {
        "when": "Cart contains Product X",
        "apply_to": "all_instances|first_instance|specific_product",
        "modifications": {
            "unlock_options": ["premium_option_1"],
            "apply_discount": "-15%",
            "add_bonus_addon": "free_gift"
        }
    }
}
```

### 9. User Experience Modifications
```javascript
{
    "action": "modify_ux",
    "modifications": {
        "highlight_field": {
            "color": "#ff6b6b",
            "animation": "pulse|shake|glow",
            "duration": 3000
        },
        "show_tooltip": {
            "content": "Save {amount} by selecting this!",
            "position": "top|right|bottom|left",
            "trigger": "hover|focus|always"
        },
        "progress_indicator": {
            "show": true,
            "steps": ["Select Base", "Add Features", "Customize", "Review"],
            "current_step": "based_on_selections"
        },
        "recommendation_engine": {
            "show_suggestions": true,
            "based_on": "popular|complementary|ai_powered",
            "position": "inline|sidebar|modal"
        }
    }
}
```

### 10. Calculation and Formula Options
```javascript
{
    "action": "custom_calculation",
    "formulas": {
        "price": {
            "formula": "(base_price + addon_total) * quantity * (1 + tax_rate) - discount",
            "variables": {
                "base_price": "product.price",
                "addon_total": "sum(selected_addons.price)",
                "quantity": "product.quantity",
                "tax_rate": "location.tax_rate",
                "discount": "if(user.vip, 0.1 * subtotal, 0)"
            },
            "rounding": "up|down|nearest|none",
            "decimal_places": 2
        },
        "custom_fields": {
            "area": "width * height",
            "volume": "width * height * depth",
            "price_per_unit": "total_price / area"
        }
    }
}
```

### 11. Quantity and Limit Modifications
```javascript
{
    "action": "modify_limits",
    "limits": {
        "min_quantity": {
            "value": "dynamic",
            "formula": "if(bulk_order, 10, 1)"
        },
        "max_quantity": {
            "value": "stock_available - reserved",
            "message": "Only {max} available"
        },
        "step": 5, // Quantity increments
        "max_selections": {
            "per_addon": 3,
            "total": 10,
            "per_category": {"toppings": 5, "sauces": 2}
        },
        "min_total_price": 50,
        "max_total_price": 5000
    }
}
```

### 12. Display and Layout Modifications
```javascript
{
    "action": "modify_display",
    "display_options": {
        "layout": "grid|list|accordion|tabs|wizard",
        "columns": "dynamic", // 1-4 based on screen size
        "group_by": "category|price_range|availability",
        "sort_by": "price|name|popularity|custom_order",
        "filter_options": {
            "show_filters": true,
            "filter_by": ["price_range", "features", "availability"]
        },
        "image_display": {
            "show_images": "on_hover|always|in_modal",
            "image_size": "thumbnail|medium|large",
            "gallery_view": true
        },
        "comparison_mode": {
            "enable": true,
            "compare_up_to": 3,
            "highlight_differences": true
        }
    }
}
```

### 13. Auto-Actions and Smart Defaults
```javascript
{
    "action": "auto_actions",
    "triggers": {
        "on_page_load": {
            "pre_select": ["most_popular_option"],
            "expand_sections": ["recommended"],
            "focus_field": "first_required"
        },
        "on_selection": {
            "auto_calculate": true,
            "auto_save_draft": true,
            "prefill_related": true
        },
        "smart_defaults": {
            "based_on": "user_history|popular_choices|ai_recommendation",
            "confidence_threshold": 0.8
        }
    }
}
```

### 14. Integration Actions
```javascript
{
    "action": "external_integration",
    "integrations": {
        "api_lookup": {
            "endpoint": "https://api.example.com/validate",
            "method": "POST",
            "send_data": ["field_1", "field_2"],
            "update_fields": {
                "price": "response.calculated_price",
                "availability": "response.in_stock"
            }
        },
        "webhook": {
            "url": "https://hooks.example.com/addon-selected",
            "events": ["selection_changed", "validation_failed"]
        },
        "third_party_service": {
            "service": "shipping_calculator",
            "auto_update_price": true
        }
    }
}
```

### 15. Conditional Styling
```javascript
{
    "action": "conditional_styling",
    "styles": {
        "css_classes": {
            "add": ["premium-option", "highlighted"],
            "remove": ["basic-option"],
            "toggle": ["expanded"]
        },
        "inline_styles": {
            "background-color": "#f0f0f0",
            "border": "2px solid {brand_color}",
            "opacity": 0.5
        },
        "animations": {
            "entrance": "fadeIn|slideDown|bounce",
            "exit": "fadeOut|slideUp|shake",
            "on_change": "pulse|flash"
        }
    }
}
```

### 16. Multi-Language Support
```javascript
{
    "action": "language_modification",
    "translations": {
        "dynamic_text": {
            "en": "Select {option_name} (+${price})",
            "es": "Seleccionar {option_name} (+${price})",
            "fr": "Sélectionner {option_name} (+${price})"
        },
        "conditional_language": {
            "show_in": ["en", "es"],
            "hide_in": ["de", "fr"]
        }
    }
}
```

### 17. Advanced Price Relationship Rules
```javascript
{
    "action": "price_relationships",
    "rules": {
        "price_matching": {
            "match_lowest": ["addon_1", "addon_2", "addon_3"],
            "match_highest": ["premium_1", "premium_2"],
            "match_average": ["standard_options"]
        },
        "price_distribution": {
            "total_budget": 1000,
            "distribute_among": ["selected_addons"],
            "distribution_method": "equal|weighted|proportional"
        },
        "price_dependencies": {
            "addon_a": "addon_b.price * 0.5",
            "addon_b": "addon_c.price + 10",
            "addon_c": "base_price * 0.1"
        }
    }
}
```

### 18. Conditional Field Types
```javascript
{
    "action": "change_field_type",
    "transformations": {
        "from": "select",
        "to": "radio|checkbox|image_select|slider",
        "conditions": {
            "when_options_count": "< 5",
            "when_screen_size": "mobile",
            "when_user_preference": "visual_mode"
        }
    }
}
```

### 19. Data Persistence and Memory
```javascript
{
    "action": "data_persistence",
    "config": {
        "remember_selections": {
            "duration": "session|day|week|forever",
            "scope": "product|category|global"
        },
        "auto_restore": true,
        "sync_across_devices": true,
        "selection_history": {
            "track": true,
            "use_for_recommendations": true
        }
    }
}
```

### 20. Advanced Conditional Operators
```javascript
{
    "operators": {
        "mathematical": [
            "equals", "not_equals", "greater_than", "less_than",
            "between", "not_between", "divisible_by", "in_range"
        ],
        "string": [
            "contains", "not_contains", "starts_with", "ends_with",
            "matches_regex", "is_email", "is_phone", "is_url"
        ],
        "date_time": [
            "is_before", "is_after", "is_between_dates", "is_weekday",
            "is_weekend", "is_holiday", "is_business_hours", "timezone_is"
        ],
        "list": [
            "in_list", "not_in_list", "list_contains_all", "list_contains_any",
            "list_is_empty", "list_count_equals"
        ],
        "special": [
            "is_first_purchase", "is_returning_customer", "has_purchased_before",
            "cart_contains", "user_meta_equals", "custom_function"
        ]
    }
}
```

## Implementation Priority Matrix

### High Priority (Core Features)
1. Basic price modifications (add/subtract/set)
2. Show/hide conditions
3. Required field conditions
4. Simple field-based rules

### Medium Priority (Enhanced Features)
1. Cascade pricing
2. Dynamic option modifications
3. Validation rule changes
4. Bundle creation
5. Cross-product conditions

### Low Priority (Advanced Features)
1. AI-powered recommendations
2. External API integrations
3. Complex formula engine
4. Multi-language dynamic text
5. Advanced animations and UX features

## Usage Examples

### Example 1: Photography Package Builder
```javascript
{
    "when": "Package Type = Wedding",
    "actions": [
        {
            "type": "cascade_price_change",
            "all_addons": "+25%",
            "message": "Wedding premium applied"
        },
        {
            "type": "unlock_options",
            "addons": ["Second Photographer", "Drone Coverage"]
        },
        {
            "type": "set_minimum",
            "addon": "Hours of Coverage",
            "value": 6
        },
        {
            "type": "bundle_suggestion",
            "suggest": ["Album", "Prints", "Digital Gallery"],
            "discount": "-15%"
        }
    ]
}
```

### Example 2: Software License Configuration
```javascript
{
    "when": "License Type = Enterprise",
    "actions": [
        {
            "type": "formula_pricing",
            "formula": "base_price * number_of_users * (1 - volume_discount)",
            "variables": {
                "volume_discount": "min(0.5, users * 0.001)"
            }
        },
        {
            "type": "dynamic_options",
            "addon": "Support Level",
            "add_options": ["24/7 Phone", "Dedicated Account Manager"]
        },
        {
            "type": "cross_sell",
            "suggest": "Training Package",
            "condition": "number_of_users > 50"
        }
    ]
}
```

This extended set of conditional logic options provides maximum flexibility for creating complex, dynamic product configurations that can adapt to any business need.