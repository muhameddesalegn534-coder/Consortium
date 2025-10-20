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

// Handle file upload via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    // Include database configuration
    include 'config.php'; // Using PDO as per project standards
    
    // Include currency functions
    include 'currency_functions.php';
    
    // Function to determine quarter from date based on database date ranges (PDO version)
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
            $stmt->execute([$year, $categoryName, $date, $userCluster]);
        } else {
            $stmt->execute([$year, $categoryName, $date]);
        }
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data['period_name'] ?? 'Q1'; // default fallback
    }
    
    // Function to handle Excel import
    function importTransactionsFromExcel($filePath, $pdo, $userId, $userCluster, $currencyRates) {
        // Check if PDO is available
        if (!isset($pdo)) {
            return [
                'success' => false,
                'message' => 'Database connection not available. Please contact administrator.'
            ];
        }
        
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            // Load PhpSpreadsheet
            require_once 'vendor/autoload.php';
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get highest row and column
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            // Expected column headers (case insensitive)
            $expectedHeaders = [
                'budget_heading', 'outcome', 'activity', 'budget_line', 
                'description', 'partner', 'date', 'amount', 'currency', 
                'usd_to_etb_rate', 'eur_to_etb_rate'
            ];
            
            // Read headers from first row
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellValue = trim(strtolower($worksheet->getCellByColumnAndRow($col, 1)->getValue()));
                $headers[$col] = $cellValue;
            }
            
            // Validate that we have the required columns
            $foundHeaders = array_intersect($expectedHeaders, $headers);
            if (count($foundHeaders) < 8) { // We need at least 8 of the expected columns
                return [
                    'success' => false,
                    'message' => 'Missing required columns. Please ensure your Excel file contains at least: budget_heading, outcome, activity, budget_line, description, partner, date, amount'
                ];
            }
            
            // Find column indices
            $columnIndices = [];
            foreach ($expectedHeaders as $header) {
                $columnIndices[$header] = array_search($header, $headers);
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            $importedCount = 0;
            $errors = [];
            
            // Process each row (starting from row 2, as row 1 contains headers)
            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    // Extract data from each column
                    $budgetHeading = $columnIndices['budget_heading'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['budget_heading'] + 1, $row)->getValue()) : '';
                    $outcome = $columnIndices['outcome'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['outcome'] + 1, $row)->getValue()) : '';
                    $activity = $columnIndices['activity'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['activity'] + 1, $row)->getValue()) : '';
                    $budgetLine = $columnIndices['budget_line'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['budget_line'] + 1, $row)->getValue()) : '';
                    $description = $columnIndices['description'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['description'] + 1, $row)->getValue()) : '';
                    $partner = $columnIndices['partner'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['partner'] + 1, $row)->getValue()) : '';
                    $date = $columnIndices['date'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['date'] + 1, $row)->getValue()) : '';
                    $amount = $columnIndices['amount'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['amount'] + 1, $row)->getValue()) : '';
                    
                    // Optional fields
                    $currency = $columnIndices['currency'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['currency'] + 1, $row)->getValue()) : 'USD';
                    $usdToEtbRate = $columnIndices['usd_to_etb_rate'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['usd_to_etb_rate'] + 1, $row)->getValue()) : null;
                    $eurToEtbRate = $columnIndices['eur_to_etb_rate'] ? 
                        trim($worksheet->getCellByColumnAndRow($columnIndices['eur_to_etb_rate'] + 1, $row)->getValue()) : null;
                    
                    // Validate required fields
                    if (empty($budgetHeading) || empty($outcome) || empty($activity) || 
                        empty($budgetLine) || empty($description) || empty($partner) || 
                        empty($date) || empty($amount)) {
                        $errors[] = "Row $row: Missing required fields";
                        continue;
                    }
                    
                    // Validate date format
                    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
                    if (!$dateObj) {
                        $errors[] = "Row $row: Invalid date format. Please use YYYY-MM-DD";
                        continue;
                    }
                    
                    // Validate amount
                    if (!is_numeric($amount)) {
                        $errors[] = "Row $row: Amount must be a number";
                        continue;
                    }
                    
                    // Validate currency
                    $validCurrencies = ['USD', 'ETB', 'EUR'];
                    if (!in_array(strtoupper($currency), $validCurrencies)) {
                        $currency = 'USD'; // Default to USD if invalid
                    } else {
                        $currency = strtoupper($currency);
                    }
                    
                    // Determine which rates to use
                    $effectiveRates = $currencyRates; // Default to cluster rates
                    
                    // If custom rates are provided in the Excel file, use those instead
                    if (!empty($usdToEtbRate) && is_numeric($usdToEtbRate)) {
                        $effectiveRates['USD_to_ETB'] = floatval($usdToEtbRate);
                    }
                    if (!empty($eurToEtbRate) && is_numeric($eurToEtbRate)) {
                        $effectiveRates['EUR_to_ETB'] = floatval($eurToEtbRate);
                    }
                    
                    // Insert into budget_preview table
                    $insertQuery = "INSERT INTO budget_preview (Amount, Category, EntryDate, user_id, cluster, subcategory, 
                                    BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, currency, 
                                    use_custom_rate, usd_to_etb, eur_to_etb) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($insertQuery);
                    $stmt->execute([
                        $amount, $budgetHeading, $date, $userId, $userCluster, $budgetHeading,
                        $budgetHeading, $outcome, $activity, $budgetLine, $description, $partner,
                        $currency,
                        (!empty($usdToEtbRate) || !empty($eurToEtbRate)) ? 1 : 0,
                        $usdToEtbRate,
                        $eurToEtbRate
                    ]);
                    
                    $insertId = $pdo->lastInsertId();
                    
                    // Update the budget_data table with proper filtering by date range, cluster, category, and quarter
                    $entryDateTime = new DateTime($date);
                    $entryYear = (int)$entryDateTime->format('Y');
                    
                    // Determine quarter from entry date
                    $month = (int)$entryDateTime->format('m');
                    if ($month <= 3) {
                        $quarterPeriod = 'Q1';
                    } elseif ($month <= 6) {
                        $quarterPeriod = 'Q2';
                    } elseif ($month <= 9) {
                        $quarterPeriod = 'Q3';
                    } else {
                        $quarterPeriod = 'Q4';
                    }
                    
                    // Check if there's enough budget available for this transaction with proper filtering
                    $budgetCheckQuery = "SELECT budget, actual, forecast, id FROM budget_data 
                                       WHERE year2 = ? AND category_name = ? 
                                       AND period_name = ?
                                       AND ? BETWEEN start_date AND end_date";
                    
                    // Add cluster condition if user has a cluster
                    if ($userCluster) {
                        $budgetCheckQuery .= " AND cluster = ?";
                        $budgetCheckStmt = $pdo->prepare($budgetCheckQuery);
                        $budgetCheckStmt->execute([$entryYear, $budgetHeading, $quarterPeriod, $date, $userCluster]);
                    } else {
                        $budgetCheckStmt = $pdo->prepare($budgetCheckQuery);
                        $budgetCheckStmt->execute([$entryYear, $budgetHeading, $quarterPeriod, $date]);
                    }
                    
                    $budgetCheckData = $budgetCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get the budget_id for linking to budget_preview
                    $budgetId = $budgetCheckData['id'] ?? null;
                    
                    // Update the quarter row: increase actual by amount, recalc forecast to keep Budget = Actual + Forecast, recompute actual_plus_forecast
                    $updateBudgetQuery = "UPDATE budget_data SET 
                        actual = COALESCE(actual, 0) + ?,
                        forecast = GREATEST(COALESCE(budget, 0) - COALESCE(actual, 0), 0),
                        actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                        WHERE year2 = ? AND category_name = ? AND period_name = ?
                        AND ? BETWEEN start_date AND end_date";
                    
                    // Add cluster condition if user has a cluster
                    if ($userCluster) {
                        $updateBudgetQuery .= " AND cluster = ?";
                        $updateStmt = $pdo->prepare($updateBudgetQuery);
                        $updateStmt->execute([$amount, $entryYear, $budgetHeading, $quarterPeriod, $date, $userCluster]);
                    } else {
                        $updateStmt = $pdo->prepare($updateBudgetQuery);
                        $updateStmt->execute([$amount, $entryYear, $budgetHeading, $quarterPeriod, $date]);
                    }
                    
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
                        $annualStmt = $pdo->prepare($updateAnnualQuery);
                        $annualStmt->execute([$entryYear, $budgetHeading, $userCluster, $entryYear, $budgetHeading, $userCluster]);
                    } else {
                        $annualStmt = $pdo->prepare($updateAnnualQuery);
                        $annualStmt->execute([$entryYear, $budgetHeading, $entryYear, $budgetHeading]);
                    }
                    
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
                        $actualStmt = $pdo->prepare($updateActualQuery);
                        $actualStmt->execute([$entryYear, $budgetHeading, $userCluster, $entryYear, $budgetHeading, $userCluster]);
                    } else {
                        $actualStmt = $pdo->prepare($updateActualQuery);
                        $actualStmt->execute([$entryYear, $budgetHeading, $entryYear, $budgetHeading]);
                    }
                    
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
                        $annualForecastSumStmt = $pdo->prepare($updateAnnualForecastSumQuery);
                        $annualForecastSumStmt->execute([$entryYear, $budgetHeading, $userCluster, $entryYear, $budgetHeading, $userCluster]);
                    } else {
                        $annualForecastSumStmt = $pdo->prepare($updateAnnualForecastSumQuery);
                        $annualForecastSumStmt->execute([$entryYear, $budgetHeading, $entryYear, $budgetHeading]);
                    }
                    
                    // Update actual_plus_forecast for Annual Total with cluster consideration
                    $updateAnnualForecastQuery = "UPDATE budget_data 
                        SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                        WHERE year2 = ? AND category_name = ? AND period_name = 'Annual Total'";
                    
                    // Add cluster condition if user has a cluster
                    if ($userCluster) {
                        $updateAnnualForecastQuery .= " AND cluster = ?";
                        $annualForecastStmt = $pdo->prepare($updateAnnualForecastQuery);
                        $annualForecastStmt->execute([$entryYear, $budgetHeading, $userCluster]);
                    } else {
                        $annualForecastStmt = $pdo->prepare($updateAnnualForecastQuery);
                        $annualForecastStmt->execute([$entryYear, $budgetHeading]);
                    }
                    
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
                        $totalBudgetStmt = $pdo->prepare($updateTotalQuery);
                        $totalBudgetStmt->execute([$entryYear, $userCluster, $entryYear, $userCluster]);
                    } else {
                        $totalBudgetStmt = $pdo->prepare($updateTotalQuery);
                        $totalBudgetStmt->execute([$entryYear, $entryYear]);
                    }
                    
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
                        $totalActualStmt = $pdo->prepare($updateTotalActualQuery);
                        $totalActualStmt->execute([$entryYear, $userCluster, $entryYear, $userCluster]);
                    } else {
                        $totalActualStmt = $pdo->prepare($updateTotalActualQuery);
                        $totalActualStmt->execute([$entryYear, $entryYear]);
                    }
                    
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
                        $totalForecastSumStmt = $pdo->prepare($updateTotalForecastSumQuery);
                        $totalForecastSumStmt->execute([$entryYear, $userCluster, $entryYear, $userCluster]);
                    } else {
                        $totalForecastSumStmt = $pdo->prepare($updateTotalForecastSumQuery);
                        $totalForecastSumStmt->execute([$entryYear, $entryYear]);
                    }
                    
                    // Update actual_plus_forecast for Total with cluster consideration
                    $updateTotalActualForecastQuery = "UPDATE budget_data 
                        SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                        WHERE year2 = ? AND category_name = 'Total' AND period_name = 'Total'";
                    
                    // Add cluster condition if user has a cluster
                    if ($userCluster) {
                        $updateTotalActualForecastQuery .= " AND cluster = ?";
                        $totalActualForecastStmt = $pdo->prepare($updateTotalActualForecastQuery);
                        $totalActualForecastStmt->execute([$entryYear, $userCluster]);
                    } else {
                        $totalActualForecastStmt = $pdo->prepare($updateTotalActualForecastQuery);
                        $totalActualForecastStmt->execute([$entryYear]);
                    }
                    
                    // Update actual_plus_forecast for all quarter rows as well with cluster consideration
                    $updateQuarterForecastQuery = "UPDATE budget_data 
                        SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                        WHERE year2 = ? AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";
                    
                    // Add cluster condition if user has a cluster
                    if ($userCluster) {
                        $updateQuarterForecastQuery .= " AND cluster = ?";
                        $quarterForecastStmt = $pdo->prepare($updateQuarterForecastQuery);
                        $quarterForecastStmt->execute([$entryYear, $userCluster]);
                    } else {
                        $quarterForecastStmt = $pdo->prepare($updateQuarterForecastQuery);
                        $quarterForecastStmt->execute([$entryYear]);
                    }
                    
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
                        $varianceStmt = $pdo->prepare($varianceQuery);
                        $varianceStmt->execute([$entryYear, $userCluster]);
                    } else {
                        $varianceStmt = $pdo->prepare($varianceQuery);
                        $varianceStmt->execute([$entryYear]);
                    }
                    
                    // Mark budget data as uncertified when new transaction is added with cluster consideration
                    $uncertifyQuery = "UPDATE budget_data SET certified = 'uncertified' WHERE year2 = ?";
                    
                    // Add cluster condition if user has a cluster
                    if ($userCluster) {
                        $uncertifyQuery .= " AND cluster = ?";
                        $uncertifyStmt = $pdo->prepare($uncertifyQuery);
                        $uncertifyStmt->execute([$entryYear, $userCluster]);
                    } else {
                        $uncertifyStmt = $pdo->prepare($uncertifyQuery);
                        $uncertifyStmt->execute([$entryYear]);
                    }
                    
                    // Update the budget_preview table with the budget_id for proper linking
                    if ($budgetId) {
                        $updatePreviewQuery = "UPDATE budget_preview SET budget_id = ? WHERE PreviewID = ?";
                        $updatePreviewStmt = $pdo->prepare($updatePreviewQuery);
                        $updatePreviewStmt->execute([$budgetId, $insertId]);
                        
                        // Also sync preview financial fields from budget_data
                        $bdStmt = $pdo->prepare("SELECT budget, actual, forecast, variance_percentage FROM budget_data WHERE id = ?");
                        $bdStmt->execute([$budgetId]);
                        $bdRow = $bdStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($bdRow) {
                            $bdBudget = (float)($bdRow['budget'] ?? 0);
                            $bdActual = (float)($bdRow['actual'] ?? 0);
                            $bdForecast = (float)($bdRow['forecast'] ?? 0);
                            $bdVariance = (float)($bdRow['variance_percentage'] ?? 0);
                            
                            $syncPreviewQuery = "UPDATE budget_preview SET OriginalBudget = ?, RemainingBudget = ?, ActualSpent = ?, ForecastAmount = ?, VariancePercentage = ? WHERE PreviewID = ?";
                            $syncPreviewStmt = $pdo->prepare($syncPreviewQuery);
                            $syncPreviewStmt->execute([$bdBudget, $bdForecast, $bdActual, $bdForecast, $bdVariance, $insertId]);
                        }
                    }
                    
                    $importedCount++;
                    
                } catch (Exception $e) {
                    error_log("Error importing row $row: " . $e->getMessage());
                    $errors[] = "Row $row: " . $e->getMessage();
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => "Successfully imported $importedCount transactions" . (count($errors) > 0 ? " with " . count($errors) . " errors" : ""),
                'imported_count' => $importedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($pdo)) {
                try {
                    $pdo->rollback();
                } catch (Exception $rollbackException) {
                    // Ignore rollback errors
                }
            }
            error_log("Error importing transactions: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error importing transactions: ' . $e->getMessage()
            ];
        }
    }
    
    // Initialize PDO connection
    try {
        include 'db_connection_pdo.php';
        $pdo = $pdo_conn; // Use the correct variable name
    } catch (Exception $e) {
        error_log("Failed to initialize PDO connection during file upload: " . $e->getMessage());
        $pdo = null;
    }
    
    // Get user cluster information
    $userCluster = $_SESSION['cluster_name'] ?? null;
    
    // Get currency rates for the user's cluster
    $currencyRates = [];
    if (!empty($userCluster) && isset($pdo)) {
        include 'currency_functions.php';
        $currencyRates = getCurrencyRatesByClusterName($pdo, $userCluster);
    } else {
        // Default rates if no cluster is assigned or PDO not available
        $currencyRates = [
            'USD_to_ETB' => 55.0000,
            'EUR_to_ETB' => 60.0000
        ];
    }
    
    $response = [];
    
    // Validate user session
    if (!isset($_SESSION['user_id'])) {
        $response = [
            'success' => false,
            'message' => 'User session not found. Please log in again.'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Check if file was uploaded without errors
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === 0) {
        $fileName = $_FILES['excel_file']['name'];
        $fileTmpPath = $_FILES['excel_file']['tmp_name'];
        $fileSize = $_FILES['excel_file']['size'];
        $fileType = $_FILES['excel_file']['type'];
        
        // Check file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'xls'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            // Check file size (limit to 5MB)
            if ($fileSize <= 5 * 1024 * 1024) {
                // Import the Excel file
                $result = importTransactionsFromExcel($fileTmpPath, $pdo, $_SESSION['user_id'], $userCluster, $currencyRates);
                $response = $result;
            } else {
                $response = [
                    'success' => false,
                    'message' => 'File size exceeds 5MB limit'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Invalid file type. Please upload an Excel file (.xlsx or .xls)'
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Error uploading file. Please try again.'
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// If not handling file upload, display the import form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Transactions from Excel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-section {
            background: linear-gradient(135deg, #ffffff 0%, #fefefe 100%);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.1);
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
        
        .form-label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            letter-spacing: 0.025em;
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
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background-color: #10b981;
        }
        
        .toast.error {
            background-color: #ef4444;
        }
        
        .toast.info {
            background-color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <div class="form-section">
                <div class="text-center">
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                        Import Transactions from Excel
                    </h2>
                    <p class="text-gray-600 mb-6">
                        Upload an Excel file to import multiple transactions at once
                    </p>
                </div>
                
                <form id="importForm" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label for="excel_file" class="form-label">
                            <i class="fas fa-file-excel text-green-600 mr-2"></i>
                            Excel File
                        </label>
                        <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" class="form-input" required>
                        <p class="mt-2 text-sm text-gray-500">
                            Supported formats: .xlsx, .xls (Max size: 5MB)
                        </p>
                    </div>
                    
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                        <h3 class="font-medium text-blue-800 mb-2 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Required Excel Columns
                        </h3>
                        <ul class="text-sm text-blue-700 list-disc pl-5 space-y-1">
                            <li><strong>budget_heading</strong> - Administrative costs, Operational support costs, etc.</li>
                            <li><strong>outcome</strong> - Project outcome description</li>
                            <li><strong>activity</strong> - Activity description</li>
                            <li><strong>budget_line</strong> - Budget line description</li>
                            <li><strong>description</strong> - Transaction description</li>
                            <li><strong>partner</strong> - Partner organization name</li>
                            <li><strong>date</strong> - Transaction date (YYYY-MM-DD)</li>
                            <li><strong>amount</strong> - Transaction amount</li>
                        </ul>
                        <p class="mt-3 text-sm text-blue-700">
                            <strong>Optional columns:</strong> currency, usd_to_etb_rate, eur_to_etb_rate
                        </p>
                        <div class="mt-3 p-3 bg-amber-50 rounded-lg border border-amber-200">
                            <p class="text-sm text-amber-800">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <strong>Note:</strong> The <code>usd_to_etb_rate</code> and <code>eur_to_etb_rate</code> columns allow you to specify custom exchange rates for each transaction. If left empty, the system will use the default cluster rates.
                            </p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <a href="excel_template.xlsx" download class="inline-flex items-center text-blue-700 hover:text-blue-900 font-medium">
                                <i class="fas fa-download mr-2"></i>
                                Download Excel Template
                            </a>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" onclick="window.location.href='financial_report_section.php'" class="btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Transactions
                        </button>
                        <button type="submit" id="importButton" class="btn-primary">
                            <i class="fas fa-upload mr-2"></i>
                            Import Transactions
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center text-sm text-gray-500">
                <p>Make sure your Excel file follows the required format for successful import</p>
            </div>
        </div>
    </div>
    
    <div id="toast" class="toast"></div>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show ' + type;
            
            setTimeout(() => {
                toast.className = 'toast';
            }, 3000);
        }
        
        // Handle form submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('excel_file');
            const file = fileInput.files[0];
            
            if (!file) {
                showToast('Please select an Excel file', 'error');
                return;
            }
            
            // Show loading state
            const importButton = document.getElementById('importButton');
            const originalText = importButton.innerHTML;
            importButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Importing...';
            importButton.disabled = true;
            
            // Create FormData object
            const formData = new FormData();
            formData.append('excel_file', file);
            
            // Send AJAX request
            fetch('import_excel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Reset form
                    document.getElementById('importForm').reset();
                    // Reload after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'financial_report_section.php';
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred during import', 'error');
            })
            .finally(() => {
                // Reset button state
                importButton.innerHTML = originalText;
                importButton.disabled = false;
            });
        });
    </script>
</body>
</html>