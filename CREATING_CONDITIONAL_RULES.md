# Creating Conditional Logic Rules

The conditional logic system is working correctly, but no rules have been created yet. The `{success: false}` response indicates the system is checking for rules but finding none in the database.

## Why No Rules Are Working

The console logs show:
- ✅ Conditional logic is initializing correctly
- ✅ Addons are being detected and cataloged
- ✅ AJAX requests are being sent successfully
- ❌ Response: `{success: false}` - This means no rules exist in the database

## How to Create Rules

### Option 1: Using the Admin Interface (Recommended)

1. Go to WordPress Admin → WooCommerce → Product Add-ons
2. Look for "Conditional Logic" tab or menu item
3. Click "Create New Rule"
4. Fill in:
   - **Rule Name**: "Hide tester123 when test selected"
   - **Rule Type**: Global
   - **Condition**: 
     - Type: "Add-on Option Selected"
     - Addon: "Test"
     - Option: "test"
     - State: "is selected"
   - **Action**:
     - Type: "Hide Add-on Option"
     - Target Addon: "example1"
     - Target Option: "tester123"
5. Save the rule

### Option 2: Direct Database Insert

If the admin interface is not available, you can insert rules directly into the database:

```sql
-- Insert into wp_wc_product_addon_rules table
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    conditions,
    actions,
    priority,
    enabled
) VALUES (
    'Hide tester123 when test selected',
    'global',
    '[{"type":"addon_selected","config":{"condition_addon":"test","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"hide_option","config":{"action_addon":"example1","action_option":"tester123"}}]',
    10,
    1
);

-- Price change rule
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    conditions,
    actions,
    priority,
    enabled
) VALUES (
    'Set tester125 price when test selected',
    'global',
    '[{"type":"addon_selected","config":{"condition_addon":"test","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"set_price","config":{"action_target_level":"option","action_addon":"example1","action_option":"tester125","action_price":"999"}}]',
    20,
    1
);
```

### Option 3: Using WP-CLI

If you have WP-CLI access:

```bash
wp eval '
global $wpdb;
$table = $wpdb->prefix . "wc_product_addon_rules";
$wpdb->insert($table, array(
    "rule_name" => "Hide tester123 when test selected",
    "rule_type" => "global",
    "conditions" => json_encode([["type" => "addon_selected", "config" => ["condition_addon" => "test", "condition_option" => "test", "condition_state" => "selected"]]]),
    "actions" => json_encode([["type" => "hide_option", "config" => ["action_addon" => "example1", "action_option" => "tester123"]]]),
    "priority" => 10,
    "enabled" => 1
));
'
```

## Verifying Rules Are Working

After creating rules:

1. Refresh the product page
2. Open browser console
3. You should see:
   - `✅ Rule evaluation response: {success: true, data: {...}}`
   - `📋 All loaded rules: [...]`
   - `🎯 Applying rule results:`

4. Select "test" in the Test dropdown
5. The "tester123" option should hide
6. The "tester125" price should change to 999

## Rule Types Explained

### Conditions
- `addon_selected`: When a specific addon option is selected
- `field_value`: When a text field has a specific value
- `product_price`: Based on product price
- `cart_total`: Based on cart total
- `user_role`: Based on user role
- `date_range`: Within specific dates

### Actions
- `hide_addon`: Hide entire addon
- `show_addon`: Show entire addon
- `hide_option`: Hide specific option
- `show_option`: Show specific option
- `set_price`: Set option price to specific value
- `adjust_price`: Adjust price by percentage or amount
- `make_required`: Make addon required
- `make_optional`: Make addon optional

## Debugging

Enable debug logging:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WC_PAO_DEBUG', true );
```

Check logs at: `wp-content/debug.log`

## Common Issues

1. **No admin interface**: The admin interface code may need to be implemented separately
2. **Table not created**: Run the plugin activation again or check table creation
3. **Rules not loading**: Check if rules are enabled and have correct JSON format
4. **Option names not matching**: Ensure addon and option names match exactly (case-sensitive)