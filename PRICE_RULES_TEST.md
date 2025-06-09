# Testing Price Rules in Frontend and Backend

## How Price Rules Work

When conditional logic modifies prices:

1. **Frontend**: JavaScript updates the price display and data attributes
2. **Backend**: PHP hooks filter the price during cart calculations

## Testing Steps

### 1. Create a Price Rule

```sql
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    scope_id,
    conditions,
    actions,
    priority,
    enabled
) VALUES (
    'Change Price When Test Selected',
    'product',
    67,
    '[{"type":"addon_selected","config":{"condition_addon":"test_product_67","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"set_price","config":{"action_addon":"example1_product_67","action_option":"tester123","action_price":"50"}}]',
    10,
    1
);
```

### 2. Frontend Testing

1. Select "test" option
2. Check console for "💰 Setting price for: example1_product_67 -> tester123 = 50"
3. The tester123 option should show new price

### 3. Backend Testing

The system includes these hooks for cart price calculation:

- `woocommerce_product_addons_option_price_raw`: Modifies the raw price value
- `woocommerce_product_addons_price`: Modifies the display price
- `woocommerce_product_addons_adjust_price`: Ensures price adjustments are applied

### 4. Cart Verification

1. Add product to cart with modified price
2. View cart - price should reflect the conditional logic change
3. Proceed to checkout - price should remain consistent

## Current Implementation

### Frontend (JavaScript)
- Updates data attributes: `data-price`, `data-raw-price`
- Updates visual price display
- Triggers `woocommerce-product-addons-update` event

### Backend (PHP)
- `modify_addon_price_raw()`: Filters raw price in cart calculations
- `should_adjust_cart_price()`: Always returns true for addon products
- `get_cart_evaluation_context()`: Gets price modifications from session/AJAX

## Known Issues and Solutions

### Issue: Price not updating in cart
**Solution**: The cart needs to store conditional logic state. This can be done by:
1. Storing price modifications in cart item data
2. Re-evaluating conditions during cart load

### Issue: Price reverts on page refresh
**Solution**: Store conditional logic results in session or cart metadata

## Future Enhancements

1. Store conditional logic evaluation results in cart item data
2. Re-evaluate conditions when cart is loaded from session
3. Add admin interface to see which rules affected cart items
4. Add order metadata showing which conditional logic rules were applied