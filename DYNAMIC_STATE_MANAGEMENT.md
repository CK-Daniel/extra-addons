# Dynamic State Management for Conditional Logic

## Overview

The conditional logic system now implements complete dynamic state management. This ensures that:

1. **All modifications are reversible**: When conditions change, elements return to their original state
2. **Multiple rules work together**: The system evaluates all rules and applies only the currently active ones
3. **No conflicts**: Each evaluation starts fresh, preventing accumulation of modifications

## How It Works

### 1. State Reset Before Each Evaluation

Every time rules are evaluated, the system first calls `resetAllModifications()` which:

- Shows all previously hidden addons/options
- Restores original prices
- Resets required/optional states
- Removes all conditional logic classes

### 2. Apply Active Rules

After reset, only the rules whose conditions are currently met are applied:

```javascript
// Example flow:
1. User selects "test" → Hide "tester123" option
2. User changes selection → Reset all → No rules apply → "tester123" is visible again
3. User selects "test" again → Hide "tester123" option
```

### 3. Original State Preservation

The system stores original addon data during initialization:

```javascript
originalAddons[addonId] = {
    element: jQuery element,
    name: display name,
    identifier: unique ID,
    prices: { option1: 100, option2: 200 },
    required: true/false
}
```

## Key Features

### Dynamic Option Visibility

- Options are marked with `conditional-logic-hidden` class
- Hidden options are disabled to prevent selection
- If a hidden option was selected, it's automatically deselected

### Price Modifications

- Original prices are preserved
- Price changes are applied dynamically
- When rules no longer apply, prices revert to original

### Cascading Rules

Multiple rules can affect the same element:

```sql
-- Rule 1: Hide option when condition A
-- Rule 2: Change price when condition B
-- Rule 3: Make required when condition C

-- All three can be active simultaneously
```

### State Tracking

The system tracks:
- Current selections
- Applied modifications
- Original values
- Rule evaluation results

## Example Scenarios

### Scenario 1: Simple Hide/Show

1. Rule: "When Test is selected, hide Tester123"
2. User selects Test → Tester123 is hidden
3. User selects Test2 → Tester123 is shown again

### Scenario 2: Multiple Rules

1. Rule A: "When Test is selected, hide Option1"
2. Rule B: "When Test2 is selected, hide Option2"
3. User selects Test → Only Option1 is hidden
4. User selects Test2 → Only Option2 is hidden
5. Both rules can be active if using checkboxes

### Scenario 3: Price Changes

1. Rule: "When Premium selected, set Basic price to $50"
2. User selects Premium → Basic shows $50
3. User deselects Premium → Basic returns to original price

## Benefits

1. **Predictable behavior**: Users always know what to expect
2. **Clean state**: No accumulated modifications
3. **Performance**: Only active rules are processed
4. **Flexibility**: Supports complex rule combinations
5. **Reliability**: No conflicts between rules

## Technical Implementation

### Reset Process

```javascript
resetAllModifications() {
    // 1. Show all hidden elements
    $('.conditional-logic-hidden').show().removeClass('conditional-logic-hidden');
    
    // 2. Restore original prices
    for each addon in originalAddons {
        resetAddonPrices(addon.element, addon.prices);
    }
    
    // 3. Restore required states
    for each addon in originalAddons {
        setAddonRequired(addon.element, addon.required);
    }
}
```

### Evaluation Flow

```javascript
evaluateAllConditions() {
    // 1. Gather current state
    // 2. Send to server for rule evaluation
    // 3. Receive active rules
    // 4. Reset all modifications
    // 5. Apply only active rules
}
```

## Best Practices

1. **Always use rule keys, not values** in rule definitions
2. **Test with multiple rules** to ensure proper interaction
3. **Use browser console** to monitor rule evaluation
4. **Check for the reset message** in console logs

## Debugging

Enable debug logging to see:
- "🔄 Resetting all modifications to default state"
- "🔧 Applying action: [action details]"
- "✨ All rule actions applied"

This ensures you can track the complete lifecycle of rule evaluation.