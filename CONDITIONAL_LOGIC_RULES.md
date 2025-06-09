# WooCommerce Product Add-ons Conditional Logic Rules

## Overview

The conditional logic system now uses a precise matching system with no room for errors. Each addon and option has multiple identifiers to ensure rules work correctly across different contexts.

## Addon Identification

Each addon has the following identifiers:

1. **Addon Identifier**: A unique identifier combining name, scope, and ID (e.g., `test_product_67`, `example1_global_173`)
2. **Field Name**: The form field name (e.g., `67-test-0`)
3. **Addon Name**: The display name (e.g., `Test`, `Example1`)
4. **Scope**: Whether it's `product`, `global`, or `category` scoped

## Option Identification

Each option within an addon has:

1. **Option Value**: The actual form value (e.g., `test-1`, `tester123-1`)
2. **Option Key**: The sanitized label (e.g., `test`, `tester123`)
3. **Option Label**: The display label (e.g., `Test`, `Tester123`)
4. **Option Index**: The numeric index (e.g., `1`, `2`)

## Rule Structure

### Conditions

```php
array(
    'type' => 'addon_selected',
    'config' => array(
        'condition_addon' => 'test_global_163',    // Use addon identifier
        'condition_option' => 'test',              // Use option key (not value!)
        'condition_state' => 'selected'            // or 'not_selected'
    )
)
```

### Actions

```php
// Hide/Show entire addon
array(
    'type' => 'hide_addon',  // or 'show_addon'
    'config' => array(
        'action_addon' => 'example1_global_173'    // Use addon identifier
    )
)

// Hide/Show specific option
array(
    'type' => 'hide_option',  // or 'show_option'
    'config' => array(
        'action_addon' => 'example1_global_173',   // Use addon identifier
        'action_option' => 'tester123'             // Use option key (not value!)
    )
)
```

## Matching System

The system uses a hierarchical matching approach:

1. **Exact Match**: First tries to match exact identifiers
2. **Key Match**: Matches by option key (sanitized label)
3. **Label Match**: Matches by original label
4. **Pattern Match**: Matches value patterns (e.g., `test` matches `test-1`)

## HTML Data Attributes

### Addon Container
```html
<div class="wc-pao-addon-container" 
     data-addon-identifier="test_product_67"
     data-addon-field-name="67-test-0"
     data-addon-name="Test"
     data-addon-type="multiple_choice"
     data-addon-scope="product">
```

### Select Options
```html
<option value="test-1"
        data-option-key="test"
        data-option-value="test-1"
        data-option-label="Test"
        data-option-index="1"
        data-option-id="67-test-0_option_1"
        data-addon-field-name="67-test-0">
```

### Radio/Checkbox Options
```html
<p class="addon-option"
   data-option-value="test"
   data-option-key="test"
   data-option-label="Test"
   data-option-index="1">
   <input type="radio"
          value="test"
          data-option-key="test"
          data-option-index="1"
          data-addon-field-name="67-test-0">
</p>
```

## Best Practices

1. **Always use option keys in rules**, not the actual values
   - ✅ Use: `'condition_option' => 'test'`
   - ❌ Don't use: `'condition_option' => 'test-1'`

2. **Use consistent addon identifiers**
   - Global addons: `name_global_ID` (e.g., `test_global_163`)
   - Product addons: `name_product_ID` (e.g., `test_product_67`)

3. **Test rules with different scope combinations**
   - Global rule → Product addon
   - Product rule → Global addon
   - Category rule → Product addon

## Debugging

Enable debug mode to see detailed matching information:

```php
define( 'WC_PAO_DEBUG', true );
```

This will show:
- Which strategies were used for matching
- What options were found
- Why rules did or didn't apply

## Template Updates

All addon templates now include comprehensive data attributes for precise matching:

### Checkbox Template
- Added `data-option-key` on both container and input
- Added `data-option-index` for numeric reference
- Added `data-option-id` for unique identification
- Added `data-addon-field-name` for field association

### Image Template
- Enhanced select options with full data attributes
- Maintains consistency with other templates

### Existing Templates
- Select: Full data attributes for all options
- Radio buttons: Full data attributes on containers and inputs
- Checkbox: Full data attributes on containers and inputs

## Example Rules

### Hide option when another is selected
```php
$rule = array(
    'rule_name' => 'Hide Tester123 when Test selected',
    'conditions' => array(
        array(
            'type' => 'addon_selected',
            'config' => array(
                'condition_addon' => 'test_global_163',
                'condition_option' => 'test',
                'condition_state' => 'selected'
            )
        )
    ),
    'actions' => array(
        array(
            'type' => 'hide_option',
            'config' => array(
                'action_addon' => 'example1_global_173',
                'action_option' => 'tester123'
            )
        )
    ),
    'rule_type' => 'global',
    'priority' => 10,
    'enabled' => 1
);
```

### Show addon when option selected
```php
$rule = array(
    'rule_name' => 'Show Example addon when Test selected',
    'conditions' => array(
        array(
            'type' => 'addon_selected',
            'config' => array(
                'condition_addon' => 'test_product_67',
                'condition_option' => 'test',
                'condition_state' => 'selected'
            )
        )
    ),
    'actions' => array(
        array(
            'type' => 'show_addon',
            'config' => array(
                'action_addon' => 'example1_product_67'
            )
        )
    ),
    'rule_type' => 'product',
    'priority' => 10,
    'enabled' => 1
);
```

## Using the Rule Builder

```php
// Include the rule builder
require_once 'includes/conditional-logic/class-wc-product-addons-rule-builder.php';

// Build a condition
$condition = WC_Product_Addons_Rule_Builder::addon_selected_condition(
    'test_global_163',  // addon identifier
    'test',            // option key
    'selected'         // state
);

// Build an action
$action = WC_Product_Addons_Rule_Builder::option_visibility_action(
    'hide_option',         // type
    'example1_global_173', // addon identifier
    'tester123'           // option key
);

// Build complete rule
$rule = WC_Product_Addons_Rule_Builder::build_rule(
    'My Rule Name',
    array( $condition ),
    array( $action ),
    array(
        'rule_type' => 'global',
        'priority' => 10
    )
);

// Validate rule
$validation = WC_Product_Addons_Rule_Builder::validate_rule( $rule );
if ( ! $validation['valid'] ) {
    foreach ( $validation['errors'] as $error ) {
        echo 'Error: ' . $error . "\n";
    }
}
```