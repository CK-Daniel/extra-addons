-- Rule for product ID 140 that matches the actual addon identifiers
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    scope_id,
    conditions,
    actions,
    priority,
    enabled
) VALUES (
    'Hide example1 when test selected (Product 140)',
    'product',
    140,
    '[{"type":"addon_selected","config":{"condition_addon":"test_product_140","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"hide_addon","config":{"action_addon":"example1_product_140"}}]',
    10,
    1
);