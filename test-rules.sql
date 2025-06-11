-- Sample conditional logic rules for testing
-- These rules will:
-- 1. Hide addon when another addon is selected
-- 2. Change price dynamically

-- Rule 1: Hide example1 addon when test addon has "test" selected
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    scope_id,
    conditions,
    actions,
    priority,
    enabled
) VALUES (
    'Hide example1 when test selected',
    'product',
    140,
    '[{"type":"addon_selected","config":{"condition_addon":"test","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"hide_addon","config":{"action_addon":"example1"}}]',
    10,
    1
);

-- Rule 2: Change price of example1 options when test is selected
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    scope_id,
    conditions,
    actions,
    priority,
    enabled
) VALUES (
    'Change example1 price when test selected',
    'product',
    140,
    '[{"type":"addon_selected","config":{"condition_addon":"test","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"set_price","config":{"action_addon":"example1","action_option":"tester125","action_price":"999"}}]',
    20,
    1
);

-- Global rule that applies to all products
INSERT INTO wp_wc_product_addon_rules (
    rule_name,
    rule_type,
    scope_id,
    conditions,
    actions,
    priority,
    enabled
) VALUES (
    'Hide specific option globally',
    'global',
    NULL,
    '[{"type":"addon_selected","config":{"condition_addon":"test","condition_option":"test","condition_state":"selected"}}]',
    '[{"type":"hide_option","config":{"action_addon":"example1","action_option":"tester123"}}]',
    5,
    1
);