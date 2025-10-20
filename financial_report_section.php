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

// Check if this file is being included or accessed directly
$included = defined('INCLUDED_FROM_INDEX') || isset($GLOBALS['in_index_context']);

if (!$included) {
    include 'header.php';
?>
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
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Financial Dashboard</h2>
        </div>
        <div class="flex items-center space-x-4">
            <!-- Notification bell -->
            <button class="relative p-2 text-gray-500 hover:text-primary-600 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="absolute top-0 right-0 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-600"></span>
                </span>
            </button>
            
            <!-- Help button -->
            <button class="p-2 text-gray-500 hover:text-primary-600 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </button>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
<?php 
} else { 
    // This is the included version for index.php
?>
    <div class="flex-1 flex flex-col overflow-hidden">
<?php 
} 
?>

<?php
// Include database configuration and currency functions
include 'currency_functions.php';

define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Get current year for calculations
$currentYear = date('Y');

// Get user cluster information
$userCluster = $_SESSION['cluster_name'] ?? null;

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

// Check if user's cluster has custom currency rates enabled
$isCustomCurrencyEnabled = isClusterCustomCurrencyEnabled($conn, $userCluster);

// Calculate total spent from Budget_Preview table (filtered by cluster if available) with custom rate support
$totalSpent = 0;
$transactionCount = 0;

if ($userCluster) {
    $totalSpentQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview WHERE YEAR(EntryDate) = ? AND cluster = ?";
    $stmt = $conn->prepare($totalSpentQuery);
    $stmt->bind_param("is", $currentYear, $userCluster);
} else {
    $totalSpentQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview WHERE YEAR(EntryDate) = ?";
    $stmt = $conn->prepare($totalSpentQuery);
    $stmt->bind_param("i", $currentYear);
}
$stmt->execute();
$result = $stmt->get_result();

// Process each transaction to calculate total using appropriate rates
$totalSpentETB = 0;
while ($row = $result->fetch_assoc()) {
    $amount = $row['Amount'] ?? 0;
    // Get currency from database, default to 'USD' only if not set
    $currency = !empty($row['currency']) ? $row['currency'] : 'USD';
    $transactionCount++;
    
    // Determine which rates to use
    $effectiveRates = $currencyRates; // Default to cluster rates
    
    // If transaction has custom rates and they're active, use those instead
    if (!empty($row['use_custom_rate']) && $row['use_custom_rate'] == 1) {
        if (!empty($row['usd_to_etb']) && $currency === 'USD') {
            $effectiveRates['USD_to_ETB'] = $row['usd_to_etb'];
        }
        if (!empty($row['eur_to_etb']) && $currency === 'EUR') {
            $effectiveRates['EUR_to_ETB'] = $row['eur_to_etb'];
        }
    }
    
    // Convert amount to ETB using the appropriate rates
    $amountETB = convertCurrency($amount, $currency, 'ETB', $effectiveRates);
    
    // Add to total
    $totalSpent += $amount; // This keeps the original currency amounts
    $totalSpentETB += $amountETB; // This is the total in ETB
}

// Calculate data for the past 3 months (instead of 4)
$currentMonth = date('Y-m');
$prevMonth1 = date('Y-m', strtotime('-1 month'));
$prevMonth2 = date('Y-m', strtotime('-2 months'));

// Variables to store the currency of transactions
$transactionCurrency = null; // Will be set from database
$threeMonthsCurrency = null; // Will be set from database

// Initialize totals
$threeMonthsSpent = 0;
$threeMonthsSpentETB = 0;

// Get transactions for the past 3 months with currency and custom rate information
if ($userCluster) {
    $threeMonthsQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview 
                        WHERE DATE_FORMAT(EntryDate, '%Y-%m') IN (?, ?, ?) AND cluster = ?
                        ORDER BY EntryDate DESC";
    $stmt = $conn->prepare($threeMonthsQuery);
    $stmt->bind_param("ssss", $currentMonth, $prevMonth1, $prevMonth2, $userCluster);
} else {
    $threeMonthsQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview 
                        WHERE DATE_FORMAT(EntryDate, '%Y-%m') IN (?, ?, ?)
                        ORDER BY EntryDate DESC";
    $stmt = $conn->prepare($threeMonthsQuery);
    $stmt->bind_param("sss", $currentMonth, $prevMonth1, $prevMonth2);
}
$stmt->execute();
$result = $stmt->get_result();

// Initialize counter for three months transactions
$threeMonthsCount = 0;
$firstCurrency = null;

// Process each transaction to calculate totals using appropriate rates
while ($row = $result->fetch_assoc()) {
    $amount = $row['Amount'] ?? 0;
    // Get currency from database, default to 'USD' only if not set
    $currency = !empty($row['currency']) ? $row['currency'] : 'USD';
    
    // Increment transaction counter
    $threeMonthsCount++;
    
    // Capture the currency of the first (most recent) transaction for display
    if ($firstCurrency === null) {
        $firstCurrency = $currency;
        $threeMonthsCurrency = $currency;
    }
    
    // Determine which rates to use
    $effectiveRates = $currencyRates; // Default to cluster rates
    
    // If transaction has custom rates and they're active, use those instead
    if (!empty($row['use_custom_rate']) && $row['use_custom_rate'] == 1) {
        if (!empty($row['usd_to_etb']) && $currency === 'USD') {
            $effectiveRates['USD_to_ETB'] = $row['usd_to_etb'];
        }
        if (!empty($row['eur_to_etb']) && $currency === 'EUR') {
            $effectiveRates['EUR_to_ETB'] = $row['eur_to_etb'];
        }
    }
    
    // Convert amount to ETB using the appropriate rates
    $amountETB = convertCurrency($amount, $currency, 'ETB', $effectiveRates);
    
    // Add to totals
    $threeMonthsSpent += $amount; // This keeps the original currency amounts
    $threeMonthsSpentETB += $amountETB; // This is the total in ETB
}

// For display purposes, use the currency of the most recent transaction
// Otherwise, keep the default USD
if ($threeMonthsSpent > 0 && $firstCurrency !== null) {
    $transactionCurrency = $threeMonthsCurrency;
} else {
    // If no transactions, default to USD
    $transactionCurrency = 'USD';
    $threeMonthsCurrency = 'USD';
}

// Calculate percentage changes - we need to get the data for current and previous months separately
// Current month data with currency and custom rates
$currentSpent = 0;
$currentCount = 0;
$transactionCurrency = null; // Will be set from database

if ($userCluster) {
    $currentMonthQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview WHERE DATE_FORMAT(EntryDate, '%Y-%m') = ? AND cluster = ?";
    $stmt = $conn->prepare($currentMonthQuery);
    $stmt->bind_param("ss", $currentMonth, $userCluster);
} else {
    $currentMonthQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview WHERE DATE_FORMAT(EntryDate, '%Y-%m') = ?";
    $stmt = $conn->prepare($currentMonthQuery);
    $stmt->bind_param("s", $currentMonth);
}
$stmt->execute();
$result = $stmt->get_result();

// Process current month transactions
while ($row = $result->fetch_assoc()) {
    $amount = $row['Amount'] ?? 0;
    // Get currency from database, default to 'USD' only if not set
    $currency = !empty($row['currency']) ? $row['currency'] : 'USD';
    $currentCount++;
    
    // Determine which rates to use
    $effectiveRates = $currencyRates; // Default to cluster rates
    
    // If transaction has custom rates and they're active, use those instead
    if (!empty($row['use_custom_rate']) && $row['use_custom_rate'] == 1) {
        if (!empty($row['usd_to_etb']) && $currency === 'USD') {
            $effectiveRates['USD_to_ETB'] = $row['usd_to_etb'];
        }
        if (!empty($row['eur_to_etb']) && $currency === 'EUR') {
            $effectiveRates['EUR_to_ETB'] = $row['eur_to_etb'];
        }
    }
    
    // Convert amount to ETB using the appropriate rates
    $amountETB = convertCurrency($amount, $currency, 'ETB', $effectiveRates);
    
    // Add to total
    $currentSpent += $amount; // This keeps the original currency amounts
    
    // Set transaction currency (this will be the currency of the last transaction processed)
    $transactionCurrency = $currency;
}

// Previous 3 months data with custom rates
$prevSpent = 0;
$prevCount = 0;

if ($userCluster) {
    $prevMonthsQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview WHERE DATE_FORMAT(EntryDate, '%Y-%m') IN (?, ?, ?) AND cluster = ?";
    $stmt = $conn->prepare($prevMonthsQuery);
    $stmt->bind_param("ssss", $prevMonth1, $prevMonth2, $prevMonth3, $userCluster);
} else {
    $prevMonthsQuery = "SELECT Amount, currency, use_custom_rate, usd_to_etb, eur_to_etb FROM budget_preview WHERE DATE_FORMAT(EntryDate, '%Y-%m') IN (?, ?, ?)";
    $stmt = $conn->prepare($prevMonthsQuery);
    $stmt->bind_param("sss", $prevMonth1, $prevMonth2, $prevMonth3);
}
$stmt->execute();
$result = $stmt->get_result();

// Process previous months transactions
while ($row = $result->fetch_assoc()) {
    $amount = $row['Amount'] ?? 0;
    // Get currency from database, default to 'USD' only if not set
    $currency = !empty($row['currency']) ? $row['currency'] : 'USD';
    $prevCount++;
    
    // Determine which rates to use
    $effectiveRates = $currencyRates; // Default to cluster rates
    
    // If transaction has custom rates and they're active, use those instead
    if (!empty($row['use_custom_rate']) && $row['use_custom_rate'] == 1) {
        if (!empty($row['usd_to_etb']) && $currency === 'USD') {
            $effectiveRates['USD_to_ETB'] = $row['usd_to_etb'];
        }
        if (!empty($row['eur_to_etb']) && $currency === 'EUR') {
            $effectiveRates['EUR_to_ETB'] = $row['eur_to_etb'];
        }
    }
    
    // Convert amount to ETB using the appropriate rates
    $amountETB = convertCurrency($amount, $currency, 'ETB', $effectiveRates);
    
    // Add to total
    $prevSpent += $amount; // This keeps the original currency amounts
}

// Calculate percentage changes
$spentChange = $prevSpent > 0 ? (($currentSpent - $prevSpent) / $prevSpent) * 100 : 0;
$transactionChange = $prevCount > 0 ? (($currentCount - $prevCount) / $prevCount) * 100 : 0;

// Get total budget and actual spent from budget_preview table (filtered by cluster if available)
// As per project specification, we should use data directly from budget_preview table
if ($userCluster) {
    $budgetQuery = "SELECT SUM(OriginalBudget) as total_budget, SUM(ActualSpent) as total_actual_spent FROM budget_preview WHERE cluster = ?";
    $stmt = $conn->prepare($budgetQuery);
    $stmt->bind_param("s", $userCluster);
} else {
    $budgetQuery = "SELECT SUM(OriginalBudget) as total_budget, SUM(ActualSpent) as total_actual_spent FROM budget_preview";
    $stmt = $conn->prepare($budgetQuery);
}
$stmt->execute();
$budgetResult = $stmt->get_result();
$budgetData = $budgetResult->fetch_assoc();
$totalBudget = $budgetData['total_budget'] ?? 0; // Total allocated budget from OriginalBudget column
$totalActualSpent = $budgetData['total_actual_spent'] ?? 0; // Total actually spent from ActualSpent column

// Calculate remaining budget and utilization

$budgetUtilization = $totalBudget > 0 ? ($totalActualSpent / $totalBudget) * 100 : 0;

