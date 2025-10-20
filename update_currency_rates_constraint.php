<?php
// Include database configuration
include 'config.php';

try {
    // Check if the new unique constraint exists
    $stmt = $conn->prepare("SHOW INDEX FROM currency_rates WHERE Key_name = 'unique_currency_pair_cluster'");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($result)) {
        // Drop the old unique constraint if it exists
        try {
            $sql = "ALTER TABLE currency_rates DROP INDEX unique_currency_pair";
            $conn->exec($sql);
            echo "Old unique constraint dropped.<br>";
        } catch (PDOException $e) {
            echo "Note: Old unique constraint may not have existed.<br>";
        }
        
        // Add new unique constraint that includes cluster
        $sql = "ALTER TABLE currency_rates ADD UNIQUE KEY unique_currency_pair_cluster (from_currency, to_currency, cluster)";
        $conn->exec($sql);
        echo "New unique constraint with cluster added.<br>";
    } else {
        echo "New unique constraint already exists.<br>";
    }
    
    echo "Database update completed successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>