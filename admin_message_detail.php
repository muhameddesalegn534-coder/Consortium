<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get message ID from URL
$message_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($message_id <= 0) {
    header('Location: admin_messages.php');
    exit();
}

// Fetch the message with sender information
try {
    $stmt = $conn->prepare("SELECT m.*, u.username, u.cluster_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ?");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        $_SESSION['error_message'] = 'Message not found.';
        header('Location: admin_messages.php');
        exit();
    }
    
    // Mark message as read if it's not already
    if (!$message['is_read']) {
        $updateStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
        $updateStmt->execute([$message_id]);
        $message['is_read'] = 1;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error fetching message: ' . $e->getMessage();
    header('Location: admin_messages.php');
    exit();
}
?>

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="flex flex-col flex-1 min-w-0">
    <!-- Header -->
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
        <div class="flex items-center">
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Message Details</h2>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Message Details</h1>
                <div class="flex space-x-2">
                    <a href="admin_messages.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Back to Messages
                    </a>
                    <a href="admin.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Admin Panel
                    </a>
                </div>
            </div>
            
            <div class="border border-gray-200 rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Sender</h3>
                        <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($message['username']); ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Cluster</h3>
                        <p class="mt-1 text-lg font-medium text-gray-900">
                            <?php echo htmlspecialchars($message['cluster_name'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Date Sent</h3>
                        <p class="mt-1 text-lg font-medium text-gray-900">
                            <?php echo date('F j, Y \a\t g:i A', strtotime($message['created_at'])); ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Status</h3>
                        <p class="mt-1 text-lg font-medium text-gray-900">
                            <?php if ($message['is_read']): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    Read
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    Unread
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-medium text-gray-500">Subject</h3>
                    <p class="mt-1 text-lg font-medium text-gray-900">
                        <?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?>
                    </p>
                </div>
                
                <div class="border-t border-gray-200 pt-6 mt-6">
                    <h3 class="text-sm font-medium text-gray-500">Message</h3>
                    <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-800 whitespace-pre-wrap"><?php echo htmlspecialchars($message['message']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'message_system.php'; ?>