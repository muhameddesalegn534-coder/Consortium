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

// Get current year for context
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Predefined Fields | Consortium Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_predefined_fields_styles.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content-flex flex-1 flex-col">
        <div class="content-container">
            <!-- Header Section -->
            <div class="glass-card p-6 md:p-8 card-hover animate-fadeIn mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-4xl font-bold heading-font text-gray-800 mb-2 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-cogs text-blue-600"></i>
                            </div>
                            Admin - Predefined Fields
                        </h1>
                        <p class="text-gray-500 text-lg">Configure predefined options for transaction input fields</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                            <p class="text-gray-500 text-sm">Administrator</p>
                        </div>
                        <a href="logout.php" class="btn-secondary">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="flex flex-wrap gap-4 mb-6">
                    <a href="admin_predefined_fields.php" class="btn-primary">
                        <i class="fas fa-cog mr-2"></i> Field Configuration
                    </a>
                  
                    <!-- Navigation button to admin.php -->
                    <a href="admin.php" class="btn-accent">
                        <i class="fas fa-user-cog mr-2"></i> Admin Panel
                    </a>
                </div>

                <!-- Cluster Selection -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-building text-blue-600 text-lg"></i>
                        <div class="flex-1">
                            <h4 class="font-semibold text-blue-800">Cluster Management</h4>
                            <p class="text-blue-700 text-sm mt-1">
                                Select a cluster to configure specific field values for that cluster, or use "Global" for default values.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-blue-800 font-medium">Cluster:</label>
                            <select id="clusterSelect" class="form-input w-auto">
                                <option value="">Global (All Clusters)</option>
                                <?php
                                $clusterResult = $conn->query("SELECT cluster_name FROM clusters WHERE is_active = 1 ORDER BY cluster_name");
                                if ($clusterResult && $clusterResult->num_rows > 0) {
                                    while ($cluster = $clusterResult->fetch_assoc()) {
                                        echo "<option value=\"" . htmlspecialchars($cluster['cluster_name']) . "\">" . htmlspecialchars($cluster['cluster_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-info-circle text-blue-600 text-lg"></i>
                        <div>
                            <h4 class="font-semibold text-blue-800">How it works:</h4>
                            <p class="text-blue-700 text-sm mt-1">
                                Configure whether users can freely input values (Input Field) or must select from predefined options (Dropdown). 
                                Dropdown fields require you to specify the available options below.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fields Container -->
            <div class="main-content-flex">
                <div class="content-container">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="section-title">
                            <div class="section-icon">
                                <i class="fas fa-th-list"></i>
                            </div>
                            Field Configurations
                        </h3>
                        <button id="refreshFieldsBtn" class="btn-secondary flex items-center gap-2">
                            <i class="fas fa-sync-alt"></i> Refresh Fields
                        </button>
                    </div>
                    <div id="fieldsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Fields will be dynamically loaded here -->
                    </div>
                </div>
            </div>

            <!-- Manage Values Section -->
            <div class="glass-card p-6 md:p-8 card-hover animate-fadeIn">
                <h3 class="text-2xl font-semibold text-gray-800 mb-6 section-title">
                    <div class="section-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    Manage Field Values
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Add Values Form -->
                    <div class="field-card">
                        <h4 class="text-lg font-semibold mb-4 text-gray-800">
                            <i class="fas fa-plus text-green-600 mr-2"></i>
                            Set Values
                        </h4>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Select Field</label>
                                <select id="fieldSelect" class="form-input">
                                    <option value="">Choose a field...</option>
                                </select>
                            </div>
                            
                            <div id="valueInputSection" style="display: none;">
                                <label class="form-label" id="valueInputLabel">New Value</label>
                                <input type="text" id="newValueInput" class="form-input" placeholder="Enter value">
                            </div>
                            
                            <button id="addValueBtn" class="btn-accent w-full" disabled>
                                <i class="fas fa-plus mr-2"></i>
                                <span id="btnText">Set Value</span>
                            </button>
                        </div>
                    </div>

                    <!-- Current Values Display -->
                    <div class="field-card">
                        <h4 class="text-lg font-semibold mb-4 text-gray-800">
                            <i class="fas fa-list text-blue-600 mr-2"></i>
                            <span id="currentValuesTitle">Current Values</span>
                        </h4>
                        
                        <div id="currentValuesDisplay" class="text-gray-500 italic">
                            Select a field to view its current configuration
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 space-y-2"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fieldsContainer = document.getElementById('fieldsContainer');
            const fieldSelect = document.getElementById('fieldSelect');
            const newValueInput = document.getElementById('newValueInput');
            const addValueBtn = document.getElementById('addValueBtn');
            const currentValuesDisplay = document.getElementById('currentValuesDisplay');
            const toastContainer = document.getElementById('toastContainer');
            
            let fieldsData = [];

            // Toast function
            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                let bgColor = 'bg-green-600';
                let icon = '<i class="fas fa-check-circle mr-2"></i>';
                
                if (type === 'error') {
                    bgColor = 'bg-red-600';
                    icon = '<i class="fas fa-exclamation-circle mr-2"></i>';
                }
                if (type === 'info') {
                    bgColor = 'bg-blue-600';
                    icon = '<i class="fas fa-info-circle mr-2"></i>';
                }

                
                toast.className = `toast p-4 rounded-xl shadow-xl text-white font-medium flex items-center ${bgColor}`;
                toast.innerHTML = `${icon} ${message}`;

                toastContainer.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }

            // Load fields
            function loadFields() {
                console.log('Loading fields...');
                const clusterSelect = document.getElementById('clusterSelect');
                const selectedCluster = clusterSelect ? clusterSelect.value : '';
                
                let formData = 'action=get_fields';

    // If a specific cluster is selected (not empty and not global)
    if (selectedCluster && selectedCluster !== '') {
        formData += '&cluster_name=' + encodeURIComponent(selectedCluster);
    } else {
        // Special flag to indicate "global/all clusters"
        formData += '&cluster_name=all';
    }

                
                fetch('admin_fields_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Fields loaded:', data);
                    if (data.success) {
                        fieldsData = data.fields;
                        console.log('Fields data stored:', fieldsData);
                        renderFields();
                        updateFieldSelect();
                    } else {
                        showToast('Failed to load fields: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Load fields error:', error);
                    showToast('Error loading fields: ' + error.message, 'error');
                });
            }

            // Render fields
            function renderFields() {
                console.log('Rendering fields with data:', fieldsData);
                fieldsContainer.innerHTML = '';
                
                fieldsData.forEach(field => {
                    console.log(`Rendering field ${field.field_name}:`, {
                        is_active: field.is_active,
                        is_active_type: typeof field.is_active,
                        is_active_value: field.is_active === true,
                        is_active_string: field.is_active === '1',
                        is_active_number: field.is_active === 1
                    });
                    
                    const fieldCard = document.createElement('div');
                    fieldCard.className = 'field-card';
                    
                    const badgeClass = field.field_type === 'dropdown' ? 'badge-dropdown' : 'badge-input';
                    const badgeIcon = field.field_type === 'dropdown' ? 'fas fa-list' : 'fas fa-edit';
                    
                    // Convert is_active to boolean properly
                    const isActiveBoolean = field.is_active == 1 || field.is_active === true || field.is_active === '1';
                    console.log(`${field.field_name} isActiveBoolean:`, isActiveBoolean);
                    
                    // Cluster badge
                    const clusterBadge = field.cluster_name ? 
                        `<span class="cluster-badge ml-2">${field.cluster_name}</span>` : 
                        '<span class="cluster-badge ml-2">Global</span>';
                    
                    let valuesDisplay = '';
                    if (field.field_type === 'dropdown' && field.field_values) {
                        const values = field.field_values.split(',').filter(v => v.trim());
                        if (values.length > 0) {
                            valuesDisplay = `
                                <div class="field-values-container">
                                    <div class="field-values-content" id="values-${field.field_name}">
                                        <p class="text-sm font-medium text-gray-600 mb-2">Values (${values.length}):</p>
                                        <div class="flex flex-wrap gap-1">
                                            ${values.map(v => `<span class="value-tag">${v.trim()}</span>`).join('')}
                                        </div>
                                    </div>
                                    <div class="expand-toggle" onclick="toggleValues('${field.field_name}')">
                                        <i class="fas fa-chevron-down text-gray-500"></i>
                                    </div>
                                </div>
                            `;
                        }
                    } else if (field.field_type === 'input' && field.field_values) {
                        valuesDisplay = `
                            <div class="field-values-container">
                                <div class="field-values-content">
                                    <p class="text-sm font-medium text-gray-600 mb-2">Predefined Text:</p>
                                    <div class="bg-gray-50 p-2 rounded border">
                                        <span class="text-gray-700">${field.field_values || 'No predefined text set'}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (field.field_type === 'dropdown') {
                        valuesDisplay = `
                            <div class="field-values-container">
                                <div class="field-values-content">
                                    <p class="text-sm font-medium text-gray-600 mb-2">Values:</p>
                                    <p class="text-gray-500 italic">No options defined yet</p>
                                </div>
                            </div>
                        `;
                    }
                    
                    fieldCard.innerHTML = `
                        <div class="field-card-content">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                    ${field.field_name}
                                    ${clusterBadge}
                                </h4>
                                <label class="switch">
                                    <input type="checkbox" ${isActiveBoolean ? 'checked' : ''} 
                                           onchange="toggleField('${field.field_name}', this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="space-y-3 mb-3">
                                <div class="flex items-center justify-between">
                                    <span class="field-type-badge ${badgeClass}">
                                        <i class="${badgeIcon}"></i>
                                        ${field.field_type === 'dropdown' ? 'Dropdown' : 'Input Field'}
                                    </span>
                                    <button onclick="toggleFieldType('${field.field_name}')" 
                                            class="btn-secondary text-xs px-3 py-1">
                                        Switch to ${field.field_type === 'dropdown' ? 'Input' : 'Dropdown'}
                                    </button>
                                </div>
                                
                                ${valuesDisplay}
                                
                                <p class="text-sm text-gray-500">
                                    ${field.field_type === 'dropdown' 
                                        ? 'Users must select from predefined options' 
                                        : 'Users can enter any value' + (field.field_values ? ' (with predefined text)' : '')}
                                </p>
                            </div>
                        </div>
                    `;
                    
                    fieldsContainer.appendChild(fieldCard);
                });
                
                // Initialize expand/collapse functionality after rendering
                setTimeout(initExpandToggle, 100);
            }
            
            // Initialize expand toggle functionality
            function initExpandToggle() {
                document.querySelectorAll('.field-values-content').forEach(container => {
                    const content = container.innerHTML;
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = content;
                    tempDiv.style.maxHeight = '80px';
                    tempDiv.style.overflow = 'hidden';
                    
                    if (tempDiv.scrollHeight > 80) {
                        container.classList.add('has-more');
                    }
                });
            }

            // Toggle values display (expand/collapse)
            window.toggleValues = function(fieldName) {
                const container = document.getElementById(`values-${fieldName}`);
                if (container) {
                    const toggle = container.nextElementSibling;
                    const icon = toggle ? toggle.querySelector('i') : null;
                    
                    if (container.classList.contains('expanded')) {
                        container.classList.remove('expanded');
                        if (icon) icon.className = 'fas fa-chevron-down text-gray-500';
                    } else {
                        container.classList.add('expanded');
                        if (icon) icon.className = 'fas fa-chevron-up text-gray-500';
                    }
                }
            };

            // Update field select dropdown
            function updateFieldSelect() {
                fieldSelect.innerHTML = '<option value="">Choose a field...</option>';
                
                // Group fields by name
                const fieldGroups = {};
                fieldsData.forEach(field => {
                    if (!fieldGroups[field.field_name]) {
                        fieldGroups[field.field_name] = [];
                    }
                    fieldGroups[field.field_name].push(field);
                });
                
                // Add options for each field group
                Object.keys(fieldGroups).forEach(fieldName => {
                    const fields = fieldGroups[fieldName];
                    
                    // Sort fields: global first, then by cluster name
                    fields.sort((a, b) => {
                        if ((a.cluster_name === null || a.cluster_name === '') && (b.cluster_name !== null && b.cluster_name !== '')) return -1;
                        if ((a.cluster_name !== null && a.cluster_name !== '') && (b.cluster_name === null || b.cluster_name === '')) return 1;
                        return (a.cluster_name || '').localeCompare(b.cluster_name || '');
                    });
                    
                    if (fields.length === 1) {
                        // Only one field with this name
                        const field = fields[0];
                        const clusterLabel = field.cluster_name ? ` (${field.cluster_name})` : ' (Global)';
                        const optionValue = field.cluster_name ? `${field.field_name}__${field.cluster_name}` : `${field.field_name}__global`;
                        const option = document.createElement('option');
                        option.value = optionValue;
                        option.textContent = `${field.field_name}${clusterLabel} [${field.field_type}]`;
                        fieldSelect.appendChild(option);
                    } else {
                        // Multiple fields with this name (different clusters)
                        // Add global field first if it exists
                        const globalField = fields.find(f => !f.cluster_name || f.cluster_name === '' || f.cluster_name === null);
                        if (globalField) {
                            const option = document.createElement('option');
                            option.value = `${globalField.field_name}__global`;
                            option.textContent = `${globalField.field_name} (Global) [${globalField.field_type}]`;
                            fieldSelect.appendChild(option);
                        }
                        
                        // Add cluster-specific fields
                        fields.filter(f => f.cluster_name).forEach(field => {
                            const option = document.createElement('option');
                            option.value = `${field.field_name}__${field.cluster_name}`;
                            option.textContent = `${field.field_name} (${field.cluster_name}) [${field.field_type}]`;
                            fieldSelect.appendChild(option);
                        });
                    }
                });
            }

            // Toggle field active/inactive
            window.toggleField = function(fieldName, isActive) {
                console.log('Toggle called:', fieldName, 'isActive:', isActive);
                
                const clusterSelect = document.getElementById('clusterSelect');
                const selectedCluster = clusterSelect ? clusterSelect.value : '';
                
                let formData = `action=toggle_field&field_name=${encodeURIComponent(fieldName)}&is_active=${isActive ? 1 : 0}`;
                // Only add cluster_name parameter if a cluster is actually selected
                if (selectedCluster !== '') {
                    formData += `&cluster_name=${encodeURIComponent(selectedCluster)}`;
                }
                
                fetch('admin_fields_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Toggle response:', data);
                    if (data.success) {
                        showToast(`${fieldName} ${isActive ? 'activated' : 'deactivated'}`);
                        loadFields();
                    } else {
                        showToast('Failed to update field: ' + data.message, 'error');
                        loadFields();
                    }
                })
                .catch(error => {
                    console.error('Toggle error:', error);
                    showToast('Error updating field', 'error');
                    loadFields();
                });
            };

            // Toggle field type
            window.toggleFieldType = function(fieldName) {
                const clusterSelect = document.getElementById('clusterSelect');
                const selectedCluster = clusterSelect ? clusterSelect.value : '';
                
                // Find the field that matches both name and cluster
                let field;
                if (selectedCluster) {
                    // Look for cluster-specific field
                    field = fieldsData.find(f => 
                        f.field_name === fieldName && 
                        f.cluster_name === selectedCluster
                    );
                } else {
                    // Look for global field or any field when "All" is selected
                    field = fieldsData.find(f => 
                        f.field_name === fieldName && 
                        (f.cluster_name === null || f.cluster_name === undefined || f.cluster_name === '')
                    ) || fieldsData.find(f => f.field_name === fieldName);
                }
                
                if (!field) return;
                
                const newType = field.field_type === 'dropdown' ? 'input' : 'dropdown';
                
                let formData = `action=toggle_type&field_name=${encodeURIComponent(fieldName)}&field_type=${newType}`;
                // Only add cluster_name parameter if a cluster is actually selected
                if (selectedCluster) {
                    formData += `&cluster_name=${encodeURIComponent(selectedCluster)}`;
                }
                
                fetch('admin_fields_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${fieldName} changed to ${newType}`);
                        loadFields();
                    } else {
                        showToast('Failed to update field type: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error updating field type', 'error');
                });
            };

            // Handle field selection for values management
            fieldSelect.addEventListener('change', function() {
                const selectedOption = this.value;
                const clusterSelect = document.getElementById('clusterSelect');
                const selectedCluster = clusterSelect ? clusterSelect.value : '';
                newValueInput.value = '';
                addValueBtn.disabled = !selectedOption;
                
                const valueInputSection = document.getElementById('valueInputSection');
                const valueInputLabel = document.getElementById('valueInputLabel');
                const btnText = document.getElementById('btnText');
                const currentValuesTitle = document.getElementById('currentValuesTitle');
                const currentValuesDisplay = document.getElementById('currentValuesDisplay');
                
                console.log('Field selected:', selectedOption);
                console.log('Cluster selected:', selectedCluster);
                console.log('Value input section element:', valueInputSection);
                
                if (selectedOption) {
                    // Parse the field name and cluster from the option value
                    const [fieldName, clusterName] = selectedOption.split('__');
                    const isGlobalField = clusterName === 'global';
                    
                    // Find the field that matches both name and cluster
                    let field = null;
                    
                    if (!isGlobalField && clusterName) {
                        // Look for cluster-specific field
                        field = fieldsData.find(f => 
                            f.field_name === fieldName && 
                            f.cluster_name === clusterName
                        );
                    }
                    
                    // If no cluster-specific field found, try to find global field
                    if (!field) {
                        field = fieldsData.find(f => 
                            f.field_name === fieldName && 
                            (!f.cluster_name || f.cluster_name === '' || f.cluster_name === null)
                        );
                    }
                    
                    console.log('Found field:', field);
                    
                    if (field && field.field_type === 'dropdown') {
                        valueInputLabel.textContent = 'New Dropdown Option';
                        newValueInput.placeholder = 'Enter new option value';
                        btnText.textContent = 'Add Option';
                        currentValuesTitle.textContent = 'Current Options';
                        
                        if (field.field_values) {
                            const values = field.field_values.split(',').filter(v => v.trim());
                            if (values.length > 0) {
                                const fieldIdentifier = field.cluster_name ? `${field.field_name}__${field.cluster_name}` : `${field.field_name}__global`;
                                currentValuesDisplay.innerHTML = `
                                    <div class="space-y-2">
                                        ${values.map(value => `
                                            <div class="flex items-center justify-between bg-gray-50 p-2 rounded">
                                                <span>${value.trim()}</span>
                                                <button onclick="removeValue('${fieldIdentifier}', '${value.trim().replace(/'/g, "\\'")}')"
                                                        class="btn-danger text-xs px-2 py-1">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        `).join('')}
                                    </div>
                                `;
                            } else {
                                currentValuesDisplay.innerHTML = '<p class="text-gray-500 italic">No options defined yet</p>';
                            }
                        } else {
                            currentValuesDisplay.innerHTML = '<p class="text-gray-500 italic">No options defined yet</p>';
                        }
                    } else if (field) {
                        // Input field
                        valueInputLabel.textContent = 'Predefined Text (Optional)';
                        newValueInput.placeholder = 'Enter text that users will see (leave empty for no predefined text)';
                        btnText.textContent = 'Set Text';
                        currentValuesTitle.textContent = 'Current Setting';
                        
                        const fieldIdentifier = field.cluster_name ? `${field.field_name}__${field.cluster_name}` : `${field.field_name}__global`;
                        
                        if (field.field_values) {
                            currentValuesDisplay.innerHTML = `
                                <div class="bg-gray-50 p-3 rounded border">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">Predefined text:</p>
                                            <p class="font-medium text-gray-800">${field.field_values}</p>
                                        </div>
                                        <button onclick="clearInputText('${fieldIdentifier}')" 
                                                class="btn-danger text-xs px-2 py-1">
                                            <i class="fas fa-trash"></i> Clear
                                        </button>
                                    </div>
                                </div>
                            `;
                        } else {
                            currentValuesDisplay.innerHTML = '<p class="text-gray-500 italic">No predefined text set - users can enter any value</p>';
                        }
                    } else {
                        // Field not found for this cluster
                        if (valueInputSection) {
                            valueInputSection.style.display = 'none';
                        }
                        currentValuesDisplay.innerHTML = '<p class="text-gray-500 italic">Field not configured for this cluster</p>';
                        addValueBtn.disabled = true;
                        return;
                    }
                    
                    // Show the value input section
                    if (valueInputSection) {
                        console.log('Showing value input section');
                        valueInputSection.style.display = 'block';
                    } else {
                        console.log('Value input section not found');
                    }
                } else {
                    if (valueInputSection) {
                        valueInputSection.style.display = 'none';
                    }
                    currentValuesDisplay.innerHTML = '<p class="text-gray-500 italic">Select a field to view its current configuration</p>';
                }
            });

            // Add new value
            addValueBtn.addEventListener('click', function() {
                const selectedOption = fieldSelect.value;
                const newValue = newValueInput.value.trim();
                const clusterSelect = document.getElementById('clusterSelect');
                const selectedCluster = clusterSelect ? clusterSelect.value : '';
                
                if (!selectedOption) {
                    showToast('Please select a field', 'error');
                    return;
                }
                
                // Parse the field name and cluster from the option value
                const [fieldName, clusterName] = selectedOption.split('__');
                const isGlobalField = clusterName === 'global';
                
                // Find the field that matches both name and cluster
                let field = null;
                
                if (!isGlobalField && clusterName) {
                    // Look for cluster-specific field
                    field = fieldsData.find(f => 
                        f.field_name === fieldName && 
                        f.cluster_name === clusterName
                    );
                }
                
                // If no cluster-specific field found, try to find global field
                if (!field) {
                    field = fieldsData.find(f => 
                        f.field_name === fieldName && 
                        (!f.cluster_name || f.cluster_name === '' || f.cluster_name === null)
                    );
                }
                
                if (!field) {
                    showToast('Field not found', 'error');
                    return;
                }
                
                // For dropdown fields, value is required
                if (field.field_type === 'dropdown' && !newValue) {
                    showToast('Please enter a dropdown option', 'error');
                    return;
                }
                
                // For input fields, empty value is allowed (it clears the predefined text)
                
                let formData = `action=add_value&field_name=${encodeURIComponent(fieldName)}&value=${encodeURIComponent(newValue)}`;
                // Only add cluster_name parameter if a cluster is actually selected and it's not a global field
                if (selectedCluster && !isGlobalField) {
                    formData += `&cluster_name=${encodeURIComponent(selectedCluster)}`;
                }
                
                fetch('admin_fields_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Value updated successfully');
                        newValueInput.value = '';
                        loadFields();
                        // Refresh current values display
                        fieldSelect.dispatchEvent(new Event('change'));
                    } else {
                        showToast('Failed to update value: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error updating value: ' + error.message, 'error');
                });
            });

            // Clear input text
            window.clearInputText = function(selectedOption) {
                const clusterSelect = document.getElementById('clusterSelect');
                const selectedCluster = clusterSelect ? clusterSelect.value : '';
                
                // Parse the field name from the option value
                const [fieldName, clusterName] = selectedOption.split('__');
                const isGlobalField = clusterName === 'global';
                
                if (confirm(`Clear predefined text for ${fieldName}?`)) {
                    let formData = `action=remove_value&field_name=${encodeURIComponent(fieldName)}`;
                    // Only add cluster_name parameter if a cluster is actually selected and it's not a global field
                    if (selectedCluster && !isGlobalField) {
                        formData += `&cluster_name=${encodeURIComponent(selectedCluster)}`;
                    }
                    
                    fetch('admin_fields_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Predefined text cleared successfully');
                            loadFields();
                            // Refresh current values display
                            fieldSelect.dispatchEvent(new Event('change'));
                        } else {
                            showToast('Failed to clear text: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Error clearing text', 'error');
                    });
                }
            };

            // Remove value
            window.removeValue = function(selectedOption, value) {
                const clusterSelect = document.getElementById('clusterSelect');
                const selectedCluster = clusterSelect ? clusterSelect.value : '';
                
                // Parse the field name from the option value
                const [fieldName, clusterName] = selectedOption.split('__');
                const isGlobalField = clusterName === 'global';
                
                if (confirm(`Remove "${value}" from ${fieldName}?`)) {
                    let formData = `action=remove_value&field_name=${encodeURIComponent(fieldName)}&value=${encodeURIComponent(value)}`;
                    // Only add cluster_name parameter if a cluster is actually selected and it's not a global field
                    if (selectedCluster && !isGlobalField) {
                        formData += `&cluster_name=${encodeURIComponent(selectedCluster)}`;
                    }
                    
                    fetch('admin_fields_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Value removed successfully');
                            loadFields();
                            // Refresh current values display
                            fieldSelect.dispatchEvent(new Event('change'));
                        } else {
                            showToast('Failed to remove value: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Error removing value', 'error');
                    });
                }
            };

            // Allow adding value with Enter key
            newValueInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !addValueBtn.disabled) {
                    addValueBtn.click();
                }
            });

            // Handle cluster selection change
            const clusterSelect = document.getElementById('clusterSelect');
            if (clusterSelect) {
                clusterSelect.addEventListener('change', function() {
                    loadFields();
                });
            }

            // Refresh fields button
            const refreshFieldsBtn = document.getElementById('refreshFieldsBtn');
            if (refreshFieldsBtn) {
                refreshFieldsBtn.addEventListener('click', function() {
                    showToast('Refreshing fields...', 'info');
                    loadFields();
                });
            }

            // Function to switch to clusters tab
            window.switchToClusters = function() {
                // Redirect to admin.php with clusters section
                window.location.href = 'admin.php#clusters';
            };

            // Initial load
            loadFields();
        });
    </script>
</body>
</html>