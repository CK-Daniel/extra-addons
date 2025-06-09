# Per-Option Price Settings in Admin Panel

## Overview

The conditional logic admin panel already supports per-option price settings. This feature allows you to:

1. Set prices for specific options within an addon (not just the entire addon)
2. Adjust prices for specific options based on conditions

## How It Works

### Admin Interface

When creating a price action (Set Add-on Price or Adjust addon price):

1. **Target Level Selector**: Choose between:
   - "Entire addon" - Apply price to all options in the addon
   - "Specific option" - Apply price to a single option

2. **Option Selector**: When "Specific option" is selected, a dropdown appears with all available options from the selected addon

### JavaScript Structure

The `conditional-logic-admin.js` file handles this with:

```javascript
// In getSetPriceActionConfig():
var html = this.buildAddonSelectHtml(true, true);
// Parameters: (includeOptions, includeTargetLevel)
// This adds both the target level selector and option selector
```

### Data Flow

1. **Frontend Collection** (conditional-logic-admin.js):
   ```javascript
   action.config = {
       action_target_level: 'option',  // or 'addon'
       action_addon: 'test_product_67',
       action_option: 'tester123',     // Only when target_level is 'option'
       action_price: '50.00'
   }
   ```

2. **Backend Processing** (class-wc-product-addons-conditional-logic.php):
   ```php
   // In process_rule_action():
   case 'set_price':
       $processed_action['target_addon'] = $config['action_addon'];
       $processed_action['target_option'] = $config['action_option'];
       $processed_action['new_price'] = $config['action_price'];
   ```

3. **Frontend Application** (conditional-logic.js):
   ```javascript
   // In handlePriceChange():
   if (optionValue) {
       // Apply to specific option
   } else {
       // Apply to all options in addon
   }
   ```

## Testing Instructions

### 1. Create a Per-Option Price Rule

1. Go to WooCommerce → Product Add-ons → Rules (or Global Add-ons)
2. Create a new rule:
   - Name: "Option-Specific Price Test"
   - Condition: When [Test] addon has [test] selected
   - Action: Set Add-on Price
   - Target Level: **Specific option** (important!)
   - Target Addon: Example1
   - Target Option: tester123
   - New Price: $25.00

3. Save the rule

### 2. Test on Frontend

1. Visit the product page
2. Select "test" from the Test dropdown
3. Check that only the "tester123" option in Example1 shows the new price ($25)
4. Other options in Example1 should keep their original prices

### 3. Database Structure

The rule is saved with this structure:
```json
{
  "type": "set_price",
  "config": {
    "action_target_level": "option",
    "action_addon": "example1_product_67",
    "action_option": "tester123",
    "action_price": "25"
  }
}
```

## Current Status

✅ **Admin Interface**: Already supports per-option selection
✅ **JavaScript**: Properly collects and sends target level and option data
✅ **Backend Processing**: Correctly processes target_option field
✅ **Frontend Application**: Applies prices to specific options when target_option is provided

## Additional Features

### Adjust Price per Option

The same per-option functionality works for price adjustments:
- Increase/decrease by fixed amount
- Increase/decrease by percentage
- All modifications can target specific options

### Multiple Rules

You can have multiple rules targeting different options:
- Rule 1: When X selected, set Option A to $10
- Rule 2: When Y selected, set Option B to $20
- Rule 3: When Z selected, adjust all options by +10%

## Troubleshooting

### Option Not Appearing in Dropdown

1. Make sure the addon has options defined
2. Select an addon first before the option dropdown populates
3. Check browser console for any JavaScript errors

### Price Not Changing

1. Verify the option value matches exactly (check for -1, -2 suffixes)
2. Enable debug mode to see rule evaluation in console
3. Check that the rule conditions are being met

### Admin Interface Issues

1. Clear browser cache if dropdowns aren't updating
2. Ensure no JavaScript errors in console
3. Check that conditional logic admin script is loaded