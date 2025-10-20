<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Mark all messages as read
try {
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE is_read = 0");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    $_SESSION['success_message'] = "Successfully marked {$count} messages as read.";
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error updating messages: ' . $e->getMessage();
}

header('Location: admin_messages.php');
exit();
?>