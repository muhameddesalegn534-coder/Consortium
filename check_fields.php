<?php
include 'setup_database.php';

echo "Checking predefined fields for BudgetHeading and Outcome:\n\n";

$result = $conn->query("SELECT * FROM predefined_fields WHERE field_name = 'BudgetHeading' OR field_name = 'Outcome' ORDER BY field_name, cluster_name");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['field_name'] . "\n";
        echo "Type: " . $row['field_type'] . "\n";
        echo "Values: " . ($row['field_values'] ?? 'NULL') . "\n";
        echo "Active: " . $row['is_active'] . "\n";
        echo "Cluster: " . ($row['cluster_name'] ?? 'NULL') . "\n";
        echo "------------------------\n";
    }
} else {
    echo "No predefined fields found for BudgetHeading or Outcome\n";
}

$conn->close();
?>