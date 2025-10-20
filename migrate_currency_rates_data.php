<?php
// Include database configuration
include 'config.php';

echo "<h2>Migration: Currency Rates Data Structure</h2>";

try {
    // Check if we have any records with empty cluster values that should be NULL
    echo "<h3>Step 1: Updating empty cluster values to NULL</h3>";
    
    $stmt = $conn->prepare("UPDATE currency_rates SET cluster = NULL WHERE cluster = ''");
    $stmt->execute();
    $rowsAffected = $stmt->rowCount();
    echo "Updated $rowsAffected records with empty cluster values to NULL<br>";
    
    // Check if we have any duplicate global rates that need to be consolidated
    echo "<h3>Step 2: Checking for duplicate global rates</h3>";
    
    // Get all global USD rates
    $stmt = $conn->prepare("SELECT id, rate, last_updated FROM currency_rates WHERE from_currency = 'USD' AND to_currency = 'ETB' AND (cluster IS NULL OR cluster = '') ORDER BY last_updated DESC");
    $stmt->execute();
    $usdRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($usdRates) > 1) {
        echo "Found " . count($usdRates) . " global USD rates. Consolidating to the most recent one.<br>";
        // Keep the first (most recent) one, delete the rest
        for ($i = 1; $i < count($usdRates); $i++) {
            $deleteStmt = $conn->prepare("DELETE FROM currency_rates WHERE id = ?");
            $deleteStmt->execute([$usdRates[$i]['id']]);
            echo "Deleted duplicate USD rate record with ID: " . $usdRates[$i]['id'] . "<br>";
        }
    } else {
        echo "No duplicate global USD rates found.<br>";
    }
    
    // Get all global EUR rates
    $stmt = $conn->prepare("SELECT id, rate, last_updated FROM currency_rates WHERE from_currency = 'EUR' AND to_currency = 'ETB' AND (cluster IS NULL OR cluster = '') ORDER BY last_updated DESC");
    $stmt->execute();
    $eurRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($eurRates) > 1) {
        echo "Found " . count($eurRates) . " global EUR rates. Consolidating to the most recent one.<br>";
        // Keep the first (most recent) one, delete the rest
        for ($i = 1; $i < count($eurRates); $i++) {
            $deleteStmt = $conn->prepare("DELETE FROM currency_rates WHERE id = ?");
            $deleteStmt->execute([$eurRates[$i]['id']]);
            echo "Deleted duplicate EUR rate record with ID: " . $eurRates[$i]['id'] . "<br>";
        }
    } else {
        echo "No duplicate global EUR rates found.<br>";
    }
    
    // Show final state
    echo "<h3>Step 3: Final state verification</h3>";
    
    $stmt = $conn->prepare("SELECT from_currency, to_currency, rate, cluster, last_updated FROM currency_rates ORDER BY cluster IS NULL DESC, cluster ASC, from_currency ASC");
    $stmt->execute();
    $allRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>From Currency</th><th>To Currency</th><th>Rate</th><th>Cluster</th><th>Last Updated</th></tr>";
    foreach ($allRates as $rate) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($rate['from_currency']) . "</td>";
        echo "<td>" . htmlspecialchars($rate['to_currency']) . "</td>";
        echo "<td>" . number_format($rate['rate'], 4) . "</td>";
        echo "<td>" . (empty($rate['cluster']) ? 'Global (Default)' : htmlspecialchars($rate['cluster'])) . "</td>";
        echo "<td>" . htmlspecialchars($rate['last_updated']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Migration completed successfully!</h3>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>