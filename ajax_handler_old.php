<?php
// AJAX handler for financial transactions
// This file should only output JSON responses

// Clean any previous output and start fresh
ob_clean();

// Set JSON content type immediately
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simple error handler to output JSON
function handleError($message, $debug = null) {
    ob_clean(); // Clear any previous output
    $response = ['success' => false, 'message' => $message];
    if ($debug) {
        $response['debug'] = $debug;
    }
    echo json_encode($response);
    exit;
}

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Log all received data for debugging
error_log('AJAX Handler - POST data: ' . print_r($_POST, true));
error_log('AJAX Handler - FILES data: ' . print_r($_FILES, true));

// Check if this is a POST or GET request for different actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    handleError('Only POST and GET requests allowed', 'Request method: ' . $_SERVER['REQUEST_METHOD']);
}

// Check if database connection exists
if (!isset($conn)) {
    handleError('Database connection variable not set', 'Connection variable missing');
}

if ($conn->connect_error) {
    handleError('Database connection failed', $conn->connect_error);
}

// Check if action is specified (support both POST and GET)
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');
if (!$action) {
    $availableKeys = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_keys($_POST) : array_keys($_GET);
    handleError('No action specified', 'Available keys: ' . implode(', ', $availableKeys));
}

error_log('AJAX Handler - Processing action: ' . $action);

