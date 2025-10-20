<?php
// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Create certificates_simple table for storing only certificate paths and metadata
$sql = "CREATE TABLE IF NOT EXISTS certificates_simple (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cluster_name VARCHAR(100) NOT NULL,
    year INT(4) NOT NULL,
    certificate_path VARCHAR(500) NOT NULL,
    uploaded_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(255) DEFAULT 'admin'
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'certificates_simple' created/verified successfully<br>";
    
    // Check if table exists
    $checkSql = "SHOW TABLES LIKE 'certificates_simple'";
    $result = $conn->query($checkSql);
    
    if ($result && $result->num_rows > 0) {
        echo "Table 'certificates_simple' confirmed to exist<br>";
        
        // Describe the table structure
        $descSql = "DESCRIBE certificates_simple";
        $descResult = $conn->query($descSql);
        
        if ($descResult && $descResult->num_rows > 0) {
            echo "<h3>Table Structure:</h3>";
            echo "<pre>";
            while($row = $descResult->fetch_assoc()) {
                print_r($row);
            }
            echo "</pre>";
        }
    }
} else {
    echo "Error creating table 'certificates_simple': " . $conn->error . "<br>";
}
?>