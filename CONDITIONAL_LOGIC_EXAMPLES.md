# Conditional Logic Examples

## Example 1: T-Shirt Customization
```javascript
// Scenario: Show embroidery options only when custom design is selected
{
    addon: "Design Type",
    options: ["Standard Design", "Custom Design"],
    conditional_logic: {
        addon: "Embroidery Options",
        show_when: "Design Type = Custom Design"
    }
}

// Advanced: Different pricing for bulk orders
{
    addon: "Quantity",
    conditional_logic: {
        rules: [
            { when: "Quantity > 10", modify_price: "-10%" },
            { when: "Quantity > 50", modify_price: "-20%" },
            { when: "Quantity > 100", modify_price: "-30%" }
        ]
    }
}
```

## Example 2: Pizza Ordering
```javascript
// Hide meat toppings for vegetarian option
{
    addon: "Pizza Type",
    options: ["Regular", "Vegetarian", "Vegan"],
    conditional_logic: {
        addon: "Toppings",
        rules: [
            {
                when: "Pizza Type = Vegetarian OR Pizza Type = Vegan",
                hide_options: ["Pepperoni", "Sausage", "Bacon", "Ham"]
            },
            {
                when: "Pizza Type = Vegan",
                hide_options: ["Cheese", "Extra Cheese"],
                show_options: ["Vegan Cheese"]
            }
        ]
    }
}
```

## Example 3: Event Booking
```javascript
// Time-based pricing and availability
{
    addon: "Event Date",
    type: "date_picker",
    conditional_logic: {
        rules: [
            {
                when: "Event Date is weekend",
                modify_price: "+25%",
                show_message: "Weekend rates apply"
            },
            {
                when: "Event Date < 7 days from today",
                modify_price: "+50%",
                show_message: "Rush booking fee applies"
            },
            {
                when: "Event Date is December",
                require_addon: "Holiday Package",
                show_message: "Holiday package required for December events"
            }
        ]
    }
}
```

## Example 4: Computer Configuration
```javascript
// Complex dependencies between components
{
    addon: "Processor",
    options: ["i5", "i7", "i9"],
    conditional_logic: {
        rules: [
            {
                when: "Processor = i9",
                addon: "Cooling System",
                require: true,
                set_minimum: "Liquid Cooling",
                message: "High-performance processor requires advanced cooling"
            },
            {
                when: "Graphics Card = RTX 4090",
                addon: "Power Supply",
                set_minimum: "850W",
                hide_options: ["550W", "650W", "750W"]
            }
        ]
    }
}
```

## Example 5: Subscription Service
```javascript
// User role and history based conditions
{
    addon: "Subscription Plan",
    conditional_logic: {
        rules: [
            {
                when: "User Role = VIP Customer",
                show_options: ["Premium Plus", "Enterprise"],
                modify_price: "-15%"
            },
            {
                when: "User Purchase History > $1000",
                show_addon: "Loyalty Rewards",
                auto_select: "Gold Status"
            },
            {
                when: "Cart Total > $500",
                show_message: "Free premium support included!",
                add_hidden_addon: "Premium Support"
            }
        ]
    }
}
```

## Example 6: Photography Service
```javascript
// Location and time-based conditions
{
    addon: "Shoot Location",
    conditional_logic: {
        rules: [
            {
                when: "Location Distance > 50 miles",
                show_addon: "Travel Fee",
                calculate: "distance * 0.5"
            },
            {
                when: "Shoot Time = Sunrise OR Shoot Time = Sunset",
                show_addon: "Golden Hour Package",
                modify_price: "+$150"
            },
            {
                when: "Location = Outdoor AND Season = Winter",
                require_addon: "Weather Protection",
                show_message: "Weather protection required for outdoor winter shoots"
            }
        ]
    }
}
```

## Example 7: Course Enrollment
```javascript
// Progressive unlocking based on selections
{
    addon: "Course Level",
    options: ["Beginner", "Intermediate", "Advanced"],
    conditional_logic: {
        rules: [
            {
                when: "Course Level = Beginner",
                hide_addon: "Advanced Modules",
                show_addon: "Beginner Support Package"
            },
            {
                when: "Course Level = Advanced",
                require_addon: "Prerequisites Check",
                validate_with: "check_user_completed_courses()"
            },
            {
                when: "Payment Plan = Installments",
                add_fee: "$25",
                show_options: ["3 months", "6 months", "12 months"]
            }
        ]
    }
}
```

## Example 8: Car Rental
```javascript
// Multi-condition logic with inventory checks
{
    addon: "Car Category",
    conditional_logic: {
        rules: [
            {
                when: "Driver Age < 25 AND Car Category = Luxury",
                add_fee: "$50/day",
                require_addon: "Young Driver Insurance"
            },
            {
                when: "Rental Duration > 7 days",
                modify_price: "-10%",
                show_addon: "Free GPS"
            },
            {
                when: "Inventory.available(Car Model) < 3",
                show_message: "Limited availability - book now!",
                add_urgency_pricing: "+5%"
            }
        ]
    }
}
```

## Admin Interface Preview

```
┌─────────────────────────────────────────────────────────┐
│ Conditional Logic Builder                                │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ IF [Addon Field ▼] [equals ▼] [Value input    ]         │
│                                                          │
│ AND/OR                                                   │
│                                                          │
│ [+ Add another condition]                                │
│                                                          │
│ THEN                                                     │
│                                                          │
│ [Show ▼] [Target Addon ▼]                               │
│ [Modify Price ▼] [by +10% ▼]                           │
│ [Make Required ▼]                                        │
│                                                          │
│ [+ Add another action]                                   │
│                                                          │
│ [Save Rule] [Test Rule] [Delete]                        │
└─────────────────────────────────────────────────────────┘
```