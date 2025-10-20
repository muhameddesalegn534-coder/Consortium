<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database configuration
require_once 'config.php'; // Use PDO connection instead of setup_database.php
require_once 'currency_functions_pdo.php'; // Include PDO-compatible currency functions

// Initialize variables
$error_message = '';
$success_message = '';

// Get transaction ID from URL
$previewId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch transaction data for display (only if not redirecting)
if ($previewId > 0) {
    try {
        $query = "SELECT * FROM budget_preview WHERE PreviewID = :previewId";
        $stmt = $conn->prepare($query);
        $stmt->execute([':previewId' => $previewId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            $_SESSION['error_message'] = "Transaction not found.";
            header("Location: history.php");
            exit();
        }
        
        // Get currency rates for the transaction's cluster
        $currencyRates = [];
        if (!empty($transaction['cluster'])) {
            $currencyRates = getCurrencyRatesByClusterNamePDO($conn, $transaction['cluster']);
        } else {
            // Default rates if no cluster is assigned
            $currencyRates = [
                'USD_to_ETB' => 55.0000,
                'EUR_to_ETB' => 60.0000
            ];
        }
        
        // Get the original currency of the transaction from the database
        $originalCurrency = $transaction['currency'] ?? 'USD'; // Default to USD if not set

        // If this transaction used custom rates, prefer those for conversions
        if (!empty($transaction['use_custom_rate']) && intval($transaction['use_custom_rate']) === 1) {
            if (!empty($transaction['usd_to_etb'])) {
                $currencyRates['USD_to_ETB'] = (float)$transaction['usd_to_etb'];
            }
            if (!empty($transaction['eur_to_etb'])) {
                $currencyRates['EUR_to_ETB'] = (float)$transaction['eur_to_etb'];
            }
        }
        
        // Convert the amount to ETB for display
        $amountInETB = convertCurrency($transaction['Amount'], $originalCurrency, 'ETB', $currencyRates);
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error fetching transaction: " . $e->getMessage();
        header("Location: history.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Invalid transaction ID.";
    header("Location: history.php");
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $budgetHeading = $_POST['budget_heading'] ?? '';
    $outcome = $_POST['outcome'] ?? '';
    $activity = $_POST['activity'] ?? '';
    $budgetLine = $_POST['budget_line'] ?? '';
    $description = $_POST['description'] ?? '';
    $partner = $_POST['partner'] ?? '';
    $entryDate = $_POST['entry_date'] ?? '';
    $amountInETB = $_POST['amount'] ?? 0; // This is the ETB amount entered by user
    $pvNumber = $_POST['pv_number'] ?? '';
    $quarterPeriod = $_POST['quarter_period'] ?? '';
    $categoryName = $_POST['category_name'] ?? '';
    $cluster = $_POST['cluster'] ?? '';
    $budgetId = $_POST['budget_id'] ?? null;
    
    // Get currency rates for conversion
    $currencyRates = [];
    if (!empty($cluster)) {
        $currencyRates = getCurrencyRatesByClusterNamePDO($conn, $cluster);
    } else {
        // Default rates if no cluster is assigned
        $currencyRates = [
            'USD_to_ETB' => 55.0000,
            'EUR_to_ETB' => 60.0000
        ];
    }
    
    // If original transaction had custom rates, override the rates used for conversion during edit
    $customRatesQuery = "SELECT use_custom_rate, usd_to_etb, eur_to_etb, usd_to_eur, currency FROM budget_preview WHERE PreviewID = :previewId";
    $customRatesStmt = $conn->prepare($customRatesQuery);
    $customRatesStmt->execute([':previewId' => $previewId]);
    $customRatesData = $customRatesStmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($customRatesData) && intval($customRatesData['use_custom_rate'] ?? 0) === 1) {
        if (!empty($customRatesData['usd_to_etb'])) {
            $currencyRates['USD_to_ETB'] = (float)$customRatesData['usd_to_etb'];
        }
        if (!empty($customRatesData['eur_to_etb'])) {
            $currencyRates['EUR_to_ETB'] = (float)$customRatesData['eur_to_etb'];
        }
    }

    // Get the original currency from the database
    $currencyDataQuery = "SELECT currency FROM budget_preview WHERE PreviewID = :previewId";
    $currencyStmt = $conn->prepare($currencyDataQuery);
    $currencyStmt->execute([':previewId' => $previewId]);
    $currencyData = $currencyStmt->fetch(PDO::FETCH_ASSOC);
    $originalCurrency = $currencyData['currency'] ?? 'USD'; // Default to USD if not set
    
    // Convert the ETB amount back to the original currency
    $amount = convertCurrency($amountInETB, 'ETB', $originalCurrency, $currencyRates);
    
    // These values are calculated automatically and should not be taken from user input
    // $originalBudget = $_POST['original_budget'] ?? 0;
    // $remainingBudget = $_POST['remaining_budget'] ?? 0;
    // $actualSpent = $_POST['actual_spent'] ?? 0;
    // $forecastAmount = $_POST['forecast_amount'] ?? 0;
    
    // Handle document data
    $documentTypes = $_POST['document_types'] ?? [];
    $documentFiles = $_FILES['document_files'] ?? [];
    $existingDocumentPaths = $_POST['existing_document_paths'] ?? [];
    
    // Format document data as JSON for the Documents field
    $documentsArray = [];
    for ($i = 0; $i < count($documentTypes); $i++) {
        if (empty($documentTypes[$i])) {
            continue;
        }
        
        $serverPath = '';
        $filename = '';
        
        // Check if there's an existing document path
        if (isset($existingDocumentPaths[$i]) && !empty($existingDocumentPaths[$i])) {
            $serverPath = $existingDocumentPaths[$i];
            $filename = basename($serverPath);
        }
        
        // Check if a new file was uploaded
        if (isset($documentFiles['tmp_name'][$i]) && !empty($documentFiles['tmp_name'][$i])) {
            $uploadDir = 'admin/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($documentFiles['name'][$i], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $fileExtension;
            $serverPath = $uploadDir . $filename;
            
            if (!move_uploaded_file($documentFiles['tmp_name'][$i], $serverPath)) {
                $error_message = "Error uploading file: " . $documentFiles['name'][$i];
                break;
            }
        }
        
        if (!empty($serverPath)) {
            $documentsArray[] = [
                'documentType' => $documentTypes[$i],
                'serverPath' => $serverPath,
                'filename' => $filename
            ];
        }
    }
    
    $documentsJson = !empty($documentsArray) ? json_encode($documentsArray) : null;
    
    // Format document data as comma-separated strings for other fields
    $documentTypesStr = !empty($documentTypes) ? implode(',', $documentTypes) : null;
    $documentPathsStr = '';
    if (!empty($documentsArray)) {
        $paths = array_column($documentsArray, 'serverPath');
        $documentPathsStr = implode(',', $paths);
    }
    
    // Before executing the update, get original transaction data to calculate the difference
    try {
        $originalDataQuery = "SELECT Amount, budget_id, CategoryName, QuarterPeriod, EntryDate FROM budget_preview WHERE PreviewID = :previewId";
        $originalStmt = $conn->prepare($originalDataQuery);
        $originalStmt->execute([':previewId' => $previewId]);
        $originalData = $originalStmt->fetch(PDO::FETCH_ASSOC);
        $originalAmount = $originalData['Amount'] ?? 0;
        $budgetId = $originalData['budget_id'] ?? $budgetId; // Use existing budget_id if not provided
        $originalCategoryName = $originalData['CategoryName'] ?? $categoryName;
        $originalQuarterPeriod = $originalData['QuarterPeriod'] ?? $quarterPeriod;
        $originalEntryDate = $originalData['EntryDate'] ?? $entryDate;
        $originalYear = $originalEntryDate ? date('Y', strtotime($originalEntryDate)) : null;
    } catch (PDOException $e) {
        $error_message = "Error fetching original transaction data: " . $e->getMessage();
    }

    // Begin transaction for database consistency
    try {
        $conn->beginTransaction();
        
        // FIRST: Rollback the original transaction amount from budget calculations
        if ($budgetId && $originalAmount > 0) {
            // Get current budget data values
            $getBudgetQuery = "SELECT budget, actual, forecast, actual_plus_forecast, variance_percentage FROM budget_data WHERE id = :budgetId";
            $getBudgetStmt = $conn->prepare($getBudgetQuery);
            $getBudgetStmt->execute([':budgetId' => $budgetId]);
            $budgetData = $getBudgetStmt->fetch(PDO::FETCH_ASSOC);
            $currentBudget = $budgetData['budget'] ?? 0;
            $currentActual = $budgetData['actual'] ?? 0;
            $currentForecast = $budgetData['forecast'] ?? 0;
            
            // Calculate new actual value (subtract the original amount)
            $newActual = max(0, $currentActual - $originalAmount);
            // Add the original amount back to forecast to preserve user-entered base
            $newForecast = max(0, ($currentForecast ?? 0) + $originalAmount);
            // Calculate new actual_plus_forecast using the recalculated forecast
            $newActualPlusForecast = $newActual + $newForecast;
            
            // Calculate new variance percentage using the requested formula
            // Variance (%) = (Budget − Actual) / Budget × 100
            $newVariancePercentage = 0;
            if ($currentBudget > 0) {
                $newVariancePercentage = round((($currentBudget - $newActual) / abs($currentBudget)) * 100, 2);
            } else if ($currentBudget == 0 && $newActual > 0) {
                $newVariancePercentage = -100.00;
            }
            
            // Update the specific quarter row in budget_data including recalculated forecast
            $updateBudgetQuery = "UPDATE budget_data SET 
                actual = :actual,
                forecast = :forecast,
                actual_plus_forecast = :actualPlusForecast,
                variance_percentage = :variancePercentage
                WHERE id = :budgetId";
            $updateStmt = $conn->prepare($updateBudgetQuery);
            $updateStmt->execute([
                ':actual' => $newActual,
                ':forecast' => $newForecast,
                ':actualPlusForecast' => $newActualPlusForecast,
                ':variancePercentage' => $newVariancePercentage,
                ':budgetId' => $budgetId
            ]);
            
            // Extract year and cluster for further updates
            $extractYearQuery = "SELECT year2, cluster FROM budget_data WHERE id = :budgetId";
            $extractYearStmt = $conn->prepare($extractYearQuery);
            $extractYearStmt->execute([':budgetId' => $budgetId]);
            $extractYearData = $extractYearStmt->fetch(PDO::FETCH_ASSOC);
            $year = $extractYearData['year2'] ?? $originalYear;
            $cluster = $extractYearData['cluster'] ?? null;
            
            if ($year && $originalCategoryName) {
                // Update Annual Total row - recalculate the sum of quarterly actuals
                $updateAnnualQuery = "UPDATE budget_data 
                    SET actual = (SELECT SUM(COALESCE(actual, 0)) FROM budget_data b 
                                WHERE b.year2 = :year AND b.category_name = :categoryName 
                                AND b.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";
                
                $annualParams = [
                    ':year' => $year,
                    ':categoryName' => $originalCategoryName
                ];
                
                if ($cluster) {
                    $updateAnnualQuery .= " AND b.cluster = :cluster";
                    $annualParams[':cluster'] = $cluster;
                }
                $updateAnnualQuery .= ") WHERE year2 = :year2 AND category_name = :categoryName2 AND period_name = 'Annual Total'";
                
                $annualParams[':year2'] = $year;
                $annualParams[':categoryName2'] = $originalCategoryName;
                
                if ($cluster) {
                    $annualParams[':cluster2'] = $cluster;
                    $updateAnnualQuery .= " AND cluster = :cluster2";
                }
                
                $updateAnnualStmt = $conn->prepare($updateAnnualQuery);
                $updateAnnualStmt->execute($annualParams);
                
                // Sync Annual Total forecast as the sum of quarterly forecasts
                $updateAnnualForecastSumQuery = "UPDATE budget_data 
                    SET forecast = (
                        SELECT SUM(COALESCE(forecast, 0)) FROM budget_data b 
                        WHERE b.year2 = :year AND b.category_name = :categoryName 
                        AND b.period_name IN ('Q1','Q2','Q3','Q4')";
                $annualForecastParams = [
                    ':year' => $year,
                    ':categoryName' => $originalCategoryName
                ];
                if ($cluster) {
                    $updateAnnualForecastSumQuery .= " AND b.cluster = :cluster";
                    $annualForecastParams[':cluster'] = $cluster;
                }
                $updateAnnualForecastSumQuery .= ") WHERE year2 = :year2 AND category_name = :categoryName2 AND period_name = 'Annual Total'";
                $annualForecastParams[':year2'] = $year;
                $annualForecastParams[':categoryName2'] = $originalCategoryName;
                if ($cluster) {
                    $updateAnnualForecastSumQuery .= " AND cluster = :cluster2";
                    $annualForecastParams[':cluster2'] = $cluster;
                }
                $updateAnnualForecastSumStmt = $conn->prepare($updateAnnualForecastSumQuery);
                $updateAnnualForecastSumStmt->execute($annualForecastParams);

                // Update actual_plus_forecast for Annual Total
                $updateAnnualActualForecastQuery = "UPDATE budget_data 
                    SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                    WHERE year2 = :year AND category_name = :categoryName AND period_name = 'Annual Total'";
                
                $actualForecastParams = [
                    ':year' => $year,
                    ':categoryName' => $originalCategoryName
                ];
                
                if ($cluster) {
                    $updateAnnualActualForecastQuery .= " AND cluster = :cluster";
                    $actualForecastParams[':cluster'] = $cluster;
                }
                
                $updateAnnualActualForecastStmt = $conn->prepare($updateAnnualActualForecastQuery);
                $updateAnnualActualForecastStmt->execute($actualForecastParams);
                
                // Update variance percentages
                $updateVarianceQuery = "UPDATE budget_data 
                    SET variance_percentage = CASE 
                        WHEN budget > 0 THEN ROUND((((budget - COALESCE(actual,0)) / ABS(budget)) * 100), 2)
                        WHEN budget = 0 AND COALESCE(actual,0) > 0 THEN -100.00
                        ELSE 0.00 
                    END
                    WHERE year2 = :year AND category_name = :categoryName";
                
                $varianceParams = [
                    ':year' => $year,
                    ':categoryName' => $originalCategoryName
                ];
                
                if ($cluster) {
                    $updateVarianceQuery .= " AND cluster = :cluster";
                    $varianceParams[':cluster'] = $cluster;
                }
                
                $updateVarianceStmt = $conn->prepare($updateVarianceQuery);
                $updateVarianceStmt->execute($varianceParams);
                
                // Finally, update the Total row
                // Calculate new actual for Total row
                $updateTotalQuery = "UPDATE budget_data 
                    SET actual = (SELECT SUM(COALESCE(actual, 0)) FROM budget_data b 
                                WHERE b.year2 = :year AND b.period_name = 'Annual Total' AND b.category_name != 'Total'";
                
                $totalParams = [
                    ':year' => $year
                ];
                
                if ($cluster) {
                    $updateTotalQuery .= " AND b.cluster = :cluster";
                    $totalParams[':cluster'] = $cluster;
                }
                $updateTotalQuery .= ") WHERE year2 = :year2 AND category_name = 'Total' AND period_name = 'Total'";
                
                $totalParams[':year2'] = $year;
                
                if ($cluster) {
                    $totalParams[':cluster2'] = $cluster;
                    $updateTotalQuery .= " AND cluster = :cluster2";
                }
                
                $updateTotalStmt = $conn->prepare($updateTotalQuery);
                $updateTotalStmt->execute($totalParams);
                
                // Sync Total forecast as sum of Annual Total forecasts across categories
                $updateTotalForecastSumQuery = "UPDATE budget_data 
                    SET forecast = (
                        SELECT SUM(COALESCE(forecast, 0)) FROM budget_data b 
                        WHERE b.year2 = :year AND b.period_name = 'Annual Total' AND b.category_name != 'Total'";
                $totalForecastParams = [
                    ':year' => $year
                ];
                if ($cluster) {
                    $updateTotalForecastSumQuery .= " AND b.cluster = :cluster";
                    $totalForecastParams[':cluster'] = $cluster;
                }
                $updateTotalForecastSumQuery .= ") WHERE year2 = :year2 AND category_name = 'Total' AND period_name = 'Total'";
                $totalForecastParams[':year2'] = $year;
                if ($cluster) {
                    $updateTotalForecastSumQuery .= " AND cluster = :cluster2";
                    $totalForecastParams[':cluster2'] = $cluster;
                }
                $updateTotalForecastSumStmt = $conn->prepare($updateTotalForecastSumQuery);
                $updateTotalForecastSumStmt->execute($totalForecastParams);

                // Update actual_plus_forecast for Total row
                $updateTotalActualForecastQuery = "UPDATE budget_data 
                    SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                    WHERE year2 = :year AND category_name = 'Total' AND period_name = 'Total'";
                
                $totalActualForecastParams = [
                    ':year' => $year
                ];
                
                if ($cluster) {
                    $updateTotalActualForecastQuery .= " AND cluster = :cluster";
                    $totalActualForecastParams[':cluster'] = $cluster;
                }
                
                $updateTotalActualForecastStmt = $conn->prepare($updateTotalActualForecastQuery);
                $updateTotalActualForecastStmt->execute($totalActualForecastParams);
                
                // Update variance for Total row
                $updateTotalVarianceQuery = "UPDATE budget_data 
                    SET variance_percentage = CASE 
                        WHEN budget > 0 THEN ROUND((((budget - COALESCE(actual,0)) / ABS(budget)) * 100), 2)
                        WHEN budget = 0 AND COALESCE(actual,0) > 0 THEN -100.00
                        ELSE 0.00 
                    END
                    WHERE year2 = :year AND category_name = 'Total' AND period_name = 'Total'";
                
                $totalVarianceParams = [
                    ':year' => $year
                ];
                
                if ($cluster) {
                    $updateTotalVarianceQuery .= " AND cluster = :cluster";
                    $totalVarianceParams[':cluster'] = $cluster;
                }
                
                $updateTotalVarianceStmt = $conn->prepare($updateTotalVarianceQuery);
                $updateTotalVarianceStmt->execute($totalVarianceParams);
            }
        }
        
        // SECOND: Update the transaction in the database (excluding ACCEPTANCE and COMMENTS)
        $updateQuery = "UPDATE budget_preview SET 
            BudgetHeading = :budgetHeading, 
            Outcome = :outcome, 
            Activity = :activity, 
            BudgetLine = :budgetLine, 
            Description = :description, 
            Partner = :partner, 
            EntryDate = :entryDate, 
            Amount = :amount, 
            PVNumber = :pvNumber, 
            Documents = :documents, 
            DocumentPaths = :documentPaths, 
            DocumentTypes = :documentTypes, 
            QuarterPeriod = :quarterPeriod, 
            CategoryName = :categoryName, 
            cluster = :cluster 
            WHERE PreviewID = :previewId";
            
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute([
            ':budgetHeading' => $budgetHeading,
            ':outcome' => $outcome,
            ':activity' => $activity,
            ':budgetLine' => $budgetLine,
            ':description' => $description,
            ':partner' => $partner,
            ':entryDate' => $entryDate,
            ':amount' => $amount,
            ':pvNumber' => $pvNumber,
            ':documents' => $documentsJson,
            ':documentPaths' => $documentPathsStr,
            ':documentTypes' => $documentTypesStr,
            ':quarterPeriod' => $quarterPeriod,
            ':categoryName' => $categoryName,
            ':cluster' => $cluster,
            ':previewId' => $previewId
        ]);
        
        // THIRD: Apply the new transaction amount to budget calculations
        if ($amount > 0) {
            // Get the budget_id if not already available
            if (!$budgetId) {
                // Find the correct budget_id based on category, quarter, and year
                $entryDateTime = new DateTime($entryDate);
                $entryYear = (int)$entryDateTime->format('Y');
                
                $budgetIdQuery = "SELECT id FROM budget_data 
                                 WHERE year2 = :year AND category_name = :categoryName AND period_name = :periodName 
                                 AND :entryDate BETWEEN start_date AND end_date";
                
                $budgetIdParams = [
                    ':year' => $entryYear,
                    ':categoryName' => $categoryName,
                    ':periodName' => $quarterPeriod,
                    ':entryDate' => $entryDate
                ];
                
                if ($cluster) {
                    $budgetIdQuery .= " AND cluster = :cluster";
                    $budgetIdParams[':cluster'] = $cluster;
                }
                
                $budgetIdStmt = $conn->prepare($budgetIdQuery);
                $budgetIdStmt->execute($budgetIdParams);
                $budgetIdData = $budgetIdStmt->fetch(PDO::FETCH_ASSOC);
                $budgetId = $budgetIdData['id'] ?? null;
                
                // Update the budget_preview with the budget_id
                if ($budgetId) {
                    $updateBudgetIdQuery = "UPDATE budget_preview SET budget_id = :budgetId WHERE PreviewID = :previewId";
                    $updateBudgetIdStmt = $conn->prepare($updateBudgetIdQuery);
                    $updateBudgetIdStmt->execute([
                        ':budgetId' => $budgetId,
                        ':previewId' => $previewId
                    ]);
                }
            }
            
            // If we have a budget_id, update the budget_data table
            if ($budgetId) {
                // First, get current budget data values
                $getBudgetQuery = "SELECT budget, actual, forecast, actual_plus_forecast, variance_percentage FROM budget_data WHERE id = :budgetId";
                $getBudgetStmt = $conn->prepare($getBudgetQuery);
                $getBudgetStmt->execute([':budgetId' => $budgetId]);
                $budgetData = $getBudgetStmt->fetch(PDO::FETCH_ASSOC);
                $currentBudget = $budgetData['budget'] ?? 0;
                $currentActual = $budgetData['actual'] ?? 0;
                $currentForecast = $budgetData['forecast'] ?? 0;
                
                // Calculate new actual value (add the new amount)
                $newActual = max(0, $currentActual + $amount);
                // Subtract only the delta from forecast to preserve user-entered base
                $newForecast = max(0, ($currentForecast ?? 0) - $amount);
                
                // Calculate new actual_plus_forecast using recalculated forecast
                $newActualPlusForecast = $newActual + $newForecast;
                
                // Calculate new variance percentage using the requested formula
                // Variance (%) = (Budget − Actual) / Budget × 100
                $newVariancePercentage = 0;
                if ($currentBudget > 0) {
                    $newVariancePercentage = round((($currentBudget - $newActual) / abs($currentBudget)) * 100, 2);
                } else if ($currentBudget == 0 && $newActual > 0) {
                    $newVariancePercentage = -100.00;
                }
                
                // Update the specific quarter row in budget_data including recalculated forecast
                $updateBudgetQuery = "UPDATE budget_data SET 
                    actual = :actual,
                    forecast = :forecast,
                    actual_plus_forecast = :actualPlusForecast,
                    variance_percentage = :variancePercentage
                    WHERE id = :budgetId";
                $updateStmt = $conn->prepare($updateBudgetQuery);
                $updateStmt->execute([
                    ':actual' => $newActual,
                    ':forecast' => $newForecast,
                    ':actualPlusForecast' => $newActualPlusForecast,
                    ':variancePercentage' => $newVariancePercentage,
                    ':budgetId' => $budgetId
                ]);
                
                // Extract year and cluster for further updates
                $extractYearQuery = "SELECT year2, cluster FROM budget_data WHERE id = :budgetId";
                $extractYearStmt = $conn->prepare($extractYearQuery);
                $extractYearStmt->execute([':budgetId' => $budgetId]);
                $extractYearData = $extractYearStmt->fetch(PDO::FETCH_ASSOC);
                $year = $extractYearData['year2'] ?? $originalYear;
                $cluster = $extractYearData['cluster'] ?? null;
                
                if ($year && $categoryName) {
                    // Update Annual Total row - recalculate the sum of quarterly actuals
                    $updateAnnualQuery = "UPDATE budget_data 
                        SET actual = (SELECT SUM(COALESCE(actual, 0)) FROM budget_data b 
                                    WHERE b.year2 = :year AND b.category_name = :categoryName 
                                    AND b.period_name IN ('Q1', 'Q2', 'Q3', 'Q4')";
                    
                    $annualParams = [
                        ':year' => $year,
                        ':categoryName' => $categoryName
                    ];
                    
                    if ($cluster) {
                        $updateAnnualQuery .= " AND b.cluster = :cluster";
                        $annualParams[':cluster'] = $cluster;
                    }
                    $updateAnnualQuery .= ") WHERE year2 = :year2 AND category_name = :categoryName2 AND period_name = 'Annual Total'";
                    
                    $annualParams[':year2'] = $year;
                    $annualParams[':categoryName2'] = $categoryName;
                    
                    if ($cluster) {
                        $annualParams[':cluster2'] = $cluster;
                        $updateAnnualQuery .= " AND cluster = :cluster2";
                    }
                    
                    $updateAnnualStmt = $conn->prepare($updateAnnualQuery);
                    $updateAnnualStmt->execute($annualParams);
                    
                    // Sync Annual Total forecast as the sum of quarterly forecasts
                    $updateAnnualForecastSumQuery = "UPDATE budget_data 
                        SET forecast = (
                            SELECT SUM(COALESCE(forecast, 0)) 
                            FROM budget_data b 
                            WHERE b.year2 = :year AND b.category_name = :categoryName 
                            AND b.period_name IN ('Q1','Q2','Q3','Q4')";
                    $annualForecastParams = [
                        ':year' => $year,
                        ':categoryName' => $categoryName
                    ];
                    if ($cluster) {
                        $updateAnnualForecastSumQuery .= " AND b.cluster = :cluster";
                        $annualForecastParams[':cluster'] = $cluster;
                    }
                    $updateAnnualForecastSumQuery .= ") WHERE year2 = :year2 AND category_name = :categoryName2 AND period_name = 'Annual Total'";
                    $annualForecastParams[':year2'] = $year;
                    $annualForecastParams[':categoryName2'] = $categoryName;
                    if ($cluster) {
                        $updateAnnualForecastSumQuery .= " AND cluster = :cluster2";
                        $annualForecastParams[':cluster2'] = $cluster;
                    }
                    $updateAnnualForecastSumStmt = $conn->prepare($updateAnnualForecastSumQuery);
                    $updateAnnualForecastSumStmt->execute($annualForecastParams);

                    // Update actual_plus_forecast for Annual Total
                    $updateAnnualActualForecastQuery = "UPDATE budget_data 
                        SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                        WHERE year2 = :year AND category_name = :categoryName AND period_name = 'Annual Total'";
                    
                    $actualForecastParams = [
                        ':year' => $year,
                        ':categoryName' => $categoryName
                    ];
                    
                    if ($cluster) {
                        $updateAnnualActualForecastQuery .= " AND cluster = :cluster";
                        $actualForecastParams[':cluster'] = $cluster;
                    }
                    
                    $updateAnnualActualForecastStmt = $conn->prepare($updateAnnualActualForecastQuery);
                    $updateAnnualActualForecastStmt->execute($actualForecastParams);
                    
                    // Update variance percentages
                    // Variance (%) = (Budget − Actual) / Budget × 100
                    $updateVarianceQuery = "UPDATE budget_data 
                        SET variance_percentage = CASE 
                            WHEN budget > 0 THEN ROUND((((budget - COALESCE(actual,0)) / budget) * 100), 2)
                            WHEN budget = 0 AND COALESCE(actual,0) > 0 THEN -100.00
                            ELSE 0.00 
                        END
                        WHERE year2 = :year AND category_name = :categoryName";
                    
                    $varianceParams = [
                        ':year' => $year,
                        ':categoryName' => $categoryName
                    ];
                    
                    if ($cluster) {
                        $updateVarianceQuery .= " AND cluster = :cluster";
                        $varianceParams[':cluster'] = $cluster;
                    }
                    
                    $updateVarianceStmt = $conn->prepare($updateVarianceQuery);
                    $updateVarianceStmt->execute($varianceParams);
                    
                    // Finally, update the Total row
                    // Calculate new actual for Total row
                    $updateTotalQuery = "UPDATE budget_data 
                        SET actual = (SELECT SUM(COALESCE(actual, 0)) FROM budget_data b 
                                    WHERE b.year2 = :year AND b.period_name = 'Annual Total' AND b.category_name != 'Total'";
                    
                    $totalParams = [
                        ':year' => $year
                    ];
                    
                    if ($cluster) {
                        $updateTotalQuery .= " AND b.cluster = :cluster";
                        $totalParams[':cluster'] = $cluster;
                    }
                    $updateTotalQuery .= ") WHERE year2 = :year2 AND category_name = 'Total' AND period_name = 'Total'";
                    
                    $totalParams[':year2'] = $year;
                    
                    if ($cluster) {
                        $totalParams[':cluster2'] = $cluster;
                        $updateTotalQuery .= " AND cluster = :cluster2";
                    }
                    
                    $updateTotalStmt = $conn->prepare($updateTotalQuery);
                    $updateTotalStmt->execute($totalParams);
                    
                    // Sync Total forecast as sum of Annual Total forecasts across categories
                    $updateTotalForecastSumQuery = "UPDATE budget_data 
                        SET forecast = (
                            SELECT SUM(COALESCE(forecast, 0)) FROM budget_data b 
                            WHERE b.year2 = :year AND b.period_name = 'Annual Total' AND b.category_name != 'Total'";
                    $totalForecastParams = [
                        ':year' => $year
                    ];
                    if ($cluster) {
                        $updateTotalForecastSumQuery .= " AND b.cluster = :cluster";
                        $totalForecastParams[':cluster'] = $cluster;
                    }
                    $updateTotalForecastSumQuery .= ") WHERE year2 = :year2 AND category_name = 'Total' AND period_name = 'Total'";
                    $totalForecastParams[':year2'] = $year;
                    if ($cluster) {
                        $updateTotalForecastSumQuery .= " AND cluster = :cluster2";
                        $totalForecastParams[':cluster2'] = $cluster;
                    }
                    $updateTotalForecastSumStmt = $conn->prepare($updateTotalForecastSumQuery);
                    $updateTotalForecastSumStmt->execute($totalForecastParams);

                    // Update actual_plus_forecast for Total row
                    $updateTotalActualForecastQuery = "UPDATE budget_data 
                        SET actual_plus_forecast = COALESCE(actual, 0) + COALESCE(forecast, 0)
                        WHERE year2 = :year AND category_name = 'Total' AND period_name = 'Total'";
                    
                    $totalActualForecastParams = [
                        ':year' => $year
                    ];
                    
                    if ($cluster) {
                        $updateTotalActualForecastQuery .= " AND cluster = :cluster";
                        $totalActualForecastParams[':cluster'] = $cluster;
                    }
                    
                    $updateTotalActualForecastStmt = $conn->prepare($updateTotalActualForecastQuery);
                    $updateTotalActualForecastStmt->execute($totalActualForecastParams);
                    
                    // Update variance for Total row
                    // Variance (%) = (Budget − Actual) / Budget × 100
                    $updateTotalVarianceQuery = "UPDATE budget_data 
                        SET variance_percentage = CASE 
                            WHEN budget > 0 THEN ROUND((((budget - COALESCE(actual,0)) / budget) * 100), 2)
                            WHEN budget = 0 AND COALESCE(actual,0) > 0 THEN -100.00
                            ELSE 0.00 
                        END
                        WHERE year2 = :year AND category_name = 'Total' AND period_name = 'Total'";
                    
                    $totalVarianceParams = [
                        ':year' => $year
                    ];
                    
                    if ($cluster) {
                        $updateTotalVarianceQuery .= " AND cluster = :cluster";
                        $totalVarianceParams[':cluster'] = $cluster;
                    }
                    
                    $updateTotalVarianceStmt = $conn->prepare($updateTotalVarianceQuery);
                    $updateTotalVarianceStmt->execute($totalVarianceParams);
                }
            }
        }
        
        // Update the budget_preview table with calculated values
        // Get the budget_id if we don't have it yet
        if (!$budgetId) {
            // Find the correct budget_id based on category, quarter, and year
            $entryDateTime = new DateTime($entryDate);
            $entryYear = (int)$entryDateTime->format('Y');
            
            $budgetIdQuery = "SELECT id FROM budget_data 
                             WHERE year2 = :year AND category_name = :categoryName AND period_name = :periodName 
                             AND :entryDate BETWEEN start_date AND end_date";
            
            $budgetIdParams = [
                ':year' => $entryYear,
                ':categoryName' => $categoryName,
                ':periodName' => $quarterPeriod,
                ':entryDate' => $entryDate
            ];
            
            if ($cluster) {
                $budgetIdQuery .= " AND cluster = :cluster";
                $budgetIdParams[':cluster'] = $cluster;
            }
            
            $budgetIdStmt = $conn->prepare($budgetIdQuery);
            $budgetIdStmt->execute($budgetIdParams);
            $budgetIdData = $budgetIdStmt->fetch(PDO::FETCH_ASSOC);
            $budgetId = $budgetIdData['id'] ?? null;
        }
        
        // Read values for budget_preview directly from budget_data
        $originalBudgetValue = 0;
        $newActualSpent = 0;
        $newForecastAmount = 0;
        $newRemainingBudget = 0;
        $newVariancePercentage = 0;
        if ($budgetId) {
            $budgetQuery = "SELECT budget, actual, forecast, variance_percentage FROM budget_data WHERE id = :budgetId";
            $budgetStmt = $conn->prepare($budgetQuery);
            $budgetStmt->execute([':budgetId' => $budgetId]);
            $budgetRow = $budgetStmt->fetch(PDO::FETCH_ASSOC);
            $originalBudgetValue = $budgetRow['budget'] ?? 0;
            $newActualSpent = $budgetRow['actual'] ?? 0;
            $newForecastAmount = $budgetRow['forecast'] ?? 0;
            $newRemainingBudget = $budgetRow['forecast'] ?? 0;
            $newVariancePercentage = $budgetRow['variance_percentage'] ?? 0;
        }
        
        // Update the budget_preview table with calculated values
        $updatePreviewQuery = "UPDATE budget_preview SET 
            OriginalBudget = :originalBudget,
            RemainingBudget = :remainingBudget,
            ActualSpent = :actualSpent,
            ForecastAmount = :forecastAmount,
            VariancePercentage = :variancePercentage
            WHERE PreviewID = :previewId";
        $updatePreviewStmt = $conn->prepare($updatePreviewQuery);
        $updatePreviewStmt->execute([
            ':originalBudget' => $originalBudgetValue,
            ':remainingBudget' => $newRemainingBudget,
            ':actualSpent' => $newActualSpent,
            ':forecastAmount' => $newForecastAmount,
            ':variancePercentage' => $newVariancePercentage,
            ':previewId' => $previewId
        ]);
        
        // Commit the transaction if everything was successful
        $conn->commit();
        
        $_SESSION['success_message'] = "Transaction updated successfully.";
        header("Location: history.php");
        exit();
    } catch (Exception $e) {
        // Rollback the transaction if there was an error
        $conn->rollback();
        $error_message = "Error updating transaction: " . $e->getMessage();
    }
}

// Parse document data for display
$documentList = [];
if (!empty($transaction['Documents'])) {
    $documents = json_decode($transaction['Documents'], true);
    if (is_array($documents)) {
        $documentList = $documents;
    }
} else if (!empty($transaction['DocumentPaths'])) {
    // Fallback to comma-separated format
    $paths = explode(',', $transaction['DocumentPaths']);
    $types = !empty($transaction['DocumentTypes']) ? explode(',', $transaction['DocumentTypes']) : [];
    
    for ($i = 0; $i < count($paths); $i++) {
        if (!empty($paths[$i])) {
            $documentList[] = [
                'documentType' => $types[$i] ?? 'Unknown',
                'serverPath' => $paths[$i],
                'filename' => basename($paths[$i])
            ];
        }
    }
}

// Fetch checklist items from database using PDO
$completeChecklist = [];
$categoryOptions = [];
try {
    // Check if database connection exists
    if (isset($conn)) {
        $query = "SELECT category, document_name FROM checklist_items WHERE is_active = 1 ORDER BY category, sort_order";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            $categories = [];
            foreach ($results as $row) {
                // Extract the category number and name
                $categoryParts = explode(' ', $row['category'], 2);
                $categoryNumber = $categoryParts[0];
                $categoryName = $categoryParts[1] ?? $categoryParts[0];
                
                // Create the key that matches the select options (using dot notation like in documents.php)
                $categoryKey = $categoryNumber . '. ' . $categoryName;
                
                // Store categories for select options
                if (!in_array($categoryKey, $categories)) {
                    $categories[] = $categoryKey;
                }
                
                // Build the completeChecklist array properly
                if (!isset($completeChecklist[$categoryKey])) {
                    $completeChecklist[$categoryKey] = [];
                }
                $completeChecklist[$categoryKey][] = $row['document_name'];
            }
            
            // Sort categories by number
            usort($categories, function($a, $b) {
                $numA = (int)explode('. ', $a)[0];
                $numB = (int)explode('. ', $b)[0];
                return $numA - $numB;
            });
            
            $categoryOptions = $categories;
        }
    }
} catch (Exception $e) {
    // Log error but continue with fallback
    error_log("Database error in edit_transaction.php: " . $e->getMessage());
}

// If database fetch failed or returned no results, fallback to hardcoded checklist
if (empty($completeChecklist)) {
    $completeChecklist = [
        "1. Withholding Tax (WHT) Payments" => [
            "Withholding Tax (WHT) Payment Request Form",
            "Withholding Tax (WHT) Calculation Sheet",
            "Payment Voucher",
            "Bank Transfer Request Letter / Cheque Copy",
            "Proof of Payment (Bank Transfer Confirmation / Cheque Copy)"
        ],
        "2. Income Tax Payments" => [
            "Income Tax Payment Request Form",
            "Income Tax Calculation Sheet",
            "Payment Voucher",
            "Bank Transfer Request Letter / Cheque Copy",
            "Proof of Payment (Bank Transfer Confirmation / Cheque Copy)"
        ],
        "3. Pension Contribution Payment" => [
            "Pension Calculation Sheet",
            "Pension Payment Slip / Receipt from Tax Authority",
            "Bank Confirmation of Pension Payment"
        ],
        "4. Payroll Payments" => [
            "Approved Timesheets / Attendance Records",
            "Payroll Register Sheet ( For Each Project )",
            "Master Payroll Register Sheet",
            "Payslips / Pay Stubs (for each employee) ( If applicable)",
            "Bank Transfer Request Letter",
            "Proof Of Payment",
            "Payment Voucher"
        ],
        "5. Telecom Services Payments" => [
            "Telecom Service Contract / Agreement (if applicable)",
            "Monthly Telecom Bill / Invoice",
            "Cost Pro-ration Sheet",
            "Payment Request Form (Approved (by authorized person) )",
            "Bank transfer Request Letter /Cheque copy",
            "Proof of Payment (Bank transfer confirmation/Cheque copy)"
        ],
        "6. Rent Payments" => [
            "Rental / Lease Agreement",
            "Landlord's Invoice / Payment Request",
            "Payment Request Form (Approved (by authorized person) )",
            "Cost Pro-ration Sheet",
            "Bank transfer Request Letter /Cheque copy",
            "Proof of Payment (Bank transfer Advice /Cheque copy)",
            "Withholding Tax (WHT) Receipt (if applicable)"
        ],
        "7. Consultant Payments" => [
            "Consultant Service Contract Agreement",
            "Scope of Work (SOW) / Terms of Reference (TOR)",
            "Consultant Invoice (if applicable)",
            "Consultant Service accomplishment Activity report / Progress Report",
            "Payment Request Form (Approved)",
            "Proof of Payment (Bank transfer confirmation/Cheque copy)",
            "Withholding Tax (WHT) Receipt (if applicable)",
            "Paymnet Voucher"
        ],
        "8. Freight Transportation" => [
            "Purchase request",
            "Quotation request (filled in and sent to suppliers)",
            "Quotation (received back, signed and stamped)",
            "Attached proforma invoices in a sealed envelope",
            "Proformas with all formalities, including trade license",
            "Competitive bid analysis (CBA) signed and approved",
            "Contract agreement or purchase order",
            "Payment request form",
            "Original waybill",
            "Goods received notes",
            "Cash receipt invoice (with Organizational TIN)",
            "Cheque copy or bank transfer letter from vendor",
            "Payment voucher"
        ],
        "9. Vehicle Rental" => [
            "Purchase request for rental service",
            "Quotation request (filled in and sent to suppliers)",
            "Quotation (received back, signed and stamped)",
            "Attached proforma invoices in a sealed envelope",
            "Proformas with all formalities, including trade license",
            "Competitive bid analysis (CBA) signed and approved",
            "Contract agreement or purchase order",
            "Payment request form",
            "Summary of payments sheet",
            "Signed and approved log book sheet",
            "Vehicle goods-outward inspection certificate",
            "Withholding receipt (for amounts over ETB 10,000)",
            "Cash receipt invoice (with Organizational TIN)",
            "Cheque copy or bank transfer letter from vendor",
            "Payment voucher"
        ],
        "10. Training, Workshop and Related" => [
            "Training approved by Program Manager",
            "Participant invitation letters from government parties",
            "Fully completed attendance sheet",
            "Manager's signature",
            "Approved payment rate (or justified reason for a different rate)",
            "Letter from government for fuel (if applicable)",
            "Activity (training) report",
            "Cash receipt or bank advice (if refund applicable)",
            "Expense settlement sheet with all information",
            "All required signatures on templates",
            "All documents stamped \"paid\"",
            "All documents are original (or cross-referenced if not)",
            "TIN and company name on receipt",
            "Check dates and all information on receipts"
        ],
        "11. Procurement of Services" => [
            "Purchase requisition",
            "Quotation request (filled in and sent to suppliers)",
            "Quotation (received back, signed and stamped)",
            "Attached proforma invoices in a sealed envelope",
            "Proformas with all formalities, including trade license",
            "Competitive bid analysis (CBA) signed and approved",
            "Contract agreement or purchase order",
            "Payment request form",
            "Withholding receipt (for amounts over ETB 10,000)",
            "Cash receipt invoice (with Organizational TIN)",
            "Service accomplishment report",
            "Cheque copy or bank transfer letter from vendor",
            "Payment voucher"
        ],
        "12. Procurement of Goods" => [
            "Purchase request",
            "Quotation request (filled in and sent to suppliers)",
            "Quotation (received back, signed and stamped)",
            "Attached proforma invoices in a sealed envelope",
            "Proformas with all formalities, including trade license",
            "Competitive bid analysis (CBA) signed and approved",
            "purchase order",
            "Contract agreement or Framework Agreement",
            "Payment request form",
            "Withholding receipt (for amounts over ETB 20,000)",
            "Cash receipt invoice (with Organizational TIN)",
            "Goods received note (GRN) or delivery note",
            "Cheque copy or bank transfer letter from vendor",
            "Payment voucher"
        ]
    ];
    
    $categoryOptions = array_keys($completeChecklist);
}

// Flatten the checklist for dropdown options
$flattenedChecklist = [];
foreach ($completeChecklist as $category => $documents) {
    $flattenedChecklist[$category] = $category; // Add category as an option
    foreach ($documents as $document) {
        $flattenedChecklist[$document] = "-- " . $document; // Add documents with indentation
    }
}

// Create JSON for JavaScript
$flattenedChecklistJson = json_encode($flattenedChecklist);

// Include header after processing to avoid header errors
include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaction</title>
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
    max-width: 1400px;
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
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            ring: 2px solid #3b82f6;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .document-item {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .add-document-btn {
            background-color: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }
        .add-document-btn:hover {
            background-color: #059669;
        }
        .remove-document-btn {
            background-color: #ef4444;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            cursor: pointer;
        }
        .remove-document-btn:hover {
            background-color: #dc2626;
        }
        .file-upload-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .file-upload-input {
            flex: 1;
        }
        .upload-btn {
            background-color: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        .upload-btn:hover {
            background-color: #2563eb;
        }
        .file-info {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Amount validation error styling */
        .amount-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
        }
        
        .amount-error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        /* Make the header padding responsive */
header {
    padding-left: 1rem; /* Reduced from 2rem (px-8) */
    padding-right: 1rem; /* Reduced from 2rem (px-8) */
}

/* For very small screens, you can remove padding entirely */
@media (max-width: 375px) {
    header {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
}

/* Make the button text wrap on small screens */
a[href="history.php"] {
    white-space: normal; /* Allows text to wrap */
    padding-left: 0.75rem; /* Adjust padding for better look when wrapped */
    padding-right: 0.75rem;
}

/* Optional: Hide text and show only icon on very small screens */
@media (max-width: 420px) {
    a[href="history.php"] span {
        display: none; /* Hides the "Back to History" text */
    }
    a[href="history.php"] {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        min-width: auto;
    }
}
    </style>
</head>
<body>
<?php 
// Check if this file is being included or accessed directly
$included = defined('INCLUDED_FROM_INDEX') || isset($GLOBALS['in_index_context']);
if (!$included): 
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
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Edit Transaction</h2>
        </div>
        <div class="flex items-center space-x-4">
            <!-- Notification bell -->
         
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
<?php endif; ?>

    <div class="main-content-flex">
        <div class="content-container">
           <div id="editTransactionSection" class="bg-white p-4 sm:p-6 md:p-10 rounded-3xl shadow-2xl w-full mx-auto card-hover animate-fadeIn">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-4xl font-extrabold text-gray-800">Edit Transaction</h3>
                    <a href="history.php" class="bg-gray-500 text-white py-3 px-6 rounded-full font-semibold transition hover:bg-gray-600 btn-shadow flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i> <span>Back to History</span>
                    </a>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <strong class="font-bold">Error: </strong>
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label" for="budget_heading">Budget Heading</label>
                            <input type="text" id="budget_heading" name="budget_heading" class="form-input" value="<?php echo htmlspecialchars($transaction['BudgetHeading'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="outcome">Outcome</label>
                            <input type="text" id="outcome" name="outcome" class="form-input" value="<?php echo htmlspecialchars($transaction['Outcome'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="activity">Activity</label>
                            <input type="text" id="activity" name="activity" class="form-input" value="<?php echo htmlspecialchars($transaction['Activity'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="budget_line">Budget Line</label>
                            <input type="text" id="budget_line" name="budget_line" class="form-input" value="<?php echo htmlspecialchars($transaction['BudgetLine'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group md:col-span-2">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-textarea" rows="3"><?php echo htmlspecialchars($transaction['Description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="partner">Partner</label>
                            <input type="text" id="partner" name="partner" class="form-input" value="<?php echo htmlspecialchars($transaction['Partner'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="entry_date">Entry Date</label>
                            <input type="date" id="entry_date" name="entry_date" class="form-input" value="<?php echo htmlspecialchars($transaction['EntryDate'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="amount">Amount (Enter in ETB)</label>
                            <input type="number" step="0.01" id="amount" name="amount" class="form-input" value="<?php echo htmlspecialchars($amountInETB ?? 0); ?>" required>
                            <div class="text-sm text-gray-500 mt-1">
                                Original amount: <?php echo htmlspecialchars($transaction['Amount'] ?? 0); ?> USD
                            </div>
                            <!-- Hidden budget_id field for transaction updates -->
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="pv_number">PV Number</label>
                            <input type="text" id="pv_number" name="pv_number" class="form-input" value="<?php echo htmlspecialchars($transaction['PVNumber'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="quarter_period">Quarter Period</label>
                            <select id="quarter_period" name="quarter_period" class="form-select" required>
                                <option value="Q1" <?php echo ($transaction['QuarterPeriod'] ?? '') === 'Q1' ? 'selected' : ''; ?>>Q1</option>
                                <option value="Q2" <?php echo ($transaction['QuarterPeriod'] ?? '') === 'Q2' ? 'selected' : ''; ?>>Q2</option>
                                <option value="Q3" <?php echo ($transaction['QuarterPeriod'] ?? '') === 'Q3' ? 'selected' : ''; ?>>Q3</option>
                                <option value="Q4" <?php echo ($transaction['QuarterPeriod'] ?? '') === 'Q4' ? 'selected' : ''; ?>>Q4</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="category_name" class="form-input" value="<?php echo htmlspecialchars($transaction['CategoryName'] ?? ''); ?>" required>
                        </div>
                        
                        <!-- Hidden fields for calculated values -->
                        <input type="hidden" id="original_budget" name="original_budget" value="<?php echo htmlspecialchars($transaction['OriginalBudget'] ?? 0); ?>">
                        <input type="hidden" id="remaining_budget" name="remaining_budget" value="<?php echo htmlspecialchars($transaction['RemainingBudget'] ?? 0); ?>">
                        <input type="hidden" id="actual_spent" name="actual_spent" value="<?php echo htmlspecialchars($transaction['ActualSpent'] ?? 0); ?>">
                        <input type="hidden" id="forecast_amount" name="forecast_amount" value="<?php echo htmlspecialchars($transaction['ForecastAmount'] ?? 0); ?>">
                        
                        <div class="form-group">
                            <label class="form-label" for="cluster">Cluster</label>
                            <input type="text" id="cluster" name="cluster" class="form-input" value="<?php echo htmlspecialchars($transaction['cluster'] ?? ''); ?>" required>
                        </div>
                        
                        <!-- Hidden field to store budget_id -->
                        <input type="hidden" id="budget_id" name="budget_id" value="<?php echo htmlspecialchars($transaction['budget_id'] ?? ''); ?>">
                        
                        <!-- Document Section -->
                        <div class="md:col-span-2">
                            <h4 class="text-xl font-bold text-gray-800 mb-4">Documents</h4>
                            <div id="documents-container">
                                <?php if (!empty($documentList)): ?>
                                    <?php foreach ($documentList as $index => $doc): ?>
                                        <div class="document-item" data-document-index="<?php echo $index; ?>">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="form-label">Document Type</label>
                                                    <select name="document_types[]" class="form-select">
                                                        <?php foreach ($flattenedChecklist as $value => $label): ?>
                                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($doc['documentType']) && $doc['documentType'] == $value) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($label); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="form-label">File Upload</label>
                                                    <div class="file-upload-wrapper">
                                                        <input type="file" name="document_files[]" class="form-input file-upload-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                                        <button type="button" class="upload-btn" onclick="triggerFileUpload(this)">Browse</button>
                                                    </div>
                                                    <?php if (!empty($doc['filename'])): ?>
                                                        <div class="file-info">
                                                            Current file: <?php echo htmlspecialchars($doc['filename']); ?>
                                                            <input type="hidden" name="existing_document_paths[]" value="<?php echo htmlspecialchars($doc['serverPath'] ?? ''); ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-end">
                                                    <button type="button" class="remove-document-btn" onclick="removeDocument(this)">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="document-item" data-document-index="0">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="form-label">Document Type</label>
                                                <select name="document_types[]" class="form-select">
                                                    <?php foreach ($flattenedChecklist as $value => $label): ?>
                                                        <option value="<?php echo htmlspecialchars($value); ?>">
                                                            <?php echo htmlspecialchars($label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label">File Upload</label>
                                                <div class="file-upload-wrapper">
                                                    <input type="file" name="document_files[]" class="form-input file-upload-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                                    <button type="button" class="upload-btn" onclick="triggerFileUpload(this)">Browse</button>
                                                </div>
                                            </div>
                                            <div class="flex items-end">
                                                <button type="button" class="remove-document-btn" onclick="removeDocument(this)">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="add-document-btn mt-2" onclick="addDocument()">
                                <i class="fas fa-plus mr-2"></i> Add Document
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-8">
                        <a href="history.php" class="px-6 py-3 text-sm font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 transition duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 transition duration-150 shadow-sm">
                            Update Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let documentIndex = <?php echo count($documentList) > 0 ? count($documentList) : 1; ?>;
        const checklistOptions = <?php echo $flattenedChecklistJson; ?>;
        
        // Function to find the two nearest valid values
        function findNearestValidValues(inputValue) {
            // Round to 2 decimal places
            const roundedValue = Math.round(inputValue * 100) / 100;
            
            // Calculate the two nearest valid values (increment/decrement by 0.01)
            const lowerValue = Math.max(0, Math.round((roundedValue - 0.01) * 100) / 100);
            const higherValue = Math.round((roundedValue + 0.01) * 100) / 100;
            
            return [lowerValue, higherValue];
        }
        
        // Function to show error message
        function showAmountError(message) {
            const amountInput = document.getElementById('amount');
            const amountGroup = amountInput.closest('.form-group');
            
            // Remove existing error message
            const existingError = amountGroup.querySelector('.amount-error-message');
            if (existingError) {
                existingError.remove();
            }
            
            // Add error styling
            amountInput.classList.add('amount-error');
            
            // Create and add error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'amount-error-message';
            errorDiv.textContent = message;
            amountGroup.appendChild(errorDiv);
            
            // Focus on the input
            amountInput.focus();
        }
        
        // Function to clear error message
        function clearAmountError() {
            const amountInput = document.getElementById('amount');
            const amountGroup = amountInput.closest('.form-group');
            
            // Remove error styling
            amountInput.classList.remove('amount-error');
            
            // Remove error message
            const existingError = amountGroup.querySelector('.amount-error-message');
            if (existingError) {
                existingError.remove();
            }
        }
        
        // Function to validate amount input
        function validateAmountInput() {
            const amountInput = document.getElementById('amount');
            const amountValue = parseFloat(amountInput.value);
            
            // Clear any existing error
            clearAmountError();
            
            // Check if the value is a valid positive number
            if (isNaN(amountValue) || amountValue <= 0) {
                showAmountError('Please enter a valid positive amount.');
                return false;
            }
            
            // Check if the value has more than 2 decimal places
            const decimalPlaces = (amountValue.toString().split('.')[1] || []).length;
            if (decimalPlaces > 2) {
                const nearestValues = findNearestValidValues(amountValue);
                showAmountError(`Please enter a valid amount. The two nearest valid values are: ${nearestValues[0].toFixed(2)} and ${nearestValues[1].toFixed(2)}`);
                return false;
            }
            
            return true;
        }
        
        // Add event listener to the form submit button
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const amountInput = document.getElementById('amount');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateAmountInput()) {
                        e.preventDefault();
                    }
                });
            }
            
            // Clear error when user starts typing
            if (amountInput) {
                amountInput.addEventListener('input', function() {
                    if (this.value !== '') {
                        clearAmountError();
                    }
                });
            }
        });
        
        function triggerFileUpload(button) {
            const fileInput = button.previousElementSibling;
            fileInput.click();
        }
        
        function addDocument() {
            const container = document.getElementById('documents-container');
            const documentItem = document.createElement('div');
            documentItem.className = 'document-item';
            documentItem.setAttribute('data-document-index', documentIndex);
            
            // Generate options for the select dropdown
            let optionsHtml = '';
            for (const [value, label] of Object.entries(checklistOptions)) {
                optionsHtml += `<option value="${value}">${label}</option>`;
            }
            
            documentItem.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Document Type</label>
                        <select name="document_types[]" class="form-select">
                            ${optionsHtml}
                        </select>
                    </div>
                    <div>
                        <label class="form-label">File Upload</label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="document_files[]" class="form-input file-upload-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            <button type="button" class="upload-btn" onclick="triggerFileUpload(this)">Browse</button>
                        </div>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="remove-document-btn" onclick="removeDocument(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(documentItem);
            documentIndex++;
        }
        
        function removeDocument(button) {
            const documentItem = button.closest('.document-item');
            if (document.getElementById('documents-container').children.length > 1) {
                documentItem.remove();
            } else {
                // Clear the fields if it's the last document
                const inputs = documentItem.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else if (input.type !== 'file') {
                        input.value = '';
                    }
                });
            }
        }
        
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
<?php if (!$included): ?>
    </main>
</div>
<?php endif; ?>
</body>
</html>