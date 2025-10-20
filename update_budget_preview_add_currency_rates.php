<?php
// Run once to add custom currency rate fields to budget_preview
// Usage: open this file in the browser or run via CLI: php update_budget_preview_add_currency_rates.php

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

// Determine current database name
$res = $conn->query('SELECT DATABASE() as db');
if (!$res) {
    die('Failed to detect database: ' . $conn->error);
}
$dbRow = $res->fetch_assoc();
$dbName = $dbRow['db'];

$table = 'budget_preview';
$changes = [];

if (!columnExists($conn, $dbName, $table, 'use_custom_rate')) {
    $changes[] = "ADD COLUMN `use_custom_rate` TINYINT(1) NOT NULL DEFAULT 0 AFTER `currency`";
}
if (!columnExists($conn, $dbName, $table, 'usd_to_etb')) {
    $changes[] = "ADD COLUMN `usd_to_etb` DECIMAL(18,4) NULL AFTER `use_custom_rate`";
}
if (!columnExists($conn, $dbName, $table, 'eur_to_etb')) {
    $changes[] = "ADD COLUMN `eur_to_etb` DECIMAL(18,4) NULL AFTER `usd_to_etb`";
}
if (!columnExists($conn, $dbName, $table, 'usd_to_eur')) {
    $changes[] = "ADD COLUMN `usd_to_eur` DECIMAL(18,4) NULL AFTER `eur_to_etb`";
}

if (empty($changes)) {
    echo json_encode(['success' => true, 'message' => 'No changes needed; columns already exist.']);
    exit;
}

$alter = "ALTER TABLE `{$table}`\n" . implode(",\n", $changes);

if ($conn->query($alter) === true) {
    echo json_encode(['success' => true, 'message' => 'Columns added successfully', 'alter' => $alter]);
} else {
    echo json_encode(['success' => false, 'message' => 'ALTER TABLE failed: ' . $conn->error, 'alter' => $alter]);
}


