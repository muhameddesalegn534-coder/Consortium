<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);
ini_set('error_log', 'import_error.log');

// Log the start of the script
error_log("Import script started at " . date('Y-m-d H:i:s'));

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle file upload via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    // This should be handled by ajax_handler.php, not here
    // Return an error directing user to use the proper handler
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'This endpoint is deprecated. Please use the AJAX handler instead.'
    ]);
    exit();
}

// Check if we have imported data to display
$importedData = [];
if (isset($_SESSION['imported_excel_data'])) {
    $importedData = $_SESSION['imported_excel_data'];
    // Don't unset yet, we'll unset after displaying
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Transactions from Excel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-section {
            background: linear-gradient(135deg, #ffffff 0%, #fefefe 100%);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.1);
        }
        
        .form-input {
            transition: all 0.3s;
            border: 2px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            width: 100%;
            font-size: 0.95rem;
            background: #ffffff;
            position: relative;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            background: #fefefe;
        }
        
        .form-label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            letter-spacing: 0.025em;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(107, 114, 128, 0.2);
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(107, 114, 128, 0.3);
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background-color: #10b981;
        }
        
        .toast.error {
            background-color: #ef4444;
        }
        
        .toast.info {
            background-color: #3b82f6;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .data-table th {
            background-color: #2563eb;
            color: white;
            font-weight: 600;
            padding: 12px 15px;
            text-align: left;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .data-table tr:hover {
            background-color: #f1f5f9;
        }
        
        .success-message {
            background-color: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl w-full space-y-8">
            <div class="form-section">
                <div class="text-center">
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                        Import Transactions from Excel
                    </h2>
                    <p class="text-gray-600 mb-6">
                        Upload an Excel file to import multiple transactions at once
                    </p>
                </div>
                
                <form id="importForm" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label for="excel_file" class="form-label">
                            <i class="fas fa-file-excel text-green-600 mr-2"></i>
                            Excel File
                        </label>
                        <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" class="form-input" required>
                        <p class="mt-2 text-sm text-gray-500">
                            Supported formats: .xlsx, .xls (Max size: 5MB)
                        </p>
                    </div>
                    
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                        <h3 class="font-medium text-blue-800 mb-2 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Required Excel Columns
                        </h3>
                        <ul class="text-sm text-blue-700 list-disc pl-5 space-y-1">
                            <li><strong>budget_heading</strong> - Administrative costs, Operational support costs, etc.</li>
                            <li><strong>outcome</strong> - Project outcome description</li>
                            <li><strong>activity</strong> - Activity description</li>
                            <li><strong>budget_line</strong> - Budget line description</li>
                            <li><strong>description</strong> - Transaction description</li>
                            <li><strong>partner</strong> - Partner organization name</li>
                            <li><strong>date</strong> - Transaction date (YYYY-MM-DD)</li>
                            <li><strong>amount</strong> - Transaction amount</li>
                        </ul>
                        <p class="mt-3 text-sm text-blue-700">
                            <strong>Optional columns:</strong> currency, usd_to_etb_rate, eur_to_etb_rate
                        </p>
                        <div class="mt-3 p-3 bg-amber-50 rounded-lg border border-amber-200">
                            <p class="text-sm text-amber-800">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <strong>Note:</strong> The <code>usd_to_etb_rate</code> and <code>eur_to_etb_rate</code> columns allow you to specify custom exchange rates for each transaction. If left empty, the system will use the default cluster rates.
                            </p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <a href="excel_template.xlsx" download class="inline-flex items-center text-blue-700 hover:text-blue-900 font-medium">
                                <i class="fas fa-download mr-2"></i>
                                Download Excel Template
                            </a>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" onclick="window.location.href='financial_report_section.php'" class="btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Transactions
                        </button>
                        <button type="submit" id="importButton" class="btn-primary">
                            <i class="fas fa-upload mr-2"></i>
                            Import Transactions
                        </button>
                    </div>
                </form>
                
                <!-- Display success or error messages -->
                <div id="importResult" class="mt-6"></div>
                
                <!-- Display imported data table -->
                <?php if (!empty($importedData)): ?>
                <div class="mt-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Imported Data Preview</h3>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Budget Heading</th>
                                    <th>Outcome</th>
                                    <th>Activity</th>
                                    <th>Budget Line</th>
                                    <th>Description</th>
                                    <th>Partner</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>USD to ETB Rate</th>
                                    <th>EUR to ETB Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($importedData as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['budget_heading'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['outcome'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['activity'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['budget_line'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['partner'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                                    <td><?php echo number_format($row['amount'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['currency'] ?? 'USD'); ?></td>
                                    <td><?php echo isset($row['usd_to_etb_rate']) && $row['usd_to_etb_rate'] ? number_format($row['usd_to_etb_rate'], 4) : 'N/A'; ?></td>
                                    <td><?php echo isset($row['eur_to_etb_rate']) && $row['eur_to_etb_rate'] ? number_format($row['eur_to_etb_rate'], 4) : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-center">
                        <button onclick="window.location.href='financial_report_section.php'" class="btn-primary">
                            <i class="fas fa-check mr-2"></i>
                            Confirm and View Transactions
                        </button>
                    </div>
                </div>
                <?php 
                // Clear the session data after displaying
                unset($_SESSION['imported_excel_data']);
                endif; ?>
            </div>
            
            <div class="text-center text-sm text-gray-500">
                <p>Make sure your Excel file follows the required format for successful import</p>
            </div>
        </div>
    </div>
    
    <div id="toast" class="toast"></div>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show ' + type;
            
            setTimeout(() => {
                toast.className = 'toast';
            }, 3000);
        }
        
        // Handle form submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('excel_file');
            const file = fileInput.files[0];
            
            if (!file) {
                showToast('Please select an Excel file', 'error');
                return;
            }
            
            // Show loading state
            const importButton = document.getElementById('importButton');
            const originalText = importButton.innerHTML;
            importButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Importing...';
            importButton.disabled = true;
            
            // Create FormData object
            const formData = new FormData();
            formData.append('excel_file', file);
            formData.append('action', 'import_excel');
            
            // Send AJAX request
            fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                const resultDiv = document.getElementById('importResult');
                
                if (data.success) {
                    // Show success message
                    resultDiv.innerHTML = `
                        <div class="success-message">
                            <h3 class="font-bold text-green-800 mb-2"><i class="fas fa-check-circle mr-2"></i>Import Successful!</h3>
                            <p class="text-green-700">${data.message}</p>
                            ${data.errors && data.errors.length > 0 ? 
                                `<div class="mt-3 p-3 bg-amber-100 rounded">
                                    <p class="font-medium text-amber-800">Warnings:</p>
                                    <ul class="list-disc pl-5 mt-1 text-amber-700">
                                        ${data.errors.map(error => `<li>${error}</li>`).join('')}
                                    </ul>
                                </div>` : ''
                            }
                        </div>
                    `;
                    
                    showToast(data.message, 'success');
                    
                    // If we have imported data, reload the page to show the table
                    if (data.imported_data && data.imported_data.length > 0) {
                        // Reload the page to display the imported data table
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        // Reset form
                        document.getElementById('importForm').reset();
                    }
                } else {
                    // Show error message
                    resultDiv.innerHTML = `
                        <div class="error-message">
                            <h3 class="font-bold text-red-800 mb-2"><i class="fas fa-exclamation-circle mr-2"></i>Import Failed</h3>
                            <p class="text-red-700">${data.message}</p>
                            ${data.debug ? `<p class="text-red-600 text-sm mt-2">Debug: ${data.debug}</p>` : ''}
                        </div>
                    `;
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const resultDiv = document.getElementById('importResult');
                resultDiv.innerHTML = `
                    <div class="error-message">
                        <h3 class="font-bold text-red-800 mb-2"><i class="fas fa-exclamation-circle mr-2"></i>Import Failed</h3>
                        <p class="text-red-700">An error occurred during import: ${error.message}. Please check your file format and try again.</p>
                    </div>
                `;
                showToast('An error occurred during import: ' + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                importButton.innerHTML = originalText;
                importButton.disabled = false;
            });
        });
    </script>
</body>
</html>