# Budget Calculation Fixes

## Issues Identified

Based on your database structure and the code analysis, I found several critical issues with the budget calculations:

### 1. **Incorrect Budget Calculation Logic**

**Problem**: The current logic in both `financial_report_section.php` and `edit_transaction.php` has flawed calculations for:
- `RemainingBudget`
- `ActualSpent` 
- `ForecastAmount`
- `VariancePercentage`

**Example from your data**:
- Original Budget: 300.00
- Current Actual: 100.00
- New Transaction: 50.00
- **Incorrect calculation**: RemainingBudget = 300 - (150 + 150) = 0 (wrong!)
- **Correct calculation**: RemainingBudget = 300 - 150 = 150

### 2. **Inconsistent Data Sources**

**Problem**: The dashboard was using `budget_preview` table for totals, but the actual budget data should come from `budget_data` table for accurate tracking.

### 3. **Wrong Variance Calculation**

**Problem**: The variance calculation was not consistent between tables and was using incorrect formulas.

## Fixes Implemented

### 1. **Fixed Budget Calculation Logic**

**In `ajax_handler.php`** (lines 177-185):
```php
// OLD (INCORRECT):
$remainingBudget = max($originalBudget - ($actualSpent + $forecastAmount), 0);
$variancePercentage = $originalBudget > 0 ? (($originalBudget - ($actualSpent + $forecastAmount)) / $originalBudget) * 100 : 0;

// NEW (CORRECT):
$forecastAmount = max($originalBudget - $actualSpent, 0); // Forecast = Budget - ActualSpent
$remainingBudget = $forecastAmount; // Remaining = Forecast (what's left to spend)
$variancePercentage = 0; // Variance is 0 when Actual + Forecast = Budget
```

**In `edit_transaction.php`** (lines 470-480):
```php
// OLD (INCORRECT):
$newRemainingBudget = max(0, $originalBudgetValue - ($newActualSpent + $amountDifference));
$newForecastAmount = max(0, $originalBudgetValue - ($newActualSpent + $amountDifference));

// NEW (CORRECT):
$newActualSpent = $newActualSpent + $amountDifference;
$newForecastAmount = max(0, $originalBudgetValue - $newActualSpent);
$newRemainingBudget = $newForecastAmount;
$newVariancePercentage = 0;
```

### 2. **Fixed Data Source for Dashboard**

**In `financial_report_section.php`** (lines 85-100):
```php
// OLD (INCORRECT):
$budgetQuery = "SELECT SUM(OriginalBudget) as total_budget, SUM(ActualSpent) as total_actual_spent FROM budget_preview WHERE cluster = ?";

// NEW (CORRECT):
$budgetQuery = "SELECT 
    SUM(CASE WHEN period_name = 'Annual Total' THEN budget ELSE 0 END) as total_budget,
    SUM(CASE WHEN period_name = 'Annual Total' THEN actual ELSE 0 END) as total_actual_spent,
    SUM(CASE WHEN period_name = 'Annual Total' THEN forecast ELSE 0 END) as total_forecast
    FROM budget_data WHERE cluster = ? AND year2 = ?";
```

### 3. **Added Missing Helper Functions**

**In `financial_report_section.php`**:
```php
function mapCategoryName($category) {
    $categoryMappings = [
        'Administrative costs' => '1. Administrative costs',
        'Operational support costs' => '2. Operational support costs',
        'Consortium Activities' => '3. Consortium Activities',
        'Targeting new CSOs' => '4. Targeting new CSOs',
        'Contingency' => '5. Contingency'
    ];
    return $categoryMappings[$category] ?? $category;
}

function mapSubcategoryName($category) {
    return $category;
}
```

## Correct Budget Calculation Logic

### For New Transactions:
1. **Original Budget**: Fixed amount from `budget_data` table
2. **Actual Spent**: Previous actual + new transaction amount
3. **Forecast Amount**: Original Budget - Actual Spent
4. **Remaining Budget**: Same as Forecast Amount
5. **Variance Percentage**: 0 (since Actual + Forecast = Budget)

### Example Calculation:
```
Original Budget: 300.00
Previous Actual: 100.00
New Transaction: 50.00

New Actual Spent: 100 + 50 = 150.00
Forecast Amount: 300 - 150 = 150.00
Remaining Budget: 150.00
Variance Percentage: 0%
```

## Testing

I've created a test script `test_budget_calculations.php` that you can run to verify:
1. Database table accessibility
2. Budget calculation logic
3. Dashboard calculations
4. Data consistency

## Database Structure Alignment

Your database structure is correct:
- `budget_data` table: Master budget information with quarters and periods
- `budget_preview` table: Transaction records with calculated budget fields

The key is ensuring that:
1. `budget_preview.OriginalBudget` = `budget_data.budget`
2. `budget_preview.ActualSpent` = Sum of all transactions for that quarter
3. `budget_preview.ForecastAmount` = `OriginalBudget - ActualSpent`
4. `budget_preview.RemainingBudget` = `ForecastAmount`

## Next Steps

1. **Test the fixes**: Run the test script to verify calculations
2. **Update existing data**: You may need to recalculate existing transactions
3. **Monitor**: Watch for any discrepancies in new transactions

## Files Modified

1. `financial_report_section.php` - Fixed dashboard calculations and added helper functions
2. `ajax_handler.php` - Fixed budget calculation logic for new transactions
3. `edit_transaction.php` - Fixed budget calculation logic for editing transactions
4. `test_budget_calculations.php` - Created test script for verification
