<?php 
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'header.php';

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Get filter parameters
$filter_cluster = isset($_GET['cluster']) ? $_GET['cluster'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$sql = "SELECT * FROM project_documents WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_cluster)) {
    $sql .= " AND cluster = ?";
    $params[] = $filter_cluster;
    $types .= "s";
}

if (!empty($filter_type)) {
    switch ($filter_type) {
        case 'documents':
            $sql .= " AND (document_file_names IS NOT NULL AND document_file_names != '[]' AND document_file_names != '')";
            break;
        case 'images':
            $sql .= " AND (image_file_names IS NOT NULL AND image_file_names != '[]' AND image_file_names != '')";
            break;
        case 'success_stories':
            $sql .= " AND (success_title IS NOT NULL AND success_title != '')";
            break;
        case 'challenges':
            $sql .= " AND (challenge_title IS NOT NULL AND challenge_title != '')";
            break;
        case 'progress':
            $sql .= " AND (progress_title IS NOT NULL AND progress_title != '')";
            break;
        case 'financial':
            $sql .= " AND (document_type = 'financial_report')";
            break;
    }
}

if (!empty($filter_date_from)) {
    $sql .= " AND uploaded_at >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $sql .= " AND uploaded_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
    $types .= "s";
}

if (!empty($filter_search)) {
    $sql .= " AND (cluster LIKE ? OR document_type LIKE ? OR progress_title LIKE ? OR challenge_title LIKE ? OR success_title LIKE ? OR uploaded_by LIKE ? OR custom_document_name LIKE ?)";
    $searchParam = "%$filter_search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sssssss";
}

$sql .= " ORDER BY uploaded_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all clusters for filter dropdown
$clusterSql = "SELECT DISTINCT cluster FROM project_documents WHERE cluster IS NOT NULL AND cluster != '' ORDER BY cluster";
$clusterResult = $conn->query($clusterSql);
$clusters = [];
if ($clusterResult && $clusterResult->num_rows > 0) {
    while ($row = $clusterResult->fetch_assoc()) {
        $clusters[] = $row['cluster'];
    }
}