// Function to determine quarter from date based on database date ranges
function getQuarterFromDate($date, $conn, $year = null, $categoryName = '1. Administrative costs') {
    if (!$year) $year = date('Y', strtotime($date));
    
    // Get user cluster from session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $userCluster = $_SESSION['cluster_name'] ?? null;
    
    $quarterQuery = "SELECT period_name FROM budget_data 
                   WHERE year2 = ? AND category_name = ? 
                   AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4')
                   AND ? BETWEEN start_date AND end_date";
    
    // Add cluster condition if user has a cluster
    if ($userCluster) {
        $quarterQuery .= " AND cluster = ?";
    }
    
    $quarterQuery .= " LIMIT 1";
    
    $stmt = $conn->prepare($quarterQuery);
    
    // Bind parameters based on whether user has a cluster
    if ($userCluster) {
        $stmt->bind_param("isss", $year, $categoryName, $date, $userCluster);
    } else {
        $stmt->bind_param("iss", $year, $categoryName, $date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['period_name'] ?? 'Q1'; // default fallback
}

// Helper function to map category names
function mapCategoryName($category) {
    // Normalize: trim, strip any leading numbering like "1.", and compare case-insensitively
    $original = trim((string)$category);
    $stripped = preg_replace('/^\s*\d+\s*\.\s*/', '', $original); // remove leading number and dot if present
    $key = strtolower($stripped);

    $baseMappings = [
        'administrative costs' => '1. Administrative costs',
        'operational support costs' => '2. Operational support costs',
        'consortium activities' => '3. Consortium Activities',
        'targeting new csos' => '4. Targeting new CSOs',
        'contingency' => '5. Contingency'
    ];

    if (isset($baseMappings[$key])) {
        return $baseMappings[$key];
    }

    // If incoming already looks numbered correctly, keep it
    if (preg_match('/^\s*\d+\s*\./', $original)) {
        return $original;
    }

    // Fallback: return original
    return $original;
}

// Helper function to map subcategory names
function mapSubcategoryName($category) {
    // Keep subcategory aligned with normalized category naming for now
    return mapCategoryName($category);
}

// AJAX Handler for saving transactions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_transaction') {
    error_log('AJAX Handler - Save Transaction Request Received');
    
    // Validate and sanitize inputs
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount <= 0) {
        handleError('Invalid amount', 'Please enter a valid positive amount');
    }
    
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    if (empty($category)) {
        handleError('Invalid category', 'Please select a valid category');
    }
    
    $entryDate = filter_input(INPUT_POST, 'entry_date', FILTER_SANITIZE_STRING);
    if (empty($entryDate) || !DateTime::createFromFormat('Y-m-d', $entryDate)) {
        handleError('Invalid date', 'Please enter a valid date in YYYY-MM-DD format');
    }
    
    $entryDateTime = DateTime::createFromFormat('Y-m-d', $entryDate);
    $entryYear = (int)$entryDateTime->format('Y');

    // Map category to proper name if necessary
    $mappedCategoryName = mapCategoryName($category);
    if (empty($mappedCategoryName)) {
        handleError('Category mapping error', 'Selected category could not be mapped to a valid category name');
    }
    
    // Map category to proper subcategory if necessary
    $mappedSubcategoryName = mapSubcategoryName($category);


    // Start transaction
    if ($conn->begin_transaction()) {
        try {
            // Insert into budget_preview table
            $insertQuery = "INSERT INTO budget_preview (Amount, Category, EntryDate, user_id, cluster, subcategory) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("dsisss", $amount, $mappedCategoryName, $entryDate, $_SESSION['user_id'], $userCluster, $mappedSubcategoryName);
            if ($stmt->execute()) {
                $insertId = $stmt->insert_id;
                error_log('AJAX Handler - Transaction inserted into budget_preview with ID: ' . $insertId);
                
                // Update the budget_data table with proper filtering by date range, cluster, category, and quarter
                error_log('AJAX Handler - Updating budget_data table');
                
                // We already have the mapped category name and quarter from above
                $categoryName = $mappedCategoryName;
                $quarter = $quarterPeriod;
                $year = $entryYear;
                $transactionDate = $entryDateTime->format('Y-m-d');
                
                // Check if there's enough budget available for this transaction with proper filtering
                $budgetCheckQuery = "SELECT budget, actual, forecast, id FROM budget_data 
                                               WHERE year2 = ? AND category_name = ? 
                                               AND period_name = ?
                                               AND ? BETWEEN start_date AND end_date";
                            
                            // Add cluster condition if user has a cluster
                            if ($userCluster) {
                                $budgetCheckQuery .= " AND cluster = ?";
                                $budgetCheckStmt = $conn->prepare($budgetCheckQuery);
                                $budgetCheckStmt->bind_param("issss", $year, $categoryName, $quarter, $transactionDate, $userCluster);
                            } else {
                                $budgetCheckStmt = $conn->prepare($budgetCheckQuery);
                                $budgetCheckStmt->bind_param("isss", $year, $categoryName, $quarter, $transactionDate);
                            }
                            
                            $budgetCheckStmt->execute();
                            $budgetCheckResult = $budgetCheckStmt->get_result();
                            $budgetCheckData = $budgetCheckResult->fetch_assoc();
                            
                            // Get the budget_id for linking to budget_preview
                            $budgetId = $budgetCheckData['id'] ?? null;

                            // Remaining available = budget - actual (handle NULLs) - forecast is future expectation, not committed spending
                            $availableBudget = max((float)($budgetCheckData['budget'] ?? 0) - (float)($budgetCheckData['actual'] ?? 0), 0);
                            
                            // Allow saving transactions even if budget is exceeded
                            // Comment out the budget validation check
                            /*
                            if ($amount > $availableBudget) {
                                handleError('Insufficient budget available', 
                                    "Transaction amount (" . number_format($amount, 2) . ") exceeds available budget (" . number_format($availableBudget, 2) . ") for $categoryName in $quarter $year");
                            }
                            */
                            
                            // Update the quarter row: increase actual by amount, recalc forecast to keep Budget = Actual + Forecast, recompute actual_plus_forecast
                            // MySQL evaluates SET clauses left to right; forecast uses the updated actual value
                            $updateBudgetQuery = "UPDATE budget_data SET 
                                actual = COALESCE(actual, 0) + ?,
                                forecast = GREATEST(COALESCE(budget, 0) - COALESCE(actual, 0), 0),
                                actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                                WHERE year2 = ? AND category_name = ? AND period_name = ?
                                AND ? BETWEEN start_date AND end_date";
                            
                            // Add cluster condition if user has a cluster
                            if ($userCluster) {
                                $updateBudgetQuery .= " AND cluster = ?";
                                $updateStmt = $conn->prepare($updateBudgetQuery);
                                // Params: 1 double (amount), 1 integer (year), 3 strings (categoryName, quarter, transactionDate), 1 string (userCluster)
                                $updateStmt->bind_param("dissss", $amount, $year, $categoryName, $quarter, $transactionDate, $userCluster);
                            } else {
                                $updateStmt = $conn->prepare($updateBudgetQuery);
                                // Params: 1 double (amount), 1 integer (year), 3 strings (categoryName, quarter, transactionDate)
                                $updateStmt->bind_param("disss", $amount, $year, $categoryName, $quarter, $transactionDate);
                            }
                            
                            if ($updateStmt->execute()) {
                                error_log('AJAX Handler - Updated quarter budget and actual amounts');
                                
                                // Update the Annual Total row for this category by summing all quarters with cluster consideration
                                $updateAnnualQuery = "UPDATE budget_data 
                                    SET budget = (
                                        SELECT SUM(COALESCE(budget, 0)) 
                                        FROM budget_data b2 
                                        WHERE b2.year2 = ? AND b2.category_name = ? AND b2.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";

                                // Add cluster condition if user has a cluster (subquery)
                                if ($userCluster) {
                                    $updateAnnualQuery .= " AND b2.cluster = ?";
                                }

                                // Target only the Annual Total row for this category/year (and cluster)
                                $updateAnnualQuery .= ") WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
                                if ($userCluster) {
                                    $updateAnnualQuery .= " AND cluster = ?";
                                    $annualStmt = $conn->prepare($updateAnnualQuery);
                                    // Params: subquery (year, category, cluster), outer where (year, category, cluster)
                                    $annualStmt->bind_param("ississ", $year, $categoryName, $userCluster, $year, $categoryName, $userCluster);
                                } else {
                                    $annualStmt = $conn->prepare($updateAnnualQuery);
                                    // Params: subquery (year, category), outer where (year, category)
                                    $annualStmt->bind_param("isis", $year, $categoryName, $year, $categoryName);
                                }

                                $annualStmt->execute();
                                
                                // Update actual for Annual Total with cluster consideration
                                $updateActualQuery = "UPDATE budget_data 
                                    SET actual = (
                                        SELECT SUM(COALESCE(actual, 0)) 
                                        FROM budget_data b3 
                                        WHERE b3.year2 = ? AND b3.category_name = ? AND b3.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";

                                if ($userCluster) {
                                    $updateActualQuery .= " AND b3.cluster = ?";
                                }

                                $updateActualQuery .= ") WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
                                if ($userCluster) {
                                    $updateActualQuery .= " AND cluster = ?";
                                    $actualStmt = $conn->prepare($updateActualQuery);
                                    $actualStmt->bind_param("ississ", $year, $categoryName, $userCluster, $year, $categoryName, $userCluster);
                                } else {
                                    $actualStmt = $conn->prepare($updateActualQuery);
                                    $actualStmt->bind_param("isis", $year, $categoryName, $year, $categoryName);
                                }

                                $actualStmt->execute();
                                
                                // Sync Annual Total forecast as sum of quarterly forecasts
                                $updateAnnualForecastSumQuery = "UPDATE budget_data 
                                    SET forecast = (
                                        SELECT COALESCE(SUM(forecast), 0) 
                                        FROM budget_data b 
                                        WHERE b.year2 = ? AND b.category_name = ? AND b.period_name IN ('Q1','Q2','Q3','Q4')";
                                if ($userCluster) {
                                    $updateAnnualForecastSumQuery .= " AND b.cluster = ?";
                                }
                                $updateAnnualForecastSumQuery .= ") WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
                                if ($userCluster) {
                                    $updateAnnualForecastSumQuery .= " AND cluster = ?";
                                    $annualForecastSumStmt = $conn->prepare($updateAnnualForecastSumQuery);
                                    $annualForecastSumStmt->bind_param("ississ", $year, $categoryName, $userCluster, $year, $categoryName, $userCluster);
                                } else {
                                    $annualForecastSumStmt = $conn->prepare($updateAnnualForecastSumQuery);
                                    $annualForecastSumStmt->bind_param("isis", $year, $categoryName, $year, $categoryName);
                                }
                                $annualForecastSumStmt->execute();

                                // Update actual_plus_forecast for Annual Total with cluster consideration
                                $updateAnnualForecastQuery = "UPDATE budget_data 
                                    SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                                    WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
                                
                                // Add cluster condition if user has a cluster
                                if ($userCluster) {
                                    $updateAnnualForecastQuery .= " AND cluster = ?";
                                    $annualForecastStmt = $conn->prepare($updateAnnualForecastQuery);
                                    $annualForecastStmt->bind_param("iss", $year, $categoryName, $userCluster);
                                } else {
                                    $annualForecastStmt = $conn->prepare($updateAnnualForecastQuery);
                                    $annualForecastStmt->bind_param("is", $year, $categoryName);
                                }
                                $annualForecastStmt->execute();
                                
                                // Update the Total row across all categories with cluster consideration
                                $updateTotalQuery = "UPDATE budget_data 
                                    SET budget = (
                                        SELECT SUM(COALESCE(budget, 0)) 
                                        FROM budget_data b2 
                                        WHERE b2.year2 = ? AND b2.period_name = 'Annual Total' AND b2.category_name != 'Total'";

                                if ($userCluster) {
                                    $updateTotalQuery .= " AND b2.cluster = ?";
                                }

                                $updateTotalQuery .= ") WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
                                if ($userCluster) {
                                    $updateTotalQuery .= " AND cluster = ?";
                                    $totalBudgetStmt = $conn->prepare($updateTotalQuery);
                                    // Params: subquery (year, cluster), outer where (year, cluster)
                                    $totalBudgetStmt->bind_param("isis", $year, $userCluster, $year, $userCluster);
                                } else {
                                    $totalBudgetStmt = $conn->prepare($updateTotalQuery);
                                    // Params: subquery (year), outer where (year)
                                    $totalBudgetStmt->bind_param("ii", $year, $year);
                                }
                                $totalBudgetStmt->execute();
                                
                                // Update actual for Total with cluster consideration
                                $updateTotalActualQuery = "UPDATE budget_data 
                                    SET actual = (
                                        SELECT SUM(COALESCE(actual, 0)) 
                                        FROM budget_data b3 
                                        WHERE b3.year2 = ? AND b3.period_name = 'Annual Total' AND b3.category_name != 'Total'";

                                if ($userCluster) {
                                    $updateTotalActualQuery .= " AND b3.cluster = ?";
                                }

                                $updateTotalActualQuery .= ") WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
                                if ($userCluster) {
                                    $updateTotalActualQuery .= " AND cluster = ?";
                                    $totalActualStmt = $conn->prepare($updateTotalActualQuery);
                                    $totalActualStmt->bind_param("isis", $year, $userCluster, $year, $userCluster);
                                } else {
                                    $totalActualStmt = $conn->prepare($updateTotalActualQuery);
                                    $totalActualStmt->bind_param("ii", $year, $year);
                                }
                                $totalActualStmt->execute();
                                
                                // Sync Total forecast as sum of Annual Total forecasts across categories
                                $updateTotalForecastSumQuery = "UPDATE budget_data 
                                    SET forecast = (
                                        SELECT COALESCE(SUM(forecast), 0)
                                        FROM budget_data b2 
                                        WHERE b2.year2 = ? AND b2.period_name = 'Annual Total' AND b2.category_name != 'Total'";
                                if ($userCluster) {
                                    $updateTotalForecastSumQuery .= " AND b2.cluster = ?";
                                }
                                $updateTotalForecastSumQuery .= ") WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
                                if ($userCluster) {
                                    $totalForecastSumStmt = $conn->prepare($updateTotalForecastSumQuery);
                                    $totalForecastSumStmt->bind_param("isis", $year, $userCluster, $year, $userCluster);
                                } else {
                                    $totalForecastSumStmt = $conn->prepare($updateTotalForecastSumQuery);
                                    $totalForecastSumStmt->bind_param("ii", $year, $year);
                                }
                                $totalForecastSumStmt->execute();

                                // Update actual_plus_forecast for Total with cluster consideration
                                $updateTotalActualForecastQuery = "UPDATE budget_data 
                                    SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                                    WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
                                
                                // Add cluster condition if user has a cluster
                                if ($userCluster) {
                                    $updateTotalActualForecastQuery .= " AND cluster = ?";
                                    $totalActualForecastStmt = $conn->prepare($updateTotalActualForecastQuery);
                                    $totalActualForecastStmt->bind_param("is", $year, $userCluster);
                                } else {
                                    $totalActualForecastStmt = $conn->prepare($updateTotalActualForecastQuery);
                                    $totalActualForecastStmt->bind_param("i", $year);
                                }
                                $totalActualForecastStmt->execute();
                                
                                // Update actual_plus_forecast for all quarter rows as well with cluster consideration
                                $updateQuarterForecastQuery = "UPDATE budget_data 
                                    SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                                    WHERE year2 = ? AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";
                                
                                // Add cluster condition if user has a cluster
                                if ($userCluster) {
                                    $updateQuarterForecastQuery .= " AND cluster = ?";
                                    $quarterForecastStmt = $conn->prepare($updateQuarterForecastQuery);
                                    $quarterForecastStmt->bind_param("is", $year, $userCluster);
                                } else {
                                    $quarterForecastStmt = $conn->prepare($updateQuarterForecastQuery);
                                    $quarterForecastStmt->bind_param("i", $year);
                                }
                                $quarterForecastStmt->execute();
                                
                                // Calculate and update variance percentages for all rows with cluster consideration
                                // Variance (%) = (Budget − Actual) / Budget × 100
                                $varianceQuery = "UPDATE budget_data 
                                    SET variance_percentage = CASE 
                                        WHEN budget > 0 THEN ROUND(((budget - COALESCE(actual,0)) / budget) * 100, 2)
                                        WHEN budget = 0 AND COALESCE(actual,0) > 0 THEN -100.00
                                        ELSE 0.00 
                                    END
                                    WHERE year2 = ?";
                                
                                // Add cluster condition if user has a cluster
                                if ($userCluster) {
                                    $varianceQuery .= " AND cluster = ?";
                                    $varianceStmt = $conn->prepare($varianceQuery);
                                    $varianceStmt->bind_param("is", $year, $userCluster);
                                } else {
                                    $varianceStmt = $conn->prepare($varianceQuery);
                                    $varianceStmt->bind_param("i", $year);
                                }
                                $varianceStmt->execute();
                                
                                error_log('AJAX Handler - Updated all related budget calculations including budget reduction');
                            } else {
                                error_log('AJAX Handler - Failed to update budget_data: ' . $updateStmt->error);
                            }
                            
                            // Mark budget data as uncertified when new transaction is added with cluster consideration
                            $uncertifyQuery = "UPDATE budget_data SET certified = 'uncertified' WHERE year2 = ?";
                            
                            // Add cluster condition if user has a cluster
                            if ($userCluster) {
                                $uncertifyQuery .= " AND cluster = ?";
                                $uncertifyStmt = $conn->prepare($uncertifyQuery);
                                $uncertifyStmt->bind_param("is", $year, $userCluster);
                            } else {
                                $uncertifyStmt = $conn->prepare($uncertifyQuery);
                                $uncertifyStmt->bind_param("i", $year);
                            }
                            
                            if ($uncertifyStmt->execute()) {
                                error_log('AJAX Handler - Budget marked as uncertified due to new transaction for year: ' . $year);
                            } else {
                                error_log('AJAX Handler - Failed to mark budget as uncertified: ' . $uncertifyStmt->error);
                            }
                            
                            // Update the budget_preview table with the budget_id for proper linking
                            if ($budgetId) {
                                $updatePreviewQuery = "UPDATE budget_preview SET budget_id = ? WHERE PreviewID = ?";
                                $updatePreviewStmt = $conn->prepare($updatePreviewQuery);
                                $updatePreviewStmt->bind_param("ii", $budgetId, $insertId);
                                if ($updatePreviewStmt->execute()) {
                                    error_log('AJAX Handler - Linked budget_preview record to budget_data record with ID: ' . $budgetId);
                                } else {
                                    error_log('AJAX Handler - Failed to link budget_preview to budget_data: ' . $updatePreviewStmt->error);
                                }

                                // Also sync preview financial fields from budget_data
                                $bdStmt = $conn->prepare("SELECT budget, actual, forecast, variance_percentage FROM budget_data WHERE id = ?");
                                $bdStmt->bind_param("i", $budgetId);
                                if ($bdStmt->execute()) {
                                    $bdRes = $bdStmt->get_result();
                                    if ($bdRow = $bdRes->fetch_assoc()) {
                                        $bdBudget = (float)($bdRow['budget'] ?? 0);
                                        $bdActual = (float)($bdRow['actual'] ?? 0);
                                        $bdForecast = (float)($bdRow['forecast'] ?? 0);
                                        $bdVariance = (float)($bdRow['variance_percentage'] ?? 0);

                                        $syncPreviewQuery = "UPDATE budget_preview SET OriginalBudget = ?, RemainingBudget = ?, ActualSpent = ?, ForecastAmount = ?, VariancePercentage = ? WHERE PreviewID = ?";
                                        $syncPreviewStmt = $conn->prepare($syncPreviewQuery);
                                        $syncPreviewStmt->bind_param("dddddi", $bdBudget, $bdForecast, $bdActual, $bdForecast, $bdVariance, $insertId);
                                        if ($syncPreviewStmt->execute()) {
                                            error_log('AJAX Handler - Synced preview financial fields for preview ID: ' . $insertId);
                                        } else {
                                            error_log('AJAX Handler - Failed syncing preview fields: ' . $syncPreviewStmt->error);
                                        }
                                    }
                                } else {
                                    error_log('AJAX Handler - Failed to read budget_data for preview sync: ' . $bdStmt->error);
                                }
                            }
                            
                            $response = [
                                'success' => true, 
                                'message' => 'Transaction saved successfully! ID: ' . $insertId,
                                'transaction_id' => $insertId
                            ];
                            error_log('AJAX Handler - Success: Transaction ID ' . $insertId);
                            
                            // Ensure clean output
                            ob_clean();
                            echo json_encode($response);
                            exit;
                        } else {
                            handleError('Database error', 'Failed to insert transaction into budget_preview: ' . $stmt->error);
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        handleError('Database error', 'Error during transaction: ' . $e->getMessage());
                    }
                } else {
                    handleError('Database error', 'Failed to start transaction');
                }
            }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard | Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap');
        
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7b68ee;
            --accent: #10b981;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --mid-text: #64748b;
            --light-text: #94a3b8;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 5px 10px -5px rgba(0, 0, 0, 0.02);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-bg) 0%, #f1f5f9 100%);
            color: var(--dark-text);
            min-height: 100vh;
        }
        
        .heading-font {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
 .main-content-flex {
    display: flex;
    justify-content: center;
    padding: 2rem 0; /* Removed horizontal padding for mobile */
    min-height: calc(100vh - 80px);
}

@media (min-width: 768px) {
    .main-content-flex {
        padding: 2rem 1rem; /* Original padding for tablets */
    }
}

@media (min-width: 1024px) {
    .main-content-flex {
        padding: 2rem; /* Spacious padding for desktop */
    }
}

@media (min-width: 768px) {
    .main-content-flex {
        padding: 2rem 1rem; /* Original padding for tablets */
    }
}

@media (min-width: 1024px) {
    .main-content-flex {
        padding: 2rem; /* Spacious padding for desktop */
    }
}

        .content-container {
            width: 100%;
           
        }
        /* Remove max-width on mobile to make content full-bleed */
@media (max-width: 767px) {
    .content-container {
        max-width: none;
        padding-left: 0;
        padding-right: 0;
    }
}
/* Remove card padding on mobile for full edge-to-edge look */
@media (max-width: 767px) {
    .glass-card {
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        border-radius: 0; /* Optional: removes rounded corners for a truly flush edge */
    }
}
        
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .modern-border {
            border: 2px solid #2563eb;
            border-radius: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
        }
        
        .form-input {
            transition: all 0.3s;
            border: 2px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            width: 100%;
            font-size: 0.95rem;
            background: #ffffff;
            position: relative;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            background: #fefefe;
        }
        
        .form-input:hover {
            border-color: #cbd5e1;
        }
        
        .form-label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            letter-spacing: 0.025em;
        }
        
        .form-label::after {
            content: ' *';
            color: #ef4444;
            font-weight: 500;
        }
        
        .form-section {
            background: linear-gradient(135deg, #ffffff 0%, #fefefe 100%);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
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
            padding: 1rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(107, 114, 128, 0.2);
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(107, 114, 128, 0.3);
        }
        
        .btn-accent {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        
        .btn-accent:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.7s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .toast {
            animation: toastIn 0.5s ease, toastOut 0.5s ease 2.5s forwards;
        }
        
        @keyframes toastIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes toastOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .table-row {
            transition: all 0.2s;
        }
        
        .table-row:hover {
            background-color: #f8fafc;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #fefefe 100%);
            border-radius: 1.5rem;
            padding: 1.25rem;
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.08), 0 4px 10px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: #2563eb;
            transform: scaleX(0);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: left;
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -10px rgba(37, 99, 235, 0.2), 0 15px 25px -8px rgba(0, 0, 0, 0.1);
            border-color: rgba(37, 99, 235, 0.2);
        }
        
        .stat-card-content {
            position: relative;
            z-index: 2;
        }
        
        .document-btn {
            transition: all 0.3s;
            border: 2px dashed #cbd5e1;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .document-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .document-btn:hover::before {
            left: 100%;
        }
        
        .document-btn:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0.1) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(37, 99, 235, 0.2);
        }
        
        .section-title {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -0.75rem;
            left: 0;
            width: 60%;
            height: 4px;
            background: #2563eb;
            border-radius: 2px;
        }
        
        .section-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.125rem;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #ffffff 0%, #fefefe 100%);
            border-radius: 1.5rem;
            padding: 2.5rem;
            border: 2px solid #2563eb;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #2563eb, #3b82f6, #2563eb);
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { background-position: 200% 0; }
            50% { background-position: -200% 0; }
        }
        
        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .dashboard-icon {
            width: 3.5rem;
            height: 3.5rem;
            background: #2563eb;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 14px 0 rgba(37, 99, 235, 0.3);
        }
        
        .dashboard-subtitle {
            color: #64748b;
            font-size: 1.125rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .metric-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 6px 12px -3px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .metric-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        
        .stat-card:hover .metric-icon::before {
            left: 100%;
        }
        
        .stat-card:hover .metric-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.2);
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 900;
            color: #1e293b;
            margin-top: 0.5rem;
            letter-spacing: -0.05em;
            line-height: 1;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .metric-value {
            color: #2563eb;
            transform: scale(1.05);
        }
        
        .metric-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .metric-label::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #2563eb;
            transition: width 0.3s ease;
        }
        
        .stat-card:hover .metric-label::after {
            width: 40%;
        }
        
        .metric-change {
            margin-top: 1rem;
            padding: 0.5rem 0.75rem;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border-radius: 0.5rem;
            border: 1px solid #bbf7d0;
            position: relative;
            overflow: hidden;
        }
        
        .metric-change::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(34, 197, 94, 0.1), transparent);
            transition: left 0.8s ease;
        }
        
        .stat-card:hover .metric-change::before {
            left: 100%;
        }
        
        .metric-change-text {
            color: #15803d;
            font-size: 0.875rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .metric-change-icon {
            animation: bounce-up 2s ease-in-out infinite;
        }
        
        @keyframes bounce-up {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-4px);
            }
            60% {
                transform: translateY(-2px);
            }
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #f1f5f9;
            border-radius: 1rem;
            margin-top: 1rem;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb 0%, #3b82f6 50%, #2563eb 100%);
            background-size: 200% 100%;
            border-radius: 1rem;
            transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            animation: gradient-flow 3s ease-in-out infinite;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: progress-shine 2.5s ease-in-out infinite;
        }
        
        @keyframes gradient-flow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @keyframes progress-shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .progress-info {
            margin-top: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .progress-text {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 600;
        }
        
        .progress-percentage {
            font-size: 0.875rem;
            color: #2563eb;
            font-weight: 700;
            background: #eff6ff;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
        }
        
        .preview-item {
            display: flex;
            justify-content: between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .preview-label {
            font-weight: 600;
            color: var(--mid-text);
            min-width: 40%;
        }
        
        .preview-value {
            color: var(--dark-text);
            text-align: right;
            flex-grow: 1;
            word-break: break-word;
        }
        
        .doc-preview {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-top: 0.5rem;
            border: 1px dashed #cbd5e1;
        }
        
        .doc-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0;
            color: var(--primary);
            overflow: hidden;
        }
        
        .doc-item span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        
        /* Budget line autocomplete styles */
        #budgetLineAutocomplete {
            z-index: 1000;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
        }
        
        #budgetLineAutocomplete div {
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #edf2f7;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        #budgetLineAutocomplete div:hover,
        #budgetLineAutocomplete div.bg-gray-100 {
            background-color: #f8fafc;
        }
        
        #budgetLineAutocomplete div:last-child {
            border-bottom: none;
        }
        
        #budgetLineAutocomplete span.font-medium {
            color: #2563eb;
        }
        
        #budgetLineAutocomplete span.text-gray-600 {
            color: #64748b;
        }
    </style>
