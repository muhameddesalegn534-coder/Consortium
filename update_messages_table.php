<?php
// update_messages_table.php - Script to update the messages table structure
// This script removes the recipient_type and recipient_id columns if they exist

session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

echo "<h2>Messages Table Update Script</h2>";

try {
    // Check if recipient_type column exists
    $checkColumnQuery = "SHOW COLUMNS FROM messages LIKE 'recipient_type'";
    $result = $conn->query($checkColumnQuery);
    
    if ($result && $result->num_rows > 0) {
        // Column exists, so we need to drop it
        echo "<p>Found recipient_type column. Removing it...</p>";
        $dropColumnQuery = "ALTER TABLE messages DROP COLUMN recipient_type";
        $conn->exec($dropColumnQuery);
        echo "<p>Successfully removed recipient_type column.</p>";
    } else {
        echo "<p>recipient_type column does not exist. No action needed.</p>";
    }
    
    // Check if recipient_id column exists
    $checkColumnQuery = "SHOW COLUMNS FROM messages LIKE 'recipient_id'";
    $result = $conn->query($checkColumnQuery);
    
    if ($result && $result->num_rows > 0) {
        // Column exists, so we need to drop it
        echo "<p>Found recipient_id column. Removing it...</p>";
        $dropColumnQuery = "ALTER TABLE messages DROP COLUMN recipient_id";
        $conn->exec($dropColumnQuery);
        echo "<p>Successfully removed recipient_id column.</p>";
    } else {
        echo "<p>recipient_id column does not exist. No action needed.</p>";
    }
    
    echo "<p><strong>Messages table structure is now up to date!</strong></p>";
    echo "<a href='admin.php'>Back to Admin Panel</a>";
    
} catch(PDOException $e) {
    echo "<p>Error updating messages table: " . $e->getMessage() . "</p>";
}

$conn = null;
?>