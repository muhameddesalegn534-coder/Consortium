<?php
include 'setup_database.php';

echo "Searching for transaction with Amount = 20000.00:\n";

$stmt = $conn->prepare("SELECT PreviewID, Amount, currency, cluster FROM budget_preview WHERE Amount = 20000.00");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        
        // Also check what cluster this transaction belongs to
        $clusterName = $row['cluster'];
        echo "Cluster: $clusterName\n";
        
        // Get currency rates for this cluster
        include 'currency_functions.php';
        $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $clusterName);
        echo "Currency rates for cluster '$clusterName':\n";
        print_r($currencyRates);
        
        // Test conversion
        $amountInETB = convertCurrency($row['Amount'], $row['currency'], 'ETB', $currencyRates);
        echo "Converted to ETB: " . number_format($amountInETB, 2) . " ETB\n\n";
    }
} else {
    echo "No transaction found with Amount = 20000.00\n";
}
?>