<?php
// Script to update decimal precision of rate fields in currency_rates table
// Changes decimal fields to higher precision for better accuracy

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

$table = 'currency_rates';

// Fields that need higher decimal precision
$rateFields = [
    'exchange_rate'
];

$modifications = [];

foreach ($rateFields as $field) {
    if (columnExists($conn, $dbName, $table, $field)) {
        $currentDefinition = getColumnDefinition($conn, $dbName, $table, $field);
        
        // Check if current definition has low precision
        if (preg_match('/decimal\(\d+,\s*[0-4]\)/i', $currentDefinition)) {
            // Change to decimal(18,8) for better precision in exchange rates
            $modifications[] = "MODIFY COLUMN `{$field}` DECIMAL(18,8) DEFAULT NULL";
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