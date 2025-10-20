<?php
// Database configuration for PDO connections
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password
$dbname = "consortium_hub"; // Your database name

try {
    // Create PDO connection
    $pdo_conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    
    // Set the PDO error mode to exception
    $pdo_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Remove this echo to prevent contaminating JSON responses
    // echo "Connected successfully to " . $dbname;
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>