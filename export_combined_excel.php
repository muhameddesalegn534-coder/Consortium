<?php
// Enable debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("=== Starting export_combined_excel ===");

// Start session
session_start();

// Include database
include 'setup_database.php';

// Get parameters
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selectedCluster = $_GET['cluster'] ?? null;

// Determine user role and cluster
$userRole = $_SESSION['role'] ?? 'user';
$userCluster = $_SESSION['cluster_name'] ?? null;

// Admin can export any cluster; others are restricted
if (!$selectedCluster) {
    if ($userRole === 'admin') {
        // Admin: no default cluster, export all
        $selectedCluster = null;
    } else {
        // Regular user: force their own cluster
        $selectedCluster = $userCluster;
    }
}

// Log for debugging
error_log("User Role: $userRole, User Cluster: $userCluster, Selected Cluster: $selectedCluster, Year: $selectedYear");

// Create temp directory
$tempDir = sys_get_temp_dir() . '/export_' . uniqid();
mkdir($tempDir, 0777, true);

try {
    // =======================
    // 1. EXPORT BUDGET_DATA.CSV (Pivoted)
    // =======================
    $budgetFile = $tempDir . '/Budget_Forecast_' . $selectedYear . '.csv';
    $budgetHandle = fopen($budgetFile, 'w');
    if (!$budgetHandle) throw new Exception('Cannot create budget CSV');

    fwrite($budgetHandle, "\xEF\xBB\xBF"); // UTF-8 BOM

    fputcsv($budgetHandle, [
        'Category',
        'Q1 Budget', 'Q1 Actual',
        'Q2 Budget', 'Q2 Actual',
        'Q3 Budget', 'Q3 Forecast',
        'Q4 Budget', 'Q4 Forecast',
        'Annual Budget', 'Actual + Forecast', 'Variance (%)'
    ]);

    // Build query with cluster condition
    $budgetSql = "SELECT category_name, period_name, budget, actual, forecast, actual_plus_forecast, variance_percentage 
                  FROM budget_data 
                  WHERE year2 = ?";
    $budgetParams = [$selectedYear];
    $budgetTypes = "i";

    if ($selectedCluster) {
        $budgetSql .= " AND cluster = ?";
        $budgetParams[] = $selectedCluster;
        $budgetTypes .= "s";
    }
    $budgetSql .= " ORDER BY category_name, period_name";

    $budgetStmt = $conn->prepare($budgetSql);
    $budgetStmt->bind_param($budgetTypes, ...$budgetParams);
    $budgetStmt->execute();
    $result = $budgetStmt->get_result();

    $categoryData = [];
    while ($row = $result->fetch_assoc()) {
        $cat = $row['category_name'];
        if (!isset($categoryData[$cat])) {
            $categoryData[$cat] = [
                'category' => $cat,
                'q1_budget' => 0, 'q1_actual' => 0,
                'q2_budget' => 0, 'q2_actual' => 0,
                'q3_budget' => 0, 'q3_forecast' => 0,
                'q4_budget' => 0, 'q4_forecast' => 0,
                'annual_budget' => 0, 'annual_actual_forecast' => 0, 'variance' => 0
            ];
        }

        switch ($row['period_name']) {
            case 'Q1':
                $categoryData[$cat]['q1_budget'] = (float)($row['budget'] ?? 0);
                $categoryData[$cat]['q1_actual'] = (float)($row['actual'] ?? 0);
                break;
            case 'Q2':
                $categoryData[$cat]['q2_budget'] = (float)($row['budget'] ?? 0);
                $categoryData[$cat]['q2_actual'] = (float)($row['actual'] ?? 0);
                break;
            case 'Q3':
                $categoryData[$cat]['q3_budget'] = (float)($row['budget'] ?? 0);
                $categoryData[$cat]['q3_forecast'] = (float)($row['forecast'] ?? 0);
                break;
            case 'Q4':
                $categoryData[$cat]['q4_budget'] = (float)($row['budget'] ?? 0);
                $categoryData[$cat]['q4_forecast'] = (float)($row['forecast'] ?? 0);
                break;
            case 'Annual Total':
                $categoryData[$cat]['annual_budget'] = (float)($row['budget'] ?? 0);
                $categoryData[$cat]['annual_actual_forecast'] = (float)($row['actual_plus_forecast'] ?? 0);
                $categoryData[$cat]['variance'] = (float)($row['variance_percentage'] ?? 0);
                break;
        }
    }

    // Grand Total
    $gt = array_fill_keys(['q1_budget','q1_actual','q2_budget','q2_actual','q3_budget','q3_forecast','q4_budget','q4_forecast','annual_budget','annual_actual_forecast'], 0);
    foreach ($categoryData as $data) {
        $name = strtoupper($data['category']);
        if ($name === 'TOTAL' || $name === 'GRAND TOTAL') continue;
        foreach ($gt as $k => $v) {
            $gt[$k] += $data[$k];
        }
    }

    $gtVariance = 0;
    if ($gt['annual_budget'] > 0) {
        $gtVariance = (($gt['annual_budget'] - $gt['annual_actual_forecast']) / $gt['annual_budget']) * 100;
    }

    $categoryData['Grand Total'] = [
        'category' => 'Grand Total',
        'q1_budget' => $gt['q1_budget'], 'q1_actual' => $gt['q1_actual'],
        'q2_budget' => $gt['q2_budget'], 'q2_actual' => $gt['q2_actual'],
        'q3_budget' => $gt['q3_budget'], 'q3_forecast' => $gt['q3_forecast'],
        'q4_budget' => $gt['q4_budget'], 'q4_forecast' => $gt['q4_forecast'],
        'annual_budget' => $gt['annual_budget'], 'annual_actual_forecast' => $gt['annual_actual_forecast'], 'variance' => $gtVariance
    ];

    // Write to CSV
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
    if (!$transactionHandle) throw new Exception('Cannot create transaction CSV');

    fwrite($transactionHandle, "\xEF\xBB\xBF");

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

    // Query with cluster and year
    $transSql = "SELECT 
        BudgetHeading, Outcome, Activity, BudgetLine, Description, Partner, EntryDate, Amount 
        FROM budget_preview 
        WHERE YEAR(EntryDate) = ?";
    $transParams = [$selectedYear];
    $transTypes = "i";

    if ($selectedCluster) {
        $transSql .= " AND cluster = ?";
        $transParams[] = $selectedCluster;
        $transTypes .= "s";
    }
    $transSql .= " ORDER BY EntryDate DESC, PreviewID DESC";

    error_log("Transaction Query: $transSql | Params: " . json_encode($transParams));

    $transStmt = $conn->prepare($transSql);
    $transStmt->bind_param($transTypes, ...$transParams);
    $transStmt->execute();
    $transResult = $transStmt->get_result();

    $rowCount = $transResult->num_rows;
    error_log("Transaction query returned $rowCount rows");

    if ($rowCount === 0) {
        fputcsv($transactionHandle, ['No transactions found for this year and cluster', '', '', '', '', '', '', '0.00']);
    } else {
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
    }
    fclose($transactionHandle);

    // =======================
    // 3. CREATE ZIP
    // =======================
    $zipFile = $tempDir . '/Budget_Report_' . $selectedYear . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
        throw new Exception('Cannot create ZIP');
    }
    $zip->addFile($budgetFile, basename($budgetFile));
    $zip->addFile($transactionFile, basename($transactionFile));
    $zip->close();

    // =======================
    // 4. DOWNLOAD ZIP
    // =======================
    header('Content-Type: application/zip');
    $filename = "Budget_Report_{$selectedYear}";
    if ($selectedCluster) $filename .= "_{$selectedCluster}";
    $filename .= '.zip';
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Content-Length: ' . filesize($zipFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($zipFile);

    // Cleanup
    unlink($budgetFile);
    unlink($transactionFile);
    unlink($zipFile);
    rmdir($tempDir);

} catch (Exception $e) {
    error_log("Export failed: " . $e->getMessage());
    if (is_dir($tempDir)) {
        array_map('unlink', glob("$tempDir/*"));
        rmdir($tempDir);
    }
    die('Export failed: ' . $e->getMessage());
}
exit;