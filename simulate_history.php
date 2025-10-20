<?php
// Simulate what happens in history.php
session_start();

// Set a test cluster
$_SESSION['cluster_name'] = 'Woldiya';

include 'setup_database.php';
include 'currency_functions.php';

// Get user cluster
$userCluster = $_SESSION['cluster_name'] ?? null;
echo "User cluster: " . ($userCluster ?? 'NULL') . "\n";

// Get currency rates for the user's cluster
$currencyRates = [];
if ($userCluster) {
    $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $userCluster);
    echo "Currency rates:\n";
    print_r($currencyRates);
} else {
    // Default rates if no cluster is assigned
    $currencyRates = [
        'USD_to_ETB' => 55.0000,
        'EUR_to_ETB' => 60.0000
    ];
    echo "Using default currency rates:\n";
    print_r($currencyRates);
}

// Test a transaction
$transaction = [
    'Amount' => 289.00,
    'currency' => 'USD',
    'OriginalBudget' => 20000.00,
    'ActualSpent' => 6889.00,
    'ForecastAmount' => 13111.00
];

echo "\nTesting display logic:\n";

// Amount display
$originalAmount = $transaction['Amount'] ?? 0;
$originalCurrency = $transaction['currency'] ?? 'ETB';
$amountInETB = convertCurrency($originalAmount, $originalCurrency, 'ETB', $currencyRates);
echo "Amount display: " . number_format($amountInETB, 2) . " ETB";
if ($originalCurrency !== 'ETB') {
    echo " (" . number_format($originalAmount, 2) . " " . $originalCurrency . ")";
}
echo "\n";

// Budget display
$budgetInETB = convertCurrency($transaction['OriginalBudget'] ?? 0, $transaction['currency'] ?? 'ETB', 'ETB', $currencyRates);
echo "Budget display: " . number_format($budgetInETB, 2) . " ETB";
if (($transaction['currency'] ?? 'ETB') !== 'ETB') {
    echo " (" . number_format($transaction['OriginalBudget'] ?? 0, 2) . " " . ($transaction['currency'] ?? 'ETB') . ")";
}
echo "\n";
?>