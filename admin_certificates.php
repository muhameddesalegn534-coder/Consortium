<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Get filter parameters
$clusterFilter = isset($_GET['cluster']) ? $_GET['cluster'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$sql = "SELECT id, cluster_name, year, certificate_path, uploaded_date, uploaded_by FROM certificates_simple WHERE 1=1";

$params = [];
$types = "";

if (!empty($clusterFilter)) {
    $sql .= " AND cluster_name = ?";
    $params[] = $clusterFilter;
    $types .= "s";
}

if (!empty($dateFrom)) {
    $sql .= " AND DATE(uploaded_date) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if (!empty($dateTo)) {
    $sql .= " AND DATE(uploaded_date) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

$sql .= " ORDER BY uploaded_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all clusters for filter dropdown
$clustersSql = "SELECT DISTINCT cluster_name FROM certificates_simple ORDER BY cluster_name";
$clustersResult = $conn->query($clustersSql);
$clusters = [];
if ($clustersResult && $clustersResult->num_rows > 0) {
    while ($row = $clustersResult->fetch_assoc()) {
        $clusters[] = $row['cluster_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprroved Document Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_certificates_styles.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content-flex flex-1 flex-col">
        <div class="flex-1 py-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto px-4">
                <header class="admin-card p-6 mb-8">
                    <div class="header-content">
                        <div class="logo">
                            <i class="fas fa-certificate"></i>
                            Approvment Management
                        </div>
                        <div class="user-info no-print">
                            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                        </div>
                    </div>
                </header>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i> Filter document
                    </div>
                    
                    <form method="GET" id="filterForm">
                        <div class="filters">
                            <div class="form-group">
                                <label for="cluster">Cluster</label>
                                <select name="cluster" id="cluster" class="form-select">
                                    <option value="">All Clusters</option>
                                    <?php foreach ($clusters as $cluster): ?>
                                        <option value="<?php echo htmlspecialchars($cluster); ?>" <?php echo ($clusterFilter === $cluster) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cluster); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_from">From Date</label>
                                <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="date_to">To Date</label>
                                <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="form-input">
                            </div>
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="button" class="btn-outline" id="resetFilters">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Certificates Table -->
                <div class="admin-card p-6">
                    <div class="table-header">
                        <i class="fas fa-table"></i> Uploaded Certificates
                    </div>
                    
                    <div class="table-container">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Cluster</th>
                                        <th>Year</th>
                                        <th>Uploaded Date</th>
                                        <th>Uploaded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['cluster_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['year']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($row['uploaded_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
                                            <td class="action-buttons">
                                                <?php if (file_exists($row['certificate_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($row['certificate_path']); ?>" target="_blank" class="btn-view">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="<?php echo htmlspecialchars($row['certificate_path']); ?>" download class="btn-download">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-danger">File not found</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-certificates">
                                <i class="fas fa-file-alt fa-3x mb-3"></i>
                                <h3>No certificates found</h3>
                                <p>Try adjusting your filters or upload some certificates first.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('resetFilters').addEventListener('click', function() {
            document.getElementById('cluster').value = '';
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
        });
    </script>
</body>
</html>