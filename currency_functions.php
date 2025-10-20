<?php
/**
 * Currency Management Functions for Consortium Hub
 * Handles currency conversion and rate management
 */

/**
 * Get currency rates for a specific cluster (PDO version)
 * @param PDO $conn Database connection
 * @param int $clusterId Cluster ID
 * @return array|false Currency rates array or false if not found
 */
function getCurrencyRates($conn, $clusterId) {
    try {
        $stmt = $conn->prepare("SELECT from_currency, to_currency, exchange_rate FROM currency_rates WHERE cluster_id = ? AND is_active = 1 ORDER BY last_updated DESC");
        $stmt->execute([$clusterId]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $rates = [];
        foreach ($result as $row) {
            $key = $row['from_currency'] . '_to_' . $row['to_currency'];
            $rates[$key] = $row['exchange_rate'];
        }
        
        return $rates;
    } catch (Exception $e) {
        error_log("Error getting currency rates: " . $e->getMessage());
        return false;
    }
}

/**
 * Get currency rates by cluster name (PDO version)
 * @param PDO $conn Database connection
 * @param string $clusterName Cluster name
 * @return array|false Currency rates array or false if not found
 */
function getCurrencyRatesByClusterName($conn, $clusterName) {
    try {
        $stmt = $conn->prepare("SELECT cr.from_currency, cr.to_currency, cr.exchange_rate 
                               FROM currency_rates cr 
                               JOIN clusters c ON cr.cluster_id = c.id 
                               WHERE c.cluster_name = ? AND cr.is_active = 1 
                               ORDER BY cr.last_updated DESC");
        $stmt->execute([$clusterName]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $rates = [];
        foreach ($result as $row) {
            $key = $row['from_currency'] . '_to_' . $row['to_currency'];
            $rates[$key] = $row['exchange_rate'];
        }
        
        return $rates;
    } catch (Exception $e) {
        error_log("Error getting currency rates by cluster name: " . $e->getMessage());
        return false;
    }
}

/**
 * Get currency rates by cluster name (MySQLi version)
 * @param mysqli $conn Database connection
 * @param string $clusterName Cluster name
 * @return array|false Currency rates array or false if not found
 */
function getCurrencyRatesByClusterNameMySQLi($conn, $clusterName) {
    try {
        $stmt = $conn->prepare("SELECT cr.from_currency, cr.to_currency, cr.exchange_rate 
                               FROM currency_rates cr 
                               JOIN clusters c ON cr.cluster_id = c.id 
                               WHERE c.cluster_name = ? AND cr.is_active = 1 
                               ORDER BY cr.last_updated DESC");
        $stmt->bind_param("s", $clusterName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rates = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['from_currency'] . '_to_' . $row['to_currency'];
            $rates[$key] = $row['exchange_rate'];
        }
        
        $stmt->close();
        return $rates;
    } catch (Exception $e) {
        error_log("Error getting currency rates by cluster name: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert amount from one currency to another
 * @param float $amount Amount to convert
 * @param string $fromCurrency Source currency (USD, EUR, ETB)
 * @param string $toCurrency Target currency (USD, EUR, ETB)
 * @param array $rates Currency rates array
 * @return float Converted amount
 */
function convertCurrency($amount, $fromCurrency, $toCurrency, $rates) {
    if ($fromCurrency === $toCurrency) {
        return $amount;
    }
    
    // Convert to ETB first if not already
    $etbAmount = $amount;
    if ($fromCurrency === 'USD') {
        $etbAmount = $amount * ($rates['USD_to_ETB'] ?? 300.0000);
    } elseif ($fromCurrency === 'EUR') {
        $etbAmount = $amount * ($rates['EUR_to_ETB'] ?? 320.0000);
    }
    
    // Convert from ETB to target currency
    if ($toCurrency === 'USD') {
        return $etbAmount / ($rates['USD_to_ETB'] ?? 300.0000);
    } elseif ($toCurrency === 'EUR') {
        return $etbAmount / ($rates['EUR_to_ETB'] ?? 320.0000);
    } else {
        return $etbAmount; // ETB
    }
}

/**
 * Format currency amount with proper symbol and decimals
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @param int $decimals Number of decimal places
 * @return string Formatted currency string
 */
function formatCurrency($amount, $currency = 'ETB', $decimals = 2) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'ETB' => 'Br'
    ];
    
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format($amount, $decimals);
}

/**
 * Format currency amount with high precision for financial calculations
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @param int $decimals Number of decimal places (default 10 for high precision)
 * @return string Formatted currency string
 */
function formatCurrencyHighPrecision($amount, $currency = 'ETB', $decimals = 10) {
    return formatCurrency($amount, $currency, $decimals);
}

/**
 * Get all available clusters with their currency rates (PDO version)
 * @param PDO $conn Database connection
 * @return array Array of clusters with rates
 */
function getAllClustersWithRates($conn) {
    try {
        $stmt = $conn->prepare("SELECT c.id, c.cluster_name, cr.from_currency, cr.to_currency, cr.exchange_rate, cr.last_updated, u.username 
                               FROM clusters c 
                               LEFT JOIN currency_rates cr ON c.id = cr.cluster_id AND cr.is_active = 1
                               LEFT JOIN users u ON cr.updated_by = u.id 
                               WHERE c.is_active = 1 
                               ORDER BY c.cluster_name, cr.from_currency, cr.to_currency");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $clusters = [];
        foreach ($result as $row) {
            $clusterName = $row['cluster_name'];
            if (!isset($clusters[$clusterName])) {
                $clusters[$clusterName] = [
                    'id' => $row['id'],
                    'cluster_name' => $clusterName,
                    'rates' => []
                ];
            }
            
            if ($row['from_currency']) {
                $clusters[$clusterName]['rates'][] = [
                    'from_currency' => $row['from_currency'],
                    'to_currency' => $row['to_currency'],
                    'exchange_rate' => $row['exchange_rate'],
                    'last_updated' => $row['last_updated'],
                    'updated_by' => $row['username']
                ];
            }
        }
        
        return array_values($clusters);
    } catch (Exception $e) {
        error_log("Error getting clusters with rates: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's preferred currency or default to ETB
 * @param array $userSession User session data
 * @return string Currency code
 */
function getUserCurrency($userSession) {
    // Check if user has selected a currency preference
    if (isset($_SESSION['selected_currency']) && in_array($_SESSION['selected_currency'], ['USD', 'EUR', 'ETB'])) {
        return $_SESSION['selected_currency'];
    }
    
    // Default to ETB for regular users, USD for admins
    return ($userSession['role'] === 'admin') ? 'USD' : 'ETB';
}

/**
 * Check if a cluster has custom currency rates enabled
 * @param mysqli $conn Database connection
 * @param string $clusterName Cluster name
 * @return bool True if custom currency rates are enabled, false otherwise
 */
function isClusterCustomCurrencyEnabled($conn, $clusterName) {
    if (empty($clusterName)) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("SELECT custom_currency_rate FROM clusters WHERE cluster_name = ? AND is_active = 1");
        $stmt->bind_param("s", $clusterName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return (bool) $row['custom_currency_rate'];
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error checking custom currency rate flag: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert budget data array to specified currency
 * @param array $budgetData Budget data array
 * @param string $targetCurrency Target currency
 * @param array $rates Currency rates
 * @return array Converted budget data
 */
function convertBudgetData($budgetData, $targetCurrency, $rates) {
    $convertedData = $budgetData;
    
    // Fields to convert
    $amountFields = ['budget', 'actual', 'forecast', 'actual_plus_forecast'];
    
    foreach ($convertedData as &$row) {
        $originalCurrency = $row['currency'] ?? 'ETB';
        
        foreach ($amountFields as $field) {
            if (isset($row[$field]) && $row[$field] !== null) {
                $row[$field] = convertCurrency($row[$field], $originalCurrency, $targetCurrency, $rates);
            }
        }
        
        // Update currency field
        $row['currency'] = $targetCurrency;
    }
    
    return $convertedData;
}

/**
 * Get currency rates for export (with fallback to default rates)
 * @param mysqli $conn Database connection
 * @param string $clusterName Cluster name
 * @return array Currency rates
 */
function getCurrencyRatesForExport($conn, $clusterName) {
    $rates = getCurrencyRatesByClusterNameMySQLi($conn, $clusterName);
    
    // Fallback to default rates if not found
    if (!$rates) {
        $rates = [
            'USD_to_ETB' => 300.0000,
            'EUR_to_ETB' => 320.0000,
            'USD_to_EUR' => 0.9375
        ];
    }
    
    return $rates;
}

/**
 * Validate currency code
 * @param string $currency Currency code to validate
 * @return bool True if valid, false otherwise
 */
function isValidCurrency($currency) {
    return in_array(strtoupper($currency), ['USD', 'EUR', 'ETB']);
}

/**
 * Get currency display name
 * @param string $currency Currency code
 * @return string Display name
 */
function getCurrencyDisplayName($currency) {
    $names = [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'ETB' => 'Ethiopian Birr'
    ];
    
    return $names[$currency] ?? $currency;
}

/**
 * Add or update currency rate (PDO version)
 * @param PDO $conn Database connection
 * @param int $clusterId Cluster ID
 * @param string $fromCurrency Source currency
 * @param string $toCurrency Target currency
 * @param float $rate Exchange rate
 * @param int $updatedBy User ID who updated
 * @return bool Success status
 */
function addOrUpdateCurrencyRate($conn, $clusterId, $fromCurrency, $toCurrency, $rate, $updatedBy) {
    try {
        $stmt = $conn->prepare("INSERT INTO currency_rates (cluster_id, from_currency, to_currency, exchange_rate, updated_by) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               exchange_rate = VALUES(exchange_rate), 
                               updated_by = VALUES(updated_by), 
                               last_updated = CURRENT_TIMESTAMP");
        return $stmt->execute([$clusterId, $fromCurrency, $toCurrency, $rate, $updatedBy]);
    } catch (Exception $e) {
        error_log("Error adding/updating currency rate: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete currency rate (PDO version)
 * @param PDO $conn Database connection
 * @param int $rateId Rate ID to delete
 * @return bool Success status
 */
function deleteCurrencyRate($conn, $rateId) {
    try {
        $stmt = $conn->prepare("UPDATE currency_rates SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$rateId]);
    } catch (Exception $e) {
        error_log("Error deleting currency rate: " . $e->getMessage());
        return false;
    }
}
?>