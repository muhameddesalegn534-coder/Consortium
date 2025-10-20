<?php
// Script to create the bank_reconciliation_documents table

require_once 'config.php';

// SQL to create bank reconciliation documents table
$sql = "CREATE TABLE IF NOT EXISTS bank_reconciliation_documents (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    cluster VARCHAR(100) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

try {
    $conn->exec($sql);
    echo "Table 'bank_reconciliation_documents' created successfully or already exists.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>