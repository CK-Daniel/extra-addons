# Conditional Logic - Price Modification Technical Specification

## Price Modification Architecture

### Core Price Actions

```php
// Price modification types enumeration
class PriceActionType {
    const ADD = 'add';                      // Add fixed amount
    const SUBTRACT = 'subtract';            // Subtract fixed amount
    const MULTIPLY = 'multiply';            // Multiply by factor
    const DIVIDE = 'divide';                // Divide by factor
    const SET = 'set';                      // Set to specific value
    const PERCENTAGE_ADD = 'percentage_add'; // Add percentage
    const PERCENTAGE_SUBTRACT = 'percentage_subtract'; // Subtract percentage
    const FORMULA = 'formula';              // Custom formula
    const CASCADE = 'cascade';              // Apply to multiple addons
    const SYNC = 'sync';                    // Sync with another addon
    const SCALE = 'scale';                  // Scale based on quantity/value
    const TIERED = 'tiered';                // Tiered pricing
    const DYNAMIC = 'dynamic';              // External/API based
}
```

### Price Modification Structure

```php
// Complete price modification configuration
array(
    'conditional_logic' => array(
        'enabled' => true,
        'rules' => array(
            array(
                'id' => 'rule_123',
                'conditions' => array(
                    array(
                        'field' => 'color_selection',
                        'operator' => 'equals',
                        'value' => 'premium_gold'
                    )
                ),
                'actions' => array(
                    array(
                        'type' => 'price_modification',
                        'price_actions' => array(
                            array(
                                'action' => 'cascade',
                                'target' => 'all_addons',
                                'modification' => array(
                                    'type' => 'percentage_add',
                                    'value' => 25,
                                    'exclude' => ['basic_options'],
                                    'compound' => true // Apply on already modified prices
                                )
                            ),
                            array(
                                'action' => 'set',
                                'target' => 'shipping_addon',
                                'value' => 0,
                                'message' => 'Free shipping with premium selection!'
                            )
                        )
                    )
                )
            )
        )
    )
)
```

## Implementation Examples

### 1. Basic Price Modifications

```javascript
// JavaScript implementation
class PriceModifier {
    applyModification(originalPrice, modification) {
        switch (modification.type) {
            case 'add':
                return originalPrice + modification.value;
            
            case 'subtract':
                return Math.max(0, originalPrice - modification.value);
            
            case 'multiply':
                return originalPrice * modification.value;
            
            case 'divide':
                return modification.value !== 0 ? originalPrice / modification.value : originalPrice;
            
            case 'set':
                return modification.value;
            
            case 'percentage_add':
                return originalPrice * (1 + modification.value / 100);
            
            case 'percentage_subtract':
                return originalPrice * (1 - modification.value / 100);
            
            case 'formula':
                return this.evaluateFormula(modification.formula, originalPrice);
        }
    }
}
```

### 2. Cascade Price Changes

```javascript
// When user selects Option X, all other addon prices change
{
    "trigger": {
        "addon": "material_type",
        "option": "premium_leather"
    },
    "cascade_effects": [
        {
            "scope": "all_addons",
            "modification": "+30%",
            "exclude": ["material_type"], // Don't modify the triggering addon
            "message": "Premium material selected - all options upgraded"
        },
        {
            "scope": "specific_addons",
            "targets": ["color_options", "finish_options"],
            "modification": "+50%",
            "reason": "Premium colors and finishes for leather"
        },
        {
            "scope": "category",
            "category": "accessories",
            "modification": "set:0",
            "message": "Free accessories with premium leather!"
        }
    ]
}
```

### 3. Complex Price Relationships

