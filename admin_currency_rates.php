<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'finance_officer') !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include database configuration
include 'config.php';

// Get all clusters for admin dropdown
$clusters = [];
try {
    $clustersQuery = "SELECT cluster_name FROM clusters WHERE is_active = 1 ORDER BY cluster_name";
    $clustersStmt = $conn->prepare($clustersQuery);
    $clustersStmt->execute();
    $clustersResult = $clustersStmt->fetchAll(PDO::FETCH_COLUMN);
    $clusters = $clustersResult;
} catch (PDOException $e) {
    // Handle error silently, continue with empty clusters array
}

// Handle form submission for updating currency rates
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rates'])) {
    try {
        $usd_rate = floatval($_POST['usd_rate']);
        $eur_rate = floatval($_POST['eur_rate']);
        $selected_cluster = !empty($_POST['cluster']) ? $_POST['cluster'] : null;
        
        // Handle USD rate
        if ($selected_cluster) {
            // Check if cluster-specific USD rate already exists
            $checkStmt = $conn->prepare("SELECT id FROM currency_rates WHERE from_currency = 'USD' AND to_currency = 'ETB' AND cluster = ?");
            $checkStmt->execute([$selected_cluster]);
            
            if ($checkStmt->fetch()) {
                // Update existing USD rate for cluster
                $stmt = $conn->prepare("UPDATE currency_rates SET rate = ? WHERE from_currency = 'USD' AND to_currency = 'ETB' AND cluster = ?");
                $stmt->execute([$usd_rate, $selected_cluster]);
            } else {
                // Insert new USD rate for cluster
                $stmt = $conn->prepare("INSERT INTO currency_rates (from_currency, to_currency, rate, cluster) VALUES ('USD', 'ETB', ?, ?)");
                $stmt->execute([$usd_rate, $selected_cluster]);
            }
        } else {
            // Handle global USD rate
            $checkStmt = $conn->prepare("SELECT id FROM currency_rates WHERE from_currency = 'USD' AND to_currency = 'ETB' AND (cluster IS NULL OR cluster = '')");
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $stmt = $conn->prepare("UPDATE currency_rates SET rate = ? WHERE from_currency = 'USD' AND to_currency = 'ETB' AND (cluster IS NULL OR cluster = '')");
                $stmt->execute([$usd_rate]);
            } else {
                $stmt = $conn->prepare("INSERT INTO currency_rates (from_currency, to_currency, rate, cluster) VALUES ('USD', 'ETB', ?, NULL)");
                $stmt->execute([$usd_rate]);
            }
        }
        
        // Handle EUR rate
        if ($selected_cluster) {
            // Check if cluster-specific EUR rate already exists
            $checkStmt = $conn->prepare("SELECT id FROM currency_rates WHERE from_currency = 'EUR' AND to_currency = 'ETB' AND cluster = ?");
            $checkStmt->execute([$selected_cluster]);
            
            if ($checkStmt->fetch()) {
                // Update existing EUR rate for cluster
                $stmt = $conn->prepare("UPDATE currency_rates SET rate = ? WHERE from_currency = 'EUR' AND to_currency = 'ETB' AND cluster = ?");
                $stmt->execute([$eur_rate, $selected_cluster]);
            } else {
                // Insert new EUR rate for cluster
                $stmt = $conn->prepare("INSERT INTO currency_rates (from_currency, to_currency, rate, cluster) VALUES ('EUR', 'ETB', ?, ?)");
                $stmt->execute([$eur_rate, $selected_cluster]);
            }
        } else {
            // Handle global EUR rate
            $checkStmt = $conn->prepare("SELECT id FROM currency_rates WHERE from_currency = 'EUR' AND to_currency = 'ETB' AND (cluster IS NULL OR cluster = '')");
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $stmt = $conn->prepare("UPDATE currency_rates SET rate = ? WHERE from_currency = 'EUR' AND to_currency = 'ETB' AND (cluster IS NULL OR cluster = '')");
                $stmt->execute([$eur_rate]);
            } else {
                $stmt = $conn->prepare("INSERT INTO currency_rates (from_currency, to_currency, rate, cluster) VALUES ('EUR', 'ETB', ?, NULL)");
                $stmt->execute([$eur_rate]);
            }
        }
        
        $message = 'Currency rates updated successfully!';
    } catch (PDOException $e) {
        $message = 'Error updating currency rates: ' . $e->getMessage();
    }
}

// Fetch current currency rates
$selected_cluster = isset($_GET['cluster']) ? $_GET['cluster'] : null;
$rates = ['USD' => 55.0000, 'EUR' => 60.0000];

