<?php
// Script to update the project_documents table structure to match the new form

// Include the database setup
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Add new columns for the financial report section
$columnsToAdd = [
    "financial_report_file_names" => "TEXT",
    "financial_report_file_paths" => "TEXT",
    "expenditure_issues" => "TEXT",
    "summary_achievements" => "TEXT",
    "operating_context" => "TEXT",
    "outcomes_outputs" => "TEXT",
    "results_framework_file_names" => "TEXT",
    "results_framework_file_paths" => "TEXT",
    "challenges_description" => "TEXT",
    "mitigation_measures" => "TEXT",
    "risk_matrix_file_names" => "TEXT",
    "risk_matrix_file_paths" => "TEXT",
    "good_practices" => "TEXT",
    "spotlight_narrative" => "TEXT",
    "spotlight_photo_file_names" => "TEXT",
    "spotlight_photo_file_paths" => "TEXT",
    "other_title" => "VARCHAR(255)",
    "other_date" => "DATE",
    "other_file_names" => "TEXT",
    "other_file_paths" => "TEXT"
];

foreach ($columnsToAdd as $columnName => $columnType) {
    // Check if column exists
    $checkSql = "SHOW COLUMNS FROM project_documents LIKE '$columnName'";
    $checkResult = $conn->query($checkSql);
    
    if ($checkResult->num_rows == 0) {
        // Column doesn't exist, add it
        $addSql = "ALTER TABLE project_documents ADD COLUMN $columnName $columnType";
        if ($conn->query($addSql) === TRUE) {
            echo "<p class='success'>Added column '$columnName' to project_documents table</p>";
        } else {
            echo "<p class='error'>Error adding column '$columnName': " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='info'>Column '$columnName' already exists in project_documents table</p>";
    }
}

// Close connection
$conn->close();
?>