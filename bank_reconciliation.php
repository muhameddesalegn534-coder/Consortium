<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if this file is being included or accessed directly
$included = defined('INCLUDED_FROM_INDEX');

// Check if user is from Woldiya cluster
$is_woldiya_user = (isset($_SESSION['cluster_name']) && $_SESSION['cluster_name'] === 'Woldiya');

// Handle file upload
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bank_document'])) {
    $target_dir = "uploads/bank_reconciliation/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($_FILES["bank_document"]["name"]);
    $description = trim($_POST["description"] ?? '');
    $target_file = $target_dir . uniqid() . "_" . $file_name;
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if file is a valid document type
    $allowed_types = array("pdf", "doc", "docx", "xls", "xlsx", "jpg", "jpeg", "png");
    if (!in_array($fileType, $allowed_types)) {
        $upload_message = "Sorry, only PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG files are allowed.";
        $uploadOk = 0;
    }
    
    // Check file size (limit to 5MB)
    if ($_FILES["bank_document"]["size"] > 5000000) {
        $upload_message = "Sorry, your file is too large. Maximum file size is 5MB.";
        $uploadOk = 0;
    }
    
    // Check if description is provided
    if (empty($description)) {
        $upload_message = "Please provide a description for the document.";
        $uploadOk = 0;
    }
    
    // If everything is ok, try to upload file
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["bank_document"]["tmp_name"], $target_file)) {
            // Save to database
            try {
                $stmt = $conn->prepare("INSERT INTO bank_reconciliation_documents (user_id, cluster, file_path, file_name, description, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $cluster = $is_woldiya_user ? 'Woldiya' : (isset($_SESSION['cluster_name']) ? $_SESSION['cluster_name'] : 'Unknown');
                $stmt->execute([$_SESSION['user_id'], $cluster, $target_file, $file_name, $description]);
                $upload_message = "The file " . htmlspecialchars($file_name) . " has been uploaded successfully.";
            } catch (PDOException $e) {
                $upload_message = "Database error: " . $e->getMessage();
            }
        } else {
            $upload_message = "Sorry, there was an error uploading your file.";
        }
    }
}
?>

<?php if (!$included): ?>
<?php include 'header.php'; ?>
<?php endif; ?>

<?php if (!$included): ?>
<!-- Main content area -->
<div class="flex flex-col flex-1 min-w-0">
    <!-- Header -->
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
        <div class="flex items-center">
            <!-- Hamburger menu for small screens -->
            <button id="mobileMenuToggle"
                class="text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-2 lg:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Bank Reconciliation</h2>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
<?php endif; ?>

            <h1 class="text-2xl font-bold text-gray-800 mb-6">Upload Bank Reconciliation Document</h1>
            
            <?php if ($upload_message): ?>
                <div class="mb-4 p-4 rounded <?php echo strpos($upload_message, 'successfully') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo $upload_message; ?>
                </div>
            <?php endif; ?>
            
            <form action="bank_reconciliation.php" method="post" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Bank Reconciliation Document</label>
                    <div class="flex items-center">
                        <input type="file" name="bank_document" required class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Allowed file types: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG. Maximum file size: 5MB.</p>
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" name="description" rows="4" required class="block w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <p class="mt-2 text-sm text-gray-500">Please provide a description of the document.</p>
                </div>
                
                <?php if ($is_woldiya_user): ?>
                    <div class="bg-blue-50 p-4 rounded-md">
                        <p class="text-blue-700 font-medium">You are logged in as a Woldiya cluster user. Documents will be automatically tagged with "Woldiya" cluster.</p>
                    </div>
                <?php endif; ?>
                
                <div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Upload Document
                    </button>
                </div>
            </form>

<?php if (!$included): ?>
        </div>
    </main>
</div>

<script>
// Mobile sidebar toggle functionality for standalone page
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    
    // Toggle sidebar on hamburger menu click (mobile)
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
        });
    }
    
    // Hide sidebar when clicking outside on mobile
    document.addEventListener('click', (event) => {
        if (window.innerWidth < 1024 && sidebar && !sidebar.contains(event.target) && 
            mobileMenuToggle && !mobileMenuToggle.contains(event.target) && 
            !sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
    });
});
</script>

<?php include 'message_system.php'; ?>
<?php endif; ?>