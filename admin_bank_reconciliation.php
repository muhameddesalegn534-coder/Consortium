<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch all bank reconciliation documents
try {
    $stmt = $conn->prepare("SELECT brd.*, u.username, u.cluster_name FROM bank_reconciliation_documents brd JOIN users u ON brd.user_id = u.id ORDER BY brd.uploaded_at DESC");
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching documents: " . $e->getMessage();
    $documents = [];
}
?>

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="flex flex-col flex-1 min-w-0">
    <!-- Header -->
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
        <div class="flex items-center">
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Bank Reconciliation Documents</h2>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Bank Reconciliation Documents</h1>
                <a href="admin.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Back to Admin
                </a>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="mb-4 p-4 rounded bg-red-100 text-red-700">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($documents)): ?>
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No documents</h3>
                    <p class="mt-1 text-sm text-gray-500">No bank reconciliation documents have been uploaded yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cluster</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($document['file_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($document['description']); ?>">
                                            <?php echo htmlspecialchars($document['description']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($document['username']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo htmlspecialchars($document['cluster_name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y g:i A', strtotime($document['uploaded_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'message_system.php'; ?>