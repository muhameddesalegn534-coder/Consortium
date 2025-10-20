<?php
// send_message.php - Handle sending messages and storing them in the database

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // For testing purposes, we'll use a default user ID
    // In production, you should ensure users are properly logged in
    $sender_id = 1; // Default to admin user
} else {
    $sender_id = $_SESSION['user_id'];
}

// Include database connection
require_once 'config.php';

// Get POST data
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validate input
if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

try {
    // Prepare SQL statement to insert message
    // Note: Removed recipient_type and recipient_id since they were dropped from the table
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, subject, message) VALUES (?, ?, ?)");
    $result = $stmt->execute([
        $sender_id,
        $subject,
        $message
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $e->getMessage()]);
}

$conn = null;
?>