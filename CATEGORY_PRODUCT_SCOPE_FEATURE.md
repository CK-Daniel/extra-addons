# Category and Product Scope Feature

## Overview

The conditional logic admin panel now supports creating rules with different scopes:
- **Global**: Rules apply to all products
- **Category**: Rules apply to products in specific categories
- **Product**: Rules apply to specific products only

## Implementation Details

### 1. Frontend (JavaScript)

The Select2 dropdowns are initialized in `conditional-logic-admin.js`:
- Category search uses `wc_product_addons_search_categories` action
- Product search uses `wc_product_addons_search_products` action
- Both support multiple selections
- Minimum 2 characters to start searching

### 2. Backend (PHP)

AJAX handlers in `class-wc-product-addons-admin.php`:
- `ajax_search_categories()` - Returns product categories
- `ajax_search_products()` - Returns published products

### 3. Database Structure

Rules are stored with:
- `rule_type`: 'global', 'category', or 'product'
- `scope_id`: The category/product ID (NULL for global)

For multiple selections, the system stores multiple rules or uses a junction table.

## How to Use

### Creating a Category Rule

1. Select "Category Rule" radio button
2. The category search dropdown appears
3. Start typing category name (min 2 characters)
4. Select one or more categories
5. Configure conditions and actions
6. Save the rule

### Creating a Product Rule

1. Select "Product Rule" radio button
2. The product search dropdown appears
3. Start typing product name (min 2 characters)
4. Select one or more products
5. Configure conditions and actions
6. Save the rule

### How It Works

When a product page loads:
1. System checks for global rules
2. Checks for category rules matching the product's categories
3. Checks for product-specific rules
4. Applies rules based on priority

## Technical Notes

### Select2 Configuration

```javascript
$('.wc-category-search').select2({
    ajax: {
        url: ajax_url,
        action: 'wc_product_addons_search_categories',
        minimumInputLength: 2,
        delay: 250
    }
});
```

### Data Flow

1. User types in Select2 input
2. AJAX request sent to WordPress
3. PHP handler searches database
4. Returns JSON array: `[{id: 1, text: "Category Name"}]`
5. Select2 displays results
6. User selections stored in hidden input

### Security

- Nonce verification: `wc-product-addons-conditional-logic`
- Capability check: `manage_woocommerce`
- Input sanitization: `sanitize_text_field()`

## Troubleshooting

### Select2 Not Working

1. Check browser console for errors
2. Verify Select2 library is loaded
3. Check AJAX URL is correct
4. Verify nonce is being passed

### No Search Results

1. Check minimum character requirement (2 chars)
2. Verify categories/products exist
3. Check PHP error logs
4. Test AJAX endpoint directly

### Saving Issues

1. Verify scope_targets array is populated
2. Check database table structure
3. Review PHP error logs
4. Check AJAX response in Network tab

## Example Database Entries

### Global Rule
```sql
rule_type: 'global'
scope_id: NULL
```

### Category Rule
```sql
rule_type: 'category'
scope_id: 15  -- Category ID
```

### Product Rule
```sql
rule_type: 'product'
scope_id: 123  -- Product ID
```

## Future Enhancements

1. Support for product tags
2. Support for product attributes
3. Bulk rule assignment
4. Rule templates by category
5. Import/export rules by scope