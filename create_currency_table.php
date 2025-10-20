<?php
// Include database configuration
include 'config.php';

try {
    // Create currency_rates table
    $sql = "CREATE TABLE IF NOT EXISTS currency_rates (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        from_currency VARCHAR(3) NOT NULL,
        to_currency VARCHAR(3) NOT NULL,
        rate DECIMAL(10, 4) NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_currency_pair (from_currency, to_currency)
    )";
    
    $conn->exec($sql);
    echo "Currency rates table created successfully.<br>";
    
    // Add currency column to budget_data table
    $sql = "ALTER TABLE budget_data ADD COLUMN currency VARCHAR(3) DEFAULT 'ETB' AFTER year2";
    $conn->exec($sql);
    echo "Currency column added to budget_data table.<br>";
    
    // Insert default currency rates (ETB as base)
    $stmt = $conn->prepare("INSERT IGNORE INTO currency_rates (from_currency, to_currency, rate) VALUES (?, ?, ?)");
    
    // USD to ETB (example rate: 1 USD = 55 ETB)
    $stmt->execute(['USD', 'ETB', 55.0000]);
    
    // EUR to ETB (example rate: 1 EUR = 60 ETB)
    $stmt->execute(['EUR', 'ETB', 60.0000]);
    
    // ETB to ETB (1:1)
    $stmt->execute(['ETB', 'ETB', 1.0000]);
    
    echo "Default currency rates inserted.<br>";
    
    // Create a view for converted budget data
    $sql = "CREATE OR REPLACE VIEW budget_data_converted AS
    SELECT 
        bd.*,
        CASE 
            WHEN bd.currency = 'USD' THEN bd.budget * (SELECT rate FROM currency_rates WHERE from_currency = 'USD' AND to_currency = 'ETB')
            WHEN bd.currency = 'EUR' THEN bd.budget * (SELECT rate FROM currency_rates WHERE from_currency = 'EUR' AND to_currency = 'ETB')
            ELSE bd.budget
        END AS budget_etb,
        CASE 
            WHEN bd.currency = 'USD' THEN bd.actual * (SELECT rate FROM currency_rates WHERE from_currency = 'USD' AND to_currency = 'ETB')
            WHEN bd.currency = 'EUR' THEN bd.actual * (SELECT rate FROM currency_rates WHERE from_currency = 'EUR' AND to_currency = 'ETB')
            ELSE bd.actual
        END AS actual_etb,
        CASE 
            WHEN bd.currency = 'USD' THEN bd.forecast * (SELECT rate FROM currency_rates WHERE from_currency = 'USD' AND to_currency = 'ETB')
            WHEN bd.currency = 'EUR' THEN bd.forecast * (SELECT rate FROM currency_rates WHERE from_currency = 'EUR' AND to_currency = 'ETB')
            ELSE bd.forecast
        END AS forecast_etb,
        CASE 
            WHEN bd.currency = 'USD' THEN bd.actual_plus_forecast * (SELECT rate FROM currency_rates WHERE from_currency = 'USD' AND to_currency = 'ETB')
            WHEN bd.currency = 'EUR' THEN bd.actual_plus_forecast * (SELECT rate FROM currency_rates WHERE from_currency = 'EUR' AND to_currency = 'ETB')
            ELSE bd.actual_plus_forecast
        END AS actual_plus_forecast_etb
    FROM budget_data bd";
    
    $conn->exec($sql);
    echo "Converted budget data view created.<br>";
    
    echo "All database modifications completed successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>