<?php
include 'setup_database.php';

// Get the structure of the budget_preview table
$result = $conn->query('DESCRIBE budget_preview');
if ($result) {
    echo "budget_preview table structure:\n";
    echo "----------------------------------------\n";
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' ' . $row['Type'] . ' ' . $row['Null'] . ' ' . $row['Key'] . ' ' . $row['Default'] . ' ' . $row['Extra'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}

echo "\n\n";

// Also check if the additional currency rate columns exist
$checkColumns = [
    'currency',
    'use_custom_rate',
    'usd_to_etb',
    'eur_to_etb',
    'usd_to_eur'
];

echo "Checking for additional columns:\n";
echo "----------------------------------------\n";
foreach ($checkColumns as $column) {
    $check = $conn->query("SHOW COLUMNS FROM budget_preview LIKE '$column'");
    if ($check && $check->num_rows > 0) {
        echo "✓ $column exists\n";
    } else {
        echo "✗ $column does not exist\n";
    }
}
?>