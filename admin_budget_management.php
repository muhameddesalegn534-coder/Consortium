<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Additional security check to ensure the session is valid
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';


// Get all clusters for filter dropdown
$clustersQuery = "SELECT * FROM clusters WHERE is_active = 1 ORDER BY cluster_name";
$clustersResult = $conn->query($clustersQuery);
$clusters = [];
if ($clustersResult && $clustersResult->num_rows > 0) {
    while ($row = $clustersResult->fetch_assoc()) {
        $clusters[] = $row;
    }
}

// Initialize filter variables
$selected_cluster = isset($_GET['cluster']) ? $_GET['cluster'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Pagination variables
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Fetch budget_data records with optional filters and pagination
$budgetDataQuery = "SELECT * FROM budget_data WHERE 1=1";
$countQuery = "SELECT COUNT(*) as total FROM budget_data WHERE 1=1";
$params = [];
$types = "";

if (!empty($selected_cluster)) {
    $budgetDataQuery .= " AND cluster = ?";
    $countQuery .= " AND cluster = ?";
    $params[] = $selected_cluster;
    $types .= "s";
}

if (!empty($start_date)) {
    $budgetDataQuery .= " AND start_date >= ?";
    $countQuery .= " AND start_date >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $budgetDataQuery .= " AND end_date <= ?";
    $countQuery .= " AND end_date <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$budgetDataQuery .= " ORDER BY year DESC, category_name, period_name LIMIT ? OFFSET ?";
$budgetDataParams = array_merge($params, [$records_per_page, $offset]);
$budgetDataTypes = $types . "ii";

if (!empty($params)) {
    $stmt = $conn->prepare($budgetDataQuery);
    $stmt->bind_param($budgetDataTypes, ...$budgetDataParams);
    $stmt->execute();
    $budgetDataResult = $stmt->get_result();
    
    // Get total count for pagination
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total_budget_data_records = $countResult->fetch_assoc()['total'];
} else {
    // When no parameters, we still need to bind the LIMIT and OFFSET
    $stmt = $conn->prepare($budgetDataQuery);
    $stmt->bind_param("ii", $records_per_page, $offset);
    $stmt->execute();
    $budgetDataResult = $stmt->get_result();
    
    // Get total count for pagination
    $countResult = $conn->query($countQuery);
    $total_budget_data_records = $countResult->fetch_assoc()['total'];
}

$budget_data_records = [];
if ($budgetDataResult && $budgetDataResult->num_rows > 0) {
    while ($row = $budgetDataResult->fetch_assoc()) {
        $budget_data_records[] = $row;
    }
}

// Calculate total pages for budget_data
$total_budget_data_pages = ceil($total_budget_data_records / $records_per_page);

// Fetch budget_preview records with optional filters and pagination
$budgetPreviewQuery = "SELECT * FROM budget_preview WHERE 1=1";
$countPreviewQuery = "SELECT COUNT(*) as total FROM budget_preview WHERE 1=1";
$params = [];
$types = "";

if (!empty($selected_cluster)) {
    $budgetPreviewQuery .= " AND cluster = ?";
    $countPreviewQuery .= " AND cluster = ?";
    $params[] = $selected_cluster;
    $types .= "s";
}

if (!empty($start_date)) {
    $budgetPreviewQuery .= " AND EntryDate >= ?";
    $countPreviewQuery .= " AND EntryDate >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $budgetPreviewQuery .= " AND EntryDate <= ?";
    $countPreviewQuery .= " AND EntryDate <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$budgetPreviewQuery .= " ORDER BY EntryDate DESC, CategoryName LIMIT ? OFFSET ?";
$budgetPreviewParams = array_merge($params, [$records_per_page, $offset]);
$budgetPreviewTypes = $types . "ii";

if (!empty($params)) {
    $stmt = $conn->prepare($budgetPreviewQuery);
    $stmt->bind_param($budgetPreviewTypes, ...$budgetPreviewParams);
    $stmt->execute();
    $budgetPreviewResult = $stmt->get_result();
    
    // Get total count for pagination
    $countStmt = $conn->prepare($countPreviewQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total_budget_preview_records = $countResult->fetch_assoc()['total'];
} else {
    // When no parameters, we still need to bind the LIMIT and OFFSET
    $stmt = $conn->prepare($budgetPreviewQuery);
    $stmt->bind_param("ii", $records_per_page, $offset);
    $stmt->execute();
    $budgetPreviewResult = $stmt->get_result();
    
    // Get total count for pagination
    $countResult = $conn->query($countPreviewQuery);
    $total_budget_preview_records = $countResult->fetch_assoc()['total'];
}

$budget_preview_records = [];
if ($budgetPreviewResult && $budgetPreviewResult->num_rows > 0) {
    while ($row = $budgetPreviewResult->fetch_assoc()) {
        $budget_preview_records[] = $row;
    }
}

// Calculate total pages for budget_preview
$total_budget_preview_pages = ceil($total_budget_preview_records / $records_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management | Consortium Hub</title>
    <?php 
    // Double-check that the user is admin even in the HTML part
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: login.php");
        exit();
    }
    include 'header.php'; 
    ?>
 
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
        }
        
        .form-input, .form-select {
            transition: all 0.3s;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            width: 100%;
            font-size: 0.95rem;
            background: #ffffff;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            background: #fefefe;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(107, 114, 128, 0.2);
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(107, 114, 128, 0.3);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);
        }
        
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.3);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.3);
        }
        
        .table-container {
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow-x: auto;
            width: 100%;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #334155;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover td {
            background-color: #f1f5f9;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .badge-certified {
            background-color: #dcfce7;
            color: #15803d;
        }
        
        .badge-uncertified {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        
        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: #64748b;
        }
        
        .tab.active {
            color: #2563eb;
            border-bottom: 3px solid #2563eb;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .filter-section {
            background: #f1f5f9;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-title {
            font-weight: 600;
            color: #334155;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .filter-title i {
            margin-right: 0.5rem;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-item {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #475569;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.25);
        }
        
        .stats-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }
        
        .stats-value {
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background: #e2e8f0;
            color: #334155;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #cbd5e1;
        }
        
        .pagination .active {
            background: #2563eb;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-button {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .edit-button {
            background-color: #f59e0b;
            color: white;
        }
        
        .edit-button:hover {
            background-color: #d97706;
        }
        
        .delete-button {
            background-color: #dc2626;
            color: white;
        }
        
        .delete-button:hover {
            background-color: #b91c1c;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }
        
        .btn-accept {
            background: #94a3b8;
            color: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-accept.accepted {
            background: #10b981;
        }
        
        .btn-accept:hover {
            background: #64748b;
        }
        
        .btn-reject {
            background: #94a3b8;
            color: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-reject.not-accepted {
            background: #dc2626;
        }
        
        .btn-reject:hover {
            background: #64748b;
        }
        
        .opacity-50 {
            opacity: 0.5;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            margin: 0;
            padding: 25px;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
        }
        
        .modal-close:hover {
            color: #64748b;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .grid {
            display: grid;
            gap: 1rem;
        }
        
        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        
        @media (min-width: 768px) {
            .grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        
        .bg-gray-100 {
            background-color: #f1f5f9;
        }
    </style>
</head>
<body>
 
    
    <div class="main-content-flex flex-1 flex-col">
        <?php include 'message_system.php'; ?>
        
        <div class="flex-1 py-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto px-4">
                <!-- Header -->
                <div class="admin-card p-6 mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">Budget Management</h1>
                            <p class="text-gray-600 mt-2">Manage budget data and preview records with cluster and date filtering</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                <p class="text-gray-500 text-sm">Administrator</p>
                            </div>
                            <a href="logout.php" class="btn-secondary flex items-center gap-2">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i> Filter Records
                    </div>
                    <form method="GET" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-item">
                                <label class="filter-label" for="cluster">
                                    <i class="fas fa-building mr-2"></i>Cluster
                                </label>
                                <select id="cluster" name="cluster" class="form-select">
                                    <option value="">All Clusters</option>
                                    <?php foreach ($clusters as $cluster): ?>
                                        <option value="<?php echo htmlspecialchars($cluster['cluster_name']); ?>" <?php echo ($selected_cluster === $cluster['cluster_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-item">
                                <label class="filter-label" for="start_date">
                                    <i class="fas fa-calendar-alt mr-2"></i>Start Date
                                </label>
                                <input 
                                    type="date" 
                                    id="start_date" 
                                    name="start_date" 
                                    class="form-input" 
                                    value="<?php echo htmlspecialchars($start_date); ?>"
                                >
                            </div>
                            
                            <div class="filter-item">
                                <label class="filter-label" for="end_date">
                                    <i class="fas fa-calendar-alt mr-2"></i>End Date
                                </label>
                                <input 
                                    type="date" 
                                    id="end_date" 
                                    name="end_date" 
                                    class="form-input" 
                                    value="<?php echo htmlspecialchars($end_date); ?>"
                                >
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-2">
                            <button type="submit" class="btn-primary flex items-center gap-2">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="admin_budget_management.php" class="btn-secondary flex items-center gap-2">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-title">Total Budget Data Records</div>
                        <div class="stats-value"><?php echo $total_budget_data_records; ?></div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-title">Total Preview Records</div>
                        <div class="stats-value"><?php echo $total_budget_preview_records; ?></div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-title">Active Clusters</div>
                        <div class="stats-value"><?php echo count($clusters); ?></div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="admin-card p-6">
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('budget-data', this)">Budget Data</div>
                        <div class="tab" onclick="switchTab('budget-preview', this)">Budget Preview</div>
                    </div>
                    
                    <!-- Budget Data Tab -->
                    <div id="budget-data-tab" class="tab-content active">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">Budget Data Records</h2>
                            <div class="flex gap-2">
                                <button class="btn-primary flex items-center gap-2" onclick="openAddBudgetDataModal()">
                                    <i class="fas fa-plus"></i> Add Budget Data
                                </button>
                                <button class="btn-success flex items-center gap-2" onclick="openImportBudgetDataModal()">
                                    <i class="fas fa-file-excel"></i> Import from Excel
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Year</th>
                                        <th>Category</th>
                                        <th>Period</th>
                                        <th>Budget</th>
                                        <th>Actual</th>
                                        <th>Forecast</th>
                                        <th>Actual + Forecast</th>
                                        <th>Variance %</th>
                                        <th>Quarter</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Certified</th>
                                        <th>Cluster</th>
                                        <th>Current Year</th>
                                        <th>Currency</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($budget_data_records)): ?>
                                        <tr>
                                            <td colspan="12" class="text-center text-gray-500 py-8">
                                                <i class="fas fa-database fa-2x mb-2"></i>
                                                <p>No budget data records found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($budget_data_records as $record): ?>
                                            <tr>
                                                <td class="font-medium"><?php echo htmlspecialchars($record['id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['year']); ?></td>
                                                <td><?php echo htmlspecialchars($record['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['period_name']); ?></td>
                                                <td><?php echo number_format($record['budget'] ?? 0, 2); ?></td>
                                                <td><?php echo number_format($record['actual'] ?? 0, 2); ?></td>
                                                <td><?php echo number_format($record['forecast'] ?? 0, 2); ?></td>
                                                <td><?php echo number_format($record['actual_plus_forecast'] ?? 0, 2); ?></td>
                                                <td><?php echo number_format($record['variance_percentage'] ?? 0, 2); ?>%</td>
                                                <td><?php echo htmlspecialchars($record['quarter_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo $record['start_date'] ? date('M j, Y', strtotime($record['start_date'])) : 'N/A'; ?></td>
                                                <td><?php echo $record['end_date'] ? date('M j, Y', strtotime($record['end_date'])) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="badge <?php echo ($record['certified'] === 'certified') ? 'badge-certified' : 'badge-uncertified'; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($record['certified'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['cluster'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php 
                                                    if (isset($record['year2']) && $record['year2'] == date('Y')) {
                                                        echo 'Current Year';
                                                    } else {
                                                        echo htmlspecialchars($record['year2'] ?? 'N/A');
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['currency'] ?? 'ETB'); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="action-button edit-button" onclick="editBudgetData(<?php echo $record['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                            <span>Edit</span>
                                                        </button>
                                                        <button class="action-button delete-button" onclick="deleteBudgetDataRecord(<?php echo $record['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Budget Data -->
                        <?php if ($total_budget_data_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $total_budget_data_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&cluster=<?php echo urlencode($selected_cluster); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
                                       class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Budget Preview Tab -->
                    <div id="budget-preview-tab" class="tab-content">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">Budget Preview Records</h2>
                        </div>
                        
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Acceptance</th>
                                        <th>Cluster</th>
                                        <th>ID</th>
                                        <th>Budget Heading</th>
                                        <th>Outcome</th>
                                        <th>Activity</th>
                                        <th>Budget Line</th>
                                        <th>Description</th>
                                        <th>Partner</th>
                                        <th>Entry Date</th>
                                        <th>Amount</th>
                                        <th>PV Number</th>
                                        <th>Quarter</th>
                                        <th>Category</th>
                                        <th>Created</th>
                                        <th>Updated</th>
                                        <th>Budget ID</th>
                                        <th>Forecast</th>
                                        <th>Comments</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($budget_preview_records)): ?>
                                        <tr>
                                            <td colspan="20" class="text-center text-gray-500 py-8">
                                                <i class="fas fa-file-invoice fa-2x mb-2"></i>
                                                <p>No budget preview records found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($budget_preview_records as $record): ?>
                                            <tr data-preview-id="<?php echo $record['PreviewID']; ?>">
                                                <td>
                                                    <?php 
                                                    $accepted = isset($record['ACCEPTANCE']) ? $record['ACCEPTANCE'] : 0;
                                                    $comment = isset($record['COMMENTS']) ? $record['COMMENTS'] : '';
                                                    ?>
                                                    <div class="flex gap-2">
                                                        <button class="btn-accept text-xs px-2 py-1 rounded <?php echo ($accepted == 1) ? 'accepted' : ''; ?>" 
                                                                onclick="setAcceptance(<?php echo $record['PreviewID']; ?>, 1)">
                                                            Accepted
                                                        </button>
                                                        <button class="btn-reject text-xs px-2 py-1 rounded <?php echo ($accepted == 0) ? 'not-accepted' : ''; ?>" 
                                                                onclick="openCommentModal(<?php echo $record['PreviewID']; ?>)">
                                                            Not Accepted
                                                        </button>
                                                    </div>
                                                    <?php if ($accepted == 0 && !empty($comment)): ?>
                                                        <div class="text-xs text-gray-500 mt-1">Comment: <?php echo htmlspecialchars($comment); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['cluster'] ?? 'N/A'); ?></td>
                                                <td class="font-medium"><?php echo htmlspecialchars($record['PreviewID']); ?></td>
                                                <td><?php echo htmlspecialchars($record['BudgetHeading'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['Outcome'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['Activity'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['BudgetLine'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['Description'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['Partner'] ?? 'N/A'); ?></td>
                                                <td><?php echo $record['EntryDate'] ? date('M j, Y', strtotime($record['EntryDate'])) : 'N/A'; ?></td>
                                                <td><?php echo number_format($record['Amount'] ?? 0, 2); ?></td>
                                                <td><?php echo htmlspecialchars($record['PVNumber'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['QuarterPeriod'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['CategoryName'] ?? 'N/A'); ?></td>
                                                <td><?php echo $record['CreatedAt'] ? date('M j, Y', strtotime($record['CreatedAt'])) : 'N/A'; ?></td>
                                                <td><?php echo $record['UpdatedAt'] ? date('M j, Y', strtotime($record['UpdatedAt'])) : 'N/A'; ?></td>
                                                <td><?php echo htmlspecialchars($record['budget_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo number_format($record['ForecastAmount'] ?? 0, 2); ?></td>
                                                <td><?php echo htmlspecialchars($record['COMMENTS'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="action-button edit-button" onclick="editBudgetPreview(<?php echo $record['PreviewID']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                            <span>Edit</span>
                                                        </button>
                                                        <button class="action-button delete-button" onclick="deleteBudgetPreview(<?php echo $record['PreviewID']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Budget Preview -->
                        <?php if ($total_budget_preview_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $total_budget_preview_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&cluster=<?php echo urlencode($selected_cluster); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
                                       class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View/Edit Budget Data Modal -->
    <div id="viewEditBudgetDataModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">View Budget Data</h3>
                <button class="modal-close" onclick="closeViewEditBudgetDataModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin_fields_handler.php">
                    <input type="hidden" name="action" value="edit_budget_data" id="modal_action">
                    <input type="hidden" id="view_edit_id" name="id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Required Fields -->
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_year">
                                <i class="fas fa-calendar mr-2"></i>Year *
                            </label>
                            <input 
                                type="number" 
                                id="view_edit_year" 
                                name="year" 
                                class="form-input" 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_category_name">
                                <i class="fas fa-tag mr-2"></i>Category Name *
                            </label>
                            <input 
                                type="text" 
                                id="view_edit_category_name" 
                                name="category_name" 
                                class="form-input" 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_period_name">
                                <i class="fas fa-clock mr-2"></i>Period Name *
                            </label>
                            <input 
                                type="text" 
                                id="view_edit_period_name" 
                                name="period_name" 
                                class="form-input" 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_budget">
                                <i class="fas fa-dollar-sign mr-2"></i>Budget *
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="view_edit_budget" 
                                name="budget" 
                                class="form-input"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_quarter_number">
                                <i class="fas fa-calendar-alt mr-2"></i>Quarter *
                            </label>
                            <select id="view_edit_quarter_number" name="quarter_number" class="form-select" required>
                                <option value="">Select Quarter</option>
                                <option value="1">Q1</option>
                                <option value="2">Q2</option>
                                <option value="3">Q3</option>
                                <option value="4">Q4</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_start_date">
                                <i class="fas fa-calendar-alt mr-2"></i>Start Date *
                            </label>
                            <input 
                                type="date" 
                                id="view_edit_start_date" 
                                name="start_date" 
                                class="form-input"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_end_date">
                                <i class="fas fa-calendar-alt mr-2"></i>End Date *
                            </label>
                            <input 
                                type="date" 
                                id="view_edit_end_date" 
                                name="end_date" 
                                class="form-input"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_cluster_select">
                                <i class="fas fa-building mr-2"></i>Cluster *
                            </label>
                            <select id="view_edit_cluster_select" name="cluster" class="form-select" required>
                                <option value="">Select Cluster</option>
                                <?php foreach ($clusters as $cluster): ?>
                                    <option value="<?php echo htmlspecialchars($cluster['cluster_name']); ?>">
                                        <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_year2">
                                <i class="fas fa-calendar mr-2"></i>Current Year *
                            </label>
                            <input 
                                type="number" 
                                id="view_edit_year2" 
                                name="year2" 
                                class="form-input" 
                                value="<?php echo date('Y'); ?>"
                                required
                            >
                        </div>
                        
                        <!-- View-only Fields -->
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-key mr-2"></i>ID
                            </label>
                            <input 
                                type="text" 
                                id="view_id" 
                                class="form-input bg-gray-100"
                                readonly
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-chart-line mr-2"></i>Actual
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="view_actual" 
                                name="actual" 
                                class="form-input bg-gray-100"
                                
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-chart-line mr-2"></i>Forecast
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="view_forecast" 
                                name="forecast" 
                                class="form-input bg-gray-100"
                                
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-plus mr-2"></i>Actual + Forecast
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="view_actual_plus_forecast" 
                                name="actual_plus_forecast" 
                                class="form-input bg-gray-100"
                                
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-percentage mr-2"></i>Variance %
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="view_variance_percentage" 
                                name="variance_percentage" 
                                class="form-input bg-gray-100"
                                
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-certificate mr-2"></i>Certified
                            </label>
                            <input 
                                type="text" 
                                id="view_certified" 
                                class="form-input bg-gray-100"
                                readonly
                            >
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeViewEditBudgetDataModal()">Close</button>
                        <button type="button" class="btn-warning" onclick="enableEditing()" id="edit_button">Edit</button>
                        <button type="submit" class="btn-primary" id="save_button" style="display: none;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Budget Data Modal -->
    <div id="addBudgetDataModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Budget Data</h3>
                <button class="modal-close" onclick="closeAddBudgetDataModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin_fields_handler.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_budget_data">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="year">
                                <i class="fas fa-calendar mr-2"></i>Year *
                            </label>
                            <input 
                                type="number" 
                                id="year" 
                                name="year" 
                                class="form-input" 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="category_name">
                                <i class="fas fa-tag mr-2"></i>Category Name *
                            </label>
                            <input 
                                type="text" 
                                id="category_name" 
                                name="category_name" 
                                class="form-input" 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="period_name">
                                <i class="fas fa-clock mr-2"></i>Period Name *
                            </label>
                            <input 
                                type="text" 
                                id="period_name" 
                                name="period_name" 
                                class="form-input" 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="budget">
                                <i class="fas fa-dollar-sign mr-2"></i>Budget *
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="budget" 
                                name="budget" 
                                class="form-input"
                                required
                            >
                        </div>

                        <!-- New: Forecast input on Add Budget Data -->
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="forecast_add">
                                <i class="fas fa-chart-line mr-2"></i>Forecast
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="forecast_add" 
                                name="forecast" 
                                class="form-input"
                                placeholder="Optional"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="quarter_number_add">
                                <i class="fas fa-calendar-alt mr-2"></i>Quarter *
                            </label>
                            <select id="quarter_number_add" name="quarter_number" class="form-select" required>
                                <option value="">Select Quarter</option>
                                <option value="1">Q1</option>
                                <option value="2">Q2</option>
                                <option value="3">Q3</option>
                                <option value="4">Q4</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="start_date_add">
                                <i class="fas fa-calendar-alt mr-2"></i>Start Date *
                            </label>
                            <input 
                                type="date" 
                                id="start_date_add" 
                                name="start_date" 
                                class="form-input"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="end_date_add">
                                <i class="fas fa-calendar-alt mr-2"></i>End Date *
                            </label>
                            <input 
                                type="date" 
                                id="end_date_add" 
                                name="end_date" 
                                class="form-input"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="cluster_select_add">
                                <i class="fas fa-building mr-2"></i>Cluster *
                            </label>
                            <select id="cluster_select_add" name="cluster" class="form-select" required>
                                <option value="">Select Cluster</option>
                                <?php foreach ($clusters as $cluster): ?>
                                    <option value="<?php echo htmlspecialchars($cluster['cluster_name']); ?>">
                                        <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_year2">
                                <i class="fas fa-calendar mr-2"></i>Current Year *
                            </label>
                            <input 
                                type="number" 
                                id="view_edit_year2" 
                                name="year2" 
                                class="form-input" 
                                value="<?php echo date('Y'); ?>"
                                required
                            >
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="view_edit_currency">
                                <i class="fas fa-money-bill-wave mr-2"></i>Currency *
                            </label>
                            <select id="view_edit_currency" name="currency" class="form-select" required>
                                <option value="ETB">ETB (Ethiopian Birr)</option>
                                <option value="USD">USD (US Dollar)</option>
                                <option value="EUR">EUR (Euro)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeAddBudgetDataModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Add Budget Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Import Budget Data from Excel Modal -->
    <div id="importBudgetDataModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Import Budget Data from Excel</h3>
                <button class="modal-close" onclick="closeImportBudgetDataModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin_fields_handler.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_budget_data_excel">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="excel_file">
                            <i class="fas fa-file-excel mr-2"></i>Excel File *
                        </label>
                        <input 
                            type="file" 
                            id="excel_file" 
                            name="excel_file" 
                            class="form-input" 
                            accept=".xlsx,.xls"
                            required
                        >
                        <p class="text-gray-500 text-sm mt-2">Upload an Excel file with budget data. The file should have columns: Year, Category Name, Period Name, Budget, Cluster, Quarter Number, Start Date, End Date, Current Year, Currency</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="import_cluster">
                            <i class="fas fa-building mr-2"></i>Cluster *
                        </label>
                        <select id="import_cluster" name="cluster" class="form-select" required>
                            <option value="">Select Cluster</option>
                            <?php foreach ($clusters as $cluster): ?>
                                <option value="<?php echo htmlspecialchars($cluster['cluster_name']); ?>">
                                    <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-gray-500 text-sm mt-2">Select the cluster for all imported records</p>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeImportBudgetDataModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Import Budget Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Budget Data Modal -->
   <!-- Edit Budget Data Modal -->
<!-- Edit Budget Data Modal -->
<div id="editBudgetDataModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Budget Data</h3>
            <button class="modal-close" onclick="closeEditBudgetDataModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="admin_fields_handler.php" onsubmit="event.preventDefault(); saveEditBudgetData();">
                <input type="hidden" name="action" value="edit_budget_data">
                <input type="hidden" id="edit_budget_data_id" name="id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Editable Fields (Same as Add Modal) -->
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_year">
                            <i class="fas fa-calendar mr-2"></i>Year *
                        </label>
                        <input type="number" id="edit_budget_data_year" name="year" class="form-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_category_name">
                            <i class="fas fa-tag mr-2"></i>Category Name *
                        </label>
                        <input type="text" id="edit_budget_data_category_name" name="category_name" class="form-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_period_name">
                            <i class="fas fa-clock mr-2"></i>Period Name *
                        </label>
                        <input type="text" id="edit_budget_data_period_name" name="period_name" class="form-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_budget">
                            <i class="fas fa-dollar-sign mr-2"></i>Budget *
                        </label>
                        <input type="number" step="0.01" id="edit_budget_data_budget" name="budget" class="form-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_quarter_number">
                            <i class="fas fa-calendar-alt mr-2"></i>Quarter *
                        </label>
                        <select id="edit_budget_data_quarter_number" name="quarter_number" class="form-select" required>
                            <option value="">Select Quarter</option>
                            <option value="1">Q1</option>
                            <option value="2">Q2</option>
                            <option value="3">Q3</option>
                            <option value="4">Q4</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_start_date">
                            <i class="fas fa-calendar-alt mr-2"></i>Start Date *
                        </label>
                        <input type="date" id="edit_budget_data_start_date" name="start_date" class="form-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_end_date">
                            <i class="fas fa-calendar-alt mr-2"></i>End Date *
                        </label>
                        <input type="date" id="edit_budget_data_end_date" name="end_date" class="form-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_cluster_select">
                            <i class="fas fa-building mr-2"></i>Cluster *
                        </label>
                        <select id="edit_budget_data_cluster_select" name="cluster" class="form-select" required>
                            <option value="">Select Cluster</option>
                            <?php foreach ($clusters as $cluster): ?>
                                <option value="<?php echo htmlspecialchars($cluster['cluster_name']); ?>">
                                    <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_year2">
                            <i class="fas fa-calendar mr-2"></i>Current Year *
                        </label>
                        <input type="number" id="edit_budget_data_year2" name="year2" class="form-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_currency">
                            <i class="fas fa-money-bill-wave mr-2"></i>Currency *
                        </label>
                        <select id="edit_budget_data_currency" name="currency" class="form-select" required>
                            <option value="ETB">ETB (Ethiopian Birr)</option>
                            <option value="USD">USD (US Dollar)</option>
                            <option value="EUR">EUR (Euro)</option>
                        </select>
                    </div>

                    <!-- View/Computed Fields -->
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-key mr-2"></i>ID
                        </label>
                        <input type="text" id="edit_budget_data_view_id" class="form-input bg-gray-100" readonly>
                    </div>
                    <!-- New: Make Actual and Forecast editable in Edit modal -->
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_view_actual">
                            <i class="fas fa-chart-line mr-2"></i>Actual
                        </label>
                        <input type="number" step="0.01" id="edit_budget_data_view_actual" name="actual" class="form-input">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="edit_budget_data_view_forecast">
                            <i class="fas fa-chart-line mr-2"></i>Forecast
                        </label>
                        <input type="number" step="0.01" id="edit_budget_data_view_forecast" name="forecast" class="form-input">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-plus mr-2"></i>Actual + Forecast
                        </label>
                        <input type="number" step="0.01" id="edit_budget_data_view_actual_plus_forecast" class="form-input bg-gray-100" >
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-percentage mr-2"></i>Variance %
                        </label>
                        <input type="number" step="0.01" id="edit_budget_data_view_variance_percentage" class="form-input bg-gray-100" >
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-certificate mr-2"></i>Certified
                        </label>
                        <input type="text" id="edit_budget_data_view_certified" class="form-input bg-gray-100" readonly>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditBudgetDataModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Update Budget Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

        </div>
    </div>
</div>
    
    <!-- Add Budget Preview Modal -->
    <div id="addBudgetPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Budget Preview</h3>
                <button class="modal-close" onclick="closeAddBudgetPreviewModal()">&times;</button>
            </div>
            <div class="modal-body">
              <form method="POST" action="admin_fields_handler.php" onsubmit="event.preventDefault(); saveEditBudgetData();">
                   <input type="hidden" name="action" value="edit_budget_data">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="budget_heading">
                            <i class="fas fa-heading mr-2"></i>Budget Heading
                        </label>
                        <?php
                        // Check field configuration for Budget Heading
                        $budget_heading_config = null;
                        $cluster_name = $_SESSION['cluster_name'] ?? '';
                        
                        if (!empty($cluster_name)) {
                            $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("s", $cluster_name);
                        } else {
                            $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name IS NULL";
                            $stmt = $conn->prepare($query);
                        }
                        
                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                $budget_heading_config = $result->fetch_assoc();
                            }
                        }
                        
                        if ($budget_heading_config && $budget_heading_config['field_type'] === 'dropdown' && !empty($budget_heading_config['field_values'])) {
                            // Render as dropdown
                            $values = explode(',', $budget_heading_config['field_values']);
                            echo '<select id="budget_heading" name="budget_heading" class="form-select" required>';
                            echo '<option value="">Select Budget Heading</option>';
                            foreach ($values as $value) {
                                echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</option>';
                            }
                            echo '</select>';
                        } else {
                            // Render as input field
                            echo '<input type="text" id="budget_heading" name="budget_heading" class="form-input" required>';
                        }
                        ?>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="category_name_preview">
                            <i class="fas fa-tag mr-2"></i>Category Name
                        </label>
                        <input 
                            type="text" 
                            id="category_name_preview" 
                            name="category_name" 
                            class="form-input" 
                            required
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="activity">
                            <i class="fas fa-tasks mr-2"></i>Activity
                        </label>
                        <input 
                            type="text" 
                            id="activity" 
                            name="activity" 
                            class="form-input"
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="amount">
                            <i class="fas fa-dollar-sign mr-2"></i>Amount
                        </label>
                        <input 
                            type="number" 
                            id="amount" 
                            name="amount" 
                            class="form-input" 
                            required
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="description">
                            <i class="fas fa-align-left mr-2"></i>Description
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="form-input" 
                            rows="4"
                        ></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="budget_month">
                            <i class="fas fa-calendar-alt mr-2"></i>Budget Month
                        </label>
                        <input 
                            type="month" 
                            id="budget_month" 
                            name="budget_month" 
                            class="form-input" 
                            required
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Budget Preview</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Budget Preview Modal -->
    <div id="editBudgetPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Budget Preview</h3>
                <button class="modal-close" onclick="closeEditBudgetPreviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin_fields_handler.php">
                    <input type="hidden" name="action" value="edit_budget_preview">
                    <input type="hidden" id="edit_budget_id" name="budget_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_budget_heading">
                                <i class="fas fa-heading mr-2"></i>Budget Heading
                            </label>
                            <?php
                            // Check field configuration for Budget Heading in edit modal
                            $budget_heading_config = null;
                            $cluster_name = $_SESSION['cluster_name'] ?? '';
                            
                            if (!empty($cluster_name)) {
                                $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("s", $cluster_name);
                            } else {
                                $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name IS NULL";
                                $stmt = $conn->prepare($query);
                            }
                            
                            if ($stmt->execute()) {
                                $result = $stmt->get_result();
                                if ($result->num_rows > 0) {
                                    $budget_heading_config = $result->fetch_assoc();
                                }
                            }
                            
                            if ($budget_heading_config && $budget_heading_config['field_type'] === 'dropdown' && !empty($budget_heading_config['field_values'])) {
                                // Render as dropdown
                                $values = explode(',', $budget_heading_config['field_values']);
                                echo '<select id="edit_budget_heading" name="budget_heading" class="form-select">';
                                echo '<option value="">Select Budget Heading</option>';
                                foreach ($values as $value) {
                                    echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</option>';
                                }
                                echo '</select>';
                            } else {
                                // Render as input field
                                echo '<input type="text" id="edit_budget_heading" name="budget_heading" class="form-input">';
                            }
                            ?>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_category_name">
                                <i class="fas fa-tag mr-2"></i>Category Name
                            </label>
                            <input 
                                type="text" 
                                id="edit_category_name" 
                                name="category_name" 
                                class="form-input"
                                required
                            >
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_activity">
                                <i class="fas fa-tasks mr-2"></i>Activity
                            </label>
                            <input 
                                type="text" 
                                id="edit_activity" 
                                name="activity" 
                                class="form-input"
                            >
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_amount">
                                <i class="fas fa-dollar-sign mr-2"></i>Amount
                            </label>
                            <input 
                                type="number" 
                                id="edit_amount" 
                                name="amount" 
                                class="form-input" 
                                required
                            >
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_description">
                                <i class="fas fa-align-left mr-2"></i>Description
                            </label>
                            <textarea 
                                id="edit_description" 
                                name="description" 
                                class="form-input" 
                                rows="4"
                            ></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_budget_month">
                                <i class="fas fa-calendar-alt mr-2"></i>Budget Month
                            </label>
                            <input 
                                type="month" 
                                id="edit_budget_month" 
                                name="budget_month" 
                                class="form-input" 
                                required
                            >
                        </div>
                        <label class="block text-gray-700 font-medium mb-2" for="partner">
                            <i class="fas fa-handshake mr-2"></i>Partner
                        </label>
                        <input 
                            type="text" 
                            id="partner" 
                            name="partner" 
                            class="form-input"
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="amount">
                            <i class="fas fa-dollar-sign mr-2"></i>Amount (EUR)
                        </label>
                        <input 
                            type="number" 
                            step="0.01"
                            id="amount" 
                            name="amount" 
                            class="form-input"
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="entry_date">
                            <i class="fas fa-calendar-alt mr-2"></i>Entry Date
                        </label>
                        <input 
                            type="date" 
                            id="entry_date" 
                            name="entry_date" 
                            class="form-input"
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="cluster_preview">
                            <i class="fas fa-building mr-2"></i>Cluster
                        </label>
                        <select id="cluster_preview" name="cluster" class="form-select">
                            <option value="">Select Cluster</option>
                            <?php foreach ($clusters as $cluster): ?>
                                <option value="<?php echo htmlspecialchars($cluster['cluster_name']); ?>">
                                    <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeAddBudgetPreviewModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Add Preview Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Budget Preview Modal -->
    <div id="editBudgetPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Budget Preview</h3>
                <button class="modal-close" onclick="closeEditBudgetPreviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin_fields_handler.php">
                    <input type="hidden" name="action" value="edit_budget_preview">
                    <input type="hidden" id="edit_preview_id" name="preview_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_budget_heading">
                                <i class="fas fa-heading mr-2"></i>Budget Heading
                            </label>
                            <input 
                                type="text" 
                                id="edit_budget_heading" 
                                name="budget_heading" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_outcome">
                                <i class="fas fa-bullseye mr-2"></i>Outcome
                            </label>
                            <input 
                                type="text" 
                                id="edit_outcome" 
                                name="outcome" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_activity">
                                <i class="fas fa-tasks mr-2"></i>Activity
                            </label>
                            <input 
                                type="text" 
                                id="edit_activity" 
                                name="activity" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_budget_line">
                                <i class="fas fa-list mr-2"></i>Budget Line
                            </label>
                            <input 
                                type="text" 
                                id="edit_budget_line" 
                                name="budget_line" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_description">
                                <i class="fas fa-align-left mr-2"></i>Description
                            </label>
                            <textarea 
                                id="edit_description" 
                                name="description" 
                                class="form-input"
                                rows="3"
                            ></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_partner">
                                <i class="fas fa-handshake mr-2"></i>Partner
                            </label>
                            <input 
                                type="text" 
                                id="edit_partner" 
                                name="partner" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_amount">
                                <i class="fas fa-dollar-sign mr-2"></i>Amount (EUR)
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="edit_amount" 
                                name="amount" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_pv_number">
                                <i class="fas fa-file-invoice mr-2"></i>PV Number
                            </label>
                            <input 
                                type="text" 
                                id="edit_pv_number" 
                                name="pv_number" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_entry_date">
                                <i class="fas fa-calendar-alt mr-2"></i>Entry Date
                            </label>
                            <input 
                                type="date" 
                                id="edit_entry_date" 
                                name="entry_date" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_quarter_period">
                                <i class="fas fa-calendar mr-2"></i>Quarter Period
                            </label>
                            <select id="edit_quarter_period" name="quarter_period" class="form-select">
                                <option value="">Select Quarter</option>
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_category_name">
                                <i class="fas fa-tag mr-2"></i>Category Name
                            </label>
                            <input 
                                type="text" 
                                id="edit_category_name" 
                                name="category_name" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_original_budget">
                                <i class="fas fa-wallet mr-2"></i>Original Budget
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="edit_original_budget" 
                                name="original_budget" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_remaining_budget">
                                <i class="fas fa-coins mr-2"></i>Remaining Budget
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="edit_remaining_budget" 
                                name="remaining_budget" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_actual_spent">
                                <i class="fas fa-chart-line mr-2"></i>Actual Spent
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="edit_actual_spent" 
                                name="actual_spent" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_variance_percentage">
                                <i class="fas fa-percentage mr-2"></i>Variance Percentage
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="edit_variance_percentage" 
                                name="variance_percentage" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_cluster">
                                <i class="fas fa-building mr-2"></i>Cluster
                            </label>
                            <select id="edit_cluster" name="cluster" class="form-select">
                                <option value="">Select Cluster</option>
                                <?php foreach ($clusters as $cluster): ?>
                                    <option value="<?php echo htmlspecialchars($cluster['cluster_name']); ?>">
                                        <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_budget_id">
                                <i class="fas fa-key mr-2"></i>Budget ID
                            </label>
                            <input 
                                type="number" 
                                id="edit_budget_id" 
                                name="budget_id" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_forecast_amount">
                                <i class="fas fa-chart-bar mr-2"></i>Forecast Amount
                            </label>
                            <input 
                                type="number" 
                                step="0.01"
                                id="edit_forecast_amount" 
                                name="forecast_amount" 
                                class="form-input"
                            >
                        </div>
                        
                        <div class="mb-4 md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2" for="edit_comments">
                                <i class="fas fa-comment mr-2"></i>Comments
                            </label>
                            <textarea 
                                id="edit_comments" 
                                name="comments" 
                                class="form-input"
                                rows="3"
                            ></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeEditBudgetPreviewModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Update Preview Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Acceptance Comment Modal -->
    <div id="commentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Not Acceptance Reason</h3>
                <button class="modal-close" onclick="closeCommentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="acceptanceForm">
                    <input type="hidden" id="comment_preview_id" name="preview_id">
                    <input type="hidden" id="acceptance_status" name="accepted" value="0">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2" for="acceptance_comment">
                            <i class="fas fa-comment mr-2"></i>Please provide a reason for not accepting this record
                        </label>
                        <textarea 
                            id="acceptance_comment" 
                            name="comment" 
                            class="form-input"
                            rows="4"
                            placeholder="Enter your reason here..."
                            required
                        ></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeCommentModal()">Cancel</button>
                        <button type="submit" class="btn-danger">Mark as Not Accepted</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching
        function switchTab(tabName, element) {
            try {
                console.log('Switching to tab:', tabName);
                console.log('Clicked element:', element);
                
                // Hide all tab contents
                const tabContents = document.querySelectorAll('.tab-content');
                console.log('Found tab contents:', tabContents.length);
                tabContents.forEach(tab => {
                    console.log('Hiding tab:', tab.id);
                    tab.classList.remove('active');
                });
                
                // Remove active class from all tabs
                const tabs = document.querySelectorAll('.tab');
                console.log('Found tabs:', tabs.length);
                tabs.forEach(tab => {
                    console.log('Removing active from tab:', tab);
                    tab.classList.remove('active');
                });
                
                // Show selected tab content
                const targetTabId = tabName + '-tab';
                console.log('Target tab ID:', targetTabId);
                const targetTab = document.getElementById(targetTabId);
                console.log('Target tab element:', targetTab);
                if (targetTab) {
                    targetTab.classList.add('active');
                    console.log('Activated tab content:', targetTabId);
                } else {
                    console.error('Could not find tab content with ID:', targetTabId);
                }
                
                // Add active class to clicked tab
                if (element) {
                    element.classList.add('active');
                    console.log('Activated tab button:', element);
                } else {
                    console.error('No element provided to activate');
                }
                
                console.log('Tab switching completed successfully');
            } catch (error) {
                console.error('Error in switchTab function:', error);
            }
        }
        
        // Modal functions for Budget Data
        function openAddBudgetDataModal() {
            document.getElementById('addBudgetDataModal').style.display = 'flex';
            // Set default value for year2 to current year
            document.getElementById('year2_add').value = new Date().getFullYear();
        }
        
        function closeAddBudgetDataModal() {
            document.getElementById('addBudgetDataModal').style.display = 'none';
        }
        
        // Modal functions for Excel Import
        function openImportBudgetDataModal() {
            document.getElementById('importBudgetDataModal').style.display = 'flex';
        }
        
        function closeImportBudgetDataModal() {
            document.getElementById('importBudgetDataModal').style.display = 'none';
        }
        
        function editBudgetData(id) {
            console.log('Editing budget data with ID:', id);
            
            // Check if modal elements exist
            const modal = document.getElementById('editBudgetDataModal');
            console.log('Edit modal element:', modal);
            
            if (!modal) {
                console.error('Edit modal not found!');
                alert('Edit modal not found!');
                return;
            }
            
            // Fetch the record data and populate the edit modal
            fetch('admin_fields_handler.php?action=get_budget_data&id=' + id)
                .then(response => {
                    console.log('Response received:', response);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data.success) {
                        // Populate the form fields with existing data
                        console.log('Record data:', data.record);
                        
                        // Set values using the new unique IDs
                        document.getElementById('edit_budget_data_id').value = data.record.id;
                        document.getElementById('edit_budget_data_view_id').value = data.record.id;
                        document.getElementById('edit_budget_data_year').value = data.record.year;
                        document.getElementById('edit_budget_data_category_name').value = data.record.category_name;
                        document.getElementById('edit_budget_data_period_name').value = data.record.period_name;
                        document.getElementById('edit_budget_data_budget').value = data.record.budget;
                        document.getElementById('edit_budget_data_quarter_number').value = data.record.quarter_number;
                        // Ensure dates are in the correct format for date inputs (YYYY-MM-DD)
                        document.getElementById('edit_budget_data_start_date').value = data.record.start_date ? data.record.start_date.split(' ')[0] : '';
                        document.getElementById('edit_budget_data_end_date').value = data.record.end_date ? data.record.end_date.split(' ')[0] : '';
                        document.getElementById('edit_budget_data_cluster_select').value = data.record.cluster;
                        document.getElementById('edit_budget_data_year2').value = data.record.year2 || new Date().getFullYear();
                        document.getElementById('edit_budget_data_currency').value = data.record.currency || 'ETB';
                        document.getElementById('edit_budget_data_view_actual').value = data.record.actual || 0;
                        document.getElementById('edit_budget_data_view_forecast').value = data.record.forecast || 0;
                        document.getElementById('edit_budget_data_view_actual_plus_forecast').value = data.record.actual_plus_forecast || 0;
                        document.getElementById('edit_budget_data_view_variance_percentage').value = data.record.variance_percentage || 0;
                        document.getElementById('edit_budget_data_view_certified').value = data.record.certified || 'uncertified';
                       
                        
                        // Populate computed fields
                        document.getElementById('edit_budget_data_view_actual').value = data.record.actual || 0;
                        document.getElementById('edit_budget_data_view_forecast').value = data.record.forecast || 0;
                        document.getElementById('edit_budget_data_view_actual_plus_forecast').value = data.record.actual_plus_forecast || 0;
                        document.getElementById('edit_budget_data_view_variance_percentage').value = data.record.variance_percentage || 0;
                        document.getElementById('edit_budget_data_view_certified').value = data.record.certified || 'uncertified';
                        
                        // Show the edit modal
                        document.getElementById('editBudgetDataModal').style.display = 'flex';
                        console.log('Modal should now be visible');

                        // Hook up live recalculation for computed fields
                        const budgetInput = document.getElementById('edit_budget_data_budget');
                        const actualInput = document.getElementById('edit_budget_data_view_actual');
                        const forecastInput = document.getElementById('edit_budget_data_view_forecast');
                        const recalc = () => recalcEditComputedFields();
                        budgetInput.oninput = recalc;
                        actualInput.oninput = recalc;
                        forecastInput.oninput = recalc;
                        // Initial calc
                        recalcEditComputedFields();
                    } else {
                        console.error('Error fetching record:', data.message);
                        alert('Error fetching record: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching record:', error);
                    alert('Error fetching record: ' + error);
                });
        }

        // Compute Actual+Forecast and Variance% in Edit modal
        function recalcEditComputedFields() {
            const budget = parseFloat(document.getElementById('edit_budget_data_budget').value || '0');
            const actual = parseFloat(document.getElementById('edit_budget_data_view_actual').value || '0');
            const forecast = parseFloat(document.getElementById('edit_budget_data_view_forecast').value || '0');
            const apf = (isNaN(actual) ? 0 : actual) + (isNaN(forecast) ? 0 : forecast);
            document.getElementById('edit_budget_data_view_actual_plus_forecast').value = apf.toFixed(2);
            // Modified variance calculation: Variance (%) = (Budget − Actual) / Budget × 100
            let variance = -100.0;
            if (!isNaN(budget) && budget > 0) {
                variance = ((budget - actual) / budget) * 100.0;
            }
            document.getElementById('edit_budget_data_view_variance_percentage').value = variance.toFixed(2);
        }
        
        function closeEditBudgetDataModal() {
            document.getElementById('editBudgetDataModal').style.display = 'none';
        }
        
        function deleteBudgetData(id) {
            if (confirm('Are you sure you want to delete this budget data?')) {
                fetch('admin_fields_handler.php?action=delete_budget_data&id=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Budget data deleted successfully');
                            // Reload the budget data table or refresh the page
                            window.location.reload();
                        } else {
                            alert('Error deleting budget data: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error deleting budget data: ' + error);
                    });
            }
        }
        
        function saveAddBudgetData() {
            const form = document.getElementById('addBudgetDataForm');
            const formData = new FormData(form);
            
            fetch('admin_fields_handler.php?action=add_budget_data', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Budget data added successfully');
                        // Close the modal
                        closeAddBudgetDataModal();
                        // Reload the budget data table or refresh the page
                        window.location.reload();
                    } else {
                        alert('Error adding budget data: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error adding budget data: ' + error);
                });
        }
        
function saveEditBudgetData() {
    console.log("saveEditBudgetData function called"); // Debug log
    // Get the form element directly from the modal's content
    const modal = document.getElementById('editBudgetDataModal');
    const form = modal.querySelector('form'); // Find the form within the modal

    if (!form) {
        console.error('Edit Budget Data form not found within the modal!');
        showMessage('Error: Form not found.', 'error');
        return; // Stop execution if form isn't found
    }

    // Verify the action input exists and has the correct value
    const actionInput = form.querySelector('input[name="action"]');
    if (!actionInput || actionInput.value !== 'edit_budget_data') {
         console.error('Hidden action input not found or incorrect value. Found:', actionInput ? actionInput.value : 'null');
         showMessage('Error: Invalid form configuration.', 'error');
         return;
    }

    const formData = new FormData(form);

    // Optional: Log formData to check if all values are included correctly
    // console.log('FormData being sent:');
    // for (let pair of formData.entries()) {
    //    console.log(pair[0] + ': ' + pair[1]);
    // }

    // Use fetch to submit the data without page reload
    fetch('admin_fields_handler.php', { // Ensure the path is correct
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log("Fetch response received:", response); // Debug log
        if (!response.ok) {
            // Handle HTTP errors (e.g., 404, 500)
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json(); // Expect JSON response
    })
    .then(data => {
         console.log("Parsed JSON response:", data); // Debug log
        if (data.success) {
            // Show success message in a more user-friendly way
            showMessage('Budget data updated successfully', 'success');
            closeEditBudgetDataModal(); // Close the modal
            // Instead of reloading the entire page, just update the table
            // This will keep the user on the same tab and position
            updateBudgetDataTable();
        } else {
             // Handle application errors returned by admin_fields_handler.php
            showMessage('Error updating budget data: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error during fetch operation:', error);
        showMessage('Error updating budget data: ' + error.message, 'error');
    });
}

// Function to show messages to the user
function showMessage(message, type) {
    // Remove any existing message divs
    const existingMessages = document.querySelectorAll('.message-notification');
    existingMessages.forEach(msg => msg.remove());
    
    // Create message div
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-notification ${type}`;
    messageDiv.textContent = message;
    
    // Style the message
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.padding = '15px 20px';
    messageDiv.style.borderRadius = '5px';
    messageDiv.style.color = 'white';
    messageDiv.style.fontWeight = 'bold';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    messageDiv.style.maxWidth = '400px';
    
    if (type === 'success') {
        messageDiv.style.backgroundColor = '#10B981'; // green
    } else {
        messageDiv.style.backgroundColor = '#EF4444'; // red
    }
    
    // Add to document
    document.body.appendChild(messageDiv);
    
    // Remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.parentNode.removeChild(messageDiv);
        }
    }, 5000);
}

// Function to update the budget data table without page reload
function updateBudgetDataTable() {
    // In a real implementation, you would fetch the updated data and update the table
    // For now, we'll just show a message that the data was updated
    console.log("Budget data table would be updated here without page reload");
    
    // Simulate a delay to show the success message
    setTimeout(() => {
        console.log("Table update simulation complete");
    }, 1000);
}

        function openBudgetDataView(id) {
            // Fetch the record data and populate the view modal
            fetch('admin_fields_handler.php?action=get_budget_data&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the view fields with existing data
                        document.getElementById('view_id').value = data.record.id;
                        document.getElementById('view_year').value = data.record.year;
                        document.getElementById('view_category_name').value = data.record.category_name;
                        document.getElementById('view_period_name').value = data.record.period_name;
                        document.getElementById('view_budget').value = data.record.budget;
                        document.getElementById('view_quarter_number').value = data.record.quarter_number;
                        // Ensure dates are in the correct format for date inputs (YYYY-MM-DD)
                        document.getElementById('view_start_date').value = data.record.start_date ? data.record.start_date.split(' ')[0] : '';
                        document.getElementById('view_end_date').value = data.record.end_date ? data.record.end_date.split(' ')[0] : '';
                        document.getElementById('view_cluster').value = data.record.cluster;
                        document.getElementById('view_year2').value = data.record.year2 || new Date().getFullYear();
                        document.getElementById('view_currency').value = data.record.currency || 'ETB';

                        // Populate view-only fields
                        document.getElementById('view_actual').value = data.record.actual || 0;
                        document.getElementById('view_forecast').value = data.record.forecast || 0;
                        document.getElementById('view_actual_plus_forecast').value = data.record.actual_plus_forecast || 0;
                        document.getElementById('view_variance_percentage').value = data.record.variance_percentage || 0;
                        document.getElementById('view_certified').value = data.record.certified || 'uncertified';
                        
                        // Show the view modal
                        document.getElementById('viewBudgetDataModal').style.display = 'flex';
                    } else {
                        alert('Error fetching record: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error fetching record: ' + error);
                });
        }
        
        function closeBudgetDataView() {
            document.getElementById('viewBudgetDataModal').style.display = 'none';
        }
        
        function toggleClusterOptions(element) {
            const showClusters = element.value === 'clusters';
            document.getElementById('clusterSelectContainer').style.display = showClusters ? 'block' : 'none';
        }
        
        function openAddClusterModal() {
            document.getElementById('addClusterModal').style.display = 'flex';
        }
        
        function closeAddClusterModal() {
            document.getElementById('addClusterModal').style.display = 'none';
        }
        
        function saveAddCluster() {
            const form = document.getElementById('addClusterForm');
            const formData = new FormData(form);
            
            fetch('admin_fields_handler.php?action=add_cluster', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cluster added successfully');
                        // Close the modal
                        closeAddClusterModal();
                        // Reload the cluster options in the budget data form
                        loadClusterOptions();
                    } else {
                        alert('Error adding cluster: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error adding cluster: ' + error);
                });
        }
        
        function loadClusterOptions() {
            fetch('admin_fields_handler.php?action=get_clusters')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const clusterSelect = document.getElementById('edit_cluster_select');
                        clusterSelect.innerHTML = '';
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.textContent = 'Select a Cluster';
                        clusterSelect.appendChild(defaultOption);
                        
                        data.clusters.forEach(cluster => {
                            const option = document.createElement('option');
                            option.value = cluster.cluster;
                            option.textContent = cluster.cluster;
                            clusterSelect.appendChild(option);
                        });
                    } else {
                        console.error('Error fetching clusters:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching clusters:', error);
                });
        }
        
        function deleteBudgetDataRecord(id) {
            if (confirm('Are you sure you want to delete this budget data record?')) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_fields_handler.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_budget_data';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Modal functions for Budget Preview
        function openAddBudgetPreviewModal() {
            document.getElementById('addBudgetPreviewModal').style.display = 'flex';
        }
        
        function closeAddBudgetPreviewModal() {
            document.getElementById('addBudgetPreviewModal').style.display = 'none';
        }
        
        function editBudgetPreview(id) {
            // Fetch the record data and populate the edit modal
            fetch('admin_fields_handler.php?action=get_budget_preview&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the form fields with existing data
                        document.getElementById('edit_preview_id').value = data.record.PreviewID;
                        
                        // Handle budget heading field (could be select or input)
                        const budgetHeadingField = document.getElementById('edit_budget_heading');
                        if (budgetHeadingField) {
                            budgetHeadingField.value = data.record.BudgetHeading;
                        }
                        
                        document.getElementById('edit_outcome').value = data.record.Outcome;
                        document.getElementById('edit_activity').value = data.record.Activity;
                        document.getElementById('edit_budget_line').value = data.record.BudgetLine;
                        document.getElementById('edit_description').value = data.record.Description;
                        document.getElementById('edit_partner').value = data.record.Partner;
                        document.getElementById('edit_amount').value = data.record.Amount;
                        document.getElementById('edit_pv_number').value = data.record.PVNumber;
                        document.getElementById('edit_entry_date').value = data.record.EntryDate;
                        document.getElementById('edit_quarter_period').value = data.record.QuarterPeriod;
                        document.getElementById('edit_category_name').value = data.record.CategoryName;
                        document.getElementById('edit_original_budget').value = data.record.OriginalBudget;
                        document.getElementById('edit_remaining_budget').value = data.record.RemainingBudget;
                        document.getElementById('edit_actual_spent').value = data.record.ActualSpent;
                        document.getElementById('edit_variance_percentage').value = data.record.VariancePercentage;
                        document.getElementById('edit_cluster').value = data.record.cluster;
                        document.getElementById('edit_budget_id').value = data.record.budget_id;
                        document.getElementById('edit_forecast_amount').value = data.record.ForecastAmount;
                        document.getElementById('edit_comments').value = data.record.COMMENTS;
                        
                        // Show the edit modal
                        document.getElementById('editBudgetPreviewModal').style.display = 'flex';
                    } else {
                        alert('Error fetching record: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error fetching record: ' + error);
                });
        }
        
        function closeEditBudgetPreviewModal() {
            document.getElementById('editBudgetPreviewModal').style.display = 'none';
        }
        
        function deleteBudgetPreview(id) {
            if (confirm('Are you sure you want to delete this budget preview record?')) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_fields_handler.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_budget_preview';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'preview_id';
                idInput.value = id;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Acceptance functions
        function setAcceptance(previewId, accepted) {
            // Prevent default behavior that might cause navigation
            if (event) {
                event.preventDefault();
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'set_acceptance');
            formData.append('preview_id', previewId);
            formData.append('accepted', accepted);
            
            // Send AJAX request
            fetch('admin_fields_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the UI without reloading the page
                    updateAcceptanceUI(previewId, accepted);
                    // If setting to accepted, also clear any comment display
                    if (accepted == 1) {
                        updateCommentDisplay(previewId, '');
                    }
                } else {
                    alert('Error updating acceptance status: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating acceptance status: ' + error);
            });
        }
        
        function updateAcceptanceUI(previewId, accepted) {
            // Find the row with the matching preview ID
            const row = document.querySelector(`tr[data-preview-id="${previewId}"]`);
            
            if (row) {
                const acceptButton = row.querySelector('.btn-accept');
                const rejectButton = row.querySelector('.btn-reject');
                
                if (acceptButton && rejectButton) {
                    // Update button classes based on acceptance status
                    if (accepted == 1) {
                        acceptButton.classList.add('accepted');
                        rejectButton.classList.remove('not-accepted');
                        // Clear any existing comment display
                        const commentDisplay = row.querySelector('.text-xs.text-gray-500.mt-1');
                        if (commentDisplay) {
                            commentDisplay.remove();
                        }
                    } else {
                        acceptButton.classList.remove('accepted');
                        rejectButton.classList.add('not-accepted');
                    }
                }
            }
        }
        
        function updateCommentDisplay(previewId, comment) {
            // Find the row with the matching preview ID
            const row = document.querySelector(`tr[data-preview-id="${previewId}"]`);
            
            if (row) {
                // Remove any existing comment display
                const existingCommentDisplay = row.querySelector('.text-xs.text-gray-500.mt-1');
                if (existingCommentDisplay) {
                    existingCommentDisplay.remove();
                }
                
                // Add new comment display if comment is not empty
                if (comment && comment.trim() !== '') {
                    const acceptanceCell = row.querySelector('td:first-child');
                    if (acceptanceCell) {
                        const commentDisplay = document.createElement('div');
                        commentDisplay.className = 'text-xs text-gray-500 mt-1';
                        commentDisplay.textContent = 'Comment: ' + comment;
                        acceptanceCell.appendChild(commentDisplay);
                    }
                }
            }
        }
        
        function openCommentModal(previewId) {
            // Prevent default behavior that might cause navigation
            if (event) {
                event.preventDefault();
            }
            
            document.getElementById('comment_preview_id').value = previewId;
            document.getElementById('acceptance_comment').value = '';
            document.getElementById('commentModal').style.display = 'flex';
        }
        
        function closeCommentModal() {
            document.getElementById('commentModal').style.display = 'none';
        }
        
        // Handle acceptance form submission
        document.getElementById('acceptanceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            
            const formData = new FormData(this);
            const comment = document.getElementById('acceptance_comment').value;
            const previewId = document.getElementById('comment_preview_id').value;
            
            formData.append('action', 'set_acceptance');
            
            fetch('admin_fields_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI with the new acceptance status and comment
                    updateAcceptanceUI(previewId, 0); // 0 for not accepted
                    updateCommentDisplay(previewId, comment);
                    
                    closeCommentModal();
                    // Don't reload the page to prevent tab switching
                } else {
                    alert('Error updating acceptance status: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating acceptance status: ' + error);
            });
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const addBudgetDataModal = document.getElementById('addBudgetDataModal');
            const editBudgetDataModal = document.getElementById('editBudgetDataModal');
            const addBudgetPreviewModal = document.getElementById('addBudgetPreviewModal');
            const editBudgetPreviewModal = document.getElementById('editBudgetPreviewModal');
            const commentModal = document.getElementById('commentModal');
            
            if (event.target == addBudgetDataModal) {
                addBudgetDataModal.style.display = 'none';
            }
            
            if (event.target == editBudgetDataModal) {
                editBudgetDataModal.style.display = 'none';
            }
            
            if (event.target == addBudgetPreviewModal) {
                addBudgetPreviewModal.style.display = 'none';
            }
            
            if (event.target == editBudgetPreviewModal) {
                editBudgetPreviewModal.style.display = 'none';
            }
            
            if (event.target == commentModal) {
                commentModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>