$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Project Documents | Consortium Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap');

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --accent: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --mid-text: #64748b;
            --border: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e0e7ff 100%);
            color: var(--dark-text);
            min-height: 100vh;
            margin: 0;
        }

        .heading-font {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .main-content-flex {
            display: flex;
            justify-content: center;
            padding: 2rem 1rem;
            min-height: calc(100vh - 80px);
        }

        .content-container {
            width: 100%;
            max-width: 1400px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 1.75rem;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-hover {
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
        }

        .btn-primary {
            background: linear-gradient(to right, #4361ee, #3a56d4);
            color: white;
            border: none;
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.35);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(107, 114, 128, 0.3);
        }

        .form-input, .form-select {
            transition: all 0.3s ease;
            border: 2px solid var(--border);
            border-radius: 1rem;
            padding: 0.75rem 1.25rem;
            width: 100%;
            font-size: 0.95rem;
            background: white;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
            background: #fff;
        }

        .section-title {
            position: relative;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60%;
            height: 4px;
            background: linear-gradient(to right, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .section-icon {
            width: 2.75rem;
            height: 2.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dbeafe, #a5b4fc);
            border-radius: 1rem;
            color: #2563eb;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15);
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background: white;
        }

        .data-table th {
            background: #f8fafc;
            padding: 1.25rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #334155;
            border-bottom: 1px solid var(--border);
        }

        .data-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            transition: background 0.2s;
        }

        .data-table tr:hover td {
            background: #f1f5f9;
        }

        .badge {
            padding: 0.4rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-primary { background: #eff6ff; color: #1d4ed8; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-financial { background: #ecfeff; color: #0891b2; }

        .filter-container {
            background: white;
            border-radius: 1.5rem;
            padding: 1.75rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .document-preview {
            max-width: 80px;
            max-height: 80px;
            border-radius: 0.5rem;
            object-fit: cover;
            border: 1px solid var(--border);
            transition: transform 0.2s;
        }

        .document-preview:hover {
            transform: scale(1.05);
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal.open {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 1.5rem;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .modal.open .modal-content {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 1.75rem;
            border-bottom: 1px solid var(--border);
            background: #f8fafc;
            border-radius: 1.5rem 1.5rem 0 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 2rem;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--mid-text);
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .data-table th, .data-table td {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
            .btn-primary, .btn-secondary {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

    <div class="main-content-flex">
        <div class="content-container">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold heading-font text-gray-900">📄 Project Documents</h1>
                    <p class="mt-2 text-gray-600">Manage and review all uploaded reports, images, and stories from clusters.</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <button onclick="window.location.reload()" class="btn-secondary">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-container card-hover">
                <h2 class="section-title mb-5">
                    <span class="section-icon">
                        <i class="fas fa-filter"></i>
                    </span>
                    Filter Documents
                </h2>

                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Cluster</label>
                            <select name="cluster" class="form-select">
                                <option value="">All Clusters</option>
                                <?php foreach ($clusters as $cluster): ?>
                                    <option value="<?= htmlspecialchars($cluster) ?>" <?= $filter_cluster === $cluster ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cluster) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <option value="documents" <?= $filter_type === 'documents' ? 'selected' : '' ?>>Documents</option>
                                <option value="images" <?= $filter_type === 'images' ? 'selected' : '' ?>>Images</option>
                                <option value="success_stories" <?= $filter_type === 'success_stories' ? 'selected' : '' ?>>Success Stories</option>
                                <option value="challenges" <?= $filter_type === 'challenges' ? 'selected' : '' ?>>Challenges</option>
                                <option value="progress" <?= $filter_type === 'progress' ? 'selected' : '' ?>>Progress Reports</option>
                                <option value="financial" <?= $filter_type === 'financial' ? 'selected' : '' ?>>Financial Reports</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">From</label>
                            <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($filter_date_from) ?>">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">To</label>
                            <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($filter_date_to) ?>">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                            <div class="relative">
                                <input type="text" name="search" class="form-input pl-12" placeholder="   Search by cluster, title, or uploader..." value="<?= htmlspecialchars($filter_search) ?>">
                                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" onclick="clearFilters()" class="btn-secondary px-4 py-2">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <button type="submit" class="btn-primary px-5 py-2">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Section -->
            <div class="glass-card p-6 rounded-2xl card-hover">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                    <h2 class="section-title">
                        <span class="section-icon">
                            <i class="fas fa-archive"></i>
                        </span>
                        Uploaded Documents
                        <span class="text-gray-500 font-normal ml-2">(<?= $result->num_rows ?>)</span>
                    </h2>
                </div>

                <?php if ($result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cluster</th>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Files</th>
                                    <th>Uploader</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="font-semibold text-primary"><?= $row['id'] ?></td>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($row['cluster']) ?></span></td>
                                        <td>
                                            <?php
                                            if ($row['document_type'] === 'financial_report') {
                                                echo '<span class="badge badge-financial"><i class="fas fa-money-bill-wave mr-1"></i> Financial</span>';
                                            } elseif (!empty($row['progress_title'])) {
                                                echo '<span class="badge badge-primary"><i class="fas fa-chart-line mr-1"></i> Progress</span>';
                                            } elseif (!empty($row['challenge_title'])) {
                                                echo '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle mr-1"></i> Challenge</span>';
                                            } elseif (!empty($row['success_title'])) {
                                                echo '<span class="badge badge-success"><i class="fas fa-star mr-1"></i> Success</span>';
                                            } elseif (!empty($row['document_file_names']) && $row['document_file_names'] !== '[]') {
                                                echo '<span class="badge badge-warning"><i class="fas fa-file-pdf mr-1"></i> Document</span>';
                                            } elseif (!empty($row['image_file_names']) && $row['image_file_names'] !== '[]') {
                                                echo '<span class="badge badge-success"><i class="fas fa-image mr-1"></i> Image</span>';
                                            } else {
                                                echo '<span class="badge badge-info">Other</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($row['custom_document_name'])) {
                                                echo htmlspecialchars($row['custom_document_name']);
                                            } elseif (!empty($row['progress_title'])) {
                                                echo htmlspecialchars($row['progress_title']);
                                            } elseif (!empty($row['challenge_title'])) {
                                                echo htmlspecialchars($row['challenge_title']);
                                            } elseif (!empty($row['success_title'])) {
                                                echo htmlspecialchars($row['success_title']);
                                            } elseif (!empty($row['other_title'])) {
                                                echo htmlspecialchars($row['other_title']);
                                            } else {
                                                echo 'Document #' . $row['id'];
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $fileCount = 0;
                                            if (!empty($row['document_file_names']) && $row['document_file_names'] !== '[]' && $row['document_file_names'] !== 'null') {
                                                $fileCount += count(json_decode($row['document_file_names'], true));
                                            }
                                            if (!empty($row['image_file_names']) && $row['image_file_names'] !== '[]' && $row['image_file_names'] !== 'null') {
                                                $fileCount += count(json_decode($row['image_file_names'], true));
                                            }
                                            if (!empty($row['financial_report_file_names']) && $row['financial_report_file_names'] !== '[]' && $row['financial_report_file_names'] !== 'null') {
                                                $fileCount += count(json_decode($row['financial_report_file_names'], true));
                                            }
                                            if (!empty($row['results_framework_file_names']) && $row['results_framework_file_names'] !== '[]' && $row['results_framework_file_names'] !== 'null') {
                                                $fileCount += count(json_decode($row['results_framework_file_names'], true));
                                            }
                                            if (!empty($row['risk_matrix_file_names']) && $row['risk_matrix_file_names'] !== '[]' && $row['risk_matrix_file_names'] !== 'null') {
                                                $fileCount += count(json_decode($row['risk_matrix_file_names'], true));
                                            }
                                            if (!empty($row['spotlight_photo_file_names']) && $row['spotlight_photo_file_names'] !== '[]' && $row['spotlight_photo_file_names'] !== 'null') {
                                                $fileCount += count(json_decode($row['spotlight_photo_file_names'], true));
                                            }
                                            if (!empty($row['other_file_names']) && $row['other_file_names'] !== '[]' && $row['other_file_names'] !== 'null') {
                                                $fileCount += count(json_decode($row['other_file_names'], true));
                                            }
                                            echo $fileCount > 0 ? "<i class='fas fa-paperclip text-gray-500'></i> $fileCount file(s)" : "<span class='text-gray-400'>None</span>";
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['uploaded_by']) ?></td>
                                        <td><?= date('M j, Y', strtotime($row['uploaded_at'])) ?></td>
                                        <td>
                                            <button onclick="viewDocument(<?= $row['id'] ?>)" class="btn-primary text-sm px-3 py-1.5">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16">
                        <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Documents Found</h3>
                        <p class="text-gray-500">
                            <?php if (empty(array_filter([$filter_cluster, $filter_type, $filter_date_from, $filter_date_to, $filter_search]))) : ?>
                                No documents have been uploaded yet.
                            <?php else : ?>
                                Try adjusting your filters to see more results.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Document Detail Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content w-full max-w-4xl">
            <div class="modal-header">
                <h3 class="text-xl font-bold">Document Details</h3>
                <button onclick="closeModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="loading">Loading document details...</div>
            </div>
        </div>
    </div>

    <script>
        function clearFilters() {
            document.querySelectorAll('select, input').forEach(el => {
                if (el.type === 'text' || el.type === 'date') el.value = '';
                else if (el.tagName === 'SELECT') el.selectedIndex = 0;
            });
        }

        function viewDocument(id) {
            const modal = document.getElementById('documentModal');
            const content = document.querySelector('#documentModal .modal-body');
            content.innerHTML = '<div class="loading">Fetching details...</div>';
            modal.classList.add('open');

            // Add a timestamp to prevent caching
            fetch('admin_document_detail.php?id=' + id + '&t=' + new Date().getTime())
                .then(res => res.text())
                .then(html => {
                    content.innerHTML = html;
                    // Add event listeners for download links
                    const downloadLinks = content.querySelectorAll('a[href*="download_file=1"], a[href*="download_all=1"]');
                    downloadLinks.forEach(link => {
                        // Update the href to point to the correct file
                        const href = link.getAttribute('href');
                        if (href) {
                            // If it's a relative link or starts with ?, make it absolute
                            if (href.startsWith('?')) {
                                link.setAttribute('href', 'admin_document_detail.php' + href);
                            } else if (!href.startsWith('admin_document_detail.php') && !href.startsWith('http')) {
                                link.setAttribute('href', 'admin_document_detail.php?' + href);
                            }
                            // Ensure the link opens in a new tab for downloads
                            link.setAttribute('target', '_blank');
                        }
                        // Prevent default behavior and stop propagation
                        link.addEventListener('click', function(e) {
                            e.stopPropagation();
                        });
                    });
                })
                .catch(err => {
                    content.innerHTML = '<p class="text-red-500">Failed to load document details.</p>';
                    console.error(err);
                });
        }

        function closeModal() {
            const modal = document.getElementById('documentModal');
            modal.classList.remove('open');
            
            // Clear modal content after closing animation
            setTimeout(() => {
                if (!modal.classList.contains('open')) {
                    document.querySelector('#documentModal .modal-body').innerHTML = '<div class="loading">Loading document details...</div>';
                }
            }, 300);
        }

        // Close modal on click outside or escape key
        document.getElementById('documentModal').addEventListener('click', e => {
            if (e.target === document.getElementById('documentModal')) closeModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>