</head>

<body>


<div class="main-content-flex">
    <div class="content-container">
        <div class="glass-card p-6 md:p-8 card-hover animate-fadeIn mb-8">
            <h1 class="text-4xl font-bold heading-font text-gray-800 mb-2 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-chart-pie text-blue-600"></i>
                </div>
                Transaction Center 
            </h1>
            <p class="text-gray-500 text-lg mb-1">Track and manage your financial performance</p>
            
            <div class="stats-container mt-6">
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="metric-label">Total Spent (Past 3 Months)</p>
                                <h3 class="metric-value"><i class="fas fa-money-bill-wave text-green-600 mr-1"></i><?php echo number_format($threeMonthsSpentETB, 2); ?></h3>
                                <p class="text-sm text-gray-500 mt-1">(<?php echo number_format($threeMonthsSpent, 2); ?> <?php echo $threeMonthsCurrency ?? 'USD'; ?>)</p>
                            </div>
                          <div class="metric-icon bg-blue-100 text-blue-600">
    <span class="font-bold text-blue-600">Birr</span>
</div>

                        </div>
                        <div class="metric-change">
                            <div class="metric-change-text">
                                <span>from <?php echo date('F', strtotime('-2 months')); ?> to <?php echo date('F'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
              <div class="stat-card">
    <div class="stat-card-content">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <p class="metric-label">Transactions (Past 3 Months)</p>
                <h3 class="metric-value">
                    <i class="fas fa-exchange-alt text-green-600 mr-1"></i>
                    <?php echo $threeMonthsCount; ?>
                </h3>
            </div>
            <div class="metric-icon bg-green-100 text-green-600">
                <i class="fas fa-exchange-alt"></i>
            </div>
        </div>
        <div class="metric-change">
            <div class="metric-change-text">
                <span>
                    from <?php echo date('F', strtotime('-2 months')); ?> 
                    to <?php echo date('F'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

  
</div>
        </div>

       <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-8">
            <div class="lg:col-span-2">
                <div class="glass-card p-6 md:p-8 card-hover animate-fadeIn mb-8 modern-border h-full flex flex-col">
                    <div class="form-section flex-grow">
                        <h3 class="text-2xl font-semibold text-gray-800 mb-6 section-title">
                            <div class="section-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            Add New Transaction
                        </h3>
                        <form id="transactionForm" class="space-y-6 flex-grow">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label for="budgetHeadingSelect" class="form-label">Budget Heading</label>
                                    <select id="budgetHeadingSelect" class="form-input" required>
                                        <option value="">Select Budget Heading</option>
                                        <option value="Administrative costs">Administrative costs</option>
                                        <option value="Operational support costs">Operational support costs</option>
                                        <option value="Consortium Activities">Consortium Activities</option>
                                        <option value="Targeting new CSOs">Targeting new CSOs</option>
                                        <option value="Contingency">Contingency</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="outcomeInput" class="form-label">Outcome</label>
                                    <input type="text" id="outcomeInput" class="form-input" placeholder="e.g., Project goal achieved" required>
                                </div>
                                <div>
                                    <label for="activityInput" class="form-label">Activity</label>
                                    <input type="text" id="activityInput" class="form-input" placeholder="e.g., Workshop organization" required>
                                </div>
                                <div>
                                    <label for="budgetLineInput" class="form-label">Budget Line</label>
                                    <input type="text" id="budgetLineInput" class="form-input" placeholder="e.g., Travel Expenses" required>
                                </div>
                                <div>
                                    <label for="transactionDescriptionInput" class="form-label">Transaction Description</label>
                                    <input type="text" id="transactionDescriptionInput" class="form-input" placeholder="e.g., Air tickets for training" required>
                                </div>
                                <div>
                                    <label for="partnerInput" class="form-label">Partner</label>
                                    <input type="text" id="partnerInput" class="form-input" placeholder="e.g., ABC Organization" required>
                                </div>
                                <div>
                                    <label for="transactionDateInput" class="form-label">Date</label>
                                    <input type="date" id="transactionDateInput" class="form-input" required>
                                </div>
                                <div>
                                    <label for="amountInput" class="form-label">Amount</label>
                                    <input type="number" id="amountInput" class="form-input" placeholder="e.g., 1500" required>
                                </div>
<?php if (!empty($isCustomCurrencyEnabled) && $isCustomCurrencyEnabled): ?>
					<div id="customRateContainer" class="rounded-xl p-4 mb-4 border border-amber-200 bg-amber-50">
						<div class="flex items-center gap-3 mb-3">
							<input type="checkbox" id="useCustomRate" class="form-checkbox">
							<label for="useCustomRate" class="text-sm text-gray-800">Use custom exchange rate for this entry</label>
						</div>
						<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
							<div>
								<label for="customUsdToEtb" class="form-label">USD→ETB rate</label>
								<input type="number" step="0.0001" id="customUsdToEtb" class="form-input" placeholder="e.g., 315.2500" disabled>
								<div class="text-xs text-gray-600 mt-1">Current: <span id="currentUsdToEtbDisplay"></span></div>
							</div>
							<div>
								<label for="customEurToEtb" class="form-label">EUR→ETB rate</label>
								<input type="number" step="0.0001" id="customEurToEtb" class="form-input" placeholder="e.g., 340.0000" disabled>
								<div class="text-xs text-gray-600 mt-1">Current: <span id="currentEurToEtbDisplay"></span></div>
							</div>
						</div>
					</div>
<?php endif; ?>
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 mb-4">
                                    <p class="text-sm text-gray-600 flex items-center gap-2">
                                        <i class="fas fa-info-circle text-blue-500"></i>
                                        Upload supporting documents to complete your transaction record
                                    </p>
                                </div>
                                <button type="button" id="supportingDocumentsButton" class="document-btn w-full">
                                    <i class="fas fa-paperclip text-lg"></i> 
                                    <span>Attach Supporting Documents</span>
                                    <i class="fas fa-arrow-right ml-auto text-sm opacity-60"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-1">
                <div class="glass-card p-6 md:p-8 card-hover animate-fadeIn h-full">
                    <h3 class="text-2xl font-semibold text-gray-800 mb-6 section-title">Actions</h3>
                    
                    <div class="space-y-4">
                        
      
                        
                        <button type="button" id="clearFormButton" 
    class="btn-secondary w-full flex items-center justify-center gap-2">
    <i class="fas fa-eraser"></i> Clear Form
</button>

                        
                       <button type="button" id="historyButton" 
    onclick="window.location.href='history.php'" 
    class="btn-primary w-full flex items-center justify-center gap-2">
    <i class="fas fa-history"></i> Transaction History
</button>

                        <button type="button" id="importExcelButton" 
    class="btn-accent w-full flex items-center justify-center gap-2">
    <i class="fas fa-file-excel"></i> Import Transactions from Excel
</button>

                    </div>
                    
                    <div class="mt-8 p-6 bg-blue-50 rounded-xl border border-blue-100">
                        <h4 class="font-medium text-blue-800 mb-4 flex items-center gap-2 text-lg">
                            <i class="fas fa-eye"></i> Live Preview
                        </h4>
                        <div class="text-sm">
                            <div class="preview-item">
                                <span class="preview-label">Budget Heading:</span>
                                <span class="preview-value" id="previewHeading">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Outcome:</span>
                                <span class="preview-value" id="previewOutcome">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Activity:</span>
                                <span class="preview-value" id="previewActivity">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Budget Line:</span>
                                <span class="preview-value" id="previewBudgetLine">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Description:</span>
                                <span class="preview-value" id="previewDescription">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Partner:</span>
                                <span class="preview-value" id="previewPartner">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Date:</span>
                                <span class="preview-value" id="previewDate">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Amount:</span>
                                <span class="preview-value" id="previewAmount">--</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Ref Number:</span>
                                <span class="preview-value" id="previewPvNumber">--</span>
                            </div>
                            <div class="preview-item border-none">
                                <span class="preview-label">Documents:</span>
                                <span class="preview-value" id="previewDocuments">--</span>
                            </div>
                            <div id="documentsPreview" class="doc-preview hidden">
                                <!-- Document preview will be inserted here -->
                            </div>
                            <div style="margin-top:10px;"> 
                               <button type="button" id="addTransactionButton" 
    class="btn-accent w-full flex items-center justify-center gap-2">
    <i class="fas fa-save"></i> Save Transaction
</button>
</div>
                             
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card p-6 md:p-8 mt-8 animate-fadeIn">
            <h3 class="text-2xl font-semibold text-gray-800 mb-6 section-title">Recent Transactions</h3>
            
            <div class="overflow-x-auto rounded-xl shadow-sm border border-gray-100">

                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-4 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Budget Heading</th>
                            <th class="py-4 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                            <th class="py-4 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Partner</th>
                            <th class="py-4 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ref Number</th>
                            <th class="py-4 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                            <th class="py-4 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                            <th class="py-4 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transactionTableBody" class="divide-y divide-gray-200">
                        <!-- Database data will be loaded here -->
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6 flex justify-end">
                <a href="history.php" class="text-sm font-medium text-blue-600 hover:text-blue-800 flex items-center gap-2">
                    View all transactions <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" class="fixed bottom-5 right-5 z-50 space-y-2"></div>

<?php if (!$included): ?>
</body>
</html>
<?php endif; ?>

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
    document.addEventListener('DOMContentLoaded', function() {
        // Form & Element selectors
        const budgetHeadingSelect = document.getElementById('budgetHeadingSelect');
        const outcomeInput = document.getElementById('outcomeInput');
        const activityInput = document.getElementById('activityInput');
        const budgetLineInput = document.getElementById('budgetLineInput');
        const transactionDescriptionInput = document.getElementById('transactionDescriptionInput');
        const partnerInput = document.getElementById('partnerInput');
        const transactionDateInput = document.getElementById('transactionDateInput');
        const amountInput = document.getElementById('amountInput');
        const transactionForm = document.getElementById('transactionForm');
        const supportingDocumentsButton = document.getElementById('supportingDocumentsButton');
        const addTransactionButton = document.getElementById('addTransactionButton');
        const clearFormButton = document.getElementById('clearFormButton');
        const toastContainer = document.getElementById('toastContainer');
        
        // Preview elements
        const previewHeading = document.getElementById('previewHeading');
        const previewOutcome = document.getElementById('previewOutcome');
        const previewActivity = document.getElementById('previewActivity');
        const previewBudgetLine = document.getElementById('previewBudgetLine');
        const previewDescription = document.getElementById('previewDescription');
        const previewPartner = document.getElementById('previewPartner');
        const previewDate = document.getElementById('previewDate');
        const previewAmount = document.getElementById('previewAmount');
        const previewPvNumber = document.getElementById('previewPvNumber');
        const previewDocuments = document.getElementById('previewDocuments');
        const documentsPreview = document.getElementById('documentsPreview');
        
        // State variables
        let uploadedDocuments = {};
        let fieldConfigurations = {};
        
        // Set default date to today
        const today = new Date().toISOString().split('T')[0];
        transactionDateInput.value = today;
        
        // --- Functions ---
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            let bgColor = 'bg-green-600';
            let icon = '<i class="fas fa-check-circle mr-2"></i>';
            
            if (type === 'error') {
                bgColor = 'bg-red-600';
                icon = '<i class="fas fa-exclamation-circle mr-2"></i>';
            }
            if (type === 'info') {
                bgColor = 'bg-blue-600';
                icon = '<i class="fas fa-info-circle mr-2"></i>';
            }

            toast.className = `toast p-4 rounded-xl shadow-xl text-white font-medium flex items-center ${bgColor}`;
            toast.innerHTML = `${icon} ${message}`;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Helper function to get field value (works for both input and select)
        function getFieldValue(fieldId) {
            const element = document.getElementById(fieldId);
            return element ? element.value.trim() : '';
        }

        function updateLivePreview() {
            // Get all field values using consistent helper function
            const heading = getFieldValue('budgetHeadingSelect') || '--';
            const outcome = getFieldValue('outcomeInput') || '--';
            const activity = getFieldValue('activityInput') || '--';
            const budgetLine = getFieldValue('budgetLineInput') || '--';
            const description = getFieldValue('transactionDescriptionInput') || '--';
            const partner = getFieldValue('partnerInput') || '--';
            const date = getFieldValue('transactionDateInput') || '--';
            const amountValue = getFieldValue('amountInput');
            // Format amount without automatically adding decimals unless they exist
            // Fix for the rounding issue - preserve exact user input
            const amount = amountValue ? `<i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${amountValue}` : '--';
            
            // Update sidebar preview
            previewHeading.textContent = heading;
            previewOutcome.textContent = outcome;
            previewActivity.textContent = activity;
            previewBudgetLine.textContent = budgetLine;
            previewDescription.textContent = description;
            previewPartner.textContent = partner;
            previewDate.textContent = date;
            previewAmount.innerHTML = amount;
            
            // Check budget availability if amount and other required fields are filled
            // Use debouncing to prevent multiple rapid calls
            clearTimeout(window.budgetCheckTimeout);
            window.budgetCheckTimeout = setTimeout(checkBudgetAvailability, 500);
            
            // Update PV Number preview
            const pvNumber = uploadedDocuments.pvNumber || '--';
            previewPvNumber.textContent = pvNumber;
            
            // Update documents preview
            let documentCount = 0;
            let docHtml = '';
            
            // Check different document sources in priority order
            const hasActiveDocuments = uploadedDocuments.documents && Object.keys(uploadedDocuments.documents).length > 0;
            const hasUploadedFiles = uploadedDocuments.uploadedFiles && uploadedDocuments.uploadedFiles.length > 0;
            const hasPersistentNames = uploadedDocuments.documentNames && Object.keys(uploadedDocuments.documentNames).length > 0;
            
            if (hasActiveDocuments) {
                // Use active documents if available (same session)
                documentCount = Object.keys(uploadedDocuments.documents).length;
                
                for (const docName in uploadedDocuments.documents) {
                    docHtml += `<div class="doc-item">
                        <i class="fas fa-file-pdf text-red-500"></i>
                        <span class="text-sm">${docName}</span>
                    </div>`;
                }
            } else if (hasUploadedFiles) {
                // Use uploaded files info (files are on server)
                documentCount = uploadedDocuments.uploadedFiles.length;
                
                uploadedDocuments.uploadedFiles.forEach(file => {
                    docHtml += `<div class="doc-item">
                        <i class="fas fa-file-pdf text-green-500"></i>
                        <span class="text-sm">${file.documentType}: ${file.originalName} ✓</span>
                    </div>`;
                });
            } else if (hasPersistentNames) {
                // Use persistent document names if files haven't been uploaded yet
                documentCount = Object.keys(uploadedDocuments.documentNames).length;
                
                for (const docType in uploadedDocuments.documentNames) {
                    const fileName = uploadedDocuments.documentNames[docType];
                    docHtml += `<div class="doc-item">
                        <i class="fas fa-file-pdf text-orange-500"></i>
                        <span class="text-sm">${docType}: ${fileName} (pending upload)</span>
                    </div>`;
                }
            }
            
            if (documentCount > 0) {
                previewDocuments.textContent = `${documentCount} document(s)`;
                documentsPreview.classList.remove('hidden');
                documentsPreview.innerHTML = docHtml;
            } else {
                previewDocuments.textContent = '--';
                documentsPreview.classList.add('hidden');
            }
        }
        
        // Add a variable to track the last budget check parameters
        let lastBudgetCheck = {
            heading: '',
            amount: 0,
            date: ''
        };
        
        function checkBudgetAvailability() {
            // Get field values using consistent helper function
            const budgetHeading = getFieldValue('budgetHeadingSelect');
            const amountValue = getFieldValue('amountInput');
            const amountETB = parseFloat(amountValue) || 0; // Amount entered by user is in ETB
            const date = getFieldValue('transactionDateInput');
            
            // Check if parameters have changed since last check
            if (lastBudgetCheck.heading === budgetHeading && 
                lastBudgetCheck.amount === amountETB && 
                lastBudgetCheck.date === date) {
                return; // Skip if nothing has changed
            }
            
            // Update last check parameters
            lastBudgetCheck.heading = budgetHeading;
            lastBudgetCheck.amount = amountETB;
            lastBudgetCheck.date = date;
            
            // Clear previous budget warnings
            const existingWarning = document.getElementById('budgetWarning');
            if (existingWarning) {
                existingWarning.remove();
            }
            
            // Reset budget check result
            window.budgetCheckResult = null;
            
            if (budgetHeading && amountETB > 0 && date) {
                // Create budget check request - let server determine the quarter
                const formData = new FormData();
                formData.append('action', 'check_budget');
                formData.append('budgetHeading', budgetHeading);
                formData.append('amount', amountETB);
				formData.append('date', date); // Send full date instead of calculated quarter
				// If custom rate is enabled, include USD/EUR overrides so server can use them
				const useCustomRateEl = document.getElementById('useCustomRate');
				const customUsdToEtbEl = document.getElementById('customUsdToEtb');
				const customEurToEtbEl = document.getElementById('customEurToEtb');
				if (useCustomRateEl && useCustomRateEl.checked) {
					let appended = false;
					if (customUsdToEtbEl) {
						const usd = parseFloat(customUsdToEtbEl.value);
						if (usd && usd > 0) { formData.append('usd_to_etb', String(usd)); appended = true; }
					}
					if (customEurToEtbEl) {
						const eur = parseFloat(customEurToEtbEl.value);
						if (eur && eur > 0) { formData.append('eur_to_etb', String(eur)); appended = true; }
					}
					if (appended) formData.append('use_custom_rate', '1');
				}
                formData.append('year', new Date(date).getFullYear());
                
                fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.budget_available !== undefined) {
                        const amountInput = document.getElementById('amountInput');
                        
                        // Get currency rates for conversion
                        const currencyRates = window.currencyRates || { 'USD_to_ETB': 55.0000, 'ETB_to_USD': 1/55.0000 };
                        
                        // Convert entered amount (ETB) to USD for comparison
                        const amountUSD = amountETB / (currencyRates.USD_to_ETB || 55.0000);
                        
                        window.budgetCheckResult = {
                            available: data.budget_available,
                            available_etb: data.budget_available_etb,
                            entered_etb: amountETB,
                            entered_usd: amountUSD,
                            exceeded: amountUSD > data.budget_available
                        };
                        
                        if (amountUSD > data.budget_available) {
                            // Show budget warning
                            const warning = document.createElement('div');
                            warning.id = 'budgetWarning';
                            warning.className = 'mt-2 p-3 bg-red-100 border border-red-300 rounded-lg text-sm text-red-800';
                            // Use the actual currency from the data instead of hardcoded currencies
                            const budgetCurrency = data.currency || 'ETB';
                            let warningMessage = `
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Budget Warning:</strong> Amount (<i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${amountETB.toLocaleString()} ETB`;
                            
                            // Add the currency-specific amount if it's not ETB
                            if (budgetCurrency !== 'ETB') {
                                warningMessage += ` / <i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${amountUSD.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} USD`;
                            }
                            
                            warningMessage += `) exceeds available budget (<i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${data.budget_available_etb.toLocaleString()} ETB`;
                            
                            // Add the currency-specific budget if it's not ETB
                            if (budgetCurrency !== 'ETB') {
                                warningMessage += ` / <i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${data.budget_available.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${budgetCurrency}`;
                            }
                            
                            warningMessage += `) for ${data.category || budgetHeading} in ${data.quarter} (${data.date_range}).`;
                            warning.innerHTML = warningMessage;
                            amountInput.parentNode.appendChild(warning);
                            amountInput.style.borderColor = '#ef4444';
                        } else {
                            amountInput.style.borderColor = '#10b981'; // Green border for valid amount
                            // Show success indicator
                            const success = document.createElement('div');
                            success.id = 'budgetWarning';
                            success.className = 'mt-2 p-3 bg-green-100 border border-green-300 rounded-lg text-sm text-green-800';
                            // Use the actual currency from the data instead of hardcoded ETB/USD
                            const budgetCurrency = data.currency || 'ETB';
                            let successMessage = `
                                <i class="fas fa-check-circle mr-2"></i>
                                <strong>Budget OK:</strong> <i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${data.budget_available_etb.toLocaleString()} ETB`;
                            
                            // Add the currency-specific amount if it's not ETB
                            if (budgetCurrency !== 'ETB') {
                                successMessage += ` (<i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${data.budget_available.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${budgetCurrency})`;
                            }
                            
                            successMessage += ` available in ${data.quarter} for ${data.display_category || budgetHeading}.`;
                            success.innerHTML = successMessage;
                            // Only append if there's no existing success message
                            if (!document.getElementById('budgetWarning')) {
                                amountInput.parentNode.appendChild(success);
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Budget check error:', error);
                });
            }
        }

        function clearForm() {
            transactionForm.reset();
            // Set date to today again after reset
            transactionDateInput.value = today;
            localStorage.removeItem('tempFormData');
            localStorage.removeItem('uploadedDocuments');
            uploadedDocuments = {};
            updateLivePreview();
            showToast('Form has been cleared', 'info');
            // Clear the saved form data reference
            window.savedFormData = null;
        }

        function addTransactionToTable(data) {
            const newRow = document.createElement('tr');
            newRow.classList.add('table-row');
            
            const amountFormatted = data.amount ? `<i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2})}` : '--';
            const description = data.description || data.transactionDescription || '--';
            const date = data.entryDate || data.date || '--';
            const refNumber = data.pvNumber || '--';

            newRow.innerHTML = `
                <td class="py-4 px-4 text-sm font-medium text-gray-900">${data.budgetHeading}</td>
                <td class="py-4 px-4 text-sm text-gray-600">${description}</td>
                <td class="py-4 px-4 text-sm text-gray-600">${data.partner}</td>
                <td class="py-4 px-4 text-sm text-gray-600">${refNumber}</td>
                <td class="py-4 px-4 text-sm text-gray-600">${date}</td>
                <td class="py-4 px-4 text-sm text-gray-600">${amountFormatted}</td>
                <td class="py-4 px-4 text-sm text-gray-600">
                    <button class="text-blue-600 hover:text-blue-800" onclick="viewTransaction(${data.PreviewID})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            `;
            
            const transactionTableBody = document.getElementById('transactionTableBody');
            transactionTableBody.insertBefore(newRow, transactionTableBody.children[0]);
        }
        
        function loadRecentTransactions() {
            // Calculate dates for past 3 months
            const currentDate = new Date();
            const currentMonth = currentDate.toISOString().slice(0, 7); // YYYY-MM
            const prevMonth1 = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1).toISOString().slice(0, 7);
            const prevMonth2 = new Date(currentDate.getFullYear(), currentDate.getMonth() - 2, 1).toISOString().slice(0, 7);
            
            const formData = new FormData();
            formData.append('action', 'get_transactions');
            formData.append('months', JSON.stringify([currentMonth, prevMonth1, prevMonth2]));
            
            fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const transactionTableBody = document.getElementById('transactionTableBody');
                    
                    // Clear existing rows
                    transactionTableBody.innerHTML = '';
                    
                    // Add transactions from database
                    data.transactions.forEach(transaction => {
                        const row = document.createElement('tr');
                        row.classList.add('table-row');
                        
                        // Convert amount to ETB for display using the actual transaction currency
                        let amountFormatted = '--';
                        if (transaction.Amount) {
                            const amount = parseFloat(transaction.Amount);
                            const transactionCurrency = transaction.currency || 'USD'; // Get actual currency or default to USD
                            
                            // Check if transaction has custom rates that should be used
                            let currencyRates = window.currencyRates || { 'USD_to_ETB': 55.0000, 'EUR_to_ETB': 60.0000 };
                            
                            // If transaction has custom rates and use_custom_rate is 1, use those instead
                            if (transaction.use_custom_rate == '1' || transaction.use_custom_rate == 1) {
                                // Use the custom rates stored with the transaction
                                if (transaction.usd_to_etb) {
                                    currencyRates.USD_to_ETB = parseFloat(transaction.usd_to_etb);
                                }
                                if (transaction.eur_to_etb) {
                                    currencyRates.EUR_to_ETB = parseFloat(transaction.eur_to_etb);
                                }
                            } else {
                                // Use currency rates from server if available
                                currencyRates = transaction.currency_rates || currencyRates;
                            }
                            
                            // Convert to ETB based on the actual currency
                            let amountETB = amount;
                            if (transactionCurrency === 'USD') {
                                amountETB = amount * (currencyRates.USD_to_ETB || 55.0000);
                            } else if (transactionCurrency === 'EUR') {
                                amountETB = amount * (currencyRates.EUR_to_ETB || 60.0000);
                            }
                            
                            // Format the display to show both ETB and original currency
                            amountFormatted = `<i class="fas fa-money-bill-wave text-green-600 mr-1"></i>${amountETB.toLocaleString('en-US', {minimumFractionDigits: 2})} <span class="text-xs text-gray-500">(${transactionCurrency}: ${amount.toLocaleString('en-US', {minimumFractionDigits: 2})})</span>`;
                        }
                        const refNumber = transaction.PVNumber || '--';
                        
                        row.innerHTML = `
                            <td class="py-4 px-4 text-sm font-medium text-gray-900">${transaction.BudgetHeading || '--'}</td>
                            <td class="py-4 px-4 text-sm text-gray-600">${transaction.Description || '--'}</td>
                            <td class="py-4 px-4 text-sm text-gray-600">${transaction.Partner || '--'}</td>
                            <td class="py-4 px-4 text-sm text-gray-600">${refNumber}</td>
                            <td class="py-4 px-4 text-sm text-gray-600">${transaction.EntryDate || '--'}</td>
                            <td class="py-4 px-4 text-sm text-gray-600">${amountFormatted}</td>
                            <td class="py-4 px-4 text-sm text-gray-600">
                                <button class="text-blue-600 hover:text-blue-800" onclick="viewTransaction(${transaction.PreviewID})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        `;
                        
                        transactionTableBody.appendChild(row);
                    });
                } else {
                    console.error('Failed to load transactions:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading transactions:', error);
            });
        }
        
        function viewTransaction(id) {
            // Redirect to transaction_detail.php with the transaction ID
            window.location.href = 'transaction_detail.php?id=' + id;
        }

        // --- Event Listeners ---

        // Real-time preview updates
        const formInputs = [
            budgetHeadingSelect, outcomeInput, activityInput, budgetLineInput, 
            transactionDescriptionInput, partnerInput, transactionDateInput, amountInput
        ];
        
        formInputs.forEach(input => {
            if (input) {  // Check if element exists before adding listener
                input.addEventListener('input', updateLivePreview);
            }
        });

        // Redirect to documents.php
        supportingDocumentsButton.addEventListener('click', function() {
            if (transactionForm.checkValidity()) {
                const temporaryFormData = {
                    budgetHeading: getFieldValue('budgetHeadingSelect'),
                    outcome: getFieldValue('outcomeInput'),
                    activity: getFieldValue('activityInput'),
                    budgetLine: getFieldValue('budgetLineInput'),
                    transactionDescription: getFieldValue('transactionDescriptionInput'),
                    partner: getFieldValue('partnerInput'),
                    date: getFieldValue('transactionDateInput'),
                    amount: getFieldValue('amountInput')
                };
                localStorage.setItem('tempFormData', JSON.stringify(temporaryFormData));
                // Preserve the form data when navigating to documents page
                localStorage.setItem('preserveFormData', 'true');
                window.location.href = 'documents.php';
            } else {
                showToast('Please fill in all transaction fields before adding documents.', 'error');
            }
        });

        // Add transaction button handler
        addTransactionButton.addEventListener('click', function() {
            // Get form data using helper function for consistency
            const formData = {
                budgetHeading: getFieldValue('budgetHeadingSelect').trim(),
                outcome: getFieldValue('outcomeInput').trim(),
                activity: getFieldValue('activityInput').trim(),
                budgetLine: getFieldValue('budgetLineInput').trim(),
                description: getFieldValue('transactionDescriptionInput').trim(),
                partner: getFieldValue('partnerInput').trim(),
                entryDate: getFieldValue('transactionDateInput'),
                amount: getFieldValue('amountInput')
            };
            
            // Check if all required fields are filled
            const missingFields = [];
            if (!formData.budgetHeading) missingFields.push('Budget Heading');
            if (!formData.outcome) missingFields.push('Outcome');
            if (!formData.activity) missingFields.push('Activity');
            if (!formData.budgetLine) missingFields.push('Budget Line');
            if (!formData.description) missingFields.push('Transaction Description');
            if (!formData.partner) missingFields.push('Partner');
            if (!formData.entryDate) missingFields.push('Date');
            if (!formData.amount || parseFloat(formData.amount) <= 0) missingFields.push('Amount');
            
            if (missingFields.length > 0) {
                showToast('Missing required fields: ' + missingFields.join(', '), 'error');
                return;
            }
            
            // Allow saving transactions even if budget is exceeded
            // Comment out the budget validation check
            /*
            // Check if budget is exceeded
            const amount = parseFloat(formData.amount);
            if (window.budgetCheckResult && window.budgetCheckResult.exceeded) {
                showToast('Transaction amount exceeds available budget. Please reduce the amount.', 'error');
                return;
            }
            */
            
            // Get saved documents data
            const savedDocs = JSON.parse(localStorage.getItem('uploadedDocuments'));
            
            // Allow saving even without documents, but show a warning
            if (!savedDocs || (!savedDocs.uploadedFiles && !savedDocs.documentNames)) {
                if (!confirm('No supporting documents have been uploaded. Do you want to save the transaction anyway?')) {
                    return;
                }
            }
            
            // Create FormData for AJAX request
            const ajaxFormData = new FormData();
            ajaxFormData.append('action', 'save_transaction');
            ajaxFormData.append('budgetHeading', formData.budgetHeading);
            ajaxFormData.append('outcome', formData.outcome);
            ajaxFormData.append('activity', formData.activity);
            ajaxFormData.append('budgetLine', formData.budgetLine);
            ajaxFormData.append('description', formData.description);
            ajaxFormData.append('partner', formData.partner);
            ajaxFormData.append('entryDate', formData.entryDate);
            ajaxFormData.append('amount', formData.amount);
			// If custom rate is enabled, include USD/EUR overrides so server can use them
			const useCustomRateEl = document.getElementById('useCustomRate');
			const customUsdToEtbEl = document.getElementById('customUsdToEtb');
			const customEurToEtbEl = document.getElementById('customEurToEtb');
			if (useCustomRateEl && useCustomRateEl.checked) {
				let appended = false;
				if (customUsdToEtbEl) {
					const usd = parseFloat(customUsdToEtbEl.value);
					if (usd && usd > 0) { ajaxFormData.append('usd_to_etb', String(usd)); appended = true; }
				}
				if (customEurToEtbEl) {
					const eur = parseFloat(customEurToEtbEl.value);
					if (eur && eur > 0) { ajaxFormData.append('eur_to_etb', String(eur)); appended = true; }
				}
				if (appended) ajaxFormData.append('use_custom_rate', '1');
			}
            
            if (savedDocs && savedDocs.pvNumber) {
                ajaxFormData.append('pvNumber', savedDocs.pvNumber);
            }
            
            // Handle uploaded file paths (files already on server)
            if (savedDocs && savedDocs.uploadedFiles && savedDocs.uploadedFiles.length > 0) {
                ajaxFormData.append('uploadedFilePaths', JSON.stringify(savedDocs.uploadedFiles));
            }
            
            // Show loading state
            addTransactionButton.disabled = true;
            addTransactionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            // Debug: Log what we're sending
            console.log('Form data being sent:', {
                budgetHeading: formData.budgetHeading,
                outcome: formData.outcome,
                activity: formData.activity,
                budgetLine: formData.budgetLine,
                description: formData.description,
                partner: formData.partner,
                entryDate: formData.entryDate,
                amount: formData.amount,
                pvNumber: savedDocs ? savedDocs.pvNumber : 'none',
                documents: savedDocs ? Object.keys(savedDocs.documents || {}).length : 0
            });
            
            // Send AJAX request
            fetch('ajax_handler.php', {
                method: 'POST',
                body: ajaxFormData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text(); // Get as text first to see raw response
            })
            .then(text => {
                console.log('Raw response:', text);
                
                // Check if response starts with valid JSON
                const trimmedText = text.trim();
                if (!trimmedText.startsWith('{') && !trimmedText.startsWith('[')) {
                    throw new Error('Invalid JSON response: ' + trimmedText.substring(0, 100));
                }
                
                try {
                    const data = JSON.parse(trimmedText);
                    console.log('Parsed response:', data);
                    if (data.success) {
                        addTransactionToTable(formData);
                        clearForm();
                        showToast(data.message, 'success');
                        loadRecentTransactions(); // Refresh the table
                        
                        // Refresh the page after 2 seconds to update budget metrics
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        let errorMessage = data.message || 'Unknown error occurred';
                        if (data.debug) {
                            console.error('Server debug info:', data.debug);
                            if (typeof data.debug === 'string') {
                                // Check if this is a budget validation error
                                if (data.debug.includes('exceeds available budget')) {
                                    errorMessage = data.debug; // Show the detailed budget error
                                } else {
                                    errorMessage += ' (Debug: ' + data.debug + ')';
                                }
                            } else if (data.debug.file) {
                                errorMessage += ' (Error in: ' + data.debug.file + ':' + data.debug.line + ')';
                            }
                        }
                        showToast(errorMessage, 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Raw server response:', text);
                    showToast('Server returned invalid response format', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while saving the transaction.', 'error');
            })
            .finally(() => {
                // Reset button state
                addTransactionButton.disabled = false;
                addTransactionButton.innerHTML = '<i class="fas fa-save"></i> Save Transaction';
            });
        });
        
        // Clear form button handler
        clearFormButton.addEventListener('click', function() {
            clearForm();
        });

        // Load predefined field configurations
        function loadFieldConfigurations() {
            const fields = ['BudgetHeading', 'Outcome', 'Activity', 'BudgetLine', 'Partner', 'Amount'];
            
            fields.forEach(fieldName => {
                // Prepare URL-encoded data with cluster information
                let bodyData = `action=get_field_config&field_name=${encodeURIComponent(fieldName)}`;
                
                // Pass user cluster if available
                const userCluster = <?php echo json_encode($userCluster); ?>;
                if (userCluster) {
                    bodyData += `&cluster_name=${encodeURIComponent(userCluster)}`;
                }
                
                fetch('admin_fields_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: bodyData
                })
                .then(response => response.json())
                .then(data => {
                    console.log(`Field config loaded for ${fieldName}:`, data);
                    if (data.success) {
                        fieldConfigurations[fieldName] = data.field;
                        setupField(fieldName, data.field);
                    }
                })
                .catch(error => {
                    console.error(`Error loading ${fieldName} config:`, error);
                    
                    // For Budget Heading, if not in admin, use default values
                    if (fieldName === 'BudgetHeading') {
                        const defaultBudgetHeadingConfig = {
                            field_name: 'BudgetHeading',
                            field_type: 'dropdown',
                            field_values: 'Administrative costs,Operational support costs,Consortium Activities,Targeting new CSOs,Contingency,TestValue',
                            is_active: 1,
                            values_array: ['Administrative costs', 'Operational support costs', 'Consortium Activities', 'Targeting new CSOs', 'Contingency', 'TestValue']
                        };
                        fieldConfigurations[fieldName] = defaultBudgetHeadingConfig;
                        setupField(fieldName, defaultBudgetHeadingConfig);
                    }
                    
                    // For Outcome and other fields, use reasonable defaults but don't force input type
                    if (fieldName === 'Outcome') {
                        // Don't force input type, let it use default configuration or remain unchanged
                        console.log('Outcome field config load failed, keeping existing configuration');
                    }
                });
            });
        }
        
        // Setup field based on configuration
        function setupField(fieldName, config) {
            console.log(`Setting up field ${fieldName}:`, config);
            const fieldMap = {
                'BudgetHeading': 'budgetHeadingSelect',
                'Outcome': 'outcomeInput',
                'Activity': 'activityInput',
                'BudgetLine': 'budgetLineInput', 
                'Partner': 'partnerInput',
                'Amount': 'amountInput'
            };
            
            const elementId = fieldMap[fieldName];
            const element = document.getElementById(elementId);
            
            if (!element || !config.is_active) return;
            
            if (config.field_type === 'dropdown' && config.values_array && config.values_array.length > 0) {
                console.log(`Converting ${fieldName} to dropdown with values:`, config.values_array);
                // For Budget Heading, update the existing select options
                if (fieldName === 'BudgetHeading') {
                    // Clear existing options except the first one (default option)
                    while (element.options.length > 1) {
                        element.remove(1);
                    }
                    
                    // Add configured options without numbering to match database values
                    config.values_array.forEach((value) => {
                        const option = document.createElement('option');
                        option.value = value.trim(); // Trim whitespace
                        // No numbering to match database values
                        option.textContent = value.trim();
                        element.appendChild(option);
                    });
                } else {
                    // Check if element is already a select
                    if (element.tagName === 'SELECT') {
                        console.log(`${fieldName} is already a select, updating options`);
                        // Update existing select options
                        // Clear existing options except the first one
                        while (element.options.length > 1) {
                            element.remove(1);
                        }
                        
                        // Add configured options
                        config.values_array.forEach(value => {
                            const option = document.createElement('option');
                            option.value = value.trim(); // Trim whitespace
                            option.textContent = value.trim();
                            element.appendChild(option);
                        });
                    } else {
                        console.log(`${fieldName} is an input, converting to select`);
                        // Convert input to dropdown for other fields
                        const parent = element.parentNode;
                        
                        const select = document.createElement('select');
                        select.id = elementId;
                        select.className = element.className;
                        select.required = element.required;
                        
                        // Add default option
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.textContent = `Select ${fieldName}...`;
                        select.appendChild(defaultOption);
                        
                        // Add configured options
                        config.values_array.forEach(value => {
                            const option = document.createElement('option');
                            option.value = value.trim(); // Trim whitespace
                            option.textContent = value.trim();
                            select.appendChild(option);
                        });
                        
                        // Replace input with select
                        parent.replaceChild(select, element);
                        
                        // Update event listeners
                        select.addEventListener('input', updateLivePreview);
                        
                        // Update form inputs array reference
                        const inputIndex = formInputs.findIndex(input => input && input.id === elementId);
                        if (inputIndex !== -1) {
                            formInputs[inputIndex] = select;
                        }
                        
                        // Update global reference
                        if (fieldName === 'Outcome') outcomeInput = select;
                        else if (fieldName === 'Activity') activityInput = select;
                        else if (fieldName === 'BudgetLine') budgetLineInput = select;
                        else if (fieldName === 'Partner') partnerInput = select;
                        else if (fieldName === 'Amount') amountInput = select;
                    }
                }
            } else if (config.field_type === 'input') {
                console.log(`Setting ${fieldName} as input field`);
                // Check if element is currently a select
                if (element.tagName === 'SELECT') {
                    console.log(`Converting ${fieldName} from select to input`);
                    // Convert dropdown back to input
                    const parent = element.parentNode;
                    
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.id = elementId;
                    input.className = element.className;
                    input.required = element.required;
                    
                    // Set placeholder and value from config
                    if (config.field_values) {
                        input.placeholder = config.field_values;
                        input.readOnly = true;
                        input.value = config.field_values;
                    } else {
                        input.placeholder = `Enter ${fieldName}...`;
                        input.readOnly = false;
                    }
                    
                    // Replace select with input
                    parent.replaceChild(input, element);
                    
                    // Update event listeners
                    input.addEventListener('input', updateLivePreview);
                    
                    // Update form inputs array reference
                    const inputIndex = formInputs.findIndex(input => input && input.id === elementId);
                    if (inputIndex !== -1) {
                        formInputs[inputIndex] = input;
                    }
                    
                    // Update global reference
                    if (fieldName === 'Outcome') outcomeInput = input;
                    else if (fieldName === 'Activity') activityInput = input;
                    else if (fieldName === 'BudgetLine') budgetLineInput = input;
                    else if (fieldName === 'Partner') partnerInput = input;
                    else if (fieldName === 'Amount') amountInput = input;
                } else {
                    // For input fields, set placeholder text if predefined text exists
                    if (config.field_values) {
                        element.placeholder = config.field_values;
                        // Make the field read-only if it has predefined data
                        element.readOnly = true;
                        element.value = config.field_values;
                    } else {
                        // Default placeholder if no predefined text
                        element.placeholder = `Enter ${fieldName}...`;
                        // Make the field editable if no predefined data
                        element.readOnly = false;
                        element.value = ''; // Clear any existing value
                    }
                }
            }
        }

        // Initial setup on page load
        const savedData = JSON.parse(localStorage.getItem('tempFormData'));
        const savedDocs = JSON.parse(localStorage.getItem('uploadedDocuments'));

        // Store saved data in a global variable for easier access
        if (savedData) {
            window.savedFormData = savedData;
        }

        if (savedDocs) {
            uploadedDocuments = savedDocs;
        }

        // Initialize budget line autocomplete
        setupBudgetLineAutocomplete();
        
        // Clear the preserveFormData flag after we've restored the data
        const preserveFormData = localStorage.getItem('preserveFormData') === 'true';
        if (preserveFormData) {
            localStorage.removeItem('preserveFormData');
        }
        
        // Helper function to set field value (works for both input and select)
        function setFieldValue(fieldId, value) {
            const element = document.getElementById(fieldId);
            if (element) {
                // Special handling for select elements to ensure options are available
                if (element.tagName === 'SELECT') {
                    // Wait for options to be populated if it's a select element
                    if (element.options.length > 1 || value === '') {
                        element.value = value || '';
                        return true;
                    } else {
                        // Options not loaded yet
                        return false;
                    }
                } else {
                    element.value = value || '';
                    return true;
                }
            }
            return false;
        }
        
        // Enhanced function to restore form data
        function restoreFormData() {
            // Check if we have saved form data
            if (window.savedFormData) {
                const data = window.savedFormData;
                let allFieldsSet = true;
                
                // Handle budget heading (could be select or input)
                if (budgetHeadingSelect && data.budgetHeading) {
                    // Wait for options to be populated if it's a select element
                    if (budgetHeadingSelect.tagName === 'SELECT') {
                        // Check if options are loaded
                        if (budgetHeadingSelect.options.length > 1) {
                            budgetHeadingSelect.value = data.budgetHeading || '';
                        } else {
                            // Options not loaded yet, try again
                            allFieldsSet = false;
                        }
                    } else {
                        budgetHeadingSelect.value = data.budgetHeading || '';
                    }
                }
                
                // Handle other form fields using consistent helper function
                if (data.outcome !== undefined) {
                    if (!setFieldValue('outcomeInput', data.outcome)) allFieldsSet = false;
                }
                if (data.activity !== undefined) {
                    if (!setFieldValue('activityInput', data.activity)) allFieldsSet = false;
                }
                if (data.budgetLine !== undefined) {
                    if (!setFieldValue('budgetLineInput', data.budgetLine)) allFieldsSet = false;
                }
                if (data.transactionDescription !== undefined) {
                    if (!setFieldValue('transactionDescriptionInput', data.transactionDescription)) allFieldsSet = false;
                }
                if (data.partner !== undefined) {
                    if (!setFieldValue('partnerInput', data.partner)) allFieldsSet = false;
                }
                if (data.date !== undefined) {
                    if (!setFieldValue('transactionDateInput', data.date)) allFieldsSet = false;
                }
                if (data.amount !== undefined) {
                    if (!setFieldValue('amountInput', data.amount)) allFieldsSet = false;
                }
                
                // If all fields were successfully set, update preview
                if (allFieldsSet) {
                    updateLivePreview();
                    console.log('Form data restored:', data);
                    return true;
                } else {
                    // Some fields weren't ready, try again
                    return false;
                }
            }
            return false;
        }
        
        // Enhanced function to safely restore form data after field configurations are loaded
        function safeRestoreFormData() {
            // Try to restore immediately
            restoreFormData();
            
            // Also try after a delay to ensure all fields are properly configured
            setTimeout(() => {
                restoreFormData();
            }, 100);
        }
        
        // Load field configurations first, then update preview
        loadFieldConfigurations();
        
        // Restore form data with multiple attempts to handle timing issues
        function multiAttemptRestore() {
            let attempts = 0;
            const maxAttempts = 15; // Increase attempts to ensure enough time for field configs
            
            function attemptRestore() {
                attempts++;
                try {
                    if (window.savedFormData) {
                        if (restoreFormData()) {
                            console.log(`Form data restored on attempt ${attempts}`);
                            return; // Success, stop trying
                        }
                    }
                } catch (error) {
                    console.error('Error restoring form data:', error);
                }
                
                if (attempts < maxAttempts) {
                    setTimeout(attemptRestore, 200); // Try every 200ms
                } else {
                    console.log('Max attempts reached, form data restoration failed');
                }
            }
            
            // Start after a small delay to ensure field configurations have time to load
            setTimeout(attemptRestore, 500);
        }
        
        // Start restoration attempts
        multiAttemptRestore();
        
        // Store currency rates for use in JavaScript
		window.currencyRates = <?php echo json_encode($currencyRates); ?>;

        // Budget line code to description mapping
        const budgetLineMapping = {
            '1.1': 'Salary and Benefit for Finance and HR Head(MMI)',
            '1.2': 'Multiple',
            '1.3': 'Office Rent',
            '1.4': 'Bank Service Fee',
            '1.5': 'Communication and internet fees',
            '1.6': 'Stationary and Office supplies',
            '1.7': 'Transport/ Taxi Fare',
            '1.8.E': 'Office Equipment Maintenance',
            '1.9.S': 'Job posting/ Recruitment fees',
            '1.10.PFW': 'Project Finance (Woldiya)',
            '1.11.PA': 'Project Associate(MMI)',
            '1.12.PAW': 'Project Associate(M&E) (Woldiya)',
            '1.13.E': 'Safetybox',
            '1.14.E': 'Document Storage Shelves and other office essentials',
            '1.15.C': 'Annual organisational and project audit for consortia',
            '2.1.S': 'Accounting Software Purchase',
            '2.2.PM': 'Project Lead/Manager - I-MED',
            '2.3.ED': 'Executive Director Salary for project work',
            '2.4.PM': 'Project Lead/Manager SME',
            '2.5.PC': 'Project Coordinator',
            '2.6.PMW': 'Project Manager(Woldiya)',
            '2.7.I': 'Biannual governing board and annual steering meetings -  Flight',
            '2.7.II': 'Biannual governing board and annual steering meetings -  Accomodation',
            '2.7.III': 'Biannual governing board and annual steering meetings -  meals and incidentals',
            '3.1.C': 'Consultant fee for Finance management support',
            '3.2.C': 'Consultant fee for project implementation support',
            '3.3.C': 'Consultant fee for Audio/visual editor, show director',
            '3.4.C': 'Consortia operational manual preparation, updates',
            '3.5.I': 'Joint operational planning meeting and training for newly hired project team - Air Ticket',
            '3.5.IV': 'Joint operational planning meeting and training for newly hired project team - Accomodation',
            '3.5.II': 'Joint operational planning meeting and training for newly hired project team - Land Tranport',
            '3.5.III': 'Joint operational planning meeting and training for newly hired project team - meals and incidentals',
            '3.6.I': 'Venue,Travel and Accomodation for consortia senior leadership',
            '3.6.IV': 'Provide consortium Management System for consortia member - Subscription Fee',
            '3.6.II': 'Provide consortium Management System for consortia member - Consultant Fee',
            '3.6.III': 'Printing operational document, voucher and receipt document  for consortia member',
            '3.7.S': 'Provide computers and software for new hires to support successful implementation of  project activities - Software Purchase',
            '3.7.C': 'Provide computers and software for new hires to support successful implementation of  project activities - Equipment Purchase',
            '3.8.P': 'Provide capacity development and mentorship for members',
            '3.9.S': 'Sustainability and future income generation personel fee',
            '3.9.E': 'Hire external play writer to develop forum theater script',
            '3.10.C': 'Branding promotion',
            '3.11.C': 'Consultant fee for marketing and business development',
            '3.12.C': 'Radio announcement/social media promotion fee',
            '3.13.C': 'Printing operational document, voucher and receipt document  for the new formed youth/women led CSO',
            '3.14.P': 'Sub-grant to implement project activities for the new formed youth/women led CSO',
            '3.15.C': 'Support the establishment/frequency boost for community radio serving Zone 4, Afar',
            '3.16.C': 'Printing operational document, voucher and receipt document for New and Emerging outside consortium member',
            '3.17.C': 'Consultant fee for HR support to outside consortium members',
            '3.18.E': 'Printing operational documents, voucher and receipt document for community radio serving Zone 4, Afar',
            '3.19.C': 'Consultant fees, editor/host for afarigna weekly program',
            '3.2.C': 'Consultant fees, content producer',
            '3.21.P': 'Recording and editing equipment to launch Afarigna language program',
            '3.22.C': 'Consultant fees for expert content producer and editor',
            '3.23.C': 'Visibility, promotion and dissemination fee',
            '3.24.C': 'Consultant fee, M&E/FGD',
            '3.24.E': 'work with youth drama clubs to record drama performance',
            '3.25.IV': 'Produce and air the drama series - Consultant Fee',
            '3.25.II': 'Produce and air the drama series - Equipment Purchase',
            '3.25.III': 'Travel and accomodation - Perdiem',
            '3.26.C': 'Travel and accomodation - Travel',
            '3.27.IV': 'Travel and accomodation - Accomodation',
            '3.28.II': 'Travel and accomodation - Accomodation',
            '3.29.V': 'Travel and accomodation - Perdiem',
            '3.30.III': 'Consultant fee for Mentorship',
            '3.31.C': 'Land transportation',
            '3.32.P': 'Accomodation for students',
            '3.33.C': 'Venue and refreshment',
            '3.34.E': 'Meal and incidentals',
            '3.35.S': 'Consultant fee, editor/host  for community radio serving Zone 4, Afar',
            '3.36.V': 'Consultant fee, content producer  for community radio serving Zone 4, Afar',
            '3.37.F': 'Recording and editing equipment  for community radio serving Zone 4, Afar',
            '3.38.R': 'Establish youth and women mentorship center',
            '3.39.V': 'Facilitator fee for community justice knowledge archive',
            '3.4.C': 'Travel and Accomodation- Air Travel',
            '3.41.C': 'Travel and accomodation - Accomodation',
            '3.42.C': 'Travel and accomodation - Perdiem',
            '3.43.II': 'Travel and Accomodation - Land Transport',
            '3.44.I': 'Stationary expenses',
            '3.45.IV': 'Module development fee',
            '3.46.V': 'Promotion of training fee',
            '3.47.III': 'Conduct training - journalism crash course',
            '3.48.P': 'Training Materials',
            '3.49.C': 'Editing software for trainees',
            '3.50.C': 'Alumni networking',
            '3.51.S': 'Alumni fellowship',
            '3.52.C': 'Traning/mentorship/studio venue',
            '3.53.C': 'Job-fair venue rental and refreshment',
            '3.54.SG': 'Consultant fee to develop a module for public interest, media and conflict sesitive reporting',
            '3.55.R': 'Trainer fee to establish in-house journalism training center and conduct ToT',
            '4.1.P': 'Trainer of trainers and journalists fee',
            '4.2.P': 'Accomodation for trainers,trainees, and project staff',
            '4.3.SG': 'Air Travel round-trip Addis Ababa to Dessie',
            '4.4.SG': 'Land transportation',
            '4.5.P': 'Venue and refreshment',
            '4.6.C': 'Meals and incidentals',
            '4.7.P': 'Announcement fee for  Ethiopian fact check accelerator challenge',
            '4.7.C': 'Consultant fee to develop fact-checking module',
            '4.8.C': 'Trainer fee: Fact-checking training workshop',
            '4.9.E': 'Website hosting fee',
            '4.10.CO': 'Website development fee',
            '4.11.C': 'Consultant fee: business development support',
            '4.12.I': 'Compititve national sub-award for fact-checking initiatives',
            '4.12.II': 'Infrastructure and space fee for shared incubation',
            '4.12.III': 'Contigency Expenses',
            '4.12.IV': 'and these are related numbers',
            '4.13.SG': '5.1.CN',
            '5.1.CN': 'Contigency Expenses'
        };

        // Budget line code to activity mapping
        const budgetLineActivityMapping = {
            // First 25 budget lines get "Multiple Activity"
            '1.1': 'Multiple Activity',
            '1.2': 'Multiple Activity',
            '1.3': 'Multiple Activity',
            '1.4': 'Multiple Activity',
            '1.5': 'Multiple Activity',
            '1.6': 'Multiple Activity',
            '1.7': 'Multiple Activity',
            '1.8.E': 'Multiple Activity',
            '1.9.S': 'Multiple Activity',
            '1.10.PFW': 'Multiple Activity',
            '1.11.PA': 'Multiple Activity',
            '1.12.PAW': 'Multiple Activity',
            '1.13.E': 'Multiple Activity',
            '1.14.E': 'Multiple Activity',
            '1.15.C': 'Multiple Activity',
            '2.1.S': 'Multiple Activity',
            '2.2.PM': 'Multiple Activity',
            '2.3.ED': 'Multiple Activity',
            '2.4.PM': 'Multiple Activity',
            '2.5.PC': 'Multiple Activity',
            '2.6.PMW': 'Multiple Activity',
            '2.7.I': 'Multiple Activity',
            '2.7.II': 'Multiple Activity',
            '2.7.III': 'Multiple Activity',
            '3.1.C': 'Multiple Activity',
            
            // Then assign activities in the exact order provided
            '3.2.C': '1.1.3: Hire project staff and provide training and mentorship',
            '3.3.C': '1.1.3: Hire project staff and provide training and mentorship',
            '3.4.C': '2.1.3: Hire project staff and provide training and mentorship',
            '3.5.I': '1.1.4: Provide capacity development (project cycle management, financial control and compliance requirements) training for North Wollo Community Radio',
            '3.5.IV': '1.1.4: Provide capacity development (project cycle management, financial control and compliance requirements) training for North Wollo Community Radio',
            '3.5.II': '1.1.4: Provide capacity development (project cycle management, financial control and compliance requirements) training for North Wollo Community Radio',
            '3.5.III': '1.1.4: Provide capacity development (project cycle management, financial control and compliance requirements) training for North Wollo Community Radio',
            '3.6.I': 'Venue,Travel and Accomodation for consortia senior leadership',
            '3.6.IV': '1.3.1: Develop income generating activity business model for consortia partners.',
            '3.6.II': '1.1.2. Acquire Consortium Project Management System',
            '3.6.III': '1.1.3: Hire project staff and provide training and mentorship',
            '3.7.S': '1.1.4: Provide capacity development (project cycle management, financial control and compliance requirements) training for North Wollo Community Radio',
            '3.7.C': '1.3.2: Develop a sustainability and income generation plan for dialogue radio programs.',
            '3.8.P': '1.3.2: Develop a sustainability and income generation plan for dialogue radio programs.',
            '3.9.S': '2.2.3: Develop forum theater scripts',
            '3.9.E': '1.3.1: Develop income generating activity business model for consortia partners.',
            '3.10.C': '1.3.1: Develop income generating activity business model for consortia partners.',
            '3.11.C': '1.2.2: Develop selection criteria and public call for new youth/women led CSOs',
            '3.12.C': '1.2.4: Provide capacity development support for new and emerging outside consortium members',
            '3.13.C': '1.2.4: Provide capacity development support for new and emerging outside consortium members',
            '3.14.P': '1.2.5: Support community radios to provide first of its kind broadcast services to citizens in Zone 4 of Afar region',
            '3.15.C': '1.2.4: Provide capacity development support for new and emerging outside consortium members',
            '3.16.C': '1.2.4: Provide capacity development support for new and emerging outside consortium members',
            '3.17.C': '1.2.4: Provide capacity development support for new and emerging outside consortium members',
            '3.18.E': '2.2.1: North Wollo community radio launches Afarigna language programming',
            '3.19.C': '2.2.1: North Wollo community radio launches Afarigna language programming',
            '3.2.C': '2.2.1: North Wollo community radio launches Afarigna language programming',
            '3.21.P': '2.1.3: Create prototypes of the dialogue episodes and test with target communities',
            '3.22.C': '2.1.4: Launch dialogue episodes on community radio',
            '3.23.C': '2.1.5: Enhance visibility, promotion and dissemination of dialogue episodes',
            '3.24.C': '2.1.6: Conduct M&E, learning and adaptation',
            '3.24.E': '2.2.4: Partner with youth drama clubs to record the drama series',
            '3.25.IV': '2.2.5: Air the drama series on community radio with follow-up call in shows',
            '3.25.II': '2.2.5: Air the drama series on community radio with follow-up call in shows',
            '3.25.III': '2.2.5: Air the drama series on community radio with follow-up call in shows',
            '3.26.C': '2.1.7: Design Workshop for journalism students',
            '3.27.IV': '2.1.7: Design Workshop for journalism students',
            '3.28.II': '2.1.7: Design Workshop for journalism students',
            '3.29.V': '2.1.7: Design Workshop for journalism students',
            '3.30.III': '2.1.7: Design Workshop for journalism students',
            '3.31.C': '2.2.2: Afar based community radio launches Amharic language programming',
            '3.32.P': '2.2.2: Afar based community radio launches Amharic language programming',
            '3.33.C': '2.2.2: Afar based community radio launches Amharic language programming',
            '3.34.E': '2.3.1: Establish youth and women mentorship centre for community justice',
            '3.35.S': '2.3.1: Establish youth and women mentorship centre for community justice',
            '3.36.V': '2.3.1: Establish youth and women mentorship centre for community justice',
            '3.37.F': '2.3.1: Establish youth and women mentorship centre for community justice',
            '3.38.R': '3.1.1: Develop a training module and an operational plan with deliverables',
            '3.39.V': '3.1.2: Conduct 12-weeks SME training cohorts',
            '3.41.C': '3.1.2: Conduct 12-weeks SME training cohorts',
            '3.42.C': '3.1.2: Conduct 12-weeks SME training cohorts',
            '3.43.II': '3.1.2: Conduct 12-weeks SME training cohorts',
            '3.44.I': '3.1.3: Organize job fairs and implement a fellowship program for trainees after each training cohort',
            '3.45.IV': '3.1.3: Organize job fairs and implement a fellowship program for trainees after each training cohort',
            '3.46.V': '3.1.2: Conduct 12-weeks SME training cohorts',
            '3.47.III': '3.1.3: Organize job fairs and implement a fellowship program for trainees after each training cohort',
            '3.48.P': '3.2.1: Co-develop module for public interest media and conflict sensitive reporting for community radio',
            '3.49.C': '3.2.2: Establish in-house journalism training center and conduct ToT for community radio',
            '3.50.C': '3.2.2: Establish in-house journalism training center and conduct ToT for community radio',
            '3.51.S': '3.2.3: Training on public interest media and conflict sensitive reporting for community radio',
            '3.52.C': '3.2.3: Training on public interest media and conflict sensitive reporting for community radio',
            '3.53.C': '3.2.3: Training on public interest media and conflict sensitive reporting for community radio',
            '3.54.SG': '3.2.3: Training on public interest media and conflict sensitive reporting for community radio',
            '3.55.R': '3.2.3: Training on public interest media and conflict sensitive reporting for community radio',
            '3.6.IV': 'Provide consortium Management System for consortia member - Subscription Fee',
            '3.6.II': 'Provide consortium Management System for consortia member - Consultant Fee',
            '3.6.III': 'Printing operational document, voucher and receipt document  for consortia member',
            '3.7.S': 'Provide computers and software for new hires to support successful implementation of  project activities - Software Purchase',
            '3.7.C': 'Provide computers and software for new hires to support successful implementation of  project activities - Equipment Purchase',
            '3.8.P': 'Provide capacity development and mentorship for members',
            '3.9.S': 'Sustainability and future income generation personel fee',
            '3.9.E': 'Hire external play writer to develop forum theater script',
            '3.10.C': 'Branding promotion',
            '3.11.C': 'Consultant fee for marketing and business development',
            '3.12.C': 'Radio announcement/social media promotion fee',
            '3.13.C': 'Printing operational document, voucher and receipt document  for the new formed youth/women led CSO',
            '3.14.P': 'Sub-grant to implement project activities for the new formed youth/women led CSO',
            '3.15.C': 'Support the establishment/frequency boost for community radio serving Zone 4, Afar',
            '3.16.C': 'Printing operational document, voucher and receipt document for New and Emerging outside consortium member',
            '3.17.C': 'Consultant fee for HR support to outside consortium members',
            '3.18.E': 'Printing operational documents, voucher and receipt document for community radio serving Zone 4, Afar',
            '3.19.C': 'Consultant fees, editor/host for afarigna weekly program',
            '3.2.C': 'Consultant fees, content producer',
            '3.21.P': 'Recording and editing equipment to launch Afarigna language program',
            '3.22.C': 'Consultant fees for expert content producer and editor',
            '3.23.C': 'Visibility, promotion and dissemination fee',
            '3.24.C': 'Consultant fee, M&E/FGD',
            '3.24.E': 'work with youth drama clubs to record drama performance',
            '3.25.IV': 'Produce and air the drama series - Consultant Fee',
            '3.25.II': 'Produce and air the drama series - Equipment Purchase',
            '3.25.III': 'Travel and accomodation - Perdiem',
            '3.26.C': 'Travel and accomodation - Travel',
            '3.27.IV': 'Travel and accomodation - Accomodation',
            '3.28.II': 'Travel and accomodation - Accomodation',
            '3.29.V': 'Travel and accomodation - Perdiem',
            '3.30.III': 'Consultant fee for Mentorship',
            '3.31.C': 'Land transportation',
            '3.32.P': 'Accomodation for students',
            '3.33.C': 'Venue and refreshment',
            '3.34.E': 'Meal and incidentals',
            '3.35.S': 'Consultant fee, editor/host  for community radio serving Zone 4, Afar',
            '3.36.V': 'Consultant fee, content producer  for community radio serving Zone 4, Afar',
            '3.37.F': 'Recording and editing equipment  for community radio serving Zone 4, Afar',
            '3.38.R': 'Establish youth and women mentorship center',
            '3.39.V': 'Facilitator fee for community justice knowledge archive',
            '4.1.P': '3.3.1: Launch Ethiopia Fact Checker Accelerator (EFCA)',
            '4.2.P': '3.3.1: Launch Ethiopia Fact Checker Accelerator (EFCA)',
            '4.3.SG': '3.3.3: Generate and refine ideas for sustainable fact checking institutional development (Ideation)',
            '4.4.SG': '3.3.3: Generate and refine ideas for sustainable fact checking institutional development (Ideation)',
            '4.5.P': '3.3.3: Generate and refine ideas for sustainable fact checking institutional development (Ideation)',
            '4.6.C': '3.3.4: Competitive national sub-Awards for fact checking initiatives: Competitive national sub-Awards for fact checking initiatives',
            '4.7.P': '3.3.4: Competitive national sub-Awards for fact checking initiatives: Competitive national sub-Awards for fact checking initiatives',
            '4.7.C': '3.3.4: Competitive national sub-Awards for fact checking initiatives: Competitive national sub-Awards for fact checking initiatives',
            '4.8.C': 'Contigency Expenses',
            '4.9.E': 'Multiple Activity',
            '4.10.CO': 'Multiple Activity',
            '4.11.C': 'Multiple Activity',
            '4.12.I': 'Multiple Activity',
            '4.12.II': 'Travel and accomodation - Accomodation',
            '4.12.III': 'Travel and Accomodation - Perdiem',
            '4.12.IV': 'Travel and Accomodation - Land Transport',
            '4.13.SG': 'Multiple Activity',
            '5.1.CN': 'Contigency Expenses'
        };

        // Budget line code to outcome mapping
        const budgetLineOutcomeMapping = {
            // First 25 budget lines get "Multiple Outcome"
            '1.1': 'Multiple Outcome',
            '1.2': 'Multiple Outcome',
            '1.3': 'Multiple Outcome',
            '1.4': 'Multiple Outcome',
            '1.5': 'Multiple Outcome',
            '1.6': 'Multiple Outcome',
            '1.7': 'Multiple Outcome',
            '1.8.E': 'Multiple Outcome',
            '1.9.S': 'Multiple Outcome',
            '1.10.PFW': 'Multiple Outcome',
            '1.11.PA': 'Multiple Outcome',
            '1.12.PAW': 'Multiple Outcome',
            '1.13.E': 'Multiple Outcome',
            '1.14.E': 'Multiple outcome',
            '1.15.C': 'Multiple Outcome',
            '2.1.S': 'Multiple Outcome',
            '2.2.PM': 'Multiple Outcome',
            '2.3.ED': 'Multiple Outcome',
            '2.4.PM': 'Multiple Outcome',
            '2.5.PC': 'Multiple Outcome',
            '2.6.PMW': 'Multiple Outcome',
            '2.7.I': 'Multiple Outcome',
            '2.7.II': 'Multiple Outcome',
            '2.7.III': 'Multiple Outcome',
            '3.1.C': 'Multiple Outcome',
            
            // Then assign outcomes in the exact order provided
            '3.2.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.3.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.4.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.5.I': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.5.IV': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.5.II': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.5.III': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.6.I': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.6.IV': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.6.II': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.6.III': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.7.S': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.7.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.8.P': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.9.S': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.9.E': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.10.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.11.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.12.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.13.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.14.P': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.15.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.16.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.17.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.18.E': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.19.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.2.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.21.P': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.22.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.23.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.24.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.24.E': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.25.IV': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.25.II': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.25.III': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.26.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.27.IV': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.28.II': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.29.V': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.30.III': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.31.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.32.P': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.33.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.34.E': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.35.S': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.36.V': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.37.F': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.38.R': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.39.V': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.41.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.42.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.43.II': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.44.I': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.45.IV': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.46.V': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.47.III': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.48.P': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.49.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.50.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.51.S': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.52.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.53.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.54.SG': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.55.R': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.6.IV': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.6.II': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.6.III': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.7.S': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.7.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.8.P': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.9.S': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.9.E': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.10.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.11.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.12.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.13.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.14.P': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.15.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.16.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.17.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.18.E': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.19.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.2.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.21.P': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.22.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.23.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.24.C': '1 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.24.E': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.25.IV': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.25.II': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.25.III': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.26.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.27.IV': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.28.II': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.29.V': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.30.III': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.31.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.32.P': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.33.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.34.E': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.35.S': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.36.V': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.37.F': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.38.R': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '3.39.V': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.1.P': '3 - Improved interregional cooperation and inclusive dialogue among target conflict affected regions on democratic governance and peacebuilding.',
            '4.2.P': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.3.SG': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.4.SG': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.5.P': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.6.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.7.P': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.7.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.8.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.9.E': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.10.CO': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.11.C': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.12.I': '2 - Strengthened the capacity and sustainability of civil society organizations (including new and emerging) to promote peace and democratic governance.',
            '4.12.II': '3 - Improved interregional cooperation and inclusive dialogue among target conflict affected regions on democratic governance and peacebuilding.',
            '4.12.III': '3 - Improved interregional cooperation and inclusive dialogue among target conflict affected regions on democratic governance and peacebuilding.',
            '4.12.IV': '3 - Improved interregional cooperation and inclusive dialogue among target conflict affected regions on democratic governance and peacebuilding.',
            '4.13.SG': '3 - Improved interregional cooperation and inclusive dialogue among target conflict affected regions on democratic governance and peacebuilding.',
            '5.1.CN': '3 - Improved interregional cooperation and inclusive dialogue among target conflict affected regions on democratic governance and peacebuilding.'
        };

        // Budget line code to budget heading mapping
        const budgetLineHeadingMapping = {
            // 1. Administrative costs
            '1.1': 'Administrative Costs',
            '1.2': 'Administrative Costs',
            '1.3': 'Administrative Costs',
            '1.4': 'Administrative Costs',
            '1.5': 'Administrative Costs',
            '1.6': 'Administrative Costs',
            '1.7': 'Administrative Costs',
            '1.8.E': 'Administrative Costs',
            '1.9.S': 'Administrative Costs',
            '1.10.PFW': 'Administrative Costs',
            '1.11.PA': 'Administrative Costs',
            '1.12.PAW': 'Administrative Costs',
            '1.13.E': 'Administrative Costs',
            '1.14.E': 'Administrative Costs',
            '1.15.C': 'Administrative Costs',
            
            // 2. Operational support costs
            '2.1.S': 'Operational Costs',
            '2.2.PM': 'Operational Costs',
            '2.3.ED': 'Operational Costs',
            '2.4.PM': 'Operational Costs',
            '2.5.PC': 'Operational Costs',
            '2.6.PMW': 'Operational Costs',
            '2.7.I': 'Operational Costs',
            // Note: 2.7.II and 2.7.III are blank, so they won't be mapped
            
            // 3. Consortium Activities
            '3.1.C': 'Consortium Activities',
            '3.2.C': 'Consortium Activities',
            '3.3.C': 'Consortium Activities',
            '3.4.C': 'Consortium Activities',
            '3.5.I': 'Consortium Activities',
            '3.5.IV': 'Consortium Activities',
            '3.5.II': 'Consortium Activities',
            '3.5.III': 'Consortium Activities',
            '3.6.I': 'Consortium Activities',
            '3.6.IV': 'Consortium Activities',
            '3.6.II': 'Consortium Activities',
            '3.6.III': 'Consortium Activities',
            '3.7.S': 'Consortium Activities',
            '3.7.C': 'Consortium Activities',
            '3.8.P': 'Consortium Activities',
            '3.9.S': 'Consortium Activities',
            '3.9.E': 'Consortium Activities',
            '3.10.C': 'Consortium Activities',
            '3.11.C': 'Consortium Activities',
            '3.12.C': 'Consortium Activities',
            '3.13.C': 'Consortium Activities',
            '3.14.P': 'Consortium Activities',
            '3.15.C': 'Consortium Activities',
            '3.16.C': 'Consortium Activities',
            '3.17.C': 'Consortium Activities',
            '3.18.E': 'Consortium Activities',
            '3.19.C': 'Consortium Activities',
            '3.2.C': 'Consortium Activities',
            '3.21.P': 'Consortium Activities',
            '3.22.C': 'Consortium Activities',
            '3.23.C': 'Consortium Activities',
            '3.24.C': 'Consortium Activities',
            '3.24.E': 'Consortium Activities',
            '3.25.IV': 'Consortium Activities',
            '3.25.II': 'Consortium Activities',
            '3.25.III': 'Consortium Activities',
            '3.26.C': 'Consortium Activities',
            '3.27.IV': 'Consortium Activities',
            '3.28.II': 'Consortium Activities',
            '3.29.V': 'Consortium Activities',
            '3.30.III': 'Consortium Activities',
            '3.31.C': 'Consortium Activities',
            '3.32.P': 'Consortium Activities',
            '3.33.C': 'Consortium Activities',
            '3.34.E': 'Consortium Activities',
            '3.35.S': 'Consortium Activities',
            '3.36.V': 'Consortium Activities',
            '3.37.F': 'Consortium Activities',
            '3.38.R': 'Consortium Activities',
            '3.39.V': 'Consortium Activities',
            '3.41.C': 'Consortium Activities',
            '3.42.C': 'Consortium Activities',
            '3.43.II': 'Consortium Activities',
            '3.44.I': 'Consortium Activities',
            '3.45.IV': 'Consortium Activities',
            '3.46.V': 'Consortium Activities',
            '3.47.III': 'Consortium Activities',
            '3.48.P': 'Consortium Activities',
            '3.49.C': 'Consortium Activities',
            '3.50.C': 'Consortium Activities',
            '3.51.S': 'Consortium Activities',
            '3.52.C': 'Consortium Activities',
            '3.53.C': 'Consortium Activities',
            '3.54.SG': 'Consortium Activities',
            '3.55.R': 'Consortium Activities',
            
            // 4. Targeting new CSOs
            '4.1.P': 'Targeting New CSOs',
            '4.2.P': 'Targeting New CSOs',
            '4.3.SG': 'Targeting New CSOs',
            '4.4.SG': 'Targeting New CSOs',
            '4.5.P': 'Targeting New CSOs',
            '4.6.C': 'Targeting New CSOs',
            '4.7.P': 'Targeting New CSOs',
            // Note: 4.12.II, 4.12.III, and 4.12.IV are blank, so they won't be mapped
            '4.7.C': 'Targeting New CSOs',
            '4.8.C': 'Targeting New CSOs',
            '4.9.E': 'Targeting New CSOs',
            '4.10.CO': 'Targeting New CSOs',
            '4.11.C': 'Targeting New CSOs',
            '4.12.I': 'Targeting New CSOs',
            '4.13.SG': 'Targeting New CSOs',
            
            // 5. Contingency
            '5.1.CN': 'Contingency'
        };

        // Function to handle budget line autocomplete
        function setupBudgetLineAutocomplete() {
            const budgetLineInput = document.getElementById('budgetLineInput');
            const activityInput = document.getElementById('activityInput');
            const outcomeInput = document.getElementById('outcomeInput');
            const budgetHeadingSelect = document.getElementById('budgetHeadingSelect');
            
            if (budgetLineInput) {
                // Create autocomplete dropdown
                const autocompleteDiv = document.createElement('div');
                autocompleteDiv.id = 'budgetLineAutocomplete';
                autocompleteDiv.className = 'absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md py-1 hidden';
                autocompleteDiv.style.maxHeight = '200px';
                autocompleteDiv.style.overflowY = 'auto';
                budgetLineInput.parentNode.style.position = 'relative';
                budgetLineInput.parentNode.appendChild(autocompleteDiv);
                
                // Track if we're currently showing suggestions
                let showingSuggestions = false;
                
                // Handle input event for autocomplete
                budgetLineInput.addEventListener('input', function() {
                    const value = this.value.trim();
                    
                    // Show suggestions if input looks like a budget line code
                    // Don't auto-fill, just show matching suggestions
                    if (value.length > 0) {
                        const suggestions = Object.keys(budgetLineMapping).filter(code => 
                            code.toLowerCase().startsWith(value.toLowerCase())
                        );
                        
                        if (suggestions.length > 0) {
                            autocompleteDiv.innerHTML = '';
                            suggestions.slice(0, 10).forEach(code => {
                                const item = document.createElement('div');
                                item.className = 'px-4 py-2 hover:bg-gray-100 cursor-pointer flex justify-between';
                                item.innerHTML = `
                                    <span class="font-medium">${code}</span>
                                    <span class="text-gray-600 text-sm truncate ml-2">${budgetLineMapping[code]}</span>
                                `;
                                item.addEventListener('click', () => {
                                    budgetLineInput.value = budgetLineMapping[code];
                                    // Automatically fill the activity field based on the budget line code
                                    if (budgetLineActivityMapping[code]) {
                                        activityInput.value = budgetLineActivityMapping[code];
                                    }
                                    // Automatically fill the outcome field based on the budget line code
                                    if (budgetLineOutcomeMapping[code]) {
                                        outcomeInput.value = budgetLineOutcomeMapping[code];
                                    }
                                    // Automatically select the budget heading based on the budget line code
                                    if (budgetLineHeadingMapping[code] && budgetHeadingSelect) {
                                        // Find the option with the matching text
                                        for (let i = 0; i < budgetHeadingSelect.options.length; i++) {
                                            if (budgetHeadingSelect.options[i].text === budgetLineHeadingMapping[code]) {
                                                budgetHeadingSelect.selectedIndex = i;
                                                break;
                                            }
                                        }
                                    }
                                    autocompleteDiv.classList.add('hidden');
                                    showingSuggestions = false;
                                    budgetLineInput.focus();
                                    updateLivePreview();
                                });
                                autocompleteDiv.appendChild(item);
                            });
                            autocompleteDiv.classList.remove('hidden');
                            showingSuggestions = true;
                        } else {
                            autocompleteDiv.classList.add('hidden');
                            showingSuggestions = false;
                        }
                    } else {
                        autocompleteDiv.classList.add('hidden');
                        showingSuggestions = false;
                    }
                });
                
                // Hide suggestions when clicking outside
                document.addEventListener('click', function(e) {
                    if (!budgetLineInput.contains(e.target) && !autocompleteDiv.contains(e.target)) {
                        autocompleteDiv.classList.add('hidden');
                        showingSuggestions = false;
                    }
                });
                
                // Handle keyboard navigation
                budgetLineInput.addEventListener('keydown', function(e) {
                    if (!showingSuggestions) return;
                    
                    const items = autocompleteDiv.querySelectorAll('div');
                    if (items.length === 0) return;
                    
                    let selectedIndex = -1;
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].classList.contains('bg-gray-100')) {
                            selectedIndex = i;
                            break;
                        }
                    }
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const nextIndex = selectedIndex < items.length - 1 ? selectedIndex + 1 : 0;
                        if (selectedIndex >= 0) items[selectedIndex].classList.remove('bg-gray-100');
                        items[nextIndex].classList.add('bg-gray-100');
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const prevIndex = selectedIndex > 0 ? selectedIndex - 1 : items.length - 1;
                        if (selectedIndex >= 0) items[selectedIndex].classList.remove('bg-gray-100');
                        items[prevIndex].classList.add('bg-gray-100');
                    } else if (e.key === 'Enter' && selectedIndex >= 0) {
                        e.preventDefault();
                        items[selectedIndex].click();
                    } else if (e.key === 'Escape') {
                        autocompleteDiv.classList.add('hidden');
                        showingSuggestions = false;
                    }
                });
            }
        }



