<?php
// Generates an Excel (.xlsx) template for importing budget data, with required columns
// Falls back to CSV if PhpSpreadsheet is unavailable

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

$action = $_GET['action'] ?? '';

if ($action === 'export_budget_template') {
    $filenameBase = 'budget_data_template';

    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $useXlsx = class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
    } catch (Throwable $e) {
        $useXlsx = false;
    }

    // Define the columns for budget data template
    $columns = [
        'Year',
        'Category Name',
        'Period Name',
        'Budget',
        'Forecast',
        'Quarter',
        'Start Date',
        'End Date',
        'Cluster',
        'Current Year',
        'Currency'
    ];

    // Sample data to show the format
    $sampleRows = [
        [2025, 'Administrative costs', 'Q1', 5000.00, 4500.00, 1, '2025-01-01', '2025-03-31', 'Cluster A', 2025, 'ETB'],
        [2025, 'Operational costs', 'Q2', 7500.00, 7000.00, 2, '2025-04-01', '2025-06-30', 'Cluster B', 2025, 'USD']
    ];

    if ($useXlsx) {
        // Output XLSX using PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Headers
        $columnIndex = 1;
        foreach ($columns as $col) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $col);
            $columnIndex++;
        }
        
        // Sample data
        $rowIndex = 2;
        foreach ($sampleRows as $row) {
            $columnIndex = 1;
            foreach ($row as $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $value);
                $columnIndex++;
            }
            $rowIndex++;
        }
        
        // Auto-size columns
        foreach (range(1, count($columns)) as $colIndex) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Set headers for Excel file download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } else {
        // Fallback to CSV output
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $columns);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
} else {
    // Redirect to admin page if no valid action
    header("Location: admin_budget_management.php");
    exit;
}
?>