```javascript
// Price dependencies between addons
{
    "price_relationships": [
        {
            "name": "complementary_pricing",
            "rules": [
                {
                    "when": "addon_a.selected && addon_b.selected",
                    "apply": {
                        "to": "addon_b",
                        "modification": "addon_a.price * 0.5",
                        "message": "50% off when purchased together"
                    }
                }
            ]
        },
        {
            "name": "inverse_relationship",
            "total_pool": 100,
            "distribute_between": ["feature_a", "feature_b", "feature_c"],
            "rule": "as one increases, others must decrease to maintain total"
        },
        {
            "name": "stepped_pricing",
            "base_addon": "quantity",
            "affected_addons": ["per_unit_features"],
            "steps": [
                {"range": "1-10", "multiplier": 1},
                {"range": "11-50", "multiplier": 0.8},
                {"range": "51-100", "multiplier": 0.6},
                {"range": "101+", "multiplier": 0.4}
            ]
        }
    ]
}
```

### 4. Dynamic Formula Engine

```javascript
// Advanced formula support
class FormulaEngine {
    constructor() {
        this.variables = {};
        this.functions = {
            'min': Math.min,
            'max': Math.max,
            'round': Math.round,
            'floor': Math.floor,
            'ceil': Math.ceil,
            'if': (condition, trueVal, falseVal) => condition ? trueVal : falseVal,
            'between': (val, min, max) => val >= min && val <= max
        };
    }
    
    evaluate(formula, context) {
        // Example formulas:
        // "base_price * quantity * (1 - discount_rate)"
        // "if(quantity > 100, base_price * 0.7, base_price)"
        // "max(50, base_price * complexity_factor)"
        // "base_price + (addon_count * 10) - loyalty_discount"
        
        // Parse and evaluate formula with context variables
        return this.parseAndEvaluate(formula, context);
    }
}

// Usage example
{
    "price_modification": {
        "type": "formula",
        "formula": "base_price * (1 + complexity_multiplier) * quantity_discount_factor",
        "variables": {
            "base_price": "product.price",
            "complexity_multiplier": "selected_options.count * 0.1",
            "quantity_discount_factor": "max(0.5, 1 - (quantity * 0.005))"
        }
    }
}
```

### 5. Time-Based Price Modifications

```javascript
{
    "time_based_pricing": {
        "schedules": [
            {
                "name": "happy_hour",
                "times": ["16:00-18:00"],
                "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
                "modification": "-20%",
                "message": "Happy Hour Discount!"
            },
            {
                "name": "weekend_premium",
                "days": ["saturday", "sunday"],
                "modification": "+15%",
                "message": "Weekend pricing applies"
            },
            {
                "name": "seasonal",
                "date_ranges": [
                    {"start": "2024-12-20", "end": "2024-12-31", "modification": "+30%", "name": "Holiday Premium"},
                    {"start": "2024-07-01", "end": "2024-08-31", "modification": "+20%", "name": "Summer Peak"}
                ]
            }
        ]
    }
}
```

### 6. Smart Bundling with Price Optimization

```javascript
{
    "bundle_pricing": {
        "detection": "automatic", // Detect common combinations
        "bundles": [
            {
                "items": ["addon_a", "addon_b", "addon_c"],
                "bundle_price": "sum * 0.8", // 20% off
                "individual_prices_visible": true,
                "savings_display": "You save $X"
            }
        ],
        "dynamic_bundles": {
            "create_on_fly": true,
            "min_items": 3,
            "discount_formula": "5% * items_count",
            "max_discount": 25
        }
    }
}
```

### 7. User-Specific Price Modifications

```javascript
{
    "user_based_pricing": {
        "vip_customers": {
            "all_addons": "-15%",
            "stack_with_other_discounts": false
        },
        "purchase_history": {
            "tiers": [
                {"total_spent": 1000, "discount": "5%"},
                {"total_spent": 5000, "discount": "10%"},
                {"total_spent": 10000, "discount": "15%"}
            ]
        },
        "loyalty_points": {
            "points_to_discount_ratio": 100, // 100 points = $1 off
            "applicable_to": "addon_prices"
        }
    }
}
```

### 8. Real-World Implementation Example

