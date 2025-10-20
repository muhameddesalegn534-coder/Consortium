<?php
// add_messages_table.php - Script to add the messages table to your database

// Database configuration (matching your config.php)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "consortium_hub";

try {
    // Create connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL to create messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        sender_id INT(11) NOT NULL,
        subject VARCHAR(255) NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    // Execute the query
    $conn->exec($sql);
    echo "Messages table created successfully\n";
    
    // Test inserting a sample message
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, subject, message) VALUES (?, ?, ?)");
    $stmt->execute([1, 'Test Message', 'This is a test message to verify the table works correctly.']);
    echo "Sample message inserted successfully\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$conn = null;
?>