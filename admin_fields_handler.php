<?php
session_start();

// Check if user is logged in and is admin
// Only require login; restrict write actions to admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include Composer autoloader for PhpSpreadsheet
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$is_admin = $_SESSION['role'] === 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'get_field_config':
        case 'get_fields':
            // Allow all authenticated users to read
            break;

        // Only allow admins for write operations
        case 'toggle_field':
        case 'toggle_type':
        case 'add_value':
        case 'remove_value':
        case 'add_budget_data':
        case 'import_budget_data_excel':
        case 'edit_budget_data':
        case 'delete_budget_data':
        case 'set_acceptance':
        case 'add_budget_preview':
        case 'edit_budget_preview':
        case 'delete_budget_preview':
            if (!$is_admin) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit();
            }
            // ... proceed with action ...
            break;
    }
}
// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Handle GET requests for fetching records
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_budget_data':
            // Get record ID
            $id = $_GET['id'] ?? 0;
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'Record ID is required']);
                exit();
            }
            
            // Fetch budget data record
            $query = "SELECT * FROM budget_data WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $record = $result->fetch_assoc();
                echo json_encode(['success' => true, 'record' => $record]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
            exit();
            
        case 'get_budget_preview':
            // Get record ID
            $preview_id = $_GET['id'] ?? 0;
            
            if (empty($preview_id)) {
                echo json_encode(['success' => false, 'message' => 'Record ID is required']);
                exit();
            }
            
            // Fetch budget preview record
            $query = "SELECT * FROM budget_preview WHERE PreviewID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $preview_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $record = $result->fetch_assoc();
                echo json_encode(['success' => true, 'record' => $record]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
            exit();
            
        case 'delete_budget_data':
            // Get record ID
            $id = $_GET['id'] ?? 0;
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'Record ID is required']);
                exit();
            }
            
            // Delete budget data record
            $deleteQuery = "DELETE FROM budget_data WHERE id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Budget data record deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting budget data record: ' . $conn->error]);
            }
            exit();
    }
}

