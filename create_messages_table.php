<?php
// create_messages_table.php - Script to create the messages table

require_once 'config.php';

try {
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
    echo "Messages table created successfully";
} catch(PDOException $e) {
    echo "Error creating messages table: " . $e->getMessage();
}

$conn = null;
?>