<?php if (!empty($isCustomCurrencyEnabled) && $isCustomCurrencyEnabled): ?>
		// Custom currency rate UI logic for clusters with custom rate enabled
		(function(){
			const useCustomRate = document.getElementById('useCustomRate');
			const customUsdInput = document.getElementById('customUsdToEtb');
			const customEurInput = document.getElementById('customEurToEtb');
			const currentUsdDisplay = document.getElementById('currentUsdToEtbDisplay');
			const currentEurDisplay = document.getElementById('currentEurToEtbDisplay');
			if (currentUsdDisplay && window.currencyRates && typeof window.currencyRates.USD_to_ETB !== 'undefined') {
				currentUsdDisplay.textContent = String(window.currencyRates.USD_to_ETB);
			}
			if (currentEurDisplay && window.currencyRates && typeof window.currencyRates.EUR_to_ETB !== 'undefined') {
				currentEurDisplay.textContent = String(window.currencyRates.EUR_to_ETB);
			}
			if (!useCustomRate) return;
			useCustomRate.addEventListener('change', () => {
				if (customUsdInput) customUsdInput.disabled = !useCustomRate.checked;
				if (customEurInput) customEurInput.disabled = !useCustomRate.checked;
				if (!useCustomRate.checked) {
					// restore server-provided rates
					window.currencyRates = <?php echo json_encode($currencyRates); ?>;
				}
			});
			if (customUsdInput) {
				customUsdInput.addEventListener('input', () => {
					const v = parseFloat(customUsdInput.value);
					if (useCustomRate.checked && v && v > 0) {
						window.currencyRates = Object.assign({}, window.currencyRates, { USD_to_ETB: v });
					}
				});
			}
			if (customEurInput) {
				customEurInput.addEventListener('input', () => {
					const v = parseFloat(customEurInput.value);
					if (useCustomRate.checked && v && v > 0) {
						window.currencyRates = Object.assign({}, window.currencyRates, { EUR_to_ETB: v });
					}
				});
			}
		})();
<?php endif; ?>
        
        // Load recent transactions from database
        loadRecentTransactions();
        
        // Add function to show remaining budget details in a popup/modal
      
        
        // Function to close the modal
        window.closeRemainingBudgetModal = function() {
            const modal = document.getElementById('remainingBudgetModal');
            if (modal) {
                modal.remove();
            }
        };
        
        // Add click event to the remaining budget card
       
        // Import Excel button handler
        const importExcelButton = document.getElementById('importExcelButton');
        if (importExcelButton) {
            importExcelButton.addEventListener('click', function() {
                // Redirect to the import Excel page
                window.location.href = 'import_excel.php';
            });
        }
    });
</script>

</body>
</html>
