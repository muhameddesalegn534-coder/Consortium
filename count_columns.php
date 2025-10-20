<?php
// Count columns and values in the INSERT statement
$columns = 'BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount, PVNumber, DocumentPaths, DocumentTypes, OriginalNames, QuarterPeriod, CategoryName, OriginalBudget, RemainingBudget, ActualSpent, ForecastAmount, VariancePercentage, cluster, budget_id, currency';
$values = 'sssssssdssssssssddssis';

$cols = explode(',', $columns);
echo "Number of columns: " . count($cols) . "\n";

$valArray = str_split($values);
echo "Number of values: " . count($valArray) . "\n";

echo "Columns:\n";
foreach ($cols as $i => $col) {
    echo ($i+1) . ". " . trim($col) . "\n";
}

echo "\nValues:\n";
foreach ($valArray as $i => $val) {
    echo ($i+1) . ". " . $val . "\n";
}
?>