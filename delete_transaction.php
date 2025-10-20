<?php
session_start();
ob_start();  // Start output buffering to catch any stray output

// Enable error reporting for debugging (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Prevent HTML errors from polluting JSON; use error_log instead

// Set JSON content type immediately
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'User not logged in'
    ];
    ob_end_clean();
    echo json_encode($response);
    exit();
}

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Initialize response
$response = [
    'success' => false,
    'message' => 'No transaction ID provided'
];

// Check if transaction ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $transactionId = intval($_GET['id']);
    
    // Log the transaction ID being processed
    error_log('Processing delete request for transaction ID: ' . $transactionId);
    
    // Begin transaction for database consistency
    $conn->begin_transaction();
    error_log('Database transaction begun');
    
    try {
        // Validate entry date early to avoid issues
        $entryDate = null;
        $year = null;
        
        // Get transaction details before deletion to update budget_data including custom rate info
        $getTransactionQuery = "SELECT Amount, budget_id, CategoryName, QuarterPeriod, EntryDate, ACCEPTANCE, currency, use_custom_rate, usd_to_etb, eur_to_etb, usd_to_eur FROM budget_preview WHERE PreviewID = ?";
        error_log('Query to get transaction: ' . $getTransactionQuery . ' with ID: ' . $transactionId);
        
        $stmt = $conn->prepare($getTransactionQuery);
        if (!$stmt) {
            throw new Exception("Prepare statement failed for transaction lookup: " . $conn->error);
        }
        
        $stmt->bind_param("i", $transactionId);
        if (!$stmt->execute()) {
            throw new Exception("Execute statement failed for transaction lookup: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("Transaction not found with ID: " . $transactionId);
            throw new Exception("Transaction not found with ID: " . $transactionId);
        }
        
        error_log("Transaction found, fetching data");
        $transaction = $result->fetch_assoc();
        error_log("Transaction data: " . print_r($transaction, true));
        
        // Check if the transaction is already accepted
        if (isset($transaction['ACCEPTANCE']) && $transaction['ACCEPTANCE'] == 1) {
            error_log("Cannot delete accepted transaction: " . $transactionId);
            throw new Exception("Cannot delete an accepted transaction");
        }
        
        // Extract transaction values safely
        $originalAmount = floatval($transaction['Amount'] ?? 0);
        $transactionCurrency = $transaction['currency'] ?? 'USD';
        $budgetId = intval($transaction['budget_id'] ?? 0);
        $categoryName = trim($transaction['CategoryName'] ?? '');
        $quarterPeriod = trim($transaction['QuarterPeriod'] ?? '');
        $entryDate = $transaction['EntryDate'] ?? '';
        
        // Validate and extract year
        if ($entryDate && ($timestamp = strtotime($entryDate)) !== false) {
            $year = intval(date('Y', $timestamp));
        } else {
            error_log("Invalid entry date for transaction ID: " . $transactionId);
            throw new Exception("Invalid entry date for transaction");
        }
        
        $cluster = null;

        // Fetch cluster from budget_data if budgetId exists
        if ($budgetId > 0) {
            $clusterQuery = "SELECT cluster FROM budget_data WHERE id = ?";
            $clusterStmt = $conn->prepare($clusterQuery);
            if (!$clusterStmt) {
                throw new Exception("Failed to prepare cluster query: " . $conn->error);
            }
            $clusterStmt->bind_param("i", $budgetId);
            $clusterStmt->execute();
            $clusterResult = $clusterStmt->get_result();
            if ($clusterRow = $clusterResult->fetch_assoc()) {
                $cluster = $clusterRow['cluster'];
            }
        }

        // Include currency functions
        include_once 'currency_functions.php';
        
        // Get currency rates for the cluster
        $currencyRates = [];
        if ($cluster) {
            $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $cluster);
        }
        if (empty($currencyRates)) {
            // Default rates if not found
            $currencyRates = [
                'USD_to_ETB' => 55.0000,
                'EUR_to_ETB' => 60.0000
            ];
        }
        
        // If transaction used custom rates, we must roll back using that original rate context
        // Fetch persisted custom rates from the transaction row
        $customRates = [
            'use_custom_rate' => intval($transaction['use_custom_rate'] ?? 0),
            'usd_to_etb' => isset($transaction['usd_to_etb']) ? (float)$transaction['usd_to_etb'] : null,
            'eur_to_etb' => isset($transaction['eur_to_etb']) ? (float)$transaction['eur_to_etb'] : null,
            'usd_to_eur' => isset($transaction['usd_to_eur']) ? (float)$transaction['usd_to_eur'] : null,
        ];
        
        // Override with custom rates if they were used for this transaction
        if ($customRates['use_custom_rate'] === 1) {
            if (!empty($customRates['usd_to_etb'])) {
                $currencyRates['USD_to_ETB'] = $customRates['usd_to_etb'];
            }
            if (!empty($customRates['eur_to_etb'])) {
                $currencyRates['EUR_to_ETB'] = $customRates['eur_to_etb'];
            }
            if (!empty($customRates['usd_to_eur'])) {
                $currencyRates['USD_to_EUR'] = $customRates['usd_to_eur'];
            }
        }
        
        // Convert the transaction amount to the same currency as used in budget_data for proper rollback
        // Get the currency of the budget_data row to ensure we're using the correct currency for calculations
        $budgetCurrency = 'ETB'; // Default
        if ($budgetId > 0) {
            $budgetCurrencyStmt = $conn->prepare("SELECT currency FROM budget_data WHERE id = ?");
            if ($budgetCurrencyStmt) {
                $budgetCurrencyStmt->bind_param("i", $budgetId);
                if ($budgetCurrencyStmt->execute()) {
                    $budgetCurrencyResult = $budgetCurrencyStmt->get_result();
                    if ($budgetCurrencyRow = $budgetCurrencyResult->fetch_assoc()) {
                        $budgetCurrency = $budgetCurrencyRow['currency'] ?: 'ETB';
                    }
                }
            }
        }
        
        // Convert the original transaction amount to the budget currency using the same rates that were used when adding
        $amountInBudgetCurrency = convertCurrency($originalAmount, $transactionCurrency, $budgetCurrency, $currencyRates);
        
        error_log("Delete transaction: Original amount: $originalAmount $transactionCurrency, Converted amount for budget rollback: $amountInBudgetCurrency $budgetCurrency");

        // FIRST: Rollback the transaction amount from budget calculations
        if ($budgetId > 0 && $amountInBudgetCurrency > 0 && $year && $categoryName) {
            error_log("Rolling back budget data for budget ID: $budgetId, year: $year, category: $categoryName, cluster: " . ($cluster ?? 'NULL'));

            // Get current budget data
            $getBudgetQuery = "SELECT actual, budget, forecast, actual_plus_forecast, cluster, currency FROM budget_data WHERE id = ?";
            $getBudgetStmt = $conn->prepare($getBudgetQuery);
            if (!$getBudgetStmt) {
                throw new Exception("Prepare failed for budget data lookup: " . $conn->error);
            }
            $getBudgetStmt->bind_param("i", $budgetId);
            $getBudgetStmt->execute();
            $budgetResult = $getBudgetStmt->get_result();

            if ($budgetResult->num_rows === 0) {
                throw new Exception("Budget data not found for ID: $budgetId");
            }

            $budgetData = $budgetResult->fetch_assoc();
            $currentActual = floatval($budgetData['actual'] ?? 0);
            $currentBudget = floatval($budgetData['budget'] ?? 0);
            $currentForecast = floatval($budgetData['forecast'] ?? 0);
            $budgetRowCurrency = $budgetData['currency'] ?: 'ETB';

            // Subtract amount from actual (never go below 0) - this is the rollback
            $newActual = max(0, $currentActual - $amountInBudgetCurrency);
            // Add the deleted amount back to forecast to preserve user-entered base
            $newForecast = max(0, $currentForecast + $amountInBudgetCurrency);
            $newActualPlusForecast = $newActual + $newForecast;
            $newVariancePercentage = 0;
            if ($currentBudget > 0) {
                // Variance (%) = (Budget − Actual) / Budget × 100
                $newVariancePercentage = round((($currentBudget - $newActual) / abs($currentBudget)) * 100, 2);
            } elseif ($currentBudget == 0 && $newActual > 0) {
                $newVariancePercentage = -100.00;
            }

            // Update the specific quarter row
            $updateBudgetQuery = "UPDATE budget_data SET 
                actual = ?, 
                forecast = ?, 
                actual_plus_forecast = ?, 
                variance_percentage = ? 
                WHERE id = ?";
            $updateStmt = $conn->prepare($updateBudgetQuery);
            if (!$updateStmt) {
                throw new Exception("Update prepare failed: " . $conn->error);
            }
            $updateStmt->bind_param("ddddi", $newActual, $newForecast, $newActualPlusForecast, $newVariancePercentage, $budgetId);
            if (!$updateStmt->execute()) {
                throw new Exception("Update execute failed: " . $updateStmt->error);
            }

            // ========== UPDATE ANNUAL TOTAL ROW ==========
            $updateAnnualQuery = "UPDATE budget_data SET actual = (
                SELECT COALESCE(SUM(actual), 0) FROM budget_data b 
                WHERE b.year2 = ? AND b.category_name = ? AND b.period_name IN ('Q1','Q2','Q3','Q4')";
            $params = [$year, $categoryName];
            $types = "is";

            if ($cluster) {
                $updateAnnualQuery .= " AND b.cluster = ?";
                $params[] = $cluster;
                $types .= "s";
            }
            $updateAnnualQuery .= "
            ) WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
            $params[] = $year;
            $params[] = $categoryName;
            $types .= "is";

            if ($cluster) {
                $updateAnnualQuery .= " AND cluster = ?";
                $params[] = $cluster;
                $types .= "s";
            }

            $updateAnnualStmt = $conn->prepare($updateAnnualQuery);
            if (!$updateAnnualStmt) {
                throw new Exception("Annual actual update prepare failed: " . $conn->error);
            }
            $updateAnnualStmt->bind_param($types, ...$params);
            if (!$updateAnnualStmt->execute()) {
                throw new Exception("Annual actual update failed: " . $updateAnnualStmt->error);
            }

            // ========== SYNC ANNUAL FORECAST AS SUM OF QUARTERS THEN UPDATE ACTUAL+FORECAST ==========
            $updateAnnualForecastSumQuery = "UPDATE budget_data SET forecast = (
                SELECT COALESCE(SUM(forecast), 0) FROM budget_data b 
                WHERE b.year2 = ? AND b.category_name = ? AND b.period_name IN ('Q1','Q2','Q3','Q4')";
            $annualSumTypes = "is";
            $annualSumParams = [$year, $categoryName];
            if ($cluster) {
                $updateAnnualForecastSumQuery .= " AND b.cluster = ?";
                $annualSumTypes .= "s";
                $annualSumParams[] = $cluster;
            }
            $updateAnnualForecastSumQuery .= ") WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
            $annualSumTypes .= "is";
            $annualSumParams[] = $year;
            $annualSumParams[] = $categoryName;
            if ($cluster) {
                $updateAnnualForecastSumQuery .= " AND cluster = ?";
                $annualSumTypes .= "s";
                $annualSumParams[] = $cluster;
            }
            $updateAnnualForecastSumStmt = $conn->prepare($updateAnnualForecastSumQuery);
            if (!$updateAnnualForecastSumStmt) {
                throw new Exception("Annual forecast sum update prepare failed: " . $conn->error);
            }
            $updateAnnualForecastSumStmt->bind_param($annualSumTypes, ...$annualSumParams);
            if (!$updateAnnualForecastSumStmt->execute()) {
                throw new Exception("Annual forecast sum update failed: " . $updateAnnualForecastSumStmt->error);
            }

            $updateAnnualActualForecastQuery = "UPDATE budget_data SET 
                actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
            $annualAfTypes = "is";
            $annualAfParams = [$year, $categoryName];
            if ($cluster) {
                $updateAnnualActualForecastQuery .= " AND cluster = ?";
                $annualAfTypes .= "s";
                $annualAfParams[] = $cluster;
            }
            $updateAnnualActualForecastStmt = $conn->prepare($updateAnnualActualForecastQuery);
            if (!$updateAnnualActualForecastStmt) {
                throw new Exception("Annual actual_plus_forecast update prepare failed: " . $conn->error);
            }
            $updateAnnualActualForecastStmt->bind_param($annualAfTypes, ...$annualAfParams);
            if (!$updateAnnualActualForecastStmt->execute()) {
                throw new Exception("Annual actual_plus_forecast update failed: " . $updateAnnualActualForecastStmt->error);
            }

            // ========== UPDATE VARIANCE FOR ALL RELEVANT ROWS ==========
            // Variance (%) = (Budget − Actual) / Budget × 100
            $updateVarianceQuery = "UPDATE budget_data SET variance_percentage = CASE 
                WHEN budget > 0 THEN ROUND((((budget - COALESCE(actual,0)) / ABS(budget)) * 100), 2)
                WHEN budget = 0 AND COALESCE(actual,0) > 0 THEN -100.00
                ELSE 0.00 
            END 
            WHERE year2 = ? AND category_name = ?";
            $varianceTypes = "is";
            $varianceParams = [$year, $categoryName];

            if ($cluster) {
                $updateVarianceQuery .= " AND cluster = ?";
                $varianceParams[] = $cluster;
                $varianceTypes .= "s";
            }

            $updateVarianceStmt = $conn->prepare($updateVarianceQuery);
            if (!$updateVarianceStmt) {
                throw new Exception("Variance update prepare failed: " . $conn->error);
            }
            $updateVarianceStmt->bind_param($varianceTypes, ...$varianceParams);
            if (!$updateVarianceStmt->execute()) {
                throw new Exception("Variance update failed: " . $updateVarianceStmt->error);
            }

            // ========== UPDATE TOTAL ROW (category = 'Total') ==========
            $updateTotalQuery = "UPDATE budget_data SET actual = (
                SELECT COALESCE(SUM(actual), 0) FROM budget_data b 
                WHERE b.year2 = ? AND b.period_name = 'Annual Total' AND b.category_name != 'Total'";
            $totalParams = [$year];
            $totalTypes = "i";

            if ($cluster) {
                $updateTotalQuery .= " AND b.cluster = ?";
                $totalParams[] = $cluster;
                $totalTypes .= "s";
            }
            $updateTotalQuery .= "
            ) WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
            $totalParams[] = $year;
            $totalTypes .= "i";

            if ($cluster) {
                $updateTotalQuery .= " AND cluster = ?";
                $totalParams[] = $cluster;
                $totalTypes .= "s";
            }

            $updateTotalStmt = $conn->prepare($updateTotalQuery);
            if (!$updateTotalStmt) {
                throw new Exception("Total actual update prepare failed: " . $conn->error);
            }
            $updateTotalStmt->bind_param($totalTypes, ...$totalParams);
            if (!$updateTotalStmt->execute()) {
                throw new Exception("Total actual update failed: " . $updateTotalStmt->error);
            }

            // ========== SYNC TOTAL FORECAST AS SUM OF ANNUAL TOTALS THEN UPDATE ACTUAL+FORECAST ==========
            $updateTotalForecastSumQuery = "UPDATE budget_data SET forecast = (
                SELECT COALESCE(SUM(forecast), 0) FROM budget_data b 
                WHERE b.year2 = ? AND b.period_name = 'Annual Total' AND b.category_name != 'Total'";
            $totalSumTypes = "i";
            $totalSumParams = [$year];
            if ($cluster) {
                $updateTotalForecastSumQuery .= " AND b.cluster = ?";
                $totalSumTypes .= "s";
                $totalSumParams[] = $cluster;
            }
            $updateTotalForecastSumQuery .= ") WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
            $totalSumTypes .= "i";
            $totalSumParams[] = $year;
            if ($cluster) {
                $updateTotalForecastSumQuery .= " AND cluster = ?";
                $totalSumTypes .= "s";
                $totalSumParams[] = $cluster;
            }
            $updateTotalForecastSumStmt = $conn->prepare($updateTotalForecastSumQuery);
            if (!$updateTotalForecastSumStmt) {
                throw new Exception("Total forecast sum update prepare failed: " . $conn->error);
            }
            $updateTotalForecastSumStmt->bind_param($totalSumTypes, ...$totalSumParams);
            if (!$updateTotalForecastSumStmt->execute()) {
                throw new Exception("Total forecast sum update failed: " . $updateTotalForecastSumStmt->error);
            }

            // ========== UPDATE ACTUAL+FORECAST FOR TOTAL ROW ==========
            $updateTotalActualForecastQuery = "UPDATE budget_data 
                SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
            $totalAfTypes = "i";
            $totalAfParams = [$year];
            if ($cluster) {
                $updateTotalActualForecastQuery .= " AND cluster = ?";
                $totalAfTypes .= "s";
                $totalAfParams[] = $cluster;
            }
            $updateTotalActualForecastStmt = $conn->prepare($updateTotalActualForecastQuery);
            if (!$updateTotalActualForecastStmt) {
                throw new Exception("Total actual_plus_forecast update prepare failed: " . $conn->error);
            }
            $updateTotalActualForecastStmt->bind_param($totalAfTypes, ...$totalAfParams);
            if (!$updateTotalActualForecastStmt->execute()) {
                throw new Exception("Total actual_plus_forecast update failed: " . $updateTotalActualForecastStmt->error);
            }
        }

        // SECOND: Now delete the transaction
        $deleteQuery = "DELETE FROM budget_preview WHERE PreviewID = ?";
        error_log("Attempting to delete with query: $deleteQuery, ID: $transactionId");

        $deleteStmt = $conn->prepare($deleteQuery);
        if (!$deleteStmt) {
            throw new Exception("Delete prepare failed: " . $conn->error);
        }
        $deleteStmt->bind_param("i", $transactionId);
        if (!$deleteStmt->execute()) {
            throw new Exception("Delete execute failed: " . $deleteStmt->error);
        }

        if ($deleteStmt->affected_rows === 0) {
            throw new Exception("No transaction was deleted. ID may not exist.");
        }

        // Also resync the preview values for remaining rows linked to the same budget_id (if any), so history displays consistent numbers
        if ($budgetId > 0) {
            $syncStmt = $conn->prepare("SELECT budget, actual, forecast, variance_percentage FROM budget_data WHERE id = ?");
            if ($syncStmt) {
                $syncStmt->bind_param("i", $budgetId);
                if ($syncStmt->execute()) {
                    $syncRes = $syncStmt->get_result();
                    if ($syncRow = $syncRes->fetch_assoc()) {
                        $bdBudget = (float)($syncRow['budget'] ?? 0);
                        $bdActual = (float)($syncRow['actual'] ?? 0);
                        $bdForecast = (float)($syncRow['forecast'] ?? 0);
                        $bdVariance = (float)($syncRow['variance_percentage'] ?? 0);
                        $updPrev = $conn->prepare("UPDATE budget_preview SET OriginalBudget = ?, RemainingBudget = ?, ActualSpent = ?, ForecastAmount = ?, VariancePercentage = ? WHERE budget_id = ?");
                        if ($updPrev) {
                            $updPrev->bind_param("dddddi", $bdBudget, $bdForecast, $bdActual, $bdForecast, $bdVariance, $budgetId);
                            $updPrev->execute();
                        }
                    }
                }
            }
        }

        // Commit transaction
        $conn->commit();
        error_log("Transaction $transactionId deleted successfully and committed.");

        $response['success'] = true;
        $response['message'] = "Transaction deleted successfully and budget data updated.";

    } catch (Exception $e) {
        // Rollback on any error
        $conn->rollback();
        $errorMessage = "Delete failed: " . $e->getMessage();
        error_log($errorMessage);
        error_log("Stack trace: " . $e->getTraceAsString());
        $response['message'] = $errorMessage;
    }

} else {
    $response['message'] = "No transaction ID provided.";
}

// Clean buffer and return JSON response
ob_end_clean();
echo json_encode($response);
exit;