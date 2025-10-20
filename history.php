<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'header.php'; 

// Include database configuration
if (!defined('INCLUDED_SETUP')) {
    define('INCLUDED_SETUP', true);
}
include 'setup_database.php';
include 'currency_functions.php';

// Get user role and cluster
$userRole = $_SESSION['role'] ?? '';
$userCluster = $_SESSION['cluster_name'] ?? null;

// Get current year for default filter
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// Pagination setup
$recordsPerPage = 15;
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Get filter parameters
$selectedCluster = isset($_GET['cluster']) ? $_GET['cluster'] : '';
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
$selectedPV = isset($_GET['pv']) ? $_GET['pv'] : '';
$selectedDateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$selectedDate = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$selectedStartDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$selectedEndDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Fetch available clusters for admin filter
$availableClusters = [];
if ($userRole === 'admin') {
    $clusterQuery = "SELECT DISTINCT cluster FROM budget_preview WHERE cluster IS NOT NULL AND cluster != '' ORDER BY cluster ASC";
    $clusterResult = $conn->query($clusterQuery);
    if ($clusterResult) {
        while ($row = $clusterResult->fetch_assoc()) {
            $availableClusters[] = $row['cluster'];
        }
    }
}

// Initialize total variables
$totalAmount = 0;
$totalBudget = 0;
$totalActual = 0;
$totalForecast = 0;
$totalActualPlusForecast = 0;
$totalVariance = 0;

// Build WHERE clause for filtering
$whereConditions = ["YEAR(bp.EntryDate) = ?"];
$params = [$selectedYear];
$paramTypes = "i";

// Cluster filtering logic
if ($userRole === 'admin' && !empty($selectedCluster)) {
    // Admin is filtering by a specific cluster
    $whereConditions[] = "bp.cluster = ?";
    $params[] = $selectedCluster;
    $paramTypes .= "s";
} elseif ($userRole !== 'admin' && $userCluster) {
    // Non-admin is restricted to their own cluster
    $whereConditions[] = "bp.cluster = ?";
    $params[] = $userCluster;
    $paramTypes .= "s";
}

if (!empty($selectedCategory)) {
    $whereConditions[] = "bp.CategoryName LIKE ?";
    $params[] = "%" . $selectedCategory . "%";
    $paramTypes .= "s";
}

if (!empty($selectedPV)) {
    $whereConditions[] = "bp.PVNumber LIKE ?";
    $params[] = "%" . $selectedPV . "%";
    $paramTypes .= "s";
}

if (!empty($selectedDateFilter) && !empty($selectedDate)) {
    if ($selectedDateFilter === 'ondate') {
        $whereConditions[] = "DATE(bp.EntryDate) = ?";
        $params[] = $selectedDate;
        $paramTypes .= "s";
    } elseif ($selectedDateFilter === 'before') {
        $whereConditions[] = "bp.EntryDate < ?";
        $params[] = $selectedDate;
        $paramTypes .= "s";
    } elseif ($selectedDateFilter === 'after') {
        $whereConditions[] = "bp.EntryDate > ?";
        $params[] = $selectedDate;
        $paramTypes .= "s";
    }
}

if (!empty($selectedDateFilter) && $selectedDateFilter === 'between' && !empty($selectedStartDate) && !empty($selectedEndDate)) {
    $whereConditions[] = "bp.EntryDate BETWEEN ? AND ?";
    $params[] = $selectedStartDate;
    $params[] = $selectedEndDate;
    $paramTypes .= "ss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get currency rates for the user's cluster
$currencyRates = [];
if ($userCluster) {
    $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $userCluster);
} else {
    // Default rates if no cluster is assigned
    $currencyRates = [
        'USD_to_ETB' => 55.0000,
        'EUR_to_ETB' => 60.0000
    ];
}