try {
    switch ($action) {
        case 'save_transaction':
            error_log('AJAX Handler - Starting save_transaction');
            
            // Get form data
            $budgetHeading = trim($_POST['budgetHeading'] ?? '');
            $outcome = trim($_POST['outcome'] ?? '');
            $activity = trim($_POST['activity'] ?? '');
            $budgetLine = trim($_POST['budgetLine'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $partner = trim($_POST['partner'] ?? '');
            $entryDate = $_POST['entryDate'] ?? '';
            // Amount entered by user is in ETB
            $amountETB = $_POST['amount'] ?? 0;
            // Ensure amount is treated as a string to preserve exact input
            if (is_numeric($amountETB)) {
                $amountETB = number_format((float)$amountETB, 2, '.', '');
            }
            $pvNumber = trim($_POST['pvNumber'] ?? '');
            
            error_log('AJAX Handler - Form data extracted successfully');
            
            // Validate required fields
            $missingFields = [];
            if (empty($budgetHeading)) $missingFields[] = 'Budget Heading';
            if (empty($outcome)) $missingFields[] = 'Outcome';
            if (empty($activity)) $missingFields[] = 'Activity';
            if (empty($budgetLine)) $missingFields[] = 'Budget Line';
            if (empty($description)) $missingFields[] = 'Description';
            if (empty($partner)) $missingFields[] = 'Partner';
            if (empty($entryDate)) $missingFields[] = 'Date';
            if (empty($amountETB) || !is_numeric($amountETB) || floatval($amountETB) <= 0) $missingFields[] = 'Amount';
            
            if (!empty($missingFields)) {
                handleError('Missing or invalid fields: ' . implode(', ', $missingFields), 'Missing fields validation failed');
            }
            
            error_log('AJAX Handler - Validation passed');
            
            // Handle document file paths (files already uploaded to server)
            $documentPaths = [];
            $documentTypes = [];
            $originalNames = [];
            $documentCount = 0;
            
            if (isset($_POST['uploadedFilePaths']) && !empty($_POST['uploadedFilePaths'])) {
                error_log('AJAX Handler - Processing uploaded file paths');
                $uploadedFilePaths = json_decode($_POST['uploadedFilePaths'], true);
                if ($uploadedFilePaths && is_array($uploadedFilePaths)) {
                    // Extract simple data from uploaded files
                    foreach ($uploadedFilePaths as $doc) {
                        if (isset($doc['serverPath']) && file_exists($doc['serverPath'])) {
                            $documentPaths[] = $doc['serverPath'];
                            $documentTypes[] = $doc['documentType'] ?? 'Unknown';
                            $originalNames[] = $doc['originalName'] ?? basename($doc['serverPath']);
                            $documentCount++;
                        } else {
                            error_log('AJAX Handler - File not found: ' . ($doc['serverPath'] ?? 'no path'));
                        }
                    }
                    error_log('AJAX Handler - Processed ' . count($uploadedFilePaths) . ' documents');
                } else {
                    error_log('AJAX Handler - Invalid JSON or not array');
                }
            } else {
                error_log('AJAX Handler - No uploaded file paths found, using sample document only');
            }
            
            // Convert arrays to comma-separated strings for database storage
            $documentPathsStr = implode(',', $documentPaths);
            $documentTypesStr = implode(',', $documentTypes);
            $originalNamesStr = implode(',', $originalNames);
            
            // Get budget data before inserting transaction
            $categoryMappings = [
                'Administrative costs' => '1. Administrative costs',
                'Operational support costs' => '2. Operational support costs',
                'Consortium Activities' => '3. Consortium Activities',
                'Targeting new CSOs' => '4. Targeting new CSOs',
                'Contingency' => '5. Contingency'
            ];
            $mappedCategoryName = $categoryMappings[$budgetHeading] ?? $budgetHeading;
            
            // Determine quarter from entry date
            $entryDateTime = new DateTime($entryDate);
            $entryYear = (int)$entryDateTime->format('Y');
            
            // Get user cluster from session (we need to start session to access it)
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Include currency functions
            include 'currency_functions.php';
            
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
            
            // Find the correct quarter and get budget data with cluster consideration
            $quarterBudgetQuery = "SELECT id, period_name, budget, actual, forecast, variance_percentage, currency 
                                 FROM budget_data 
                                 WHERE year2 = ? AND category_name = ? 
                                 AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4')
                                 AND ? BETWEEN start_date AND end_date";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $quarterBudgetQuery .= " AND cluster = ?";
            }
            
            $quarterBudgetQuery .= " LIMIT 1";
            
            if ($userCluster) {
                $quarterBudgetStmt = $conn->prepare($quarterBudgetQuery);
                $quarterBudgetStmt->bind_param("isss", $entryYear, $mappedCategoryName, $entryDateTime->format('Y-m-d'), $userCluster);
            } else {
                $quarterBudgetStmt = $conn->prepare($quarterBudgetQuery);
                $quarterBudgetStmt->bind_param("iss", $entryYear, $mappedCategoryName, $entryDateTime->format('Y-m-d'));
            }
            
            $quarterBudgetStmt->execute();
            $quarterBudgetResult = $quarterBudgetStmt->get_result();
            $quarterBudgetData = $quarterBudgetResult->fetch_assoc();
            
            // Get the budget_id and currency for linking to budget_preview
            $budgetId = $quarterBudgetData['id'] ?? null;
            $targetCurrency = $quarterBudgetData['currency'] ?? 'ETB'; // Default to ETB if not set
            
            // Convert amount from ETB to target currency
            $amount = convertCurrency($amountETB, 'ETB', $targetCurrency, $currencyRates);
            
            // Set budget tracking values for budget_preview table
            $quarterPeriod = $quarterBudgetData['period_name'] ?? 'Unknown';
            $originalBudget = $quarterBudgetData['budget'] ?? 0; // Original planned budget (unchanged)
            $currentActual = $quarterBudgetData['actual'] ?? 0; // Current actual spending
            $actualSpent = $currentActual + $amount; // What actual will be after this transaction
            // Calculate forecast amount - this should be the remaining budget after actual spending
            $forecastAmount = max($originalBudget - $actualSpent, 0); // Forecast = Budget - ActualSpent
            // Remaining budget should be the same as forecast (what's left to spend)
            $remainingBudget = $forecastAmount; // Remaining = Forecast (what's left to spend)
            // Variance calculation: ((Budget - (Actual + Forecast)) / Budget) * 100
            // Since Actual + Forecast = Budget, variance should be 0 for proper budget management
            $variancePercentage = 0; // Variance is 0 when Actual + Forecast = Budget
            
            // Prepare and execute statement
            error_log('AJAX Handler - Preparing database statement');
            // Add cluster column to the INSERT statement and include currency
            $stmt = $conn->prepare("INSERT INTO budget_preview (BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount, PVNumber, DocumentPaths, DocumentTypes, OriginalNames, QuarterPeriod, CategoryName, OriginalBudget, RemainingBudget, ActualSpent, ForecastAmount, VariancePercentage, cluster, budget_id, currency, COMMENTS, ACCEPTANCE) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                handleError('Database prepare error', $conn->error);
            }
            
            // Empty values for COMMENTS and ACCEPTANCE fields
            $emptyString = '';
            
            error_log('AJAX Handler - Binding parameters');
            // Add cluster parameter, budget_id, and currency to the bind_param call
            $stmt->bind_param("sssssssdssssssssddssisss", $budgetHeading, $outcome, $activity, $budgetLine, $description, $partner, $entryDate, $amount, $pvNumber, $documentPathsStr, $documentTypesStr, $originalNamesStr, $quarterPeriod, $mappedCategoryName, $originalBudget, $remainingBudget, $actualSpent, $forecastAmount, $variancePercentage, $userCluster, $budgetId, $targetCurrency, $emptyString, $emptyString);
            
            error_log('AJAX Handler - Executing statement');
            if ($stmt->execute()) {
                $insertId = $stmt->insert_id;
                
                // Update the budget_data table with proper filtering by date range, cluster, category, and quarter
                error_log('AJAX Handler - Updating budget_data table');
                
                // We already have the mapped category name and quarter from above
                $categoryName = $mappedCategoryName;
                $quarter = $quarterPeriod;
                $year = $entryYear;
                $transactionDate = $entryDateTime->format('Y-m-d');
                
                // Check if there's enough budget available for this transaction with proper filtering
                $budgetCheckQuery = "SELECT budget, actual, forecast, id, currency FROM budget_data 
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

                // Get the budget_id and currency for linking to budget_preview
                $budgetId = $budgetCheckData['id'] ?? null;
                $budgetCurrency = $budgetCheckData['currency'] ?? 'ETB'; // Default to ETB if not set

                // Remaining available = budget - actual (handle NULLs) - forecast is future expectation, not committed spending
                $availableBudget = max((float)($budgetCheckData['budget'] ?? 0) - (float)($budgetCheckData['actual'] ?? 0), 0);
                
                // Convert the entered amount to the same currency as the budget for comparison
                $amountInBudgetCurrency = convertCurrency($amountETB, 'ETB', $budgetCurrency, $currencyRates);
                
                if ($amountInBudgetCurrency > $availableBudget) {
                    handleError('Insufficient budget available', 
                        "Transaction amount (" . number_format($amountInBudgetCurrency, 2) . " $budgetCurrency) exceeds available budget (" . number_format($availableBudget, 2) . " $budgetCurrency) for $categoryName in $quarter $year");
                }
                
                // Update the quarter row: increase actual by amount, auto-adjust forecast to remaining budget, recompute actual_plus_forecast
                // MySQL evaluates SET clauses left to right, so later expressions see updated column values
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
                    // Params: 1 double (amount), 1 integer (year), 4 strings (categoryName, quarter, transactionDate, userCluster)
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
                    
                    // Update forecast for Annual Total with cluster consideration
                    $updateForecastQuery = "UPDATE budget_data 
                        SET forecast = (
                            SELECT SUM(COALESCE(forecast, 0)) 
                            FROM budget_data b4 
                            WHERE b4.year2 = ? AND b4.category_name = ? AND b4.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";

                    if ($userCluster) {
                        $updateForecastQuery .= " AND b4.cluster = ?";
                    }

                    $updateForecastQuery .= ") WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
                    if ($userCluster) {
                        $updateForecastQuery .= " AND cluster = ?";
                        $forecastStmt = $conn->prepare($updateForecastQuery);
                        $forecastStmt->bind_param("ississ", $year, $categoryName, $userCluster, $year, $categoryName, $userCluster);
                    } else {
                        $forecastStmt = $conn->prepare($updateForecastQuery);
                        $forecastStmt->bind_param("isis", $year, $categoryName, $year, $categoryName);
                    }

                    $forecastStmt->execute();
                    
                    // Removed invalid standalone WHERE statement that caused SQL syntax error
                    
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
                    
                    // Update forecast for Total with cluster consideration
                    $updateTotalForecastQuery = "UPDATE budget_data 
                        SET forecast = (
                            SELECT SUM(COALESCE(forecast, 0)) 
                            FROM budget_data b4 
                            WHERE b4.year2 = ? AND b4.period_name = 'Annual Total' AND b4.category_name != 'Total'";

                    if ($userCluster) {
                        $updateTotalForecastQuery .= " AND b4.cluster = ?";
                    }

                    $updateTotalForecastQuery .= ") WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
                    if ($userCluster) {
                        $updateTotalForecastQuery .= " AND cluster = ?";
                        $totalForecastStmt = $conn->prepare($updateTotalForecastQuery);
                        $totalForecastStmt->bind_param("isis", $year, $userCluster, $year, $userCluster);
                    } else {
                        $totalForecastStmt = $conn->prepare($updateTotalForecastQuery);
                        $totalForecastStmt->bind_param("ii", $year, $year);
                    }
                    $totalForecastStmt->execute();
                    
                    // Removed invalid standalone WHERE statement for Total row
                    
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
                    // Variance = ((Budget - (Actual + Forecast)) / Budget) * 100
                    $varianceQuery = "UPDATE budget_data 
                        SET variance_percentage = CASE 
                            WHEN budget > 0 THEN ROUND(((budget - (COALESCE(actual,0) + COALESCE(forecast,0))) / budget) * 100, 2)
                            WHEN budget = 0 AND (COALESCE(actual,0) + COALESCE(forecast,0)) > 0 THEN -100.00
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
                
                // Update the budget_preview table with the budget_id for proper linking if not already set
                if ($budgetId && $insertId) {
                    $updatePreviewQuery = "UPDATE budget_preview SET budget_id = ?, currency = ? WHERE PreviewID = ?";
                    $updatePreviewStmt = $conn->prepare($updatePreviewQuery);
                    $updatePreviewStmt->bind_param("isi", $budgetId, $targetCurrency, $insertId);
                    if ($updatePreviewStmt->execute()) {
                        error_log('AJAX Handler - Linked budget_preview record to budget_data record with ID: ' . $budgetId);
                    } else {
                        error_log('AJAX Handler - Failed to link budget_preview to budget_data: ' . $updatePreviewStmt->error);
                    }
                }
                
                $response = [
                    'success' => true, 
                    'message' => 'Transaction saved successfully! ID: ' . $insertId,
                    'transaction_id' => $insertId,
                    'amount_entered_etb' => $amountETB,
                    'amount_converted' => $amount,
                    'currency' => $targetCurrency
                ];
                error_log('AJAX Handler - Success: Transaction ID ' . $insertId);
                
                // Ensure clean output
                ob_clean();
                echo json_encode($response);
                exit;
            } else {
                handleError('Database execute error', $stmt->error);
            }
            
            $stmt->close();
            break;

        case 'get_transactions':
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Include currency functions
            include 'currency_functions.php';
            
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
            
            if ($userCluster) {
                $stmt = $conn->prepare("SELECT * FROM budget_preview WHERE cluster = ? ORDER BY PreviewID DESC LIMIT 3");
                $stmt->bind_param("s", $userCluster);
            } else {
                $stmt = $conn->prepare("SELECT * FROM budget_preview ORDER BY PreviewID DESC LIMIT 3");
            }
            
            if (!$stmt) {
                handleError('Database prepare error', $conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $transactions = [];
            
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // Convert comma-separated strings back to arrays for display
                    if (!empty($row['DocumentPaths'])) {
                        $row['DocumentPaths'] = explode(',', $row['DocumentPaths']);
                        $row['DocumentTypes'] = !empty($row['DocumentTypes']) ? explode(',', $row['DocumentTypes']) : [];
                        $row['OriginalNames'] = !empty($row['OriginalNames']) ? explode(',', $row['OriginalNames']) : [];
                    } else {
                        $row['DocumentPaths'] = [];
                        $row['DocumentTypes'] = [];
                        $row['OriginalNames'] = [];
                    }
                    
                    // Add currency rates to the transaction data
                    $row['currency_rates'] = $currencyRates;
                    $transactions[] = $row;
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'transactions' => $transactions]);
            exit;
            
        case 'mark_uncertified_on_transaction':
            // Mark budget data as uncertified when new transaction is added
            $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
            
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            $updateSql = "UPDATE budget_data SET certified = 'uncertified' WHERE year2 = ? ";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $updateSql .= " AND cluster = ? ";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("is", $year, $userCluster);
            } else {
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("i", $year);
            }
            
            if ($stmt->execute()) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Budget marked as uncertified due to new transaction']);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to update certification status']);
            }
            exit;
            
        case 'delete_transaction':
            // Delete a transaction and update budget data accordingly
            $transactionId = isset($_POST['id']) ? intval($_POST['id']) : 0;
            
            if ($transactionId <= 0) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
                exit;
            }
            
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Include currency functions
            include 'currency_functions.php';
            
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
            
            // First, get the transaction details before deleting
            $getTransactionQuery = "SELECT bp.Amount, bp.CategoryName, bp.EntryDate, bp.QuarterPeriod, bp.currency, bd.id as budget_id, bd.year2 
                                  FROM budget_preview bp 
                                  LEFT JOIN budget_data bd ON bp.budget_id = bd.id 
                                  WHERE bp.PreviewID = ?";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $getTransactionQuery .= " AND bp.cluster = ?";
                $getStmt = $conn->prepare($getTransactionQuery);
                $getStmt->bind_param("is", $transactionId, $userCluster);
            } else {
                $getStmt = $conn->prepare($getTransactionQuery);
                $getStmt->bind_param("i", $transactionId);
            }
            
            $getStmt->execute();
            $getResult = $getStmt->get_result();
            $transaction = $getResult->fetch_assoc();
            
            if (!$transaction) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Transaction not found']);
                exit;
            }
            
            $amount = $transaction['Amount'];
            $categoryName = $transaction['CategoryName'];
            $entryDate = $transaction['EntryDate'];
            $quarterPeriod = $transaction['QuarterPeriod'];
            $transactionCurrency = $transaction['currency'] ?? 'ETB';
            $budgetId = $transaction['budget_id'];
            $year = $transaction['year2'];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Delete the transaction
                $deleteQuery = "DELETE FROM budget_preview WHERE PreviewID = ?";
                
                // Add cluster condition if user has a cluster
                if ($userCluster) {
                    $deleteQuery .= " AND cluster = ?";
                    $deleteStmt = $conn->prepare($deleteQuery);
                    $deleteStmt->bind_param("is", $transactionId, $userCluster);
                } else {
                    $deleteStmt = $conn->prepare($deleteQuery);
                    $deleteStmt->bind_param("i", $transactionId);
                }
                
                if (!$deleteStmt->execute()) {
                    throw new Exception("Failed to delete transaction: " . $deleteStmt->error);
                }
                
                // Update the budget_data table to reduce actual spending
                if ($budgetId) {
                    $updateBudgetQuery = "UPDATE budget_data SET 
                        actual = GREATEST(COALESCE(actual, 0) - ?, 0),
                        forecast = COALESCE(budget, 0) - GREATEST(COALESCE(actual, 0) - ?, 0),
                        actual_plus_forecast = GREATEST(COALESCE(actual, 0) - ?, 0) + COALESCE(forecast, 0)
                        WHERE id = ?";
                    
                    $updateStmt = $conn->prepare($updateBudgetQuery);
                    $updateStmt->bind_param("dddi", $amount, $amount, $amount, $budgetId);
                    
                    if (!$updateStmt->execute()) {
                        throw new Exception("Failed to update budget data: " . $updateStmt->error);
                    }
                    
                    // Update the Annual Total row for this category
                    $updateAnnualQuery = "UPDATE budget_data 
                        SET budget = (
                            SELECT SUM(COALESCE(budget, 0)) 
                            FROM budget_data b2 
                            WHERE b2.year2 = ? AND b2.category_name = ? AND b2.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')" . 
                            ($userCluster ? " AND b2.cluster = ?" : "") . "
                        ),
                        actual = (
                            SELECT SUM(COALESCE(actual, 0)) 
                            FROM budget_data b3 
                            WHERE b3.year2 = ? AND b3.category_name = ? AND b3.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')" . 
                            ($userCluster ? " AND b3.cluster = ?" : "") . "
                        ),
                        forecast = (
                            SELECT SUM(COALESCE(forecast, 0)) 
                            FROM budget_data b4 
                            WHERE b4.year2 = ? AND b4.category_name = ? AND b4.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')" . 
                            ($userCluster ? " AND b4.cluster = ?" : "") . "
                        ),
                        actual_plus_forecast = (
                            SELECT SUM(COALESCE(actual_plus_forecast, 0)) 
                            FROM budget_data b5 
                            WHERE b5.year2 = ? AND b5.category_name = ? AND b5.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')" . 
                            ($userCluster ? " AND b5.cluster = ?" : "") . "
                        )
                        WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'" . 
                        ($userCluster ? " AND cluster = ?" : "");
                    
                    if ($userCluster) {
                        $annualStmt = $conn->prepare($updateAnnualQuery);
                        $annualStmt->bind_param("issississississis", 
                            $year, $categoryName, $userCluster,
                            $year, $categoryName, $userCluster,
                            $year, $categoryName, $userCluster,
                            $year, $categoryName, $userCluster,
                            $year, $categoryName, $userCluster);
                    } else {
                        $annualStmt = $conn->prepare($updateAnnualQuery);
                        $annualStmt->bind_param("isisisisisisi", 
                            $year, $categoryName,
                            $year, $categoryName,
                            $year, $categoryName,
                            $year, $categoryName,
                            $year, $categoryName);
                    }
                    
                    if (!$annualStmt->execute()) {
                        throw new Exception("Failed to update annual budget data: " . $annualStmt->error);
                    }
                    
                    // Update the Total row across all categories
                    $updateTotalQuery = "UPDATE budget_data 
                        SET budget = (
                            SELECT SUM(COALESCE(budget, 0)) 
                            FROM budget_data b2 
                            WHERE b2.year2 = ? AND b2.period_name = 'Annual Total' AND b2.category_name != 'Total'" . 
                            ($userCluster ? " AND b2.cluster = ?" : "") . "
                        ),
                        actual = (
                            SELECT SUM(COALESCE(actual, 0)) 
                            FROM budget_data b3 
                            WHERE b3.year2 = ? AND b3.period_name = 'Annual Total' AND b3.category_name != 'Total'" . 
                            ($userCluster ? " AND b3.cluster = ?" : "") . "
                        ),
                        forecast = (
                            SELECT SUM(COALESCE(forecast, 0)) 
                            FROM budget_data b4 
                            WHERE b4.year2 = ? AND b4.period_name = 'Annual Total' AND b4.category_name != 'Total'" . 
                            ($userCluster ? " AND b4.cluster = ?" : "") . "
                        ),
                        actual_plus_forecast = (
                            SELECT SUM(COALESCE(actual_plus_forecast, 0)) 
                            FROM budget_data b5 
                            WHERE b5.year2 = ? AND b5.period_name = 'Annual Total' AND b5.category_name != 'Total'" . 
                            ($userCluster ? " AND b5.cluster = ?" : "") . "
                        )
                        WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'" . 
                        ($userCluster ? " AND cluster = ?" : "");
                    
                    if ($userCluster) {
                        $totalStmt = $conn->prepare($updateTotalQuery);
                        $totalStmt->bind_param("isisisisisisis", 
                            $year, $userCluster,
                            $year, $userCluster,
                            $year, $userCluster,
                            $year, $userCluster,
                            $year, $userCluster);
                    } else {
                        $totalStmt = $conn->prepare($updateTotalQuery);
                        $totalStmt->bind_param("iiiiii", 
                            $year, $year, $year, $year, $year);
                    }
                    
                    if (!$totalStmt->execute()) {
                        throw new Exception("Failed to update total budget data: " . $totalStmt->error);
                    }
                    
                    // Update actual_plus_forecast for all quarter rows
                    $updateQuarterForecastQuery = "UPDATE budget_data 
                        SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                        WHERE year2 = ? AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4')" . 
                        ($userCluster ? " AND cluster = ?" : "");
                    
                    if ($userCluster) {
                        $quarterForecastStmt = $conn->prepare($updateQuarterForecastQuery);
                        $quarterForecastStmt->bind_param("is", $year, $userCluster);
                    } else {
                        $quarterForecastStmt = $conn->prepare($updateQuarterForecastQuery);
                        $quarterForecastStmt->bind_param("i", $year);
                    }
                    
                    if (!$quarterForecastStmt->execute()) {
                        throw new Exception("Failed to update quarter forecast data: " . $quarterForecastStmt->error);
                    }
                    
                    // Calculate and update variance percentages
                    $varianceQuery = "UPDATE budget_data 
                        SET variance_percentage = CASE 
                            WHEN budget > 0 THEN ROUND(((budget - (COALESCE(actual,0) + COALESCE(forecast,0))) / budget) * 100, 2)
                            WHEN budget = 0 AND (COALESCE(actual,0) + COALESCE(forecast,0)) > 0 THEN -100.00
                            ELSE 0.00 
                        END
                        WHERE year2 = ?" . ($userCluster ? " AND cluster = ?" : "");
                    
                    if ($userCluster) {
                        $varianceStmt = $conn->prepare($varianceQuery);
                        $varianceStmt->bind_param("is", $year, $userCluster);
                    } else {
                        $varianceStmt = $conn->prepare($varianceQuery);
                        $varianceStmt->bind_param("i", $year);
                    }
                    
                    if (!$varianceStmt->execute()) {
                        throw new Exception("Failed to update variance percentages: " . $varianceStmt->error);
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Transaction deleted successfully',
                    'deleted_amount' => $amount,
                    'currency' => $transactionCurrency
                ]);
                exit;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                ob_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error deleting transaction: ' . $e->getMessage()
                ]);
                exit;
            }
            break;

        case 'get_transactions':
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Include currency functions
            include 'currency_functions.php';
            
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
            
            if ($userCluster) {
                $stmt = $conn->prepare("SELECT * FROM budget_preview WHERE cluster = ? ORDER BY PreviewID DESC LIMIT 3");
                $stmt->bind_param("s", $userCluster);
            } else {
                $stmt = $conn->prepare("SELECT * FROM budget_preview ORDER BY PreviewID DESC LIMIT 3");
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $transactions = [];
            
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // Convert comma-separated strings back to arrays for display
                    if (!empty($row['DocumentPaths'])) {
                        $row['DocumentPaths'] = explode(',', $row['DocumentPaths']);
                        $row['DocumentTypes'] = !empty($row['DocumentTypes']) ? explode(',', $row['DocumentTypes']) : [];
                        $row['OriginalNames'] = !empty($row['OriginalNames']) ? explode(',', $row['OriginalNames']) : [];
                    } else {
                        $row['DocumentPaths'] = [];
                        $row['DocumentTypes'] = [];
                        $row['OriginalNames'] = [];
                    }
                    
                    // Add currency rates to the transaction data
                    $row['currency_rates'] = $currencyRates;
                    $transactions[] = $row;
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'transactions' => $transactions]);
            exit;
            
        case 'check_budget':
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Include currency functions
            include 'currency_functions.php';
            
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
            
            // Check available budget for a specific category and date
            $budgetHeading = trim($_POST['budgetHeading'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $date = trim($_POST['date'] ?? '');
            $year = intval($_POST['year'] ?? date('Y'));
            
            if (empty($budgetHeading) || $amount <= 0 || empty($date)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid parameters for budget check']);
                exit;
            }
            
            // Map budget heading to category name
            $categoryMappings = [
                'Administrative costs' => '1. Administrative costs',
                'Operational support costs' => '2. Operational support costs',
                'Consortium Activities' => '3. Consortium Activities',
                'Targeting new CSOs' => '4. Targeting new CSOs',
                'Contingency' => '5. Contingency'
            ];
            $categoryName = $categoryMappings[$budgetHeading] ?? $budgetHeading;
            
            // Find the correct quarter based on date ranges in database with cluster consideration
            $quarterQuery = "SELECT period_name, budget, actual, forecast, start_date, end_date 
                           FROM budget_data 
                           WHERE year2 = ? AND category_name = ? 
                           AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4')
                           AND ? BETWEEN start_date AND end_date";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $quarterQuery .= " AND cluster = ?";
            }
            
            $quarterQuery .= " LIMIT 1";
            
            if ($userCluster) {
                $stmt = $conn->prepare($quarterQuery);
                $stmt->bind_param("isss", $year, $categoryName, $date, $userCluster);
            } else {
                $stmt = $conn->prepare($quarterQuery);
                $stmt->bind_param("iss", $year, $categoryName, $date);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $quarterData = $result->fetch_assoc();
            
            if (!$quarterData) {
                ob_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => 'No budget period found for the selected date',
                    'debug' => "Date: $date, Category: $categoryName, Year: $year" . ($userCluster ? ", Cluster: $userCluster" : "")
                ]);
                exit;
            }
            
            // Remaining available = budget - actual (handle NULLs) - forecast is future expectation, not committed spending
            $availableBudget = max((float)($quarterData['budget'] ?? 0) - (float)($quarterData['actual'] ?? 0), 0);
            
            // Convert available budget to ETB
            $availableBudgetETB = convertCurrency($availableBudget, 'USD', 'ETB', $currencyRates);
            
            $quarter = $quarterData['period_name'];
            $startDate = $quarterData['start_date'];
            $endDate = $quarterData['end_date'];
            $dateRange = date('M j', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
            
            ob_clean();
            echo json_encode([
                'success' => true, 
                'budget_available' => $availableBudget,
                'budget_available_etb' => $availableBudgetETB,
                'category' => $categoryName,
                'quarter' => $quarter,
                'year' => $year,
                'date_range' => $dateRange,
                'selected_date' => $date,
                'cluster' => $userCluster
            ]);
            exit;
            
        case 'export_budget_data':
            // This endpoint provides data for Excel export
            // Check if year is provided in GET parameters
            $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : 2025;
            
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Query to get all necessary data for the export with cluster consideration
            $sql = "SELECT 
                category_name, 
                period_name, 
                budget, 
                actual, 
                forecast, 
                actual_plus_forecast, 
                variance_percentage 
                FROM budget_data 
                WHERE year2 = ? ";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $sql .= " AND cluster = ? ";
            }
            
            $sql .= "ORDER BY 
                CASE 
                    WHEN category_name LIKE '1.%' THEN 1
                    WHEN category_name LIKE '2.%' THEN 2
                    WHEN category_name LIKE '3.%' THEN 3
                    WHEN category_name LIKE '4.%' THEN 4
                    WHEN category_name LIKE '5.%' THEN 5
                    ELSE 6
                END, 
                CASE 
                    WHEN period_name = 'Q1' THEN 1
                    WHEN period_name = 'Q2' THEN 2
                    WHEN period_name = 'Q3' THEN 3
                    WHEN period_name = 'Q4' THEN 4
                    WHEN period_name = 'Annual Total' THEN 5
                    WHEN period_name = 'Total' THEN 6
                    ELSE 7
                END";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                handleError('Database prepare error', $conn->error);
            }
            
            // Bind parameters based on whether user has a cluster
            if ($userCluster) {
                $stmt->bind_param("is", $selectedYear, $userCluster);
            } else {
                $stmt->bind_param("i", $selectedYear);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Process data into the format needed for CSV
            $categories = [];
            $categoryData = [];
            
            while ($row = $result->fetch_assoc()) {
                $categoryName = $row['category_name'];
                $periodName = $row['period_name'];
                
                if (!isset($categoryData[$categoryName])) {
                    $categoryData[$categoryName] = [
                        'name' => $categoryName,
                        'q1_budget' => '',
                        'q1_actual' => '',
                        'q2_budget' => '',
                        'q2_actual' => '',
                        'q3_budget' => '',
                        'q3_forecast' => '',
                        'q4_budget' => '',
                        'q4_forecast' => '',
                        'annual_budget' => '',
                        'annual_actual_forecast' => '',
                        'variance' => ''
                    ];
                }
                
                // Map periods to corresponding fields
                switch ($periodName) {
                    case 'Q1':
                        $categoryData[$categoryName]['q1_budget'] = $row['budget'] ?? '';
                        $categoryData[$categoryName]['q1_actual'] = $row['actual'] ?? '';
                        break;
                    case 'Q2':
                        $categoryData[$categoryName]['q2_budget'] = $row['budget'] ?? '';
                        $categoryData[$categoryName]['q2_actual'] = $row['actual'] ?? '';
                        break;
                    case 'Q3':
                        $categoryData[$categoryName]['q3_budget'] = $row['budget'] ?? '';
                        $categoryData[$categoryName]['q3_forecast'] = $row['forecast'] ?? '';
                        break;
                    case 'Q4':
                        $categoryData[$categoryName]['q4_budget'] = $row['budget'] ?? '';
                        $categoryData[$categoryName]['q4_forecast'] = $row['forecast'] ?? '';
                        break;
                    case 'Annual Total':
                        $categoryData[$categoryName]['annual_budget'] = $row['budget'] ?? '';
                        $categoryData[$categoryName]['annual_actual_forecast'] = $row['actual_plus_forecast'] ?? '';
                        $categoryData[$categoryName]['variance'] = $row['variance_percentage'] ? $row['variance_percentage'] . '%' : '0%';
                        break;
                }
            }
            
            // Convert to indexed array for JSON output
            foreach ($categoryData as $data) {
                $categories[] = $data;
            }
            
            // Return formatted data
            ob_clean();
            echo json_encode(['success' => true, 'categories' => $categories]);
            exit;
            
        case 'export_budget_preview_data':
            // This endpoint provides budget preview transaction data for Excel export
            // Check if year is provided in GET parameters
            $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : 2025;
            
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Query to get all transaction data from budget_preview table with cluster consideration
            $sql = "SELECT 
                BudgetHeading, 
                Outcome, 
                Activity, 
                BudgetLine, 
                Description, 
                Partner, 
                EntryDate, 
                Amount 
                FROM budget_preview 
                WHERE YEAR(EntryDate) = ? ";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $sql .= " AND cluster = ? ";
            }
            
            $sql .= "ORDER BY EntryDate DESC, PreviewID DESC";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                handleError('Database prepare error', $conn->error);
            }
            
            // Bind parameters based on whether user has a cluster
            if ($userCluster) {
                $stmt->bind_param("is", $selectedYear, $userCluster);
            } else {
                $stmt->bind_param("i", $selectedYear);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Process data for CSV export
            $transactions = [];
            
            while ($row = $result->fetch_assoc()) {
                $transactions[] = [
                    'budget_heading' => $row['BudgetHeading'] ?? '',
                    'outcome' => $row['Outcome'] ?? '',
                    'activity' => $row['Activity'] ?? '',
                    'budget_line' => $row['BudgetLine'] ?? '',
                    'description' => $row['Description'] ?? '',
                    'partner' => $row['Partner'] ?? '',
                    'entry_date' => $row['EntryDate'] ? date('d/m/Y', strtotime($row['EntryDate'])) : '',
                    'amount' => $row['Amount'] ? number_format($row['Amount'], 2) : '0.00'
                ];
            }
            
            // Return formatted data
            ob_clean();
            echo json_encode(['success' => true, 'transactions' => $transactions]);
            exit;
            
        case 'export_transactions_csv':
            // Return transactions as CSV text for client-side ZIP creation
            $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
            $targetCurrency = isset($_GET['currency']) && in_array(strtoupper($_GET['currency']), ['USD','EUR','ETB']) ? strtoupper($_GET['currency']) : 'ETB';

            // Session for role/cluster
            session_start();
            $userRole = $_SESSION['role'] ?? 'finance_officer';
            $userCluster = $_SESSION['cluster_name'] ?? null;

            // Log the parameters for debugging
            error_log("Export CSV - Year: $selectedYear, User Role: $userRole, User Cluster: " . ($userCluster ?? 'null'));

            // Cluster handling: admin may pass ?cluster=..., non-admins are restricted to own cluster
            $selectedCluster = isset($_GET['cluster']) && !empty($_GET['cluster']) ? trim($_GET['cluster']) : null;
            if ($userRole !== 'admin') {
                // Non-admin users can only see their assigned cluster data
                $selectedCluster = $userCluster;
            } else if (!$selectedCluster && $userCluster) {
                // For admin without explicit filter, fall back to user's cluster if present
                $selectedCluster = $userCluster;
            }

            error_log("Export CSV - Selected Cluster: " . ($selectedCluster ?? 'null'));

            // Include currency functions and rates for conversion
            include_once 'currency_functions.php';

            // Determine cluster for rate lookup
            $ratesCluster = $selectedCluster ?? $userCluster ?? null;
            $currencyRates = [];
            if ($ratesCluster) {
                $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $ratesCluster) ?: [];
            }
            if (!$currencyRates) {
                // Fallback defaults
                $currencyRates = [
                    'USD_to_ETB' => 300.0000,
                    'EUR_to_ETB' => 320.0000,
                ];
            }

            // Use year2 column for proper year filtering (contains actual years like 2025)
            $sql = "SELECT 
                BudgetHeading,
                Outcome,
                Activity,
                BudgetLine,
                Description,
                Partner,
                EntryDate,
                Amount,
                currency
            FROM budget_preview
            WHERE YEAR(EntryDate) = ? ";

            if ($selectedCluster) {
                $sql .= " AND cluster = ? ";
            }

            $sql .= "ORDER BY EntryDate DESC, PreviewID DESC";

            error_log("Export CSV - SQL Query: $sql");

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                handleError('Database prepare error', $conn->error);
            }

            if ($selectedCluster) {
                $stmt->bind_param("is", $selectedYear, $selectedCluster);
                error_log("Export CSV - Binding parameters: year=$selectedYear, cluster=$selectedCluster");
            } else {
                $stmt->bind_param("i", $selectedYear);
                error_log("Export CSV - Binding parameters: year=$selectedYear");
            }

            $stmt->execute();
            $result = $stmt->get_result();

            error_log("Export CSV - Number of rows found: " . $result->num_rows);

            // Build CSV in memory
            $fp = fopen('php://temp', 'w+');
            // UTF-8 BOM so Excel opens it correctly
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, ['Budget Heading', 'Outcome', 'Activity', 'Budget Line', 'Transaction Description', 'Partner', 'Payment Date (dd/mm/yyyy)', 'Amount']);

            // Check if we have data
            $rowCount = 0;
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $rowCount++;
                    error_log("Export CSV - Processing row $rowCount: " . json_encode($row));
                    // Convert amount to requested currency using cluster-specific rates
                    $rowCurrency = $row['currency'] ?? 'ETB';
                    $amountOriginal = $row['Amount'] ? floatval($row['Amount']) : 0.0;
                    $amountConverted = convertCurrency($amountOriginal, $rowCurrency, $targetCurrency, $currencyRates);

                    $csvRow = [
                        $row['BudgetHeading'] ?? '',
                        $row['Outcome'] ?? '',
                        $row['Activity'] ?? '',
                        $row['BudgetLine'] ?? '',
                        $row['Description'] ?? '',
                        $row['Partner'] ?? '',
                        $row['EntryDate'] ? date('d/m/Y', strtotime($row['EntryDate'])) : '',
                        number_format($amountConverted, 2, '.', '')
                    ];
                    fputcsv($fp, $csvRow);
                }
            }

            error_log("Export CSV - Total rows processed: $rowCount");

            rewind($fp);
            $csv = stream_get_contents($fp);
            fclose($fp);

            // Output CSV (override JSON header set at file start)
            if (function_exists('header_remove')) {
                header_remove('Content-Type');
            }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="Transactions_'. $targetCurrency .'.csv"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');

            ob_clean();
            echo $csv;
            exit;

        case 'export_transactions_multi_csv':
            // Return transactions as a single CSV with ETB, USD, EUR columns
            $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

            // Session for role/cluster
            session_start();
            $userRole = $_SESSION['role'] ?? 'finance_officer';
            $userCluster = $_SESSION['cluster_name'] ?? null;

            // Cluster handling
            $selectedCluster = isset($_GET['cluster']) && !empty($_GET['cluster']) ? trim($_GET['cluster']) : null;
            if ($userRole !== 'admin') {
                $selectedCluster = $userCluster;
            } else if (!$selectedCluster && $userCluster) {
                $selectedCluster = $userCluster;
            }

            include_once 'currency_functions.php';
            $ratesCluster = $selectedCluster ?? $userCluster ?? null;
            $currencyRates = [];
            if ($ratesCluster) {
                $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $ratesCluster) ?: [];
            }
            if (!$currencyRates) {
                $currencyRates = [
                    'USD_to_ETB' => 300.0000,
                    'EUR_to_ETB' => 320.0000,
                ];
            }

            $sql = "SELECT 
                BudgetHeading,
                Outcome,
                Activity,
                BudgetLine,
                Description,
                Partner,
                EntryDate,
                Amount,
                currency
            FROM budget_preview
            WHERE YEAR(EntryDate) = ? ";

            if ($selectedCluster) {
                $sql .= " AND cluster = ? ";
            }
            $sql .= "ORDER BY EntryDate DESC, PreviewID DESC";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                handleError('Database prepare error', $conn->error);
            }
            if ($selectedCluster) {
                $stmt->bind_param("is", $selectedYear, $selectedCluster);
            } else {
                $stmt->bind_param("i", $selectedYear);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $fp = fopen('php://temp', 'w+');
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, ['Budget Heading', 'Outcome', 'Activity', 'Budget Line', 'Transaction Description', 'Partner', 'Payment Date (dd/mm/yyyy)', 'Amount (ETB)', 'Amount (USD)', 'Amount (EUR)']);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $rowCurrency = $row['currency'] ?? 'ETB';
                    $amountOriginal = $row['Amount'] ? floatval($row['Amount']) : 0.0;
                    $amtETB = convertCurrency($amountOriginal, $rowCurrency, 'ETB', $currencyRates);
                    $amtUSD = convertCurrency($amountOriginal, $rowCurrency, 'USD', $currencyRates);
                    $amtEUR = convertCurrency($amountOriginal, $rowCurrency, 'EUR', $currencyRates);

                    fputcsv($fp, [
                        $row['BudgetHeading'] ?? '',
                        $row['Outcome'] ?? '',
                        $row['Activity'] ?? '',
                        $row['BudgetLine'] ?? '',
                        $row['Description'] ?? '',
                        $row['Partner'] ?? '',
                        $row['EntryDate'] ? date('d/m/Y', strtotime($row['EntryDate'])) : '',
                        number_format($amtETB, 2, '.', ''),
                        number_format($amtUSD, 2, '.', ''),
                        number_format($amtEUR, 2, '.', '')
                    ]);
                }
            }

            rewind($fp);
            $csv = stream_get_contents($fp);
            fclose($fp);

            if (function_exists('header_remove')) header_remove('Content-Type');
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="Transactions_MultiCurrency.csv"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');

            ob_clean();
            echo $csv;
            exit;

        case 'export_combined_excel':
            // Enable error reporting
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            // Start session
            session_start();

            // Include database config
            include 'setup_database.php';

            // Get parameters
            $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
            $selectedCluster = $_GET['cluster'] ?? null;

            // If no cluster selected, use user's cluster
            if (!$selectedCluster) {
                $userCluster = $_SESSION['cluster_name'] ?? null;
                $selectedCluster = $userCluster;
            }

            // Create temporary directory
            $tempDir = sys_get_temp_dir() . '/budget_export_' . time() . '_' . rand(1000, 9999);
            mkdir($tempDir, 0777, true);

            try {
                // =======================
                // 1. EXPORT BUDGET_DATA.CSV (Pivoted Format)
                // =======================
                $budgetFile = $tempDir . '/Budget_Forecast_' . $selectedYear . '.csv';
                $budgetHandle = fopen($budgetFile, 'w');
                if (!$budgetHandle) throw new Exception('Failed to create budget CSV');

                // Add UTF-8 BOM for Excel compatibility
                fwrite($budgetHandle, "\xEF\xBB\xBF");

                // Headers
                $headers = [
                    'Category',
                    'Q1 Budget', 'Q1 Actual',
                    'Q2 Budget', 'Q2 Actual',
                    'Q3 Budget', 'Q3 Forecast',
                    'Q4 Budget', 'Q4 Forecast',
                    'Annual Budget', 'Actual + Forecast', 'Variance (%)'
                ];
                fputcsv($budgetHandle, $headers);

                // Step 1: Get all categories
                $categoriesQuery = "SELECT DISTINCT category_name FROM budget_data WHERE year2 = ?";
                if ($selectedCluster) $categoriesQuery .= " AND cluster = ?";
                $catStmt = $conn->prepare($categoriesQuery);
                if ($selectedCluster) {
                    $catStmt->bind_param("is", $selectedYear, $selectedCluster);
                } else {
                    $catStmt->bind_param("i", $selectedYear);
                }
                $catStmt->execute();
                $catResult = $catStmt->get_result();

                $categoryData = [];
                while ($row = $catResult->fetch_assoc()) {
                    $catName = $row['category_name'];
                    $categoryData[$catName] = [
                        'category' => $catName,
                        'q1_budget' => 0, 'q1_actual' => 0,
                        'q2_budget' => 0, 'q2_actual' => 0,
                        'q3_budget' => 0, 'q3_forecast' => 0,
                        'q4_budget' => 0, 'q4_forecast' => 0,
                        'annual_budget' => 0, 'annual_actual_forecast' => 0, 'variance' => 0
                    ];
                }

                // Step 2: Fill in values
                $dataQuery = "SELECT category_name, period_name, budget, actual, forecast, actual_plus_forecast, variance_percentage 
                              FROM budget_data 
                              WHERE year2 = ?";
                if ($selectedCluster) $dataQuery .= " AND cluster = ?";
                $dataQuery .= " ORDER BY category_name, period_name";

                $dataStmt = $conn->prepare($dataQuery);
                if ($selectedCluster) {
                    $dataStmt->bind_param("is", $selectedYear, $selectedCluster);
                } else {
                    $dataStmt->bind_param("i", $selectedYear);
                }
                $dataStmt->execute();
                $result = $dataStmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $cat = $row['category_name'];
                    if (!isset($categoryData[$cat])) continue;

                    $data = &$categoryData[$cat];
                    $period = $row['period_name'];

                    switch ($period) {
                        case 'Q1':
                            $data['q1_budget'] = (float)($row['budget'] ?? 0);
                            $data['q1_actual'] = (float)($row['actual'] ?? 0);
                            break;
                        case 'Q2':
                            $data['q2_budget'] = (float)($row['budget'] ?? 0);
                            $data['q2_actual'] = (float)($row['actual'] ?? 0);
                            break;
                        case 'Q3':
                            $data['q3_budget'] = (float)($row['budget'] ?? 0);
                            $data['q3_forecast'] = (float)($row['forecast'] ?? 0);
                            break;
                        case 'Q4':
                            $data['q4_budget'] = (float)($row['budget'] ?? 0);
                            $data['q4_forecast'] = (float)($row['forecast'] ?? 0);
                            break;
                        case 'Annual Total':
                            $data['annual_budget'] = (float)($row['budget'] ?? 0);
                            $data['annual_actual_forecast'] = (float)($row['actual_plus_forecast'] ?? 0);
                            $data['variance'] = (float)($row['variance_percentage'] ?? 0);
                            break;
                    }
                }

                // Step 3: Calculate Grand Total
                $grandTotal = [
                    'q1_budget' => 0, 'q1_actual' => 0,
                    'q2_budget' => 0, 'q2_actual' => 0,
                    'q3_budget' => 0, 'q3_forecast' => 0,
                    'q4_budget' => 0, 'q4_forecast' => 0,
                    'annual_budget' => 0, 'annual_actual_forecast' => 0
                ];

                foreach ($categoryData as $data) {
                    if (in_array(strtoupper($data['category']), ['TOTAL', 'GRAND TOTAL'])) continue;

                    foreach ($grandTotal as $key => $value) {
                        $grandTotal[$key] += $data[$key];
                    }
                }

                // Grand Total Variance: (budget - (actual+forecast)) / budget * 100
                $gtVariance = 0;
                if ($grandTotal['annual_budget'] > 0) {
                    $gtVariance = (($grandTotal['annual_budget'] - $grandTotal['annual_actual_forecast']) / $grandTotal['annual_budget']) * 100;
                }

                $categoryData['Grand Total'] = [
                    'category' => 'Grand Total',
                    'q1_budget' => $grandTotal['q1_budget'],
                    'q1_actual' => $grandTotal['q1_actual'],
                    'q2_budget' => $grandTotal['q2_budget'],
                    'q2_actual' => $grandTotal['q2_actual'],
                    'q3_budget' => $grandTotal['q3_budget'],
                    'q3_forecast' => $grandTotal['q3_forecast'],
                    'q4_budget' => $grandTotal['q4_budget'],
                    'q4_forecast' => $grandTotal['q4_forecast'],
                    'annual_budget' => $grandTotal['annual_budget'],
                    'annual_actual_forecast' => $grandTotal['annual_actual_forecast'],
                    'variance' => $gtVariance
                ];

                // Write rows
                foreach ($categoryData as $data) {
                    fputcsv($budgetHandle, [
                        $data['category'],
                        number_format($data['q1_budget'], 2, '.', ''),
                        number_format($data['q1_actual'], 2, '.', ''),
                        number_format($data['q2_budget'], 2, '.', ''),
                        number_format($data['q2_actual'], 2, '.', ''),
                        number_format($data['q3_budget'], 2, '.', ''),
                        number_format($data['q3_forecast'], 2, '.', ''),
                        number_format($data['q4_budget'], 2, '.', ''),
                        number_format($data['q4_forecast'], 2, '.', ''),
                        number_format($data['annual_budget'], 2, '.', ''),
                        number_format($data['annual_actual_forecast'], 2, '.', ''),
                        number_format($data['variance'], 2, '.', '') . '%'
                    ]);
                }

                fclose($budgetHandle);

                // =======================
                // 2. EXPORT TRANSACTIONS.CSV
                // =======================
                $transactionFile = $tempDir . '/Budget_Transactions_' . $selectedYear . '.csv';
                $transactionHandle = fopen($transactionFile, 'w');
                if (!$transactionHandle) throw new Exception('Failed to create transaction CSV');

                fwrite($transactionHandle, "\xEF\xBB\xBF"); // UTF-8 BOM

                fputcsv($transactionHandle, [
                    'Budget Heading',
                    'Outcome',
                    'Activity',
                    'Budget Line',
                    'Transaction Description',
                    'Partner',
                    'Payment Date (dd/mm/yyyy)',
                    'Amount'
                ]);

                $transSql = "SELECT 
                    BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount 
                    FROM budget_preview 
                    WHERE YEAR(EntryDate) = ? ";
                if ($selectedCluster) $transSql .= " AND cluster = ?";
                $transSql .= " ORDER BY EntryDate DESC, PreviewID DESC";

                $transStmt = $conn->prepare($transSql);
                if ($selectedCluster) {
                    $transStmt->bind_param("is", $selectedYear, $selectedCluster);
                } else {
                    $transStmt->bind_param("i", $selectedYear);
                }
                $transStmt->execute();
                $transResult = $transStmt->get_result();

                while ($row = $transResult->fetch_assoc()) {
                    fputcsv($transactionHandle, [
                        $row['BudgetHeading'] ?? '',
                        $row['Outcome'] ?? '',
                        $row['Activity'] ?? '',
                        $row['BudgetLine'] ?? '',
                        $row['Description'] ?? '',
                        $row['Partner'] ?? '',
                        $row['EntryDate'] ? date('d/m/Y', strtotime($row['EntryDate'])) : '',
                        $row['Amount'] ? number_format($row['Amount'], 2, '.', '') : '0.00'
                    ]);
                }

                fclose($transactionHandle);

                // =======================
                // 3. CREATE ZIP FILE
                // =======================
                $zipFile = $tempDir . '/Budget_Report_' . $selectedYear . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
                    throw new Exception('Cannot create ZIP');
                }

                $zip->addFile($budgetFile, 'Budget_Forecast_' . $selectedYear . '.csv');
                $zip->addFile($transactionFile, 'Budget_Transactions_' . $selectedYear . '.csv');
                $zip->close();

                // =======================
                // 4. SEND ZIP TO BROWSER
                // =======================
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="Budget_Report_' . $selectedYear . ($selectedCluster ? '_' . $selectedCluster : '') . '.zip"');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                readfile($zipFile);

                // Cleanup
                unlink($budgetFile);
                unlink($transactionFile);
                unlink($zipFile);
                rmdir($tempDir);
                exit;

            } catch (Exception $e) {
                // Clean up on error
                if (is_dir($tempDir)) {
                    array_map('unlink', glob("$tempDir/*"));
                    rmdir($tempDir);
                }
                handleError('Export failed: ' . $e->getMessage());
            }
            
        case 'upload_certificate':
            // Handle certificate upload and database updates
            $selectedYear = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
            
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Validate file upload
            if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
                handleError('No certificate file uploaded or upload error occurred');
            }
            
            $file = $_FILES['certificate'];
            
            // Validate file type (PDF only)
            $allowedTypes = ['application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                handleError('Only PDF files are allowed for certificates');
            }
            
            // Validate file size (max 10MB)
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                handleError('Certificate file size must be less than 10MB');
            }
            
            // Create certificate directory if it doesn't exist
            $certificateDir = 'admin/uploads/certificates/';
            if (!file_exists($certificateDir)) {
                if (!mkdir($certificateDir, 0777, true)) {
                    handleError('Failed to create certificate upload directory');
                }
            }
            
            // Generate unique filename
            $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $timestamp = date('Y-m-d_H-i-s');
            $uniqueId = uniqid();
            $fileName = "certificate_{$selectedYear}_{$timestamp}_{$uniqueId}.{$extension}";
            $filePath = $certificateDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                handleError('Failed to save certificate file');
            }
            
            try {
                // Instead of copying all budget data, just insert a simple record
                $insertSql = "INSERT INTO certificates_simple (cluster_name, year, certificate_path, uploaded_by) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insertSql);
                
                // Use 'Unknown' if no cluster is set
                $clusterName = $userCluster ?? 'Unknown';
                $uploadedBy = 'admin'; // In a real app, you might want to get the actual username
                
                $stmt->bind_param("siss", $clusterName, $selectedYear, $filePath, $uploadedBy);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to save certificate information: ' . $stmt->error);
                }
                
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => "Certificate uploaded successfully for year {$selectedYear}",
                    'certificate_path' => $filePath,
                    'filename' => $fileName
                ]);
                exit;
                
            } catch (Exception $e) {
                // Delete uploaded file if database operations failed
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                handleError('Database error during certificate upload: ' . $e->getMessage());
            }
            
        case 'mark_uncertified_on_transaction':
            // Mark budget data as uncertified when new transaction is added
            $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
            
            // Start session to get user cluster
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            $updateSql = "UPDATE budget_data SET certified = 'uncertified' WHERE year2 = ? ";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $updateSql .= " AND cluster = ? ";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("is", $year, $userCluster);
            } else {
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("i", $year);
            }
            
            if ($stmt->execute()) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Budget marked as uncertified due to new transaction']);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to update certification status']);
            }
            exit;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            exit;
    }
} catch (Exception $e) {
    handleError('Server exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    handleError('Fatal error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

error_log('AJAX Handler - Request completed');
?>