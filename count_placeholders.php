<?php
$sql = "INSERT INTO budget_preview (BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount, PVNumber, DocumentPaths, DocumentTypes, OriginalNames, QuarterPeriod, CategoryName, OriginalBudget, RemainingBudget, ActualSpent, ForecastAmount, VariancePercentage, cluster, budget_id, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
preg_match_all('/\?/', $sql, $matches);
echo "Number of placeholders: " . count($matches[0]) . "\n";

// Count columns
$columns = "BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount, PVNumber, DocumentPaths, DocumentTypes, OriginalNames, QuarterPeriod, CategoryName, OriginalBudget, RemainingBudget, ActualSpent, ForecastAmount, VariancePercentage, cluster, budget_id, currency";
$cols = explode(',', $columns);
echo "Number of columns: " . count($cols) . "\n";

// Check if they match
if (count($matches[0]) == count($cols)) {
    echo "Columns and placeholders match!\n";
} else {
    echo "MISMATCH: Columns and placeholders don't match!\n";
}
?>