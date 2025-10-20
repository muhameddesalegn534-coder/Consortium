<?php
// Generates an Excel (.xlsx) template for importing transactions, with required columns
// Falls back to CSV if PhpSpreadsheet is unavailable

ini_set('display_errors', 1);
error_reporting(E_ALL);

$filenameBase = 'transactions_template';

try {
    require_once __DIR__ . '/vendor/autoload.php';
    $useXlsx = class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
} catch (Throwable $e) {
    $useXlsx = false;
}

$columns = [
    'Budget Heading',
    'Outcome',
    'Activity',
    'Budget Line',
    'Description',
    'Partner',
    'Date',
    'Amount',
    'Currency',
    'Reference Number',
    'Document URL'
];

$sampleRows = [
    ['Administrative costs','Project goal achieved','Workshop organization','Travel Expenses','Air tickets for training','ABC Organization','2025-01-15','1500.00','USD','REF-001','https://example.com/doc1.pdf'],
    ['Operational support costs','Operational goal','Meeting facilitation','Meeting Expenses','Meeting room booking','XYZ Organization','2025-01-20','2000.00','EUR','REF-002','https://example.com/doc2.pdf']
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
    
    // Data
    $rowIndex = 2;
    foreach ($sampleRows as $row) {
        $columnIndex = 1;
        foreach ($row as $value) {
            $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $value);
            $columnIndex++;
        }
        $rowIndex++;
    }
    
    // Auto-size
    foreach (range(1, count($columns)) as $colIndex) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
    }

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
    // UTF-8 BOM
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $columns);
    foreach ($sampleRows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}