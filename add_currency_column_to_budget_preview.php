<?php
// Migration script to add currency column to budget_preview table
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Include database configuration
if (!defined('INCLUDED_SETUP')) {
    define('INCLUDED_SETUP', true);
}
include 'setup_database.php';

$message = '';
$error_message = '';

// Check if currency column exists
$checkColumn = "SHOW COLUMNS FROM budget_preview LIKE 'currency'";
$result = $conn->query($checkColumn);

if ($result->num_rows == 0) {
    // Add currency column
    $addColumn = "ALTER TABLE budget_preview ADD COLUMN currency VARCHAR(3) DEFAULT 'ETB'";
    if ($conn->query($addColumn) === TRUE) {
        $message = "Currency column added successfully to budget_preview table";
        
        // Update existing rows to have ETB as default currency
        $updateQuery = "UPDATE budget_preview SET currency = 'ETB' WHERE currency IS NULL";
        if ($conn->query($updateQuery) === TRUE) {
            $message .= " and existing rows updated with default currency (ETB)";
        }
    } else {
        $error_message = "Error adding currency column: " . $conn->error;
    }
} else {
    $message = "Currency column already exists in budget_preview table";
}

// Fetch some sample data to verify
$sampleQuery = "SELECT PreviewID, Amount, currency FROM budget_preview LIMIT 5";
$sampleResult = $conn->query($sampleQuery);
$sampleData = [];
if ($sampleResult && $sampleResult->num_rows > 0) {
    while ($row = $sampleResult->fetch_assoc()) {
        $sampleData[] = $row;
    }
}
?>

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="flex flex-col flex-1 min-w-0">
    <!-- Header -->
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
        <div class="flex items-center">
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Currency Column Migration for Budget Preview</h2>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
        <div class="max-w-6xl mx-auto space-y-6">
            
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Sample Data -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Sample Data Verification</h3>
                <?php if (!empty($sampleData)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preview ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($sampleData as $row): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['PreviewID']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($row['Amount'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['currency']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">No sample data available.</p>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Next Steps</h3>
                <p class="text-gray-600 mb-4">Now that the currency column has been added to the budget_preview table, currency conversion will work properly in the financial report section.</p>
                <a href="financial_report_section.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Go to Financial Report Section
                </a>
            </div>
        </div>
    </main>
</div>

<?php include 'message_system.php'; ?>