try {
    if ($selected_cluster) {
        // Try to get cluster-specific rates first
        $stmt = $conn->prepare("SELECT from_currency, rate FROM currency_rates WHERE to_currency = 'ETB' AND cluster = ?");
        $stmt->execute([$selected_cluster]);
        $clusterRates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($clusterRates)) {
            $rates = array_merge($rates, $clusterRates);
        } else {
            // Fall back to global rates
            $stmt = $conn->prepare("SELECT from_currency, rate FROM currency_rates WHERE to_currency = 'ETB' AND (cluster IS NULL OR cluster = '')");
            $stmt->execute();
            $globalRates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $rates = array_merge($rates, $globalRates);
        }
    } else {
        // Get global rates
        $stmt = $conn->prepare("SELECT from_currency, rate FROM currency_rates WHERE to_currency = 'ETB' AND (cluster IS NULL OR cluster = '')");
        $stmt->execute();
        $globalRates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $rates = array_merge($rates, $globalRates);
    }
} catch (PDOException $e) {
    // Use default rates if there's an error
    $rates = ['USD' => 55.0000, 'EUR' => 60.0000];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Currency Rates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #6c757d;
            --success: #10b981;
            --light: #f8fafc;
            --dark: #1e293b;
            --border: #e2e8f0;
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: white;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-content h1 {
            color: var(--dark);
            margin: 0;
            font-size: 28px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
        }
        
        .card-header {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .currency-info {
            background: #eff6ff;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }
        
        .currency-info h3 {
            margin-top: 0;
            color: var(--primary-dark);
        }
        
        .currency-info ul {
            margin-bottom: 0;
        }
        
        .currency-info li {
            margin-bottom: 8px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-exchange-alt"></i> Manage Currency Rates</h1>
            </div>
            <div class="user-info">
                <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
            </div>
        </header>
        
        <a href="admin.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
        </a>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Current Exchange Rates
            </div>
            
            <form method="GET" id="clusterFilterForm" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="clusterFilter">
                        <i class="fas fa-building"></i> Select Cluster
                    </label>
                    <select id="clusterFilter" name="cluster" class="form-control" onchange="this.form.submit()">
                        <option value="">Global (Default for all clusters)</option>
                        <?php foreach ($clusters as $cluster): ?>
                            <option value="<?php echo htmlspecialchars($cluster); ?>" <?php echo ($selected_cluster === $cluster) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cluster); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Select "Global" to set default rates for all clusters. Select a specific cluster to set custom rates for that cluster only.</small>
                </div>
            </form>
            
            <div class="currency-info">
                <h3><i class="fas fa-info-circle"></i> How Currency Conversion Works</h3>
                <ul>
                    <li>All budget data is stored in its original currency</li>
                    <li>When displaying data, values are converted to the selected display currency using these rates</li>
                    <li>ETB (Ethiopian Birr) is the base currency</li>
                    <li>Rates are applied as: Value in ETB = Value in Source Currency × Rate</li>
                    <?php if ($selected_cluster): ?>
                    <li><strong>Currently managing rates for cluster: <?php echo htmlspecialchars($selected_cluster); ?></strong></li>
                    <li>These rates will be used only for this specific cluster</li>
                    <?php else: ?>
                    <li><strong>Currently managing global default rates</strong></li>
                    <li>These rates will be used for all clusters that don't have specific rates</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <?php
            // Show existing cluster-specific rates
            try {
                $stmt = $conn->prepare("SELECT DISTINCT cluster FROM currency_rates WHERE cluster IS NOT NULL AND cluster != ''");
                $stmt->execute();
                $clustersWithRates = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($clustersWithRates)): ?>
                <div class="currency-info">
                    <h3><i class="fas fa-list"></i> Clusters with Custom Rates</h3>
                    <p>The following clusters have custom currency rates:</p>
                    <ul>
                        <?php foreach ($clustersWithRates as $cluster): ?>
                        <li><?php echo htmlspecialchars($cluster); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p>To edit rates for a specific cluster, select it from the dropdown above.</p>
                </div>
                <?php endif;
            } catch (PDOException $e) {
                // Silently ignore errors in this informational section
            }
            ?>
            
            <form method="POST">
                <input type="hidden" name="cluster" value="<?php echo htmlspecialchars($selected_cluster ?? ''); ?>">
                <div class="form-group">
                    <label for="usd_rate">
                        <i class="fas fa-dollar-sign"></i> USD to ETB Exchange Rate
                    </label>
                    <input type="number" id="usd_rate" name="usd_rate" step="0.0001" min="0" 
                           value="<?php echo htmlspecialchars($rates['USD'] ?? '55.0000'); ?>" 
                           required>
                    <small>Current rate: 1 USD = <?php echo htmlspecialchars($rates['USD'] ?? '55.0000'); ?> ETB</small>
                </div>
                
                <div class="form-group">
                    <label for="eur_rate">
                        <i class="fas fa-euro-sign"></i> EUR to ETB Exchange Rate
                    </label>
                    <input type="number" id="eur_rate" name="eur_rate" step="0.0001" min="0" 
                           value="<?php echo htmlspecialchars($rates['EUR'] ?? '60.0000'); ?>" 
                           required>
                    <small>Current rate: 1 EUR = <?php echo htmlspecialchars($rates['EUR'] ?? '60.0000'); ?> ETB</small>
                </div>
                
                <button type="submit" name="update_rates" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Currency Rates
                </button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-question-circle"></i> Help
            </div>
            <p>To update currency rates:</p>
            <ol>
                <li>Select "Global (Default for all clusters)" to set default rates, or select a specific cluster</li>
                <li>Enter the current exchange rate for USD to ETB</li>
                <li>Enter the current exchange rate for EUR to ETB</li>
                <li>Click "Update Currency Rates" to save changes</li>
            </ol>
            <p><strong>How cluster-specific rates work:</strong></p>
            <ul>
                <li><strong>Global rates:</strong> Used as default for all clusters that don't have specific rates</li>
                <li><strong>Cluster-specific rates:</strong> Used only for the selected cluster, overriding global rates</li>
                <li>When displaying data, the system first looks for cluster-specific rates, then falls back to global rates</li>
            </ul>
            <p><strong>Note:</strong> These rates are used for display purposes only. The original currency values are preserved in the database.</p>
            <p><a href="view_all_currency_rates.php" class="btn btn-primary"><i class="fas fa-list"></i> View All Currency Rates</a></p>
        </div>
    </div>
</body>
</html>