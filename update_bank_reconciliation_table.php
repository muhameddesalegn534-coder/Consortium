<?php
// Script to add description column to the bank_reconciliation_documents table

require_once 'config.php';

try {
    // Check if description column already exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM bank_reconciliation_documents LIKE 'description'");
    $stmt->execute();
    $column = $stmt->fetch();
    
    if (!$column) {
        // Add description column
        $sql = "ALTER TABLE bank_reconciliation_documents ADD COLUMN description TEXT DEFAULT NULL";
        $conn->exec($sql);
        echo "Description column added successfully to bank_reconciliation_documents table.\n";
    } else {
        echo "Description column already exists in bank_reconciliation_documents table.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>