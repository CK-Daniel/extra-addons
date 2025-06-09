# WooCommerce Product Add-ons Precise Matching System

## Overview

This document describes the precise matching system implemented to ensure conditional logic rules work correctly with no room for mistakes, even with many complex rules.

## Key Components

### 1. Comprehensive Data Attributes

Every addon container and option now includes multiple data attributes for precise identification:

#### Addon Container Attributes
- `data-addon-identifier`: Unique identifier (e.g., `test_product_67`)
- `data-addon-field-name`: Form field name (e.g., `67-test-0`)
- `data-addon-name`: Display name (e.g., `Test`)
- `data-addon-type`: Addon type (e.g., `multiple_choice`)
- `data-addon-scope`: Scope (`product`, `global`, or `category`)
- `data-addon-global-id`: Global addon ID if applicable

#### Option Attributes
- `data-option-key`: Sanitized label (e.g., `test`, `tester123`)
- `data-option-value`: Actual form value (e.g., `test-1`, `tester123-1`)
- `data-option-label`: Display label (e.g., `Test`, `Tester123`)
- `data-option-index`: Numeric index (e.g., `1`, `2`)
- `data-option-id`: Unique option ID (e.g., `67-test-0_option_1`)
- `data-addon-field-name`: Parent addon field name

### 2. JavaScript Option Matcher

The `OptionMatcher` object provides precise option matching using multiple strategies:

```javascript
var OptionMatcher = {
    findOptions: function(addon, ruleValue) {
        // Strategy 1: Exact value match
        // Strategy 2: Match by data-option-key
        // Strategy 3: Match by data-label
        // Strategy 4: Match value with -N pattern
    }
};
```

### 3. PHP Addon Identifier System

The `WC_Product_Addons_Addon_Identifier` class provides:
- Consistent identifier generation
- Flexible name matching across scopes
- Pattern-based matching for cross-scope rules

### 4. Rule Builder

The `WC_Product_Addons_Rule_Builder` class provides:
- Consistent rule structure
- Validation methods
- Helper functions for creating conditions and actions

## How It Works

### 1. Rule Definition

Rules use option keys (not values) for matching:

```php
'condition_option' => 'test',      // ✅ Correct: Use key
'condition_option' => 'test-1',    // ❌ Wrong: Don't use value
```

### 2. Matching Process

When evaluating rules:

1. **Backend (PHP)**:
   - Rules are evaluated against current selections
   - Flexible matching allows global rules to target product addons
   - Multiple matching strategies ensure rules work across scopes

2. **Frontend (JavaScript)**:
   - OptionMatcher finds options using hierarchical strategies
   - Data attributes provide multiple ways to identify options
   - Precise matching prevents false positives/negatives

### 3. Cross-Scope Matching

The system handles complex scenarios:
- Global rule → Product addon
- Product rule → Global addon
- Different naming conventions
- Dynamic ID generation

## Benefits

1. **No Ambiguity**: Every addon and option has multiple identifiers
2. **Flexible Matching**: Rules work across different contexts
3. **Debugging Support**: Comprehensive logging shows exactly what's being matched
4. **Future-Proof**: Easy to extend with new matching strategies
5. **Performance**: Efficient matching using data attributes

## Example: Hide Option Rule

```php
// Rule definition
$rule = array(
    'conditions' => array(
        array(
            'type' => 'addon_selected',
            'config' => array(
                'condition_addon' => 'test_global_163',
                'condition_option' => 'test',  // Use key!
                'condition_state' => 'selected'
            )
        )
    ),
    'actions' => array(
        array(
            'type' => 'hide_option',
            'config' => array(
                'action_addon' => 'example1_global_173',
                'action_option' => 'tester123'  // Use key!
            )
        )
    )
);
```

When this rule executes:
1. Backend checks if `test` is selected in `test_global_163`
2. If true, sends action to hide `tester123` in `example1_global_173`
3. Frontend uses OptionMatcher to find all elements matching `tester123`
4. Hides the found options using precise data attribute matching

## Testing

To test the precise matching system:

1. Enable debug mode:
   ```php
   define( 'WC_PAO_DEBUG', true );
   ```

2. Create rules with different scope combinations
3. Check console logs for matching details
4. Verify options are correctly hidden/shown

## Maintenance

When adding new addon types:
1. Include all standard data attributes in the template
2. Test with existing rules
3. Update documentation