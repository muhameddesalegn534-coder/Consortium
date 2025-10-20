<?php
include 'db_connection.php';

echo "=== Sample data from budget_preview ===\n";
$result = $conn->query('SELECT * FROM budget_preview LIMIT 1');
if ($result) {
    $row = $result->fetch_assoc();
    print_r($row);
}

echo "\n=== Sample data from budget_data ===\n";
$result = $conn->query('SELECT * FROM budget_data LIMIT 1');
if ($result) {
    $row = $result->fetch_assoc();
    print_r($row);
}
?>