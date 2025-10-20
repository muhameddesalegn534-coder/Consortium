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

// Get all currency rates
$rates = [];
try {
    $stmt = $conn->prepare("SELECT from_currency, to_currency, rate, cluster, last_updated FROM currency_rates ORDER BY cluster IS NULL DESC, cluster ASC, from_currency ASC");
    $stmt->execute();
    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching currency rates: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Currency Rates</title>
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
            text-decoration: none;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: var(--dark);
        }
        
        tr:hover {
            background-color: #f8fafc;
        }
        
        .global-rates {
            background-color: #dbeafe;
        }
        
        .cluster-rates {
            background-color: #d1fae5;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-exchange-alt"></i> All Currency Rates</h1>
            </div>
            <div class="user-info">
                <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
            </div>
        </header>
        
        <a href="admin_currency_rates.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Manage Currency Rates
        </a>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i> Currency Rates Overview
            </div>
            
            <?php if (empty($rates)): ?>
                <p>No currency rates found in the database.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>From Currency</th>
                            <th>To Currency</th>
                            <th>Rate</th>
                            <th>Cluster</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rates as $rate): ?>
                        <tr class="<?php echo empty($rate['cluster']) ? 'global-rates' : 'cluster-rates'; ?>">
                            <td><?php echo htmlspecialchars($rate['from_currency']); ?></td>
                            <td><?php echo htmlspecialchars($rate['to_currency']); ?></td>
                            <td><?php echo number_format($rate['rate'], 4); ?></td>
                            <td><?php echo empty($rate['cluster']) ? 'Global (Default)' : htmlspecialchars($rate['cluster']); ?></td>
                            <td><?php echo htmlspecialchars($rate['last_updated']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-question-circle"></i> Help
            </div>
            <p><strong>Global (Default) rates:</strong> Used for all clusters that don't have specific rates.</p>
            <p><strong>Cluster-specific rates:</strong> Override global rates for the specified cluster.</p>
            <p>When displaying data, the system first looks for cluster-specific rates, then falls back to global rates.</p>
        </div>
    </div>
</body>
</html>