// Handle POST requests for predefined fields management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'get_field_config':
            try {
                $field_name = $_POST['field_name'] ?? '';
                $cluster_name = $_POST['cluster_name'] ?? '';
                
                error_log("get_field_config called with field_name: $field_name, cluster_name: $cluster_name");
                
                if (empty($field_name)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Field name is required']);
                    exit();
                }
                
                // First, try to get field config for specific cluster
                if (!empty($cluster_name)) {
                    error_log("Looking for cluster-specific config for $field_name in cluster $cluster_name");
                    $query = "SELECT * FROM predefined_fields WHERE field_name = ? AND cluster_name = ? AND is_active = 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $field_name, $cluster_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        $field = $result->fetch_assoc();
                        error_log("Found cluster-specific config for $field_name: " . json_encode($field));
                        // Add values array for easier frontend processing
                        if (!empty($field['field_values'])) {
                            $values = explode(',', $field['field_values']);
                            // Trim whitespace from each value
                            $field['values_array'] = array_map('trim', $values);
                        } else {
                            $field['values_array'] = [];
                        }
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'field' => $field]);
                        exit();
                    } else {
                        error_log("No cluster-specific config found for $field_name in cluster $cluster_name");
                    }
                }
                
                // If no cluster-specific config found or no cluster specified, get global config
                error_log("Looking for global config for $field_name");
                $query = "SELECT * FROM predefined_fields WHERE field_name = ? AND cluster_name IS NULL AND is_active = 1";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $field_name);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $field = $result->fetch_assoc();
                    error_log("Found global config for $field_name: " . json_encode($field));
                    // Add values array for easier frontend processing
                    if (!empty($field['field_values'])) {
                        $values = explode(',', $field['field_values']);
                        // Trim whitespace from each value
                        $field['values_array'] = array_map('trim', $values);
                    } else {
                        $field['values_array'] = [];
                    }
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'field' => $field]);
                } else {
                    error_log("No global config found for $field_name");
                    // Field not configured, return default config
                    $default_field = [
                        'field_name' => $field_name,
                        'field_type' => 'input',
                        'field_values' => '',
                        'is_active' => 1,
                        'cluster_name' => null,
                        'values_array' => []
                    ];
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'field' => $default_field]);
                }
            } catch (Exception $e) {
                error_log("Error in get_field_config: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error loading field config: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_fields':
            try {
                // Get cluster name if provided
                $cluster_name = $_POST['cluster_name'] ?? '';
                
                if ($cluster_name === 'all' || empty($cluster_name)) {
                    // Get all fields (global and cluster-specific)
                    $query = "SELECT * FROM predefined_fields ORDER BY field_name, cluster_name IS NULL, cluster_name";
                    $result = $conn->query($query);
                } else {
                    // Get fields for specific cluster plus global fields
                    $query = "SELECT * FROM predefined_fields WHERE cluster_name = ? OR cluster_name IS NULL ORDER BY field_name, cluster_name IS NULL, cluster_name";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $cluster_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                }
                
                $fields = [];
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $fields[] = $row;
                    }
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'fields' => $fields]);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error loading fields: ' . $e->getMessage()]);
            }
            exit();
            
        case 'toggle_field':
            try {
                $field_name = $_POST['field_name'] ?? '';
                $is_active = $_POST['is_active'] ?? 0;
                $cluster_name = $_POST['cluster_name'] ?? '';
                
                if (empty($field_name)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Field name is required']);
                    exit();
                }
                
                // Check if field exists for this cluster
                if (!empty($cluster_name)) {
                    $query = "SELECT id FROM predefined_fields WHERE field_name = ? AND cluster_name = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $field_name, $cluster_name);
                } else {
                    $query = "SELECT id FROM predefined_fields WHERE field_name = ? AND cluster_name IS NULL";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $field_name);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing field
                    if (!empty($cluster_name)) {
                        $query = "UPDATE predefined_fields SET is_active = ? WHERE field_name = ? AND cluster_name = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("iss", $is_active, $field_name, $cluster_name);
                    } else {
                        $query = "UPDATE predefined_fields SET is_active = ? WHERE field_name = ? AND cluster_name IS NULL";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("is", $is_active, $field_name);
                    }
                } else {
                    // Create new field for this cluster
                    $query = "INSERT INTO predefined_fields (field_name, field_type, is_active, cluster_name) VALUES (?, 'dropdown', ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sis", $field_name, $is_active, $cluster_name);
                }
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Field updated successfully']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error updating field: ' . $conn->error]);
                }
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error toggling field: ' . $e->getMessage()]);
            }
            exit();
            
        case 'toggle_type':
            try {
                $field_name = $_POST['field_name'] ?? '';
                $field_type = $_POST['field_type'] ?? 'dropdown';
                $cluster_name = $_POST['cluster_name'] ?? '';
                
                if (empty($field_name)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Field name is required']);
                    exit();
                }
                
                // Check if field exists for this cluster
                if (!empty($cluster_name)) {
                    $query = "SELECT id FROM predefined_fields WHERE field_name = ? AND cluster_name = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $field_name, $cluster_name);
                } else {
                    $query = "SELECT id FROM predefined_fields WHERE field_name = ? AND cluster_name IS NULL";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $field_name);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing field
                    if (!empty($cluster_name)) {
                        $query = "UPDATE predefined_fields SET field_type = ? WHERE field_name = ? AND cluster_name = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("sss", $field_type, $field_name, $cluster_name);
                    } else {
                        $query = "UPDATE predefined_fields SET field_type = ? WHERE field_name = ? AND cluster_name IS NULL";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("ss", $field_type, $field_name);
                    }
                } else {
                    // Create new field for this cluster
                    $query = "INSERT INTO predefined_fields (field_name, field_type, is_active, cluster_name) VALUES (?, ?, 1, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sss", $field_name, $field_type, $cluster_name);
                }
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Field type updated successfully']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error updating field type: ' . $conn->error]);
                }
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error toggling field type: ' . $e->getMessage()]);
            }
            exit();
            
        case 'add_value':
            try {
                $field_name = $_POST['field_name'] ?? '';
                $value = $_POST['value'] ?? '';
                $cluster_name = $_POST['cluster_name'] ?? '';
                
                if (empty($field_name)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Field name is required']);
                    exit();
                }
                
                // Get current field values
                if (!empty($cluster_name)) {
                    $query = "SELECT id, field_type, field_values FROM predefined_fields WHERE field_name = ? AND cluster_name = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $field_name, $cluster_name);
                } else {
                    $query = "SELECT id, field_type, field_values FROM predefined_fields WHERE field_name = ? AND cluster_name IS NULL";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $field_name);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $field = $result->fetch_assoc();
                    $field_type = $field['field_type'];
                    $current_values = $field['field_values'];
                    
                    if ($field_type === 'dropdown') {
                        // For dropdown fields, add the new value to the comma-separated list
                        $values = $current_values ? explode(',', $current_values) : [];
                        if (!in_array($value, $values)) {
                            $values[] = $value;
                        }
                        $new_values = implode(',', $values);
                        
                        // Update field values
                        if (!empty($cluster_name)) {
                            $query = "UPDATE predefined_fields SET field_values = ? WHERE field_name = ? AND cluster_name = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("sss", $new_values, $field_name, $cluster_name);
                        } else {
                            $query = "UPDATE predefined_fields SET field_values = ? WHERE field_name = ? AND cluster_name IS NULL";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ss", $new_values, $field_name);
                        }
                    } else {
                        // For input fields, set the predefined text
                        if (!empty($cluster_name)) {
                            $query = "UPDATE predefined_fields SET field_values = ? WHERE field_name = ? AND cluster_name = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("sss", $value, $field_name, $cluster_name);
                        } else {
                            $query = "UPDATE predefined_fields SET field_values = ? WHERE field_name = ? AND cluster_name IS NULL";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ss", $value, $field_name);
                        }
                    }
                    
                    if ($stmt->execute()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Value updated successfully']);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error updating value: ' . $conn->error]);
                    }
                } else {
                    // Field doesn't exist, create it
                    $field_type = 'dropdown'; // Default to dropdown
                    $field_values = $value;
                    
                    $query = "INSERT INTO predefined_fields (field_name, field_type, field_values, is_active, cluster_name) VALUES (?, ?, ?, 1, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssss", $field_name, $field_type, $field_values, $cluster_name);
                    
                    if ($stmt->execute()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Field and value created successfully']);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error creating field: ' . $conn->error]);
                    }
                }
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error adding value: ' . $e->getMessage()]);
            }
            exit();
            
        case 'remove_value':
            try {
                $field_name = $_POST['field_name'] ?? '';
                $value = $_POST['value'] ?? '';
                $cluster_name = $_POST['cluster_name'] ?? '';
                
                if (empty($field_name)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Field name is required']);
                    exit();
                }
                
                // Get current field values
                if (!empty($cluster_name)) {
                    $query = "SELECT id, field_type, field_values FROM predefined_fields WHERE field_name = ? AND cluster_name = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $field_name, $cluster_name);
                } else {
                    $query = "SELECT id, field_type, field_values FROM predefined_fields WHERE field_name = ? AND cluster_name IS NULL";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $field_name);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $field = $result->fetch_assoc();
                    $field_type = $field['field_type'];
                    $current_values = $field['field_values'];
                    
                    if ($field_type === 'dropdown' && !empty($value)) {
                        // For dropdown fields, remove the specified value from the comma-separated list
                        $values = $current_values ? explode(',', $current_values) : [];
                        $values = array_diff($values, [$value]);
                        $new_values = implode(',', $values);
                        
                        // Update field values
                        if (!empty($cluster_name)) {
                            $query = "UPDATE predefined_fields SET field_values = ? WHERE field_name = ? AND cluster_name = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("sss", $new_values, $field_name, $cluster_name);
                        } else {
                            $query = "UPDATE predefined_fields SET field_values = ? WHERE field_name = ? AND cluster_name IS NULL";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ss", $new_values, $field_name);
                        }
                    } else {
                        // For input fields or when clearing all values, set field_values to NULL or empty
                        if (!empty($cluster_name)) {
                            $query = "UPDATE predefined_fields SET field_values = NULL WHERE field_name = ? AND cluster_name = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ss", $field_name, $cluster_name);
                        } else {
                            $query = "UPDATE predefined_fields SET field_values = NULL WHERE field_name = ? AND cluster_name IS NULL";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("s", $field_name);
                        }
                    }
                    
                    if ($stmt->execute()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Value removed successfully']);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error removing value: ' . $conn->error]);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Field not found']);
                }
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error removing value: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Handle form submissions for budget management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_budget_data':
                // Get form data
                $year = $_POST['year'] ?? '';
                $category_name = $_POST['category_name'] ?? '';
                $period_name = $_POST['period_name'] ?? '';
            $budget = $_POST['budget'] ?? null;
            // New: optional actual and forecast inputs from Add modal
            $actual = $_POST['actual'] ?? 0;
            $forecast = $_POST['forecast'] ?? 0;
                $cluster = $_POST['cluster'] ?? '';
                $quarter_number = $_POST['quarter_number'] ?? null;
                $start_date = $_POST['start_date'] ?? null;
                $end_date = $_POST['end_date'] ?? null;
                $year2 = $_POST['year2'] ?? date('Y');
                $currency = $_POST['currency'] ?? 'ETB';
                
                // Validate input
                if (empty($year) || empty($category_name) || empty($period_name) || empty($cluster) || empty($start_date) || empty($end_date)) {
                    $_SESSION['error_message'] = "All fields are required";
                    header("Location: admin_budget_management.php#budget-data-tab");
                    exit();
                }
                
            // Normalize numeric values
            $budgetVal = is_numeric($budget) ? (float)$budget : 0.0;
            $actualVal = is_numeric($actual) ? (float)$actual : 0.0;
            $forecastVal = is_numeric($forecast) ? (float)$forecast : 0.0;
            // Auto-calculate computed fields
            // Variance (%) = (Budget − Actual) / Budget × 100
            $actualPlusForecast = $actualVal + $forecastVal;
            $variancePercentage = ($budgetVal > 0)
                ? round((($budgetVal - $actualVal) / $budgetVal) * 100, 2)
                : -100.00;

            // Insert new budget data record including forecast and computed fields
            $insertQuery = "INSERT INTO budget_data (year, category_name, period_name, budget, actual, forecast, actual_plus_forecast, variance_percentage, cluster, quarter_number, start_date, end_date, year2, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param(
                "issdddddsissis",
                $year,
                $category_name,
                $period_name,
                $budgetVal,
                $actualVal,
                $forecastVal,
                $actualPlusForecast,
                $variancePercentage,
                $cluster,
                $quarter_number,
                $start_date,
                $end_date,
                $year2,
                $currency
            );
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Budget data record added successfully";
                } else {
                    $_SESSION['error_message'] = "Error adding budget data record: " . $conn->error;
                }
                
                header("Location: admin_budget_management.php");
                exit();
                
            case 'import_budget_data_excel':
                // Handle Excel file upload and import
                if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                    $_SESSION['error_message'] = "Error uploading Excel file";
                    header("Location: admin_budget_management.php#budget-data-tab");
                    exit();
                }
                
                $cluster = $_POST['cluster'] ?? '';
                if (empty($cluster)) {
                    $_SESSION['error_message'] = "Cluster is required";
                    header("Location: admin_budget_management.php#budget-data-tab");
                    exit();
                }
                
                try {
                    // Load the Excel file
                    $inputFileName = $_FILES['excel_file']['tmp_name'];
                    $spreadsheet = IOFactory::load($inputFileName);
                    $worksheet = $spreadsheet->getActiveSheet();
                    
                    // Get the highest row and column
                    $highestRow = $worksheet->getHighestRow();
                    $highestColumn = $worksheet->getHighestColumn();
                    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                    
                    // Check if we have at least headers
                    if ($highestRow < 2) {
                        $_SESSION['error_message'] = "Excel file is empty or invalid";
                        header("Location: admin_budget_management.php#budget-data-tab");
                        exit();
                    }
                    
                    // Read headers from the first row
                    $headers = [];
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                        $cell = $worksheet->getCell($columnLetter . '1');
                        // Clean header value - remove non-breaking spaces and other special characters
                        $headerValue = trim($cell->getValue());
                        $headerValue = preg_replace('/[^\x20-\x7E]/', '', $headerValue); // Remove non-ASCII characters
                        $headerValue = trim($headerValue);
                        $headers[] = $headerValue;
                    }
                    
                    // Log headers for debugging
                    error_log('Excel headers: ' . json_encode($headers));
                    
                    // Required headers (Forecast can be optional but supported)
                    $requiredHeaders = ['Year', 'Category Name', 'Period Name', 'Budget', 'Quarter', 'Start Date', 'End Date'];
                    foreach ($requiredHeaders as $requiredHeader) {
                        if (!in_array($requiredHeader, $headers)) {
                            $_SESSION['error_message'] = "Missing required column: $requiredHeader";
                            header("Location: admin_budget_management.php#budget-data-tab");
                            exit();
                        }
                    }
                    
                    // Filter out empty headers to handle deleted columns
                    $filteredHeaders = array_filter($headers, function($header) {
                        return !empty(trim($header));
                    });
                    
                    // Re-index the filtered headers
                    $headers = array_values($filteredHeaders);
                    
                    error_log('Filtered Excel headers: ' . json_encode($headers));
                    
                    // Helper function for flexible header matching
                    function findColumnIndex($headers, $targetHeader) {
                        foreach ($headers as $index => $header) {
                            // Case-insensitive comparison with whitespace trimming
                            if (strtolower(trim($header)) === strtolower(trim($targetHeader))) {
                                return $index + 1;
                            }
                        }
                        return false;
                    }
                    
                    // Get column indices using flexible matching
                    $yearCol = findColumnIndex($headers, 'Year');
                    $categoryNameCol = findColumnIndex($headers, 'Category Name');
                    $periodNameCol = findColumnIndex($headers, 'Period Name');
                    $budgetCol = findColumnIndex($headers, 'Budget');
                    $forecastCol = findColumnIndex($headers, 'Forecast'); // optional
                    $quarterNumberCol = findColumnIndex($headers, 'Quarter');
                    $startDateCol = findColumnIndex($headers, 'Start Date');
                    $endDateCol = findColumnIndex($headers, 'End Date');
                    
                    // Optional columns
                    $clusterCol = findColumnIndex($headers, 'Cluster');
                    $year2Col = findColumnIndex($headers, 'Start Date Year');
                    $currencyCol = findColumnIndex($headers, 'Currency');
                    
                    // Log column indices for debugging
                    error_log("Year column: $yearCol, Category column: $categoryNameCol, Period column: $periodNameCol, Budget column: $budgetCol, Forecast column: $forecastCol, Quarter column: $quarterNumberCol, StartDate column: $startDateCol, EndDate column: $endDateCol");
                    error_log("Cluster column: $clusterCol, Year2 column: $year2Col, Currency column: $currencyCol");
                    
                    // Validate that all required columns were found
                    $requiredColumns = [
                        'Year' => $yearCol,
                        'Category Name' => $categoryNameCol,
                        'Period Name' => $periodNameCol,
                        'Budget' => $budgetCol,
                        'Quarter' => $quarterNumberCol,
                        'Start Date' => $startDateCol,
                        'End Date' => $endDateCol
                    ];
                    
                    foreach ($requiredColumns as $columnName => $columnIndex) {
                        if ($columnIndex === false) {
                            $_SESSION['error_message'] = "Required column '$columnName' not found in Excel file";
                            header("Location: admin_budget_management.php#budget-data-tab");
                            exit();
                        }
                    }
                    
                    // Prepare insert statement (includes forecast and computed fields)
                    $insertQuery = "INSERT INTO budget_data (year, category_name, period_name, budget, actual, forecast, actual_plus_forecast, variance_percentage, cluster, quarter_number, start_date, end_date, year2, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insertQuery);
                    
                    $importedCount = 0;
                    $errorCount = 0;
                    
                    // Log total rows for debugging
                    error_log("Total rows in Excel: $highestRow");
                    
                    // Count actual data rows
                    $dataRowCount = 0;
                    
                    // Process each row (starting from row 2 to skip headers)
                    for ($row = 2; $row <= $highestRow; $row++) {
                        error_log("Processing row $row");
                        
                        // Skip completely empty rows
                        $isEmptyRow = true;
                        for ($col = 1; $col <= $highestColumnIndex; $col++) {
                            $cellValue = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row)->getValue();
                            if (!empty(trim($cellValue))) {
                                $isEmptyRow = false;
                                break;
                            }
                        }
                        
                        if ($isEmptyRow) {
                            error_log("Row $row: Skipping empty row");
                            continue;
                        }
                        
                        // Check if this is a valid data row by checking if required fields are present
                        $year = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($yearCol) . $row)->getValue();
                        $categoryName = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($categoryNameCol) . $row)->getValue();
                        $periodName = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($periodNameCol) . $row)->getValue();
                        $startDate = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startDateCol) . $row)->getValue();
                        $endDate = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endDateCol) . $row)->getValue();
                        
                        // Skip rows with missing required data
                        if (empty($year) && empty($categoryName) && empty($periodName) && empty($startDate) && empty($endDate)) {
                            error_log("Row $row: Skipping row with no required data");
                            continue;
                        }
                        
                        // Increment data row count
                        $dataRowCount++;
                        error_log("Row $row: Valid data row #$dataRowCount");
                        
                        try {
                            // Get cell values
                            $year = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($yearCol) . $row)->getValue();
                            $categoryName = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($categoryNameCol) . $row)->getValue();
                            $periodName = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($periodNameCol) . $row)->getValue();
                            $budget = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($budgetCol) . $row)->getValue();
                            // Optional forecast
                            $forecastValue = 0;
                            if ($forecastCol !== false) {
                                $forecastValue = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($forecastCol) . $row)->getValue();
                            }
                            $quarterNumber = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($quarterNumberCol) . $row)->getValue();
                            $startDate = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startDateCol) . $row)->getValue();
                            $endDate = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endDateCol) . $row)->getValue();
                            
                            // Get optional values
                            $clusterValue = $cluster; // Default to the cluster selected in the form
                            if ($clusterCol !== null) {
                                $clusterValue = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($clusterCol) . $row)->getValue() ?: $cluster;
                            }
                            
                            $year2Value = $year; // Default to the same year
                            if ($year2Col !== null) {
                                $year2Value = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($year2Col) . $row)->getValue();
                                // If year2 value is empty, default to the year value
                                if (empty($year2Value)) {
                                    $year2Value = $year;
                                }
                            }
                            
                            $currencyValue = 'ETB'; // Default currency
                            if ($currencyCol !== null) {
                                $currencyValue = trim($worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currencyCol) . $row)->getValue());
                                // If currency value is empty, default to ETB
                                if (empty($currencyValue)) {
                                    $currencyValue = 'ETB';
                                }
                                // Log the currency value for debugging
                                error_log("Row $row: Currency value from Excel: '" . $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currencyCol) . $row)->getValue() . "' trimmed: '$currencyValue'");
                                
                                // Validate currency value and default to ETB if invalid
                                $validCurrencies = ['ETB', 'USD', 'EUR'];
                                if (!in_array(strtoupper($currencyValue), $validCurrencies)) {
                                    error_log("Row $row: Invalid currency '$currencyValue', defaulting to ETB");
                                    $currencyValue = 'ETB';
                                } else {
                                    $currencyValue = strtoupper($currencyValue);
                                }
                            }
                            
                            // Convert Excel date values to PHP date format if needed
                            if (is_numeric($startDate)) {
                                try {
                                    $startDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($startDate)->format('Y-m-d');
                                } catch (Exception $e) {
                                    error_log("Error converting start date: " . $e->getMessage());
                                    $startDate = null;
                                }
                            } elseif (is_string($startDate)) {
                                // Try to parse string dates
                                $date = strtotime($startDate);
                                if ($date !== false) {
                                    $startDate = date('Y-m-d', $date);
                                } else {
                                    $startDate = null;
                                }
                            }
                            
                            if (is_numeric($endDate)) {
                                try {
                                    $endDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($endDate)->format('Y-m-d');
                                } catch (Exception $e) {
                                    error_log("Error converting end date: " . $e->getMessage());
                                    $endDate = null;
                                }
                            } elseif (is_string($endDate)) {
                                // Try to parse string dates
                                $date = strtotime($endDate);
                                if ($date !== false) {
                                    $endDate = date('Y-m-d', $date);
                                } else {
                                    $endDate = null;
                                }
                            }
                            
                            // Validate required fields
                            if (empty($year) || empty($categoryName) || empty($periodName) || empty($startDate) || empty($endDate)) {
                                error_log("Row $row: Skipping due to missing required fields - Year: $year, Category: $categoryName, Period: $periodName, StartDate: $startDate, EndDate: $endDate");
                                $errorCount++;
                                continue;
                            }
                            
                            // Normalize numeric values and compute
                            $budgetVal = is_numeric($budget) ? (float)$budget : 0.0;
                            $actualVal = 0.0; // Excel import doesn't provide actual; default 0
                            $forecastVal = is_numeric($forecastValue) ? (float)$forecastValue : 0.0;
                            $actualPlusForecast = $actualVal + $forecastVal;
                            // Modified variance calculation: Variance (%) = (Budget − Actual) / Budget × 100
                            $variancePercentage = ($budgetVal > 0)
                                ? round((($budgetVal - $actualPlusForecast) / $budgetVal) * 100, 2)
                                : -100.00;

                            // Log values for debugging
                            error_log("Row $row: Inserting values - Year: $year, Category: $categoryName, Period: $periodName, Budget: $budgetVal, Forecast: $forecastVal, APF: $actualPlusForecast, Var%: $variancePercentage, Cluster: $clusterValue, Quarter: $quarterNumber, StartDate: $startDate, EndDate: $endDate, Year2: $year2Value, Currency: $currencyValue");
                            
                            // Bind parameters and execute
                            $stmt->bind_param(
                                "issdddddsissis",
                                $year,
                                $categoryName,
                                $periodName,
                                $budgetVal,
                                $actualVal,
                                $forecastVal,
                                $actualPlusForecast,
                                $variancePercentage,
                                $clusterValue,
                                $quarterNumber,
                                $startDate,
                                $endDate,
                                $year2Value,
                                $currencyValue
                            );
                            
                            if ($stmt->execute()) {
                                $importedCount++;
                            } else {
                                error_log("Row $row: Database insert failed: " . $stmt->error);
                                $errorCount++;
                            }
                        } catch (Exception $e) {
                            error_log("Row $row: Exception occurred - " . $e->getMessage());
                            $errorCount++;
                        }
                    }
                    
                    $_SESSION['success_message'] = "Import completed. Processed $dataRowCount data rows. $importedCount records imported successfully. $errorCount records failed to import. Check error logs for details.";
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Error importing Excel file: " . $e->getMessage();
                }
                
                header("Location: admin_budget_management.php#budget-data-tab");
                exit();
                
            case 'set_acceptance':
                // Get form data
                $preview_id = $_POST['preview_id'] ?? 0;
                $accepted = $_POST['accepted'] ?? 0;
                $comment = $_POST['comment'] ?? '';
                
                // Validate input
                if (empty($preview_id)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Record ID is required']);
                    exit();
                }
                
                // Update acceptance status in budget_preview table
                if (!empty($comment)) {
                    // If there's a comment, update both acceptance and comment
                    $updateQuery = "UPDATE budget_preview SET ACCEPTANCE = ?, COMMENTS = ? WHERE PreviewID = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("isi", $accepted, $comment, $preview_id);
                } else {
                    // Only update acceptance status
                    $updateQuery = "UPDATE budget_preview SET ACCEPTANCE = ? WHERE PreviewID = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("ii", $accepted, $preview_id);
                }
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Acceptance status updated successfully']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error updating acceptance status: ' . $conn->error]);
                }
                exit();
                
            case 'add_budget_preview':
                // Get form data
                $budget_heading = $_POST['budget_heading'] ?? '';
                $category_name = $_POST['category_name'] ?? '';
                $activity = $_POST['activity'] ?? '';
                $partner = $_POST['partner'] ?? '';
                $amount = $_POST['amount'] ?? null;
                $entry_date = $_POST['entry_date'] ?? null;
                $cluster = $_POST['cluster'] ?? '';
                
                // Validate input - handle both dropdown and input field types
                if (empty($cluster)) {
                    $_SESSION['error_message'] = "Cluster is required";
                    header("Location: admin_budget_management.php#budget-preview-tab");
                    exit();
                }
                
                // For budget heading, check if it's configured as an input field
                // If so, we don't require a predefined value, just that it's not empty
                $budget_heading_config = null;
                if (!empty($cluster)) {
                    $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $cluster);
                } else {
                    $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name IS NULL";
                    $stmt = $conn->prepare($query);
                }
                
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $budget_heading_config = $result->fetch_assoc();
                    }
                }
                
                // Validate budget heading based on field configuration
                if ($budget_heading_config) {
                    if ($budget_heading_config['field_type'] === 'dropdown') {
                        // For dropdown, check if value is in predefined values
                        if (empty($budget_heading)) {
                            $_SESSION['error_message'] = "Budget heading is required";
                            header("Location: admin_budget_management.php#budget-preview-tab");
                            exit();
                        }
                        // Check if the value is in the allowed list
                        $allowed_values = explode(',', $budget_heading_config['field_values']);
                        if (!in_array($budget_heading, $allowed_values)) {
                            $_SESSION['error_message'] = "Invalid budget heading value";
                            header("Location: admin_budget_management.php#budget-preview-tab");
                            exit();
                        }
                    } else {
                        // For input field, just check that it's not empty
                        if (empty($budget_heading)) {
                            $_SESSION['error_message'] = "Budget heading is required";
                            header("Location: admin_budget_management.php#budget-preview-tab");
                            exit();
                        }
                    }
                } else {
                    // No configuration found, treat as required field
                    if (empty($budget_heading)) {
                        $_SESSION['error_message'] = "Budget heading is required";
                        header("Location: admin_budget_management.php#budget-preview-tab");
                        exit();
                    }
                }
                
                // Validate category name
                if (empty($category_name)) {
                    $_SESSION['error_message'] = "Category name is required";
                    header("Location: admin_budget_management.php#budget-preview-tab");
                    exit();
                }
                
                // Insert new budget preview record
                $insertQuery = "INSERT INTO budget_preview (BudgetHeading, CategoryName, Activity, Partner, Amount, EntryDate, cluster) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("sssssds", $budget_heading, $category_name, $activity, $partner, $amount, $entry_date, $cluster);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Budget preview record added successfully";
                } else {
                    $_SESSION['error_message'] = "Error adding budget preview record: " . $conn->error;
                }
                
                header("Location: admin_budget_management.php#budget-preview-tab");
                exit();
                
            case 'edit_budget_preview':
                // Get form data
                $preview_id = $_POST['preview_id'] ?? 0;
                $budget_heading = $_POST['budget_heading'] ?? '';
                $category_name = $_POST['category_name'] ?? '';
                $activity = $_POST['activity'] ?? '';
                $partner = $_POST['partner'] ?? '';
                $amount = $_POST['amount'] ?? null;
                $entry_date = $_POST['entry_date'] ?? null;
                $cluster = $_POST['cluster'] ?? '';
                
                // Validate input
                if (empty($preview_id)) {
                    $_SESSION['error_message'] = "Record ID is required";
                    header("Location: admin_budget_management.php#budget-preview-tab");
                    exit();
                }
                
                // For budget heading, check if it's configured as an input field
                // If so, we don't require a predefined value, just that it's not empty
                $budget_heading_config = null;
                if (!empty($cluster)) {
                    $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $cluster);
                } else {
                    $query = "SELECT field_type, field_values FROM predefined_fields WHERE field_name = 'BudgetHeading' AND cluster_name IS NULL";
                    $stmt = $conn->prepare($query);
                }
                
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $budget_heading_config = $result->fetch_assoc();
                    }
                }
                
                // Validate budget heading based on field configuration
                if ($budget_heading_config) {
                    if ($budget_heading_config['field_type'] === 'dropdown') {
                        // For dropdown, check if value is in predefined values
                        if (empty($budget_heading)) {
                            $_SESSION['error_message'] = "Budget heading is required";
                            header("Location: admin_budget_management.php#budget-preview-tab");
                            exit();
                        }
                        // Check if the value is in the allowed list
                        $allowed_values = explode(',', $budget_heading_config['field_values']);
                        if (!in_array($budget_heading, $allowed_values)) {
                            $_SESSION['error_message'] = "Invalid budget heading value";
                            header("Location: admin_budget_management.php#budget-preview-tab");
                            exit();
                        }
                    } else {
                        // For input field, just check that it's not empty
                        if (empty($budget_heading)) {
                            $_SESSION['error_message'] = "Budget heading is required";
                            header("Location: admin_budget_management.php#budget-preview-tab");
                            exit();
                        }
                    }
                } else {
                    // No configuration found, treat as required field
                    if (empty($budget_heading)) {
                        $_SESSION['error_message'] = "Budget heading is required";
                        header("Location: admin_budget_management.php#budget-preview-tab");
                        exit();
                    }
                }
                
                // Validate category name and cluster
                if (empty($category_name) || empty($cluster)) {
                    $_SESSION['error_message'] = "Category name and cluster are required";
                    header("Location: admin_budget_management.php#budget-preview-tab");
                    exit();
                }
                
                // Update budget preview record
                $updateQuery = "UPDATE budget_preview SET BudgetHeading = ?, CategoryName = ?, Activity = ?, Partner = ?, Amount = ?, EntryDate = ?, cluster = ? WHERE PreviewID = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("sssssdsi", $budget_heading, $category_name, $activity, $partner, $amount, $entry_date, $cluster, $preview_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Budget preview record updated successfully";
                } else {
                    $_SESSION['error_message'] = "Error updating budget preview record: " . $conn->error;
                }
                
                header("Location: admin_budget_management.php#budget-preview-tab");
                exit();
                
            case 'delete_budget_preview':
                // Get record ID
                $preview_id = $_POST['preview_id'] ?? 0;
                
                // Validate input
                if (empty($preview_id)) {
                    $_SESSION['error_message'] = "Record ID is required";
                    header("Location: admin_budget_management.php#budget-preview-tab");
                    exit();
                }
                
                // Delete budget preview record
                $deleteQuery = "DELETE FROM budget_preview WHERE PreviewID = ?";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("i", $preview_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Budget preview record deleted successfully";
                } else {
                    $_SESSION['error_message'] = "Error deleting budget preview record: " . $conn->error;
                }
                
                header("Location: admin_budget_management.php#budget-preview-tab");
                exit();
                
            case 'delete_budget_data':
                // Get record ID
                $id = $_POST['id'] ?? 0;
                
                // Validate input
                if (empty($id)) {
                    $_SESSION['error_message'] = "Record ID is required";
                    header("Location: admin_budget_management.php#budget-data-tab");
                    exit();
                }
                
                // Delete budget data record
                $deleteQuery = "DELETE FROM budget_data WHERE id = ?";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Budget data record deleted successfully";
                } else {
                    $_SESSION['error_message'] = "Error deleting budget data record: " . $conn->error;
                }
                
                header("Location: admin_budget_management.php#budget-data-tab");
                exit();
                
            case 'edit_budget_data':
                // Get form data
                $id = $_POST['id'] ?? 0;
                $year = $_POST['year'] ?? '';
                $category_name = $_POST['category_name'] ?? '';
                $period_name = $_POST['period_name'] ?? '';
            $budget = $_POST['budget'] ?? null;
            // New: editable Actual and Forecast in edit modal
            $actual = $_POST['actual'] ?? 0;
            $forecast = $_POST['forecast'] ?? 0;
                $cluster = $_POST['cluster'] ?? '';
                $quarter_number = $_POST['quarter_number'] ?? null;
                $start_date = $_POST['start_date'] ?? null;
                $end_date = $_POST['end_date'] ?? null;
                $year2 = $_POST['year2'] ?? date('Y');
                $currency = $_POST['currency'] ?? 'ETB';
                
                // Validate input
                if (empty($id) || empty($year) || empty($category_name) || empty($period_name) || empty($cluster) || empty($start_date) || empty($end_date)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    exit();
                }
                
            // Normalize numeric values and compute
            $budgetVal = is_numeric($budget) ? (float)$budget : 0.0;
            $actualVal = is_numeric($actual) ? (float)$actual : 0.0;
            $forecastVal = is_numeric($forecast) ? (float)$forecast : 0.0;
            $actualPlusForecast = $actualVal + $forecastVal;
            // Modified variance calculation: Variance (%) = (Budget − Actual) / Budget × 100
            $variancePercentage = ($budgetVal > 0)
                ? round((($budgetVal - $actualVal) / $budgetVal) * 100, 2)
                : -100.00;

            // Update budget data record including actual/forecast and computed fields
            $updateQuery = "UPDATE budget_data SET year = ?, category_name = ?, period_name = ?, budget = ?, actual = ?, forecast = ?, actual_plus_forecast = ?, variance_percentage = ?, cluster = ?, quarter_number = ?, start_date = ?, end_date = ?, year2 = ?, currency = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param(
                "issdddddsissisi",
                $year,
                $category_name,
                $period_name,
                $budgetVal,
                $actualVal,
                $forecastVal,
                $actualPlusForecast,
                $variancePercentage,
                $cluster,
                $quarter_number,
                $start_date,
                $end_date,
                $year2,
                $currency,
                $id
            );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Budget data record updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating budget data record: ' . $conn->error]);
                }
                exit();
        }
    }
}

// If no action was specified, redirect to the admin page
header("Location: admin_budget_management.php");
exit();
?>