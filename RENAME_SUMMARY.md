# WooCommerce Product Addons Extra Digital - Rename Summary

This plugin has been renamed from "WooCommerce Product Add-ons" to "WooCommerce Product Add-ons Extra Digital".

## What Has Been Changed

1. **Plugin File Name**: 
   - `woocommerce-product-addons.php` → `woocommerce-product-addons-extra-digital.php`

2. **Plugin Header**:
   - Plugin Name: `WooCommerce Product Add-ons` → `WooCommerce Product Add-ons Extra Digital`
   - Text Domain: `woocommerce-product-addons` → `woocommerce-product-addons-extra-digital`

3. **Text Domain**: All translation strings now use `woocommerce-product-addons-extra-digital`

4. **Script/Style Handles**:
   - `woocommerce-addons` → `woocommerce-addons-extra-digital`
   - `woocommerce-addons-css` → `woocommerce-addons-extra-digital-css`
   - `woocommerce_product_addons_css` → `woocommerce-product-addons-extra-digital-admin-css`
   - `woocommerce_product_addons` → `woocommerce-product-addons-extra-digital-admin`
   - `woocommerce-addons-quickview-compat` → `woocommerce-addons-extra-digital-quickview-compat`

5. **Language Files**:
   - All `.po`, `.mo`, and `.pot` files renamed to use the new text domain

6. **Backwards Compatibility**:
   - Updated `product-addons.php` to redirect to the new plugin file name

## What Has NOT Been Changed (For Data Compatibility)

The following database fields and keys remain unchanged to ensure compatibility with existing data:

### Options Table:
- `wc_pao_version`
- `wc_pao_activation_notice`
- `wc_pao_pre_wc_30_notice`
- `woocommerce_product_addons_%` (any options with this prefix)

### Post Meta Keys:
- `_product_addons`
- `_product_addons_exclude_global`
- `_product_addons_old`
- `_product_addons_converted`
- `_priority`
- `_all_products`

### Post Types:
- `global_product_addon`

### Class Names and Function Names:
- All PHP class names remain the same (e.g., `WC_Product_Addons`)
- All function names remain the same
- All hook names remain the same

### JavaScript Variables:
- `woocommerce_addons_params` (localized script object)
- `wc_pao_params` (admin localized script object)

## Installation Instructions

1. Deactivate the original "WooCommerce Product Add-ons" plugin
2. Upload and activate "WooCommerce Product Add-ons Extra Digital"
3. All existing addon data will continue to work without any migration needed

## Important Notes

- This renamed plugin shares the same database structure as the original plugin
- Only one of these plugins should be active at a time
- No data migration is required when switching between the plugins