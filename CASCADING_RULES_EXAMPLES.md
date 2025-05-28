# Cascading Conditional Logic Examples

## Overview

Cascading conditional logic allows rules to trigger other rules, creating complex dependency chains where one addon's state can influence multiple other addons in sequence.

## How It Works

1. **Multi-pass Evaluation**: The system evaluates all rules up to 10 times until no more changes occur
2. **Priority-based Execution**: Rules with lower priority numbers execute first (Priority 1 before Priority 5)
3. **Circular Dependency Protection**: The system detects and prevents infinite loops
4. **State Tracking**: Each rule can check the current state of other rules' results

## Basic Cascading Example

### Scenario: Photography Package Builder

```javascript
// Rule 1 (Priority 1): Package Selection
{
    "addon": "Package Type",
    "conditions": [
        {
            "type": "field",
            "target": "package_selection",
            "operator": "equals",
            "value": "wedding"
        }
    ],
    "actions": [
        {
            "type": "visibility",
            "config": {
                "target": "other",
                "target_addon": "Ceremony Coverage",
                "action": "show"
            }
        },
        {
            "type": "price",
            "config": {
                "target": "all",
                "modification": {
                    "method": "percentage_add",
                    "value": 50
                }
            }
        }
    ]
}

// Rule 2 (Priority 2): Triggered by Rule 1
{
    "addon": "Additional Services",
    "conditions": [
        {
            "type": "rule", // <-- This checks another rule's state
            "target_addon": "Ceremony Coverage",
            "property": "visible",
            "operator": "equals",
            "value": "true"
        }
    ],
    "actions": [
        {
            "type": "modifier",
            "config": {
                "text_modifications": {
                    "label": {
                        "type": "replace",
                        "value": "Wedding Additional Services"
                    }
                }
            }
        }
    ]
}

// Rule 3 (Priority 3): Triggered by Rule 2
{
    "addon": "Photographer Count",
    "conditions": [
        {
            "type": "rule",
            "target_addon": "Additional Services", 
            "property": "has_modifications",
            "operator": "equals",
            "value": "true"
        }
    ],
    "actions": [
        {
            "type": "requirement",
            "config": {
                "required": true
            }
        }
    ]
}
```

## Advanced Cascading Example

### Scenario: Custom Computer Builder with Dependencies

```javascript
// Rule 1: CPU Selection Triggers Multiple Changes
{
    "addon": "Processor",
    "priority": 1,
    "conditions": [
        {
            "type": "field",
            "target": "processor",
            "operator": "in",
            "value": ["i9-13900K", "i7-13700K"]
        }
    ],
    "actions": [
        // Show high-end cooling options
        {
            "type": "visibility",
            "target": "other",
            "target_addon": "Cooling System",
            "action": "show"
        },
        // Make power supply required
        {
            "type": "requirement",
            "target": "other", 
            "target_addon": "Power Supply",
            "required": true
        },
        // Increase all component prices
        {
            "type": "price",
            "target": "category",
            "category": "components",
            "modification": {
                "method": "percentage_add",
                "value": 15
            }
        }
    ]
}

// Rule 2: Cooling System Becomes Visible → Memory Speed Options
{
    "addon": "Memory Speed",
    "priority": 2,
    "conditions": [
        {
            "type": "rule",
            "target_addon": "Cooling System",
            "operator": "is_visible"
        }
    ],
    "actions": [
        {
            "type": "modifier",
            "config": {
                "add_options": [
                    {
                        "label": "DDR5-6000 (High Performance)",
                        "value": "ddr5_6000",
                        "price": "200"
                    }
                ]
            }
        }
    ]
}

// Rule 3: High-End Memory → Storage Recommendations
{
    "addon": "Storage",
    "priority": 3,
    "conditions": [
        {
            "type": "rule",
            "target_addon": "Memory Speed",
            "operator": "has_options_modified"
        },
        {
            "type": "field",
            "target": "memory_speed",
            "operator": "contains",
            "value": "ddr5_6000"
        }
    ],
    "actions": [
        {
            "type": "modifier",
            "config": {
                "text_modifications": {
                    "label": {
                        "type": "replace",
                        "value": "High-Performance Storage (Recommended)"
                    }
                }
            }
        },
        {
            "type": "price",
            "config": {
                "modification": {
                    "method": "percentage_subtract",
                    "value": 10
                }
            }
        }
    ]
}
```

## Conditional Logic State Operators

### Available Rule State Operators

