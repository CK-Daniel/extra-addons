# Testing Per-Option Price Settings

## Verification Steps

### 1. Admin Interface Test

1. **Navigate to**: WooCommerce → Product Add-ons → Conditional Logic (or the page with `?page=addon-conditional-logic`)

2. **Create a new rule**:
   - Rule Name: "Test Per-Option Pricing"
   - Scope: Global (or Product-specific)
   
3. **Add a condition**:
   - Type: "Add-on Option Selected"
   - Addon: [Select an addon with multiple options]
   - Option: [Select a specific option]
   - State: "is selected"

4. **Add an action** and verify:
   - Type: "Set Add-on Price" or "Adjust Add-on Price"
   - **IMPORTANT**: You should see THREE dropdowns:
     1. **Target Level**: "Entire Add-on" or "Specific Option"
     2. **Target Addon**: List of available addons
     3. **Target Option**: List of options (appears when "Specific Option" is selected)
   - Price: Enter a test price

### 2. JavaScript Console Test

Open browser console and run:
```javascript
// Check if the localization is loaded
console.log('Entire addon text:', wc_product_addons_params.i18n_entire_addon);
console.log('Specific option text:', wc_product_addons_params.i18n_specific_option);

// Check the buildAddonSelectHtml function
console.log('Function exists:', typeof WC_Product_Addons_Conditional_Logic_Admin.buildAddonSelectHtml);
```

### 3. HTML Structure Test

When you add a price action, inspect the HTML. You should see:
```html
<div class="action-config">
    <select class="target-level" name="action_target_level">
        <option value="addon">Entire Add-on</option>
        <option value="option">Specific Option</option>
    </select>
    <select class="addon-select" name="action_addon">
        <!-- Addon options -->
    </select>
    <select class="option-select" name="action_option" style="display: none;">
        <!-- Option choices - visible when "Specific Option" selected -->
    </select>
    <input type="number" class="price-input" name="action_price">
</div>
```

### 4. Data Submission Test

1. Fill out the rule completely
2. Save the rule
3. Check the database `wp_wc_product_addon_rules` table
4. The `actions` column should contain JSON like:
```json
[{
    "type": "set_price",
    "config": {
        "action_target_level": "option",
        "action_addon": "example_addon_id",
        "action_option": "specific_option_value",
        "action_price": "25.00"
    }
}]
```

## Current Implementation Status

✅ **JavaScript Functions**: 
- `getSetPriceActionConfig()` includes target level selector
- `getAdjustPriceActionConfig()` includes target level selector
- `buildAddonSelectHtml(true, true)` creates the UI with both parameters

✅ **Localization**: 
- `i18n_entire_addon` and `i18n_specific_option` are properly defined
- All necessary strings are passed to JavaScript

✅ **Event Handlers**:
- `updateTargetLevelDisplay()` shows/hides option selector
- `updateAddonOptions()` populates options when addon is selected

✅ **Backend Processing**:
- `process_rule_action()` extracts `target_option` from config
- Frontend application checks for `optionValue` to apply per-option pricing

## If Not Working

If you don't see the target level selector:

1. **Clear browser cache** - JavaScript might be cached
2. **Check console errors** - Look for any JavaScript errors
3. **Verify script loading** - Check Network tab for `conditional-logic-admin.js`
4. **Check jQuery conflicts** - Ensure no other plugins are interfering

## Manual Database Test

You can also manually insert a test rule to verify the frontend works:
```sql
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    conditions,
    actions,
    enabled
) VALUES (
    'Manual Per-Option Test',
    'global',
    '[{"type":"addon_selected","config":{"condition_addon":"test_addon","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"set_price","config":{"action_target_level":"option","action_addon":"target_addon","action_option":"specific_option","action_price":"99.99"}}]',
    1
);
```

Then check if the frontend correctly applies the price to only the specific option.