// Count total records for pagination with filters
$countQuery = "SELECT COUNT(*) as total FROM budget_preview bp WHERE " . $whereClause;
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($paramTypes, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch transactions from budget_preview table with budget data and filters
$transactionsQuery = "SELECT 
    bp.PreviewID,
    bp.BudgetHeading,
    bp.Description as Activity,
    bp.Partner,
    bp.EntryDate,
    bp.Amount,
    bp.PVNumber,
    bp.QuarterPeriod,
    bp.CategoryName,
    bp.cluster,
    bp.OriginalBudget,
    bp.RemainingBudget,
    bp.ActualSpent,
    bp.ForecastAmount,
    bp.VariancePercentage,
    bp.ACCEPTANCE,
    bp.COMMENTS,
    YEAR(bp.EntryDate) as TransactionYear,
    bp.DocumentPaths,
    bp.DocumentTypes,
    bp.OriginalNames,
    bp.currency,
    bp.use_custom_rate,
    bp.usd_to_etb,
    bp.eur_to_etb,
    bp.usd_to_eur
FROM budget_preview bp 
WHERE " . $whereClause . "
ORDER BY bp.EntryDate DESC, bp.PreviewID DESC
LIMIT ? OFFSET ?";

// Add pagination parameters
$paramsWithPagination = array_merge($params, [$recordsPerPage, $offset]);
$paramTypesWithPagination = $paramTypes . "ii";

$stmt = $conn->prepare($transactionsQuery);
$stmt->bind_param($paramTypesWithPagination, ...$paramsWithPagination);
$stmt->execute();
$transactionsResult = $stmt->get_result();
$transactions = [];
while ($row = $transactionsResult->fetch_assoc()) {
    $transactions[] = $row;
    
    // Use custom rates if this transaction has use_custom_rate = 1
    $effectiveRates = $currencyRates;
    if (!empty($row['use_custom_rate']) && intval($row['use_custom_rate']) === 1) {
        if (!empty($row['usd_to_etb'])) {
            $effectiveRates['USD_to_ETB'] = (float)$row['usd_to_etb'];
        }
        if (!empty($row['eur_to_etb'])) {
            $effectiveRates['EUR_to_ETB'] = (float)$row['eur_to_etb'];
        }
    }
    
    // Convert all financial values to ETB for totals using effective rates
    $transactionCurrency = $row['currency'] ?? 'USD';
    $amountUSD = $row['Amount'] ?? 0;
    if ($transactionCurrency === 'USD') {
        $amountETB = $amountUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
    } elseif ($transactionCurrency === 'EUR') {
        $amountETB = $amountUSD * ($effectiveRates['EUR_to_ETB'] ?? 60.0000);
    } else {
        $amountETB = $amountUSD; // Already in ETB
    }
    $totalAmount += $amountETB;
    
    $budgetUSD = $row['OriginalBudget'] ?? 0;
    $budgetETB = $budgetUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
    $totalBudget += $budgetETB;
    
    $actualUSD = $row['ActualSpent'] ?? 0;
    $actualETB = $actualUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
    $totalActual += $actualETB;
    
    $forecastUSD = $row['RemainingBudget'] ?? 0;
    $forecastETB = $forecastUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
    $totalForecast += $forecastETB;
}
$totalActualPlusForecast = $totalActual + $totalForecast;
$totalVariance = $totalBudget > 0 ? (($totalActual - $totalBudget) / $totalBudget * 100) : 0;

// Get summary data for the year
$summaryQuery = "SELECT 
    CategoryName,
    QuarterPeriod,
    SUM(Amount) as TotalAmount,
    AVG(VariancePercentage) as AvgVariance,
    COUNT(*) as TransactionCount
FROM budget_preview 
WHERE YEAR(EntryDate) = ?" . ($userRole !== 'admin' && $userCluster ? " AND cluster = ?" : "") . "
GROUP BY CategoryName, QuarterPeriod
ORDER BY CategoryName, QuarterPeriod";

$summaryStmt = $conn->prepare($summaryQuery);
if ($userRole !== 'admin' && $userCluster) {
    $summaryStmt->bind_param("is", $selectedYear, $userCluster);
} else {
    $summaryStmt->bind_param("i", $selectedYear);
}
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summaryData = [];
while ($row = $summaryResult->fetch_assoc()) {
    $summaryData[$row['CategoryName']][$row['QuarterPeriod']] = $row;
}

// Get available PV numbers for filter dropdown (filtered by cluster for non-admin users)
$pvQuery = "SELECT DISTINCT PVNumber FROM budget_preview WHERE PVNumber IS NOT NULL AND PVNumber != ''" . 
           ($userRole !== 'admin' && $userCluster ? " AND cluster = ?" : "") . 
           " ORDER BY PVNumber";
$pvStmt = $conn->prepare($pvQuery);
if ($userRole !== 'admin' && $userCluster) {
    $pvStmt->bind_param("s", $userCluster);
}
$pvStmt->execute();
$pvResult = $pvStmt->get_result();
$availablePVs = [];
while ($row = $pvResult->fetch_assoc()) {
    $availablePVs[] = $row['PVNumber'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', 'Inter', sans-serif;
            background-color: #f0f4f8;
        }
    .main-content-flex {
    display: flex;
    justify-content: center;
    padding: 2rem 0; /* Zero horizontal padding on mobile */
    width: 100%;
}
#historySection {
    background: white;
    padding: 1.5rem; /* Default padding */
    border-radius: 1rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

@media (max-width: 767px) {
    #historySection {
        padding: 1rem 0; /* Zero horizontal padding on mobile */
        border-radius: 0; /* Optional: removes rounded corners for true edge-to-edge */
    }
}

@media (min-width: 768px) {
    .main-content-flex {
        padding: 2rem 1rem; /* Padding for tablets */
    }
}

@media (min-width: 1024px) {
    .main-content-flex {
        padding: 2rem; /* Original padding for desktop */
    }
}
.content-container {
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
}

/* Full edge-to-edge on mobile */
@media (max-width: 767px) {
    .content-container {
        max-width: none;
        padding-left: 0;
        padding-right: 0;
    }
}
        .animate-fadeIn {
            animation: fadeIn 0.8s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-hover {
            transition: transform 0.3s ease-out, box-shadow 0.3s ease-out;
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }
        .btn-shadow {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .financial-table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .table-header {
            background: linear-gradient(to right, #1d4ed8, #2563eb);
        }
        .table-header th {
            color: #ffffff;
            font-weight: 600;
            text-align: left;
            padding: 1rem 1.5rem;
        }
         .table-header th:first-child {
        background: #1d4ed8 !important; /* Solid color instead of gradient */
    }
        .table-body tr:nth-child(odd) {
            background-color: #f9fafb;
        }
        .table-body tr:nth-child(even) {
            background-color: #ffffff;
        }
        .table-body tr:hover {
            background-color: #f1f5f9;
        }
        .table-body td {
            padding: 1rem 1.5rem;
            color: #4b5563;
            font-size: 0.875rem;
        }
        .category-row {
            background-color: #f8fafc; /* Light dim color as default */
        }
        /* Alternating category backgrounds - Mixed Pattern */
        .category-row:nth-of-type(5n+1) { /* 1st category (Administrative Costs) */
            background-color: #f8fafc; /* Light dim color */
        }
        .category-row:nth-of-type(5n+1) td:first-child {
            background-color: #f8fafc !important;
        }
        .category-row:nth-of-type(5n+2) { /* 2nd category (Operational Support) */
            background-color: #ffffff; /* White */
        }
        .category-row:nth-of-type(5n+2) td:first-child {
            background-color: #ffffff !important;
        }
        .category-row:nth-of-type(5n+3) { /* 3rd category (Consortium Activities) */
            background-color: #f8fafc; /* Light dim color */
        }
        .category-row:nth-of-type(5n+3) td:first-child {
            background-color: #f8fafc !important;
        }
        .category-row:nth-of-type(5n+4) { /* 4th category (Targeting New CSOs) */
            background-color: #ffffff; /* White */
        }
        .category-row:nth-of-type(5n+4) td:first-child {
            background-color: #ffffff !important;
        }
        .category-row:nth-of-type(5n+5) { /* 5th category (Contingency) */
            background-color: #f8fafc; /* Light dim color */
        }
        .category-row:nth-of-type(5n+5) td:first-child {
            background-color: #f8fafc !important;
        }
        
        .category-row td {
            font-weight: 600;
            color: #1e3a8a;
        }
        /* Override styling for Q1 cells to match other quarters */
        .category-row td:not(:first-child) {
            background-color: inherit;
            font-weight: 400;
            color: #4b5563;
        }
        /* Make category-row behave like quarter-row for non-category cells */
        .category-row:nth-child(odd) {
            background-color: inherit;
        }
        .category-row:nth-child(even) {
            background-color: inherit;
        }
        
        /* Remove CSS-based hover effects - will be handled by JavaScript */
        .table-body tr {
            background-color: inherit;
        }
        .table-body tr:hover {
            background-color: inherit !important; /* Remove CSS hover, use JS instead */
        }
        .table-body tr:hover td {
            background-color: inherit !important; /* Remove CSS hover, use JS instead */
        }
        
        /* JavaScript-controlled hover class */
        .table-body tr.js-hover {
            background-color: #e2e8f0 !important; /* Professional dim gray-blue */
        }
        .table-body tr.js-hover td:not([rowspan]) {
            background-color: #e2e8f0 !important;
        }
        
        /* Special styling for category cells in Q1 quarter-rows (rows with rowspan category cells) */
        .quarter-row td[rowspan] {
            font-weight: 700 !important;
            color: #1d4ed8 !important; /* Distinctive blue for category names */
            background-color: inherit; /* Inherit from parent row */
        }
        
        /* Prevent hover effects on category cells when hovering Q1 rows */
        .quarter-row:hover td[rowspan] {
            background-color: inherit !important; /* Don't change background on hover */
            color: #1d4ed8 !important; /* Keep original category color */
        }
        
        /* Keep the first cell (category name) with distinctive styling and colors */
        .category-row td:first-child {
            font-weight: 700 !important;
            color: #1d4ed8 !important; /* Distinctive blue for category names */
        }
        .category-name {
            font-weight: 700;
            color: #1d4ed8;
            font-size: 1rem;
        }
        .annual-total, .grand-total {
            font-weight: 700;
        }
        .total-cell-bg {
            background: #e5e7eb;
            font-weight: 600;
        }
        .grand-total-cell-bg {
            background: #4f46e5;
            color: white;
            font-weight: 800;
            font-size: 1.125rem;
        }
        .variance-positive {
            color: #ef4444; /* red for overspent */
            font-weight: 600;
        }
        .variance-negative {
            color: #10b981; /* green for underspent */
            font-weight: 600;
        }
        .variance-neutral {
            color: #64748b; /* gray for zero variance */
            font-weight: 600;
        }
        .action-btn {
            transition: all 0.2s;
            border-radius: 4px;
            padding: 6px;
        }
        .edit-btn:hover {
            background-color: #dbeafe;
            transform: scale(1.1);
        }
        .delete-btn:hover {
            background-color: #fee2e2;
            transform: scale(1.1);
        }
        .empty-data {
            color: #9ca3af;
            font-style: italic;
        }
        .table-container {
            max-width: 100%;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #4f46e5 #e5e7eb;
        }
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #e5e7eb;
            border-radius: 4px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #4f46e5;
            border-radius: 4px;
        }
     
        .hover-scale {
            transition: transform 0.2s ease;
        }
        .hover-scale:hover {
            transform: scale(1.1);
        }
        th, td {
            transition: background-color 0.3s ease, color 0.3s ease;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body>
<?php 
// Check if this file is being included or accessed directly
$included = defined('INCLUDED_FROM_INDEX') || isset($GLOBALS['in_index_context']);
if (!$included): 
?>
<div class="flex flex-col flex-1 min-w-0">
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
        <div class="flex items-center">
            <button id="mobileMenuToggle"
                class="text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-2 lg:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Transaction History</h2>
        </div>
        <div class="flex items-center space-x-4">
            <button class="relative p-2 text-gray-500 hover:text-primary-600 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="absolute top-0 right-0 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-600"></span>
                </span>
            </button>
        </div>
    </header>

    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
<?php endif; ?>

<div class="main-content-flex">
    <div class="content-container">
        <div id="historySection" class="bg-white p-4 md:p-6 rounded-3xl shadow-2xl w-full mx-auto card-hover animate-fadeIn">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h3 class="text-3xl md:text-4xl font-extrabold text-gray-800">Transaction History 📜</h3>
                <button id="addTransactionBtn" class="bg-green-500 text-white py-2 px-4 md:py-3 md:px-8 rounded-full font-semibold transition hover:bg-green-600 btn-shadow flex items-center space-x-2 whitespace-nowrap">
                    <i class="fas fa-plus-circle"></i> <span>Add New</span>
                </button>
            </div>
            <p class="text-gray-500 text-base md:text-lg mb-6 md:mb-8">View and analyze your past financial transactions and forecasts.</p>

            <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border border-gray-100 mb-8">
                <div class="flex items-center mb-6 border-b border-gray-200 pb-4">
                    <svg class="h-6 w-6 text-indigo-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.572a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                    </svg>
                    <h4 class="text-xl font-bold text-gray-800">Filter Options</h4>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
                    <div>
                        <label for="categoryFilter" class="block text-sm font-medium text-gray-600 mb-2">Category</label>
                        <div class="relative">
                            <select id="categoryFilter" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                                <option value="">All Categories</option>
                                <option value="Administrative Costs"<?php echo $selectedCategory === 'Administrative Costs' ? ' selected' : ''; ?>>1. Administrative Costs</option>
                                <option value="Operational Support Costs"<?php echo $selectedCategory === 'Operational Support Costs' ? ' selected' : ''; ?>>2. Operational Support Costs</option>
                                <option value="Consortium Activities"<?php echo $selectedCategory === 'Consortium Activities' ? ' selected' : ''; ?>>3. Consortium Activities</option>
                                <option value="Targeting New CSOs"<?php echo $selectedCategory === 'Targeting New CSOs' ? ' selected' : ''; ?>>4. Targeting New CSOs</option>
                                <option value="Contingency"<?php echo $selectedCategory === 'Contingency' ? ' selected' : ''; ?>>5. Contingency</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="pvFilter" class="block text-sm font-medium text-gray-600 mb-2">Reference Number</label>
                        <div class="relative">
                            <select id="pvFilter" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                                <option value="">All Reference Numbers</option>
                                <option value="custom"<?php echo !empty($selectedPV) ? ' selected' : ''; ?>>Enter Reference Number...</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div id="pvInputContainer" class="mt-3<?php echo !empty($selectedPV) ? '' : ' hidden'; ?>">
                            <input type="text" id="pvCustomInput" placeholder="Enter Reference Number (e.g., PV-ADM-001)" value="<?php echo !empty($selectedPV) ? htmlspecialchars($selectedPV) : ''; ?>" class="w-full bg-white border border-gray-300 rounded-lg py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                        </div>
                    </div>
                    <div>
                        <label for="dateFilterDropdown" class="block text-sm font-medium text-gray-600 mb-2">Date Filter</label>
                        <div class="relative">
                            <select id="dateFilterDropdown" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                                <option value="">No Date Filter</option>
                                <option value="ondate">On Date</option>
                                <option value="before">Before Date</option>
                                <option value="after">After Date</option>
                                <option value="between">Between Dates</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div id="dateInputs" class="mt-4 space-y-3"></div>
                    </div>
                    <div>
                        <label for="yearFilter" class="block text-sm font-medium text-gray-600 mb-2">Year</label>
                        <div class="relative">
                            <select id="yearFilter" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                                <option value="">All Years</option>
                                <option value="2023"<?php echo $selectedYear == 2023 ? ' selected' : ''; ?>>2023</option>
                                <option value="2024"<?php echo $selectedYear == 2024 ? ' selected' : ''; ?>>2024</option>
                                <option value="2025"<?php echo $selectedYear == 2025 ? ' selected' : ''; ?>>2025</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <?php if ($userRole === 'admin'): ?>
                    <div>
                        <label for="clusterFilter" class="block text-sm font-medium text-gray-600 mb-2">Cluster</label>
                        <div class="relative">
                            <select id="clusterFilter" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                                <option value="">All Clusters</option>
                                <?php foreach ($availableClusters as $cluster): ?>
                                    <option value="<?php echo htmlspecialchars($cluster); ?>"<?php echo $selectedCluster === $cluster ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cluster); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex justify-end items-center mt-8 pt-4 border-t border-gray-200 space-x-4">
                    <button id="clearFiltersBtn" class="px-6 py-2.5 text-sm font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 transition duration-150">Clear</button>
                    <button id="applyFiltersBtn" class="px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 transition duration-150 shadow-sm">Apply Filters</button>
                </div>
            </div>

            <h4 class="text-3xl font-extrabold mb-6 border-b-4 border-indigo-200 pb-4 text-left">Forecast Budget 2025</h4>
            <div class="table-container rounded-xl shadow-2xl border border-gray-200 bg-white overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300 financial-table">
                    <thead class="table-header">
                        <tr>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-left text-sm font-bold uppercase tracking-wider sticky left-0 bg-gradient-to-r from-indigo-500 to-blue-600">Category</th>
                            <?php if ($userRole === 'admin'): ?>
                                <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-left text-sm font-bold uppercase tracking-wider">Cluster</th>
                            <?php endif; ?>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-left text-sm font-bold uppercase tracking-wider">Period</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-sm font-bold uppercase tracking-wider">Activity</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-right text-sm font-bold uppercase tracking-wider">Budget</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-right text-sm font-bold uppercase tracking-wider">Amount</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-right text-sm font-bold uppercase tracking-wider">Actual</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-right text-sm font-bold uppercase tracking-wider">Forecast</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-right text-sm font-bold uppercase tracking-wider">Actual + Forecast</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-right text-sm font-bold uppercase tracking-wider">Variance (%)</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-left text-sm font-bold uppercase tracking-wider">Acceptance</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-left text-sm font-bold uppercase tracking-wider">Comments</th>
                            <th scope="col" class="px-4 py-3 md:px-6 md:py-4 text-center text-sm font-bold uppercase tracking-wider border-r border-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transactionTableBody" class="table-body divide-y divide-gray-200">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="<?php echo ($userRole === 'admin') ? '13' : '12'; ?>" class="px-4 py-6 md:px-6 md:py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-2xl md:text-3xl mb-2"></i>
                                    <p class="text-base md:text-lg font-medium">No transactions found for <?php echo $selectedYear; ?></p>
                                    <p class="text-sm">Try selecting a different year or add some transactions.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
    <?php 
        // Fix: Set display variables for each transaction
        $categoryDisplay = $transaction['CategoryName'] ?? 'N/A';
        $quarterDisplay = $transaction['QuarterPeriod'] ?? 'N/A';
        $pvNumber = $transaction['PVNumber'] ?? 'N/A';
        $formattedDate = !empty($transaction['EntryDate']) ? date('d M Y', strtotime($transaction['EntryDate'])) : 'N/A';

        // Use custom rates if this transaction has use_custom_rate = 1
        $effectiveRates = $currencyRates;
        if (!empty($transaction['use_custom_rate']) && intval($transaction['use_custom_rate']) === 1) {
            if (!empty($transaction['usd_to_etb'])) {
                $effectiveRates['USD_to_ETB'] = (float)$transaction['usd_to_etb'];
            }
            if (!empty($transaction['eur_to_etb'])) {
                $effectiveRates['EUR_to_ETB'] = (float)$transaction['eur_to_etb'];
            }
        }
        
        // Get transaction currency
        $transactionCurrency = $transaction['currency'] ?? 'USD';
        
        // Convert all financial values to ETB using effective rates
        $budgetUSD = $transaction['OriginalBudget'] ?? 0;
        $budgetETB = $budgetUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
        $budgetFormatted = number_format($budgetETB, 2) . ' <span class="text-xs text-gray-500">(USD: ' . number_format($budgetUSD, 2) . ')</span>';
        
        $actualUSD = $transaction['ActualSpent'] ?? 0;
        $actualETB = $actualUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
        $actualFormatted = number_format($actualETB, 2) . ' <span class="text-xs text-gray-500">(USD: ' . number_format($actualUSD, 2) . ')</span>';
        
        $forecastUSD = $transaction['RemainingBudget'] ?? 0;
        $forecastETB = $forecastUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
        $forecastFormatted = number_format($forecastETB, 2) . ' <span class="text-xs text-gray-500">(USD: ' . number_format($forecastUSD, 2) . ')</span>';
        
        $amountOriginal = $transaction['Amount'] ?? 0;
        if ($transactionCurrency === 'USD') {
            $amountETB = $amountOriginal * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
            $amountFormatted = number_format($amountETB, 2) . ' <span class="text-xs text-gray-500">(USD: ' . number_format($amountOriginal, 2) . ')</span>';
        } elseif ($transactionCurrency === 'EUR') {
            $amountETB = $amountOriginal * ($effectiveRates['EUR_to_ETB'] ?? 60.0000);
            $amountFormatted = number_format($amountETB, 2) . ' <span class="text-xs text-gray-500">(EUR: ' . number_format($amountOriginal, 2) . ')</span>';
        } else {
            $amountETB = $amountOriginal; // Already in ETB
            $amountFormatted = number_format($amountETB, 2) . ' <span class="text-xs text-gray-500">(ETB)</span>';
        }
        
        // Show custom rate indicator if used
        $customRateIndicator = '';
        if (!empty($transaction['use_custom_rate']) && intval($transaction['use_custom_rate']) === 1) {
            $customRateIndicator = ' <span class="text-xs bg-blue-100 text-blue-800 px-1 rounded">Custom Rate</span>';
        }
        $amountFormatted .= $customRateIndicator;
        
        $actualPlusForecastUSD = $actualUSD + $forecastUSD;
        $actualPlusForecastETB = $actualPlusForecastUSD * ($effectiveRates['USD_to_ETB'] ?? 55.0000);
        $actualPlusForecastFormatted = number_format($actualPlusForecastETB, 2) . ' <span class="text-xs text-gray-500">(USD: ' . number_format($actualPlusForecastUSD, 2) . ')</span>';
        
        $variance = $budgetUSD > 0 ? (($actualPlusForecastUSD - $budgetUSD) / $budgetUSD * 100) : 0;
        
        // Get acceptance and comments
        $accepted = $transaction['ACCEPTANCE'] ?? 0;
        $comments = $transaction['COMMENTS'] ?? '';

        // Determine variance class
        $varianceClass = 'variance-neutral';
        if ($variance > 0) {
            $varianceClass = 'variance-positive';
        } elseif ($variance < 0) {
            $varianceClass = 'variance-negative';
        }
        
        // Determine if buttons should be enabled (only if not accepted)
        $buttonsDisabled = $accepted == 1 ? 'disabled' : '';
        $buttonClass = $accepted == 1 ? 'opacity-50 cursor-not-allowed' : 'hover-scale';
    ?>
    <tr class="transaction-row" 
        data-category="<?php echo htmlspecialchars($categoryDisplay); ?>"
        data-quarter="<?php echo htmlspecialchars($quarterDisplay); ?>"
        data-date="<?php echo htmlspecialchars($transaction['EntryDate']); ?>"
        data-pv="<?php echo htmlspecialchars($pvNumber); ?>"
        data-year="<?php echo $transaction['TransactionYear']; ?>">
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
            <span class="category-name text-base"><?php echo htmlspecialchars($categoryDisplay); ?></span><br />
            <span class="text-xs text-gray-500">Date: <?php echo $formattedDate; ?></span><br />
            <?php if ($pvNumber !== 'N/A'): ?>
                <a href="transaction_detail.php?id=<?php echo $transaction['PreviewID']; ?>" 
                   class="text-sm text-blue-600 font-semibold hover:text-blue-800 hover:underline cursor-pointer transition-colors duration-200">
                    Ref: <?php echo htmlspecialchars($pvNumber); ?>
                </a>
            <?php else: ?>
                <span class="text-sm text-gray-500 font-semibold">Ref: N/A</span>
            <?php endif; ?>
        </td>
        
        <?php if ($userRole === 'admin'): ?>
            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700">
                <?php echo htmlspecialchars($transaction['cluster'] ?? 'N/A'); ?>
            </td>
        <?php endif; ?>

        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700">
            <?php echo htmlspecialchars($quarterDisplay); ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 max-w-xs truncate">
            <?php echo htmlspecialchars($transaction['Activity'] ?? 'N/A'); ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 text-right">
          <?php echo $budgetFormatted; ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 text-right">
          <?php echo $amountFormatted; ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 text-right">
          <?php echo $actualFormatted; ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 text-right">
          <?php echo $forecastFormatted; ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 text-right">
          <?php echo $actualPlusForecastFormatted; ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 text-right <?php echo $varianceClass; ?>">
            <?php echo number_format($variance, 2); ?>%
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700">
            <?php if ($accepted == 1): ?>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fas fa-check-circle mr-1"></i> Accepted
                </span>
            <?php else: ?>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    <i class="fas fa-clock mr-1"></i> Pending
                </span>
            <?php endif; ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-sm text-gray-700 max-w-xs truncate">
            <?php echo htmlspecialchars($comments); ?>
        </td>
        
        <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-center text-sm font-medium border-r border-gray-200">
            <button class="text-blue-500 hover:text-blue-700 <?php echo $buttonClass; ?> action-btn edit-btn <?php echo $buttonsDisabled; ?>" 
                    title="<?php echo $accepted == 1 ? 'Cannot edit accepted transaction' : 'Edit'; ?>" 
                    data-id="<?php echo $transaction['PreviewID']; ?>"
                    <?php echo $buttonsDisabled; ?>>
                <i class="fas fa-edit"></i>
            </button>
            <button class="text-red-500 hover:text-red-700 <?php echo $buttonClass; ?> ml-2 action-btn delete-btn <?php echo $buttonsDisabled; ?>" 
                    title="<?php echo $accepted == 1 ? 'Cannot delete accepted transaction' : 'Delete'; ?>" 
                    data-id="<?php echo $transaction['PreviewID']; ?>"
                    <?php echo $buttonsDisabled; ?>>
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    </tr>
<?php endforeach; ?>
                        <?php endif; ?>
                     
                    </tbody>
                    <tfoot>
                        <tr class="grand-total">
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap grand-total-cell-bg rounded-bl-lg"></td>
                            <?php if ($userRole === 'admin'): ?>
                                <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap grand-total-cell-bg"></td>
                            <?php endif; ?>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap grand-total-cell-bg text-right text-lg font-bold text-white">Total</td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"><?php echo number_format($totalBudget, 2); ?></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"><?php echo number_format($totalAmount, 2); ?></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"><?php echo number_format($totalActual, 2); ?></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"><?php echo number_format($totalForecast, 2); ?></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"><?php echo number_format($totalActualPlusForecast, 2); ?></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"><?php echo number_format($totalVariance, 2); ?>%</td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 whitespace-nowrap text-right grand-total-cell-bg text-lg font-bold text-white"></td>
                            <td class="px-4 py-3 md:px-6 md:py-4 border-r border-gray-200 grand-total-cell-bg rounded-br-lg text-right text-lg font-bold text-white"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-b-xl">
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo $currentPage - 1; ?>&year=<?php echo $selectedYear; ?><?php echo !empty($selectedCategory) ? '&category=' . urlencode($selectedCategory) : ''; ?><?php echo !empty($selectedPV) ? '&pv=' . urlencode($selectedPV) : ''; ?><?php echo !empty($selectedDateFilter) ? '&date_filter=' . urlencode($selectedDateFilter) : ''; ?><?php echo !empty($selectedDate) ? '&filter_date=' . urlencode($selectedDate) : ''; ?><?php echo !empty($selectedStartDate) ? '&start_date=' . urlencode($selectedStartDate) : ''; ?><?php echo !empty($selectedEndDate) ? '&end_date=' . urlencode($selectedEndDate) : ''; ?><?php echo ($userRole === 'admin' && !empty($selectedCluster)) ? '&cluster=' . urlencode($selectedCluster) : ''; ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?>&year=<?php echo $selectedYear; ?><?php echo !empty($selectedCategory) ? '&category=' . urlencode($selectedCategory) : ''; ?><?php echo !empty($selectedPV) ? '&pv=' . urlencode($selectedPV) : ''; ?><?php echo !empty($selectedDateFilter) ? '&date_filter=' . urlencode($selectedDateFilter) : ''; ?><?php echo !empty($selectedDate) ? '&filter_date=' . urlencode($selectedDate) : ''; ?><?php echo !empty($selectedStartDate) ? '&start_date=' . urlencode($selectedStartDate) : ''; ?><?php echo !empty($selectedEndDate) ? '&end_date=' . urlencode($selectedEndDate) : ''; ?><?php echo ($userRole === 'admin' && !empty($selectedCluster)) ? '&cluster=' . urlencode($selectedCluster) : ''; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo (($currentPage - 1) * $recordsPerPage) + 1; ?></span> to <span class="font-medium"><?php echo min($currentPage * $recordsPerPage, $totalRecords); ?></span> of <span class="font-medium"><?php echo $totalRecords; ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?>&year=<?php echo $selectedYear; ?><?php echo !empty($selectedCategory) ? '&category=' . urlencode($selectedCategory) : ''; ?><?php echo !empty($selectedPV) ? '&pv=' . urlencode($selectedPV) : ''; ?><?php echo !empty($selectedDateFilter) ? '&date_filter=' . urlencode($selectedDateFilter) : ''; ?><?php echo !empty($selectedDate) ? '&filter_date=' . urlencode($selectedDate) : ''; ?><?php echo !empty($selectedStartDate) ? '&start_date=' . urlencode($selectedStartDate) : ''; ?><?php echo !empty($selectedEndDate) ? '&end_date=' . urlencode($selectedEndDate) : ''; ?><?php echo ($userRole === 'admin' && !empty($selectedCluster)) ? '&cluster=' . urlencode($selectedCluster) : ''; ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                <?php if ($i == $currentPage): ?>
                                    <span class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&year=<?php echo $selectedYear; ?><?php echo !empty($selectedCategory) ? '&category=' . urlencode($selectedCategory) : ''; ?><?php echo !empty($selectedPV) ? '&pv=' . urlencode($selectedPV) : ''; ?><?php echo !empty($selectedDateFilter) ? '&date_filter=' . urlencode($selectedDateFilter) : ''; ?><?php echo !empty($selectedDate) ? '&filter_date=' . urlencode($selectedDate) : ''; ?><?php echo !empty($selectedStartDate) ? '&start_date=' . urlencode($selectedStartDate) : ''; ?><?php echo !empty($selectedEndDate) ? '&end_date=' . urlencode($selectedEndDate) : ''; ?><?php echo ($userRole === 'admin' && !empty($selectedCluster)) ? '&cluster=' . urlencode($selectedCluster) : ''; ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-3300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?>&year=<?php echo $selectedYear; ?><?php echo !empty($selectedCategory) ? '&category=' . urlencode($selectedCategory) : ''; ?><?php echo !empty($selectedPV) ? '&pv=' . urlencode($selectedPV) : ''; ?><?php echo !empty($selectedDateFilter) ? '&date_filter=' . urlencode($selectedDateFilter) : ''; ?><?php echo !empty($selectedDate) ? '&filter_date=' . urlencode($selectedDate) : ''; ?><?php echo !empty($selectedStartDate) ? '&start_date=' . urlencode($selectedStartDate) : ''; ?><?php echo !empty($selectedEndDate) ? '&end_date=' . urlencode($selectedEndDate) : ''; ?><?php echo ($userRole === 'admin' && !empty($selectedCluster)) ? '&cluster=' . urlencode($selectedCluster) : ''; ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    
    document.addEventListener('DOMContentLoaded', () => {
        const dateFilterDropdown = document.getElementById('dateFilterDropdown');
        const dateInputsContainer = document.getElementById('dateInputs');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        const addTransactionBtn = document.getElementById('addTransactionBtn');
        const transactionTableBody = document.getElementById('transactionTableBody');

        // Set initial filter values from PHP
        const initialFilters = {
            category: '<?php echo addslashes($selectedCategory); ?>',
            pv: '<?php echo addslashes($selectedPV); ?>',
            dateFilter: '<?php echo addslashes($selectedDateFilter); ?>',
            filterDate: '<?php echo addslashes($selectedDate); ?>',
            startDate: '<?php echo addslashes($selectedStartDate); ?>',
            endDate: '<?php echo addslashes($selectedEndDate); ?>',
            year: '<?php echo $selectedYear; ?>',
            cluster: '<?php echo addslashes($selectedCluster); ?>'
        };

        // Set filter dropdown values
        if (initialFilters.category) {
            document.getElementById('categoryFilter').value = initialFilters.category;
        }
        if (initialFilters.year) {
            document.getElementById('yearFilter').value = initialFilters.year;
        }
        if (initialFilters.cluster) {
            const clusterFilter = document.getElementById('clusterFilter');
            if (clusterFilter) clusterFilter.value = initialFilters.cluster;
        }
        if (initialFilters.pv) {
            document.getElementById('pvFilter').value = 'custom';
            document.getElementById('pvInputContainer').classList.remove('hidden');
            document.getElementById('pvCustomInput').value = initialFilters.pv;
        }
        if (initialFilters.dateFilter) {
            document.getElementById('dateFilterDropdown').value = initialFilters.dateFilter;
            // Trigger change event to show date inputs
            dateFilterDropdown.dispatchEvent(new Event('change'));
            
            // Set date values after inputs are created
            setTimeout(() => {
                if (initialFilters.dateFilter === 'ondate' && initialFilters.filterDate) {
                    const onDateInput = document.getElementById('onDate');
                    if (onDateInput) onDateInput.value = initialFilters.filterDate;
                } else if (initialFilters.dateFilter === 'before' && initialFilters.filterDate) {
                    const beforeDateInput = document.getElementById('beforeDate');
                    if (beforeDateInput) beforeDateInput.value = initialFilters.filterDate;
                } else if (initialFilters.dateFilter === 'after' && initialFilters.filterDate) {
                    const afterDateInput = document.getElementById('afterDate');
                    if (afterDateInput) afterDateInput.value = initialFilters.filterDate;
                } else if (initialFilters.dateFilter === 'between') {
                    const startDateInput = document.getElementById('startDate');
                    const endDateInput = document.getElementById('endDate');
                    if (startDateInput && initialFilters.startDate) startDateInput.value = initialFilters.startDate;
                    if (endDateInput && initialFilters.endDate) endDateInput.value = initialFilters.endDate;
                }
            }, 100);
        }

        const applyFilters = () => {
            const filterType = dateFilterDropdown.value;
            const category = document.getElementById('categoryFilter').value;
            const year = document.getElementById('yearFilter').value;
            const pvDropdown = document.getElementById('pvFilter').value;
            const pvCustomInput = document.getElementById('pvCustomInput').value.trim();
            const clusterFilter = document.getElementById('clusterFilter');
            const cluster = clusterFilter ? clusterFilter.value : '';
            
            // Determine the actual PV filter value
            let pvNumber = '';
            if (pvDropdown === 'custom') {
                pvNumber = pvCustomInput;
            } else {
                pvNumber = pvDropdown;
            }

            // Build URL with filters
            let url = new URL(window.location.href);
            url.searchParams.set('page', '1'); // Reset to first page when filtering
            

            if (category) url.searchParams.set('category', category);
            else url.searchParams.delete('category');
            
            if (cluster) url.searchParams.set('cluster', cluster);
            else url.searchParams.delete('cluster');
            
            if (year) url.searchParams.set('year', year);
            else url.searchParams.set('year', '<?php echo $currentYear; ?>'); // Default to current year
            
            if (pvNumber) url.searchParams.set('pv', pvNumber);
            else url.searchParams.delete('pv');
            
            if (filterType) {
                url.searchParams.set('date_filter', filterType);
                
                if (filterType === 'ondate') {
                    const onDate = document.getElementById('onDate')?.value;
                    if (onDate) url.searchParams.set('filter_date', onDate);
                } else if (filterType === 'before') {
                    const beforeDate = document.getElementById('beforeDate')?.value;
                    if (beforeDate) url.searchParams.set('filter_date', beforeDate);
                } else if (filterType === 'after') {
                    const afterDate = document.getElementById('afterDate')?.value;
                    if (afterDate) url.searchParams.set('filter_date', afterDate);
                } else if (filterType === 'between') {
                    const startDate = document.getElementById('startDate')?.value;
                    const endDate = document.getElementById('endDate')?.value;
                    if (startDate) url.searchParams.set('start_date', startDate);
                    if (endDate) url.searchParams.set('end_date', endDate);
                }
            } else {
                url.searchParams.delete('date_filter');
                url.searchParams.delete('filter_date');
                url.searchParams.delete('start_date');
                url.searchParams.delete('end_date');
            }
            


            // Redirect to the filtered URL
            window.location.href = url.toString();
        };

        dateFilterDropdown.addEventListener('change', (event) => {
            const filterType = event.target.value;
            dateInputsContainer.innerHTML = '';
            
            if (filterType === 'ondate') {
                dateInputsContainer.innerHTML = `
                    <div>
                        <label for="onDate" class="block text-sm font-medium text-gray-600 mb-2">Select Date</label>
                        <input type="date" id="onDate" class="w-full bg-white border border-gray-300 rounded-lg py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                    </div>
                `;
            } else if (filterType === 'before') {
                dateInputsContainer.innerHTML = `
                    <div>
                        <label for="beforeDate" class="block text-sm font-medium text-gray-600 mb-2">Before Date</label>
                        <input type="date" id="beforeDate" class="w-full bg-white border border-gray-300 rounded-lg py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                    </div>
                `;
            } else if (filterType === 'after') {
                dateInputsContainer.innerHTML = `
                    <div>
                        <label for="afterDate" class="block text-sm font-medium text-gray-600 mb-2">After Date</label>
                        <input type="date" id="afterDate" class="w-full bg-white border border-gray-300 rounded-lg py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                    </div>
                `;
            } else if (filterType === 'between') {
                dateInputsContainer.innerHTML = `
                    <div>
                        <label for="startDate" class="block text-sm font-medium text-gray-600 mb-2">Start Date</label>
                        <input type="date" id="startDate" class="w-full bg-white border border-gray-300 rounded-lg py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out mb-3">
                    </div>
                    <div>
                        <label for="endDate" class="block text-sm font-medium text-gray-600 mb-2">End Date</label>
                        <input type="date" id="endDate" class="w-full bg-white border border-gray-300 rounded-lg py-3 px-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out">
                    </div>
                `;
            }
            
            // Add event listeners to newly created date inputs (removed auto-filtering)
            const newDateInputs = dateInputsContainer.querySelectorAll('input[type="date"]');
            newDateInputs.forEach(input => {
                // Remove auto-filtering - now only triggered by Apply button
                // input.addEventListener('change', applyFilters);
            });
            
            // Remove auto-filtering - now only triggered by Apply button
            // applyFilters();
        });

        // Handle PV filter dropdown change to show/hide custom input
        document.getElementById('pvFilter').addEventListener('change', (event) => {
            const pvInputContainer = document.getElementById('pvInputContainer');
            const pvCustomInput = document.getElementById('pvCustomInput');
            
            if (event.target.value === 'custom') {
                pvInputContainer.classList.remove('hidden');
                pvCustomInput.focus(); // Focus on the input field when it appears
            } else {
                pvInputContainer.classList.add('hidden');
                pvCustomInput.value = ''; // Clear the custom input when hidden
            }
        });

        // Remove automatic filtering - now only triggered by Apply button
        // const filterControls = document.querySelectorAll('#categoryFilter, #yearFilter');
        // filterControls.forEach(control => {
        //     control.addEventListener('change', applyFilters);
        // });

        // Add input event listener for custom PV input for real-time filtering
        document.getElementById('pvCustomInput').addEventListener('input', () => {
            // Real-time filtering removed - now handled by apply button
        });
        
        // Apply Filters button
        document.getElementById('applyFiltersBtn').addEventListener('click', applyFilters);

        clearFiltersBtn.addEventListener('click', () => {
            // Redirect to clean page without filters
            window.location.href = 'history.php?year=<?php echo $currentYear; ?>';
        });

        addTransactionBtn.addEventListener('click', () => {
            window.location.href = 'financial_report_section.php';
        });

        transactionTableBody.addEventListener('click', (event) => {
            if (event.target.closest('.edit-btn')) {
                const button = event.target.closest('.edit-btn');
                // Check if button is disabled
                if (button.hasAttribute('disabled')) {
                    return; // Do nothing if disabled
                }
                const rowId = button.getAttribute('data-id');
                // Redirect to edit page
                window.location.href = 'edit_transaction.php?id=' + rowId;
            }
            if (event.target.closest('.delete-btn')) {
                const button = event.target.closest('.delete-btn');
                // Check if button is disabled
                if (button.hasAttribute('disabled')) {
                    return; // Do nothing if disabled
                }
                const rowId = button.getAttribute('data-id');
                if (confirm(`Are you sure you want to delete this transaction with ID: ${rowId}?`)) {
                    // Show loading message
                    const loadingMessage = "Processing deletion...";
                    alert(loadingMessage);
                    
                    // Send delete request to the server
                    fetch('delete_transaction.php?id=' + rowId, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => {
                        console.log('Response received:', response);
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Data received:', data);
                        if (data.success) {
                            alert(data.message);
                            // Refresh the page to update the table
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error details:', error);
                        alert('An error occurred while deleting the transaction. Please check the console for details: ' + error.message);
                    });
                }
            }
        });

        // Enhanced hover effect for individual rows only
        const tableRows = document.querySelectorAll('#transactionTableBody .transaction-row');
        
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', () => {
                // Add hover class to the current row only
                row.classList.add('js-hover');
            });
            
            row.addEventListener('mouseleave', () => {
                // Remove hover class from the current row only
                row.classList.remove('js-hover');
            });
        });
    });

    <?php if (!$included): ?>
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
    <?php endif; ?>
</script>
</body>
</html>