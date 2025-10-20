<?php
include 'db_connection.php';

echo "=== budget_preview table structure ===\n";
$result = $conn->query('DESCRIBE budget_preview');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' ' . $row['Type'] . ' ' . $row['Null'] . ' ' . $row['Key'] . ' ' . $row['Default'] . ' ' . $row['Extra'] . "\n";
    }
}

echo "\n=== budget_data table structure ===\n";
$result = $conn->query('DESCRIBE budget_data');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' ' . $row['Type'] . ' ' . $row['Null'] . ' ' . $row['Key'] . ' ' . $row['Default'] . ' ' . $row['Extra'] . "\n";
    }
}

echo "\n=== Checking for currency column in budget_preview ===\n";
$check = $conn->query("SHOW COLUMNS FROM budget_preview LIKE 'currency'");
if ($check && $check->num_rows > 0) {
    echo "✓ currency column exists in budget_preview\n";
} else {
    echo "✗ currency column does not exist in budget_preview\n";
}

echo "\n=== Checking for currency column in budget_data ===\n";
$check = $conn->query("SHOW COLUMNS FROM budget_data LIKE 'currency'");
if ($check && $check->num_rows > 0) {
    echo "✓ currency column exists in budget_data\n";
} else {
    echo "✗ currency column does not exist in budget_data\n";
}
?>