```php
// PHP Backend Implementation
class WC_Product_Addons_Price_Engine {
    
    public function calculate_modified_price($addon, $base_price, $conditions, $context) {
        $modified_price = $base_price;
        $modifications_applied = array();
        
        foreach ($conditions as $condition) {
            if ($this->evaluate_condition($condition, $context)) {
                foreach ($condition['price_actions'] as $action) {
                    $result = $this->apply_price_action($modified_price, $action, $context);
                    
                    $modifications_applied[] = array(
                        'action' => $action,
                        'before' => $modified_price,
                        'after' => $result['price'],
                        'message' => $result['message']
                    );
                    
                    $modified_price = $result['price'];
                }
            }
        }
        
        return array(
            'original_price' => $base_price,
            'modified_price' => $modified_price,
            'modifications' => $modifications_applied,
            'total_change' => $modified_price - $base_price,
            'percentage_change' => (($modified_price - $base_price) / $base_price) * 100
        );
    }
    
    private function apply_price_action($current_price, $action, $context) {
        switch ($action['type']) {
            case 'cascade':
                return $this->apply_cascade_pricing($current_price, $action, $context);
                
            case 'formula':
                return $this->evaluate_price_formula($current_price, $action['formula'], $context);
                
            case 'sync':
                return $this->sync_with_addon_price($current_price, $action['sync_with'], $context);
                
            // ... other cases
        }
    }
}
```

### 9. Frontend Price Update System

```javascript
// Real-time price updates in the browser
class AddonPriceUpdater {
    constructor() {
        this.originalPrices = new Map();
        this.modifiedPrices = new Map();
        this.activeModifications = new Map();
    }
    
    updatePrices(triggerAddon, selectedValue) {
        const cascadeRules = this.getCascadeRules(triggerAddon, selectedValue);
        
        cascadeRules.forEach(rule => {
            const targetAddons = this.getTargetAddons(rule);
            
            targetAddons.forEach(addon => {
                const originalPrice = this.originalPrices.get(addon.id);
                const newPrice = this.calculateNewPrice(originalPrice, rule.modification);
                
                this.modifiedPrices.set(addon.id, newPrice);
                this.updateAddonDisplay(addon, newPrice, rule.message);
                
                // Trigger event for other systems
                $(document).trigger('addon-price-modified', {
                    addon: addon.id,
                    originalPrice: originalPrice,
                    newPrice: newPrice,
                    modification: rule
                });
            });
        });
        
        this.updateTotalPrice();
    }
    
    calculateNewPrice(originalPrice, modification) {
        // Handle all modification types
        if (typeof modification === 'string') {
            if (modification.includes('%')) {
                const percentage = parseFloat(modification) / 100;
                return modification.startsWith('+') 
                    ? originalPrice * (1 + percentage)
                    : originalPrice * (1 - Math.abs(percentage));
            }
            // Handle other string formats
        }
        
        return originalPrice;
    }
}
```

### 10. Price Modification Audit Trail

```javascript
{
    "audit_configuration": {
        "track_all_modifications": true,
        "audit_data": {
            "timestamp": "2024-01-15T10:30:00Z",
            "user": "customer_123",
            "modifications": [
                {
                    "addon": "color_selection",
                    "trigger": "material_type = premium",
                    "original_price": 10.00,
                    "modification_applied": "+25%",
                    "new_price": 12.50,
                    "rule_id": "premium_cascade_rule"
                }
            ],
            "total_impact": {
                "original_total": 100.00,
                "modified_total": 125.00,
                "total_increase": 25.00
            }
        }
    }
}
```

## Testing Price Modifications

```javascript
// Unit tests for price modifications
describe('Price Modification Engine', () => {
    it('should apply cascade pricing correctly', () => {
        const addons = [
            { id: 'addon1', price: 10 },
            { id: 'addon2', price: 20 },
            { id: 'addon3', price: 30 }
        ];
        
        const cascadeRule = {
            type: 'cascade',
            target: 'all_addons',
            modification: '+25%',
            exclude: ['addon1']
        };
        
        const result = priceEngine.applyCascade(addons, cascadeRule);
        
        expect(result).toEqual([
            { id: 'addon1', price: 10 }, // Excluded
            { id: 'addon2', price: 25 }, // 20 * 1.25
            { id: 'addon3', price: 37.5 } // 30 * 1.25
        ]);
    });
});
```

This implementation provides complete flexibility for any price modification scenario while maintaining clean, testable code architecture.