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
            // Normalize category input to support both numbered and non-numbered forms, case-insensitive
            // Based on user's database format, category names are stored WITHOUT prefixes
            $normalizeCategory = function(string $cat): string {
                $original = trim($cat);
                // For user's database, we need to strip prefixes, not add them
                $stripped = preg_replace('/^\s*\d+\s*\.\s*/', '', $original);
                return $stripped;
            };
            $mappedCategoryName = $normalizeCategory($budgetHeading);
            
            // Debug logging
            error_log("AJAX Handler - Budget heading: '$budgetHeading', Mapped category: '$mappedCategoryName'");
            
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
            }
            if (!$currencyRates) {
                // Default rates if not found
                $currencyRates = [
                    'USD_to_ETB' => 55.0000,
                    'EUR_to_ETB' => 60.0000
                ];
            }

            // If cluster has custom currency rates enabled and custom rate provided, override USD/EUR -> ETB for this request
            if (isClusterCustomCurrencyEnabled($conn, $userCluster) && isset($_POST['use_custom_rate']) && $_POST['use_custom_rate'] === '1') {
                $customUsdToEtb = isset($_POST['usd_to_etb']) ? floatval($_POST['usd_to_etb']) : 0;
                $customEurToEtb = isset($_POST['eur_to_etb']) ? floatval($_POST['eur_to_etb']) : 0;
                if ($customUsdToEtb > 0) { $currencyRates['USD_to_ETB'] = $customUsdToEtb; }
                if ($customEurToEtb > 0) { $currencyRates['EUR_to_ETB'] = $customEurToEtb; }
                error_log("Custom rates applied: USD_to_ETB=$customUsdToEtb, EUR_to_ETB=$customEurToEtb");
            } else {
                error_log("Custom rates NOT applied. Enabled: " . (isClusterCustomCurrencyEnabled($conn, $userCluster) ? 'true' : 'false') . ", use_custom_rate: " . ($_POST['use_custom_rate'] ?? 'not set'));
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
            
            // Set budget tracking values for budget_preview table (use values as-is from budget_data)
            $quarterPeriod = $quarterBudgetData['period_name'] ?? 'Unknown';
            $originalBudget = (float)($quarterBudgetData['budget'] ?? 0);
            $currentActual = (float)($quarterBudgetData['actual'] ?? 0);
            // For preview, take values directly from budget_data (will refresh after update below)
            $actualSpent = $currentActual;
            $forecastAmount = (float)($quarterBudgetData['forecast'] ?? 0);
            $remainingBudget = $forecastAmount;
            $variancePercentage = (float)($quarterBudgetData['variance_percentage'] ?? 0);
            
            // Prepare and execute statement
            error_log('AJAX Handler - Preparing database statement');
            // Capture custom rate usage for persistence
            $useCustomRateFlag = 0;
            $usdToEtbPersist = null;
            $eurToEtbPersist = null;
            $usdToEurPersist = null;
            if (isClusterCustomCurrencyEnabled($conn, $userCluster) && isset($_POST['use_custom_rate']) && $_POST['use_custom_rate'] === '1') {
                $useCustomRateFlag = 1;
                $usdToEtbPersist = isset($_POST['usd_to_etb']) && floatval($_POST['usd_to_etb']) > 0 ? floatval($_POST['usd_to_etb']) : null;
                $eurToEtbPersist = isset($_POST['eur_to_etb']) && floatval($_POST['eur_to_etb']) > 0 ? floatval($_POST['eur_to_etb']) : null;
                // not always provided but keep slot available
                $usdToEurPersist = isset($_POST['usd_to_eur']) && floatval($_POST['usd_to_eur']) > 0 ? floatval($_POST['usd_to_eur']) : null;
            }

            // Check if new rate columns exist; if not, fall back to legacy INSERT to avoid prepare errors
            $hasRatesCols = false;
            if ($resDb = $conn->query("SELECT DATABASE() as db")) {
                $dbRowX = $resDb->fetch_assoc();
                $dbNameX = $dbRowX['db'] ?? '';
                if ($dbNameX) {
                    $checkSql = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbNameX) . "' AND TABLE_NAME = 'budget_preview' AND COLUMN_NAME = 'use_custom_rate'";
                    if ($resCols = $conn->query($checkSql)) {
                        $cntRow = $resCols->fetch_assoc();
                        $hasRatesCols = intval($cntRow['cnt'] ?? 0) > 0;
                    }
                }
            }

            if ($hasRatesCols) {
                // Insert including persisted custom rate columns
                $stmt = $conn->prepare("INSERT INTO budget_preview (BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount, PVNumber, DocumentPaths, DocumentTypes, OriginalNames, QuarterPeriod, CategoryName, OriginalBudget, RemainingBudget, ActualSpent, ForecastAmount, VariancePercentage, cluster, budget_id, currency, COMMENTS, ACCEPTANCE, use_custom_rate, usd_to_etb, eur_to_etb, usd_to_eur) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            } else {
                // Legacy insert without custom rate columns
                $stmt = $conn->prepare("INSERT INTO budget_preview (BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount, PVNumber, DocumentPaths, DocumentTypes, OriginalNames, QuarterPeriod, CategoryName, OriginalBudget, RemainingBudget, ActualSpent, ForecastAmount, VariancePercentage, cluster, budget_id, currency, COMMENTS, ACCEPTANCE) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            }
            
            if (!$stmt) {
                handleError('Database prepare error', $conn->error);
            }
            
            // Empty values for COMMENTS and ACCEPTANCE fields
            $emptyString = '';
            
            error_log('AJAX Handler - Binding parameters');
            
            // DEBUG: Log all variables and their values
            $debugVars = [
                'budgetHeading' => $budgetHeading,
                'outcome' => $outcome,
                'activity' => $activity,
                'budgetLine' => $budgetLine,
                'description' => $description,
                'partner' => $partner,
                'entryDate' => $entryDate,
                'amount' => $amount,
                'pvNumber' => $pvNumber,
                'documentPathsStr' => $documentPathsStr,
                'documentTypesStr' => $documentTypesStr,
                'originalNamesStr' => $originalNamesStr,
                'quarterPeriod' => $quarterPeriod,
                'mappedCategoryName' => $mappedCategoryName,
                'originalBudget' => $originalBudget,
                'remainingBudget' => $remainingBudget,
                'actualSpent' => $actualSpent,
                'forecastAmount' => $forecastAmount,
                'variancePercentage' => $variancePercentage,
                'userCluster' => $userCluster,
                'budgetId' => $budgetId,
                'targetCurrency' => $targetCurrency,
                'emptyString' => $emptyString,
                'hasRatesCols' => $hasRatesCols
            ];
            
            if ($hasRatesCols) {
                $debugVars['useCustomRateFlag'] = $useCustomRateFlag;
                $debugVars['usdToEtbPersist'] = $usdToEtbPersist;
                $debugVars['eurToEtbPersist'] = $eurToEtbPersist;
                $debugVars['usdToEurPersist'] = $usdToEurPersist;
                
                error_log('DEBUG - hasRatesCols=true, variables: ' . json_encode($debugVars));
                $typeString = "sssssssdssssssssddssisssiddd";
                error_log('DEBUG - Type string: ' . $typeString . ' (length: ' . strlen($typeString) . ')');
                error_log('DEBUG - Parameter count: 28');
                
                // With persisted custom rate fields
                // Ensure all variables are defined
                $useCustomRateFlag = $useCustomRateFlag ?? 0;
                $usdToEtbPersist = $usdToEtbPersist ?? null;
                $eurToEtbPersist = $eurToEtbPersist ?? null;
                $usdToEurPersist = $usdToEurPersist ?? null;
                
                // Count actual parameters being passed
                $params = [$budgetHeading, $outcome, $activity, $budgetLine, $description, $partner, $entryDate, $amount, $pvNumber, $documentPathsStr, $documentTypesStr, $originalNamesStr, $quarterPeriod, $mappedCategoryName, $originalBudget, $remainingBudget, $actualSpent, $forecastAmount, $variancePercentage, $userCluster, $budgetId, $targetCurrency, $emptyString, $emptyString, $useCustomRateFlag, $usdToEtbPersist, $eurToEtbPersist, $usdToEurPersist];
                error_log('DEBUG - Actual parameter count: ' . count($params));
                
                $stmt->bind_param("sssssssdssssssssddssisssiddd", $budgetHeading, $outcome, $activity, $budgetLine, $description, $partner, $entryDate, $amount, $pvNumber, $documentPathsStr, $documentTypesStr, $originalNamesStr, $quarterPeriod, $mappedCategoryName, $originalBudget, $remainingBudget, $actualSpent, $forecastAmount, $variancePercentage, $userCluster, $budgetId, $targetCurrency, $emptyString, $emptyString, $useCustomRateFlag, $usdToEtbPersist, $eurToEtbPersist, $usdToEurPersist);
            } else {
                error_log('DEBUG - hasRatesCols=false, variables: ' . json_encode($debugVars));
                $typeString = "sssssssdssssssssddssisss";
                error_log('DEBUG - Type string: ' . $typeString . ' (length: ' . strlen($typeString) . ')');
                error_log('DEBUG - Parameter count: 24');
                
                // Legacy binding without the extra columns - EXACT COPY of old working code
                $stmt->bind_param("sssssssdssssssssddssisss", $budgetHeading, $outcome, $activity, $budgetLine, $description, $partner, $entryDate, $amount, $pvNumber, $documentPathsStr, $documentTypesStr, $originalNamesStr, $quarterPeriod, $mappedCategoryName, $originalBudget, $remainingBudget, $actualSpent, $forecastAmount, $variancePercentage, $userCluster, $budgetId, $targetCurrency, $emptyString, $emptyString);
            }
            
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
                // If quarter is 'Unknown', it means no budget period was found for the transaction date
                if ($quarter === 'Unknown') {
                    handleError('No budget period found for the transaction date', 
                        "No budget period found for date $transactionDate, category $categoryName, year $year" . ($userCluster ? ", cluster $userCluster" : ""));
                }
                
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
                
                // Allow saving transactions even if budget is exceeded
                // Comment out the budget validation check
                /*
                if ($amountInBudgetCurrency > $availableBudget) {
                    handleError('Insufficient budget available', 
                        "Transaction amount (" . number_format($amountInBudgetCurrency, 2) . " $budgetCurrency) exceeds available budget (" . number_format($availableBudget, 2) . " $budgetCurrency) for $categoryName in $quarter $year");
                }
                */
                
                // Update the quarter row: increase actual by amount, and decrease forecast by the same amount (do NOT set forecast = budget - actual)
                // MySQL evaluates SET clauses left to right, so later expressions see updated column values
                $updateBudgetQuery = "UPDATE budget_data SET 
                    actual = COALESCE(actual, 0) + ?,
                    forecast = GREATEST(COALESCE(forecast, 0) - ?, 0),
                    actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                    WHERE year2 = ? AND category_name = ? AND period_name = ?
                    AND ? BETWEEN start_date AND end_date";
                
                // Add cluster condition if user has a cluster
                if ($userCluster) {
                    $updateBudgetQuery .= " AND cluster = ?";
                    $updateStmt = $conn->prepare($updateBudgetQuery);
                    // Params: 2 doubles (amount for actual increase and forecast decrease), 1 integer (year), 4 strings (categoryName, quarter, transactionDate, userCluster)
                    $updateStmt->bind_param("ddissss", $amountInBudgetCurrency, $amountInBudgetCurrency, $year, $categoryName, $quarter, $transactionDate, $userCluster);
                } else {
                    $updateStmt = $conn->prepare($updateBudgetQuery);
                    // Params: 2 doubles (amount for actual increase and forecast decrease), 1 integer (year), 3 strings (categoryName, quarter, transactionDate)
                    $updateStmt->bind_param("ddisss", $amountInBudgetCurrency, $amountInBudgetCurrency, $year, $categoryName, $quarter, $transactionDate);
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
                    
                    // Do not auto-update forecast for Annual Total; forecast remains manual
                    
                    // Synchronize Annual Total forecast as sum of quarter forecasts with cluster consideration
                    $updateAnnualForecastSumQuery = "UPDATE budget_data 
                        SET forecast = (
                            SELECT COALESCE(SUM(forecast), 0)
                            FROM budget_data b 
                            WHERE b.year2 = ? AND b.category_name = ? AND b.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";
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
                    
                    // Do not auto-update forecast for Total; forecast remains manual
                    
                    // Synchronize Total forecast as sum of Annual Total forecasts across categories with cluster consideration
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
                        // Ensure outer update also filters by cluster to match bound parameters
                        $updateTotalForecastSumQuery .= " AND cluster = ?";
                        $updateTotalForecastSumStmt = $conn->prepare($updateTotalForecastSumQuery);
                        $updateTotalForecastSumStmt->bind_param("isis", $year, $userCluster, $year, $userCluster);
                    } else {
                        $updateTotalForecastSumStmt = $conn->prepare($updateTotalForecastSumQuery);
                        $updateTotalForecastSumStmt->bind_param("ii", $year, $year);
                    }
                    $updateTotalForecastSumStmt->execute();

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
                            WHEN budget > 0 THEN ROUND((((budget - COALESCE(actual,0)) / ABS(budget)) * 100), 2)
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
                    
                    // Update the preview table with the latest budget data for consistency
                    $previewUpdateQuery = "UPDATE budget_preview bp
                        JOIN budget_data bd ON bp.budget_id = bd.id
                        SET bp.OriginalBudget = bd.budget,
                            bp.RemainingBudget = bd.forecast,
                            bp.ActualSpent = bd.actual,
                            bp.ForecastAmount = bd.forecast,
                            bp.VariancePercentage = bd.variance_percentage
                        WHERE bp.budget_id = ?";
                    
                    $previewUpdateStmt = $conn->prepare($previewUpdateQuery);
                    $previewUpdateStmt->bind_param("i", $budgetId);
                    $previewUpdateStmt->execute();
                    
                    // Return success response
                    $response = [
                        'success' => true,
                        'message' => 'Transaction saved successfully',
                        'insert_id' => $insertId,
                        'amount_converted' => $amount,
                        'amount_etb' => $amountETB,
                        'currency_rates' => $currencyRates,
                        'use_custom_rate' => $useCustomRateFlag,
                        'usd_to_etb_persist' => $usdToEtbPersist,
                        'eur_to_etb_persist' => $eurToEtbPersist
                    ];
                    echo json_encode($response);
                    exit;
                } else {
                    handleError('Failed to update budget data', $updateStmt->error);
                }
            } else {
                handleError('Failed to insert transaction', $stmt->error);
            }
            break;
            
        case 'check_budget':
            error_log('AJAX Handler - Starting check_budget');
            
            // Get form data
            $budgetHeading = trim($_POST['budgetHeading'] ?? '');
            $amountETB = floatval($_POST['amount'] ?? 0);
            $date = $_POST['date'] ?? '';
            $year = intval($_POST['year'] ?? date('Y'));
            
            // Get user cluster from session
            session_start();
            $userCluster = $_SESSION['cluster_name'] ?? null;
            
            // Include currency functions
            include 'currency_functions.php';
            
            // Get currency rates for the user's cluster
            $currencyRates = [];
            if ($userCluster) {
                $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $userCluster);
            }
            if (!$currencyRates) {
                // Default rates if not found
                $currencyRates = [
                    'USD_to_ETB' => 55.0000,
                    'EUR_to_ETB' => 60.0000
                ];
            }

            // If cluster has custom currency rates enabled and custom rate provided, override USD/EUR -> ETB for this request
            if (isClusterCustomCurrencyEnabled($conn, $userCluster) && isset($_POST['use_custom_rate']) && $_POST['use_custom_rate'] === '1') {
                $customUsdToEtb = isset($_POST['usd_to_etb']) ? floatval($_POST['usd_to_etb']) : 0;
                $customEurToEtb = isset($_POST['eur_to_etb']) ? floatval($_POST['eur_to_etb']) : 0;
                if ($customUsdToEtb > 0) { $currencyRates['USD_to_ETB'] = $customUsdToEtb; }
                if ($customEurToEtb > 0) { $currencyRates['EUR_to_ETB'] = $customEurToEtb; }
            }
            
            // Normalize category input
            $normalizeCategory = function(string $cat): string {
                $original = trim($cat);
                $stripped = preg_replace('/^\s*\d+\s*\.\s*/', '', $original);
                return $stripped;
            };
            $mappedCategoryName = $normalizeCategory($budgetHeading);
            
            // Determine quarter from entry date
            $entryDateTime = new DateTime($date);
            
            // Find the correct quarter and get budget data with cluster consideration
            $quarterBudgetQuery = "SELECT budget, actual, forecast, currency 
                                 FROM budget_data 
                                 WHERE year2 = ? AND category_name = ? 
                                 AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4')
                                 AND ? BETWEEN start_date AND end_date";
            
            // Add cluster condition if user has a cluster
            if ($userCluster) {
                $quarterBudgetQuery .= " AND cluster = ?";
                $quarterBudgetStmt = $conn->prepare($quarterBudgetQuery);
                $quarterBudgetStmt->bind_param("isss", $year, $mappedCategoryName, $entryDateTime->format('Y-m-d'), $userCluster);
            } else {
                $quarterBudgetStmt = $conn->prepare($quarterBudgetQuery);
                $quarterBudgetStmt->bind_param("iss", $year, $mappedCategoryName, $entryDateTime->format('Y-m-d'));
            }
            
            $quarterBudgetStmt->execute();
            $quarterBudgetResult = $quarterBudgetStmt->get_result();
            $quarterBudgetData = $quarterBudgetResult->fetch_assoc();
            
            if (!$quarterBudgetData) {
                handleError('No budget data found for the selected category and date');
            }
            
            // Get the currency for the budget data
            $budgetCurrency = $quarterBudgetData['currency'] ?? 'ETB';
            
            // Convert the entered amount to the same currency as the budget for comparison
            $amountInBudgetCurrency = convertCurrency($amountETB, 'ETB', $budgetCurrency, $currencyRates);
            
            // Calculate available budget (budget - actual)
            $availableBudget = max((float)($quarterBudgetData['budget'] ?? 0) - (float)($quarterBudgetData['actual'] ?? 0), 0);
            
            // Return the result
            $response = [
                'success' => true,
                'budget_available' => $availableBudget,
                'budget_available_etb' => convertCurrency($availableBudget, $budgetCurrency, 'ETB', $currencyRates),
                'entered_amount' => $amountInBudgetCurrency,
                'entered_amount_etb' => $amountETB,
                'currency' => $budgetCurrency,
                'currency_rates' => $currencyRates
            ];
            
            echo json_encode($response);
            exit;
            
        default:
            handleError('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    handleError('Server error: ' . $e->getMessage(), $e->getTraceAsString());
}