1. **`is_visible`** / **`is_hidden`**: Check if target addon is shown/hidden
2. **`is_required`** / **`is_not_required`**: Check if target addon is required
3. **`has_price_modified`**: Check if target addon has price changes
4. **`has_options_modified`**: Check if target addon has option changes
5. **`equals`** / **`not_equals`**: Compare specific rule property values

### Rule Properties You Can Check

- `visible`: Boolean - addon visibility state
- `required`: Boolean - addon requirement state  
- `price_modifiers_count`: Number - count of price modifications
- `option_modifiers_count`: Number - count of option modifications
- `has_modifications`: Boolean - any modifications applied
- `modification_count`: Number - total modifications

## Complex Multi-Level Example

### Scenario: Restaurant Menu with Dietary Restrictions and Preparation Dependencies

```javascript
// Level 1: Dietary Preference Selection
{
    "addon": "Dietary Preferences",
    "priority": 1,
    "conditions": [
        {
            "type": "field",
            "target": "dietary_type",
            "operator": "equals", 
            "value": "vegan"
        }
    ],
    "actions": [
        {
            "type": "visibility",
            "target": "other",
            "target_addon": "Vegan Proteins",
            "action": "show"
        },
        {
            "type": "visibility", 
            "target": "other",
            "target_addon": "Dairy Products",
            "action": "hide"
        }
    ]
}

// Level 2: Vegan Proteins Visible → Special Preparation Options
{
    "addon": "Preparation Methods",
    "priority": 2,
    "conditions": [
        {
            "type": "rule",
            "target_addon": "Vegan Proteins", 
            "operator": "is_visible"
        }
    ],
    "actions": [
        {
            "type": "modifier",
            "config": {
                "add_options": [
                    {
                        "label": "Plant-Based Marinade",
                        "value": "plant_marinade",
                        "price": "3"
                    }
                ]
            }
        }
    ]
}

// Level 3: Special Prep Added → Cooking Time Adjustment
{
    "addon": "Delivery Time",
    "priority": 3,
    "conditions": [
        {
            "type": "rule",
            "target_addon": "Preparation Methods",
            "operator": "has_options_modified"
        },
        {
            "type": "field",
            "target": "preparation_method",
            "operator": "equals",
            "value": "plant_marinade"
        }
    ],
    "actions": [
        {
            "type": "modifier",
            "config": {
                "text_modifications": {
                    "description": {
                        "type": "replace", 
                        "value": "Extended preparation time required for marinated vegan proteins (+15 minutes)"
                    }
                }
            }
        }
    ]
}

// Level 4: Extended Time → Premium Delivery Option
{
    "addon": "Delivery Options", 
    "priority": 4,
    "conditions": [
        {
            "type": "rule",
            "target_addon": "Delivery Time",
            "property": "has_modifications",
            "operator": "equals",
            "value": "true"
        }
    ],
    "actions": [
        {
            "type": "visibility",
            "config": {
                "show_options": ["priority_delivery"],
                "action": "show"
            }
        }
    ]
}
```

## Best Practices for Cascading Rules

### 1. Use Clear Priorities
```javascript
// High Priority (1-3): Foundation rules that other rules depend on
// Medium Priority (4-6): Secondary effects and modifications  
// Low Priority (7-10): Final adjustments and notifications
```

### 2. Avoid Circular Dependencies
```javascript
// ❌ BAD: Circular dependency
// Rule A depends on Rule B, Rule B depends on Rule A

// ✅ GOOD: Linear dependency chain
// Rule A → Rule B → Rule C
```

### 3. Test Complex Chains
```javascript
// Always test cascading rules with different selection combinations
// Check that all 10 evaluation iterations complete without loops
// Verify the final state matches expectations
```

### 4. Use Rule State Checks Strategically
```javascript
// Check rule states for genuine dependencies
// Don't overuse - simple field conditions are more efficient
// Use for complex multi-step workflows
```

## Debugging Cascading Rules

### Console Logging
The system logs warnings when maximum iterations are reached:
```
WC Product Addons: Maximum iterations reached in cascading evaluation. Possible circular dependency.
```

### Priority Visualization
Rules execute in priority order (1, 2, 3... 10). Use the admin interface priority indicators:
- 🔴 Red border: High priority (1-3)
- 🟡 Yellow border: Medium priority (4-6)  
- 🟢 Green border: Low priority (7-10)

### Testing Strategy
1. Start with simple single-level rules
2. Add one dependency level at a time
3. Test all possible selection combinations
4. Verify performance with complex rule sets

This cascading system enables incredibly sophisticated product configuration workflows while maintaining performance and preventing infinite loops.