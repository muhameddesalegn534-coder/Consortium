<?php
// Add Budget Heading and Outcome to predefined_fields table
define('INCLUDED_SETUP', true);
include 'setup_database.php';

echo "<h2>Adding Budget Heading and Outcome to Admin Configuration</h2>";

// Fields to add
$fieldsToAdd = [
    [
        'name' => 'BudgetHeading',
        'type' => 'dropdown',
        'values' => 'Administrative costs,Operational support costs,Consortium Activities,Targeting new CSOs,Contingency',
        'active' => 1
    ],
    [
        'name' => 'Outcome',
        'type' => 'input',
        'values' => '',
        'active' => 1
    ]
];

foreach ($fieldsToAdd as $field) {
    // Check if field already exists
    $checkQuery = "SELECT * FROM predefined_fields WHERE field_name = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $field['name']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ {$field['name']} already exists in the database</p>";
        $existing = $result->fetch_assoc();
        echo "<p>Current configuration: Type = {$existing['field_type']}, Active = " . ($existing['is_active'] ? 'Yes' : 'No') . "</p>";
    } else {
        // Add field
        $insertQuery = "INSERT INTO predefined_fields (field_name, field_type, field_values, is_active) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("sssi", $field['name'], $field['type'], $field['values'], $field['active']);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ {$field['name']} added to admin configuration</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add {$field['name']}: " . $conn->error . "</p>";
        }
    }
}

// Show all current fields
echo "<h3>Current Admin Fields:</h3>";
$result = $conn->query("SELECT * FROM predefined_fields ORDER BY field_name");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field Name</th><th>Type</th><th>Values</th><th>Active</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['field_name']}</strong></td>";
        echo "<td>{$row['field_type']}</td>";
        echo "<td>" . (strlen($row['field_values']) > 30 ? substr($row['field_values'], 0, 30) . "..." : $row['field_values']) . "</td>";
        echo "<td>" . ($row['is_active'] ? '✅ Yes' : '❌ No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><p><a href='admin_predefined_fields.php'>Go to Admin Interface</a></p>";
echo "<p><a href='financial_report_section.php'>Go to Financial Report</a></p>";
?>