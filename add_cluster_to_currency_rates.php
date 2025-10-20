<?php
// Include database configuration
include 'config.php';

try {
    // Check if cluster column exists in currency_rates table
    $stmt = $conn->prepare("SHOW COLUMNS FROM currency_rates LIKE 'cluster'");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($result)) {
        // Add cluster column to currency_rates table
        $sql = "ALTER TABLE currency_rates ADD COLUMN cluster VARCHAR(500) DEFAULT NULL";
        $conn->exec($sql);
        echo "Cluster column added to currency_rates table.<br>";
        
        // Add index for cluster column for better performance
        $sql = "ALTER TABLE currency_rates ADD INDEX idx_cluster (cluster)";
        $conn->exec($sql);
        echo "Index added for cluster column.<br>";
        
        // Update unique constraint to include cluster
        $sql = "ALTER TABLE currency_rates DROP INDEX unique_currency_pair";
        $conn->exec($sql);
        echo "Old unique constraint dropped.<br>";
        
        // Add new unique constraint that includes cluster
        $sql = "ALTER TABLE currency_rates ADD UNIQUE KEY unique_currency_pair_cluster (from_currency, to_currency, cluster)";
        $conn->exec($sql);
        echo "New unique constraint with cluster added.<br>";
    } else {
        echo "Cluster column already exists in currency_rates table.<br>";
    }
    
    echo "Database update completed successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>