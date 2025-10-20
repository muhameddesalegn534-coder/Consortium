<?php
session_start();

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Handle POST requests for predefined fields
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
                            $field['values_array'] = explode(',', $field['field_values']);
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
                        $field['values_array'] = explode(',', $field['field_values']);
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
    }
}
?>