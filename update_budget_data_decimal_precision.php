<?php
// Script to update decimal precision of financial fields in budget_data table
// Changes decimal(18,2) to decimal(18,10) for better precision

define('INCLUDED_SETUP', true);
include 'setup_database.php';

function columnExists(mysqli $conn, string $dbName, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('sss', $dbName, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return (int)($row['cnt'] ?? 0) > 0;
}

function getColumnDefinition(mysqli $conn, string $dbName, string $table, string $column): string {
    $sql = "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('sss', $dbName, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return $row['COLUMN_TYPE'] ?? '';
}

// Determine current database name
$res = $conn->query('SELECT DATABASE() as db');
if (!$res) {
    die('Failed to detect database: ' . $conn->error);
}
$dbRow = $res->fetch_assoc();
$dbName = $dbRow['db'];

$table = 'budget_data';

// Fields that need higher decimal precision
$financialFields = [
    'budget',
    'actual',
    'forecast',
    'actual_plus_forecast',
    'variance_percentage'
];

$modifications = [];

foreach ($financialFields as $field) {
    if (columnExists($conn, $dbName, $table, $field)) {
        $currentDefinition = getColumnDefinition($conn, $dbName, $table, $field);
        
        // Check if current definition is decimal(18,2) or similar low precision
        if (preg_match('/decimal\(\d+,\s*[0-9][0-2]?\)/i', $currentDefinition)) {
            // Change to higher precision decimal
            if ($field === 'variance_percentage') {
                // For variance percentage, we can use decimal(10,6) which gives us 6 decimal places
                $modifications[] = "MODIFY COLUMN `{$field}` DECIMAL(10,6) DEFAULT NULL";
            } else {
                // For monetary amounts, use decimal(18,10)
                $modifications[] = "MODIFY COLUMN `{$field}` DECIMAL(18,10) DEFAULT NULL";
            }
        }
    }
}

if (empty($modifications)) {
    echo json_encode(['success' => true, 'message' => 'No precision changes needed; fields already have sufficient precision.']);
    exit;
}

$alter = "ALTER TABLE `{$table}`\n" . implode(",\n", $modifications);

if ($conn->query($alter) === true) {
    echo json_encode(['success' => true, 'message' => 'Decimal precision updated successfully', 'alter' => $alter]);
} else {
    echo json_encode(['success' => false, 'message' => 'ALTER TABLE failed: ' . $conn->error, 'alter' => $alter]);
}
?>