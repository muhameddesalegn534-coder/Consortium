<?php
session_start();
// Use PDO connection instead of the old config
require_once 'db_connection_pdo.php';
require_once 'currency_functions.php';

// Use $pdo_conn instead of $conn for PDO operations
$conn = $pdo_conn;

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_rate':
                try {
                    // Check if required POST values exist before accessing them
                    if (!isset($_POST['cluster_id']) || !isset($_POST['from_currency']) || !isset($_POST['to_currency']) || !isset($_POST['exchange_rate'])) {
                        throw new Exception('Missing required fields');
                    }
                    
                    $clusterId = intval($_POST['cluster_id']);
                    $fromCurrency = strtoupper(trim($_POST['from_currency']));
                    $toCurrency = strtoupper(trim($_POST['to_currency']));
                    $rate = floatval($_POST['exchange_rate']);
                    
                    // Validate inputs
                    if ($clusterId <= 0) {
                        throw new Exception('Please select a cluster');
                    }
                    
                    if (!in_array($fromCurrency, ['USD', 'EUR', 'ETB']) || !in_array($toCurrency, ['USD', 'EUR', 'ETB'])) {
                        throw new Exception('Invalid currency codes');
                    }
                    
                    if ($fromCurrency === $toCurrency) {
                        throw new Exception('From and To currencies cannot be the same');
                    }
                    
                    if ($rate <= 0) {
                        throw new Exception('Exchange rate must be greater than 0');
                    }
                    
                    if (addOrUpdateCurrencyRate($conn, $clusterId, $fromCurrency, $toCurrency, $rate, $_SESSION['user_id'])) {
                        $message = "Currency rate added/updated successfully";
                    } else {
                        throw new Exception('Failed to add/update currency rate');
                    }
                } catch (Exception $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'delete_rate':
                try {
                    if (!isset($_POST['rate_id'])) {
                        throw new Exception('Missing rate ID');
                    }
                    
                    $rateId = intval($_POST['rate_id']);
                    
                    if ($rateId <= 0) {
                        throw new Exception('Invalid rate ID');
                    }
                    
                    if (deleteCurrencyRate($conn, $rateId)) {
                        $message = "Currency rate deleted successfully";
                    } else {
                        throw new Exception('Failed to delete currency rate');
                    }
                } catch (Exception $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
                
            // Handle custom currency rate flag update
            case 'update_custom_rate_flag':
                try {
                    if (!isset($_POST['cluster_id'])) {
                        throw new Exception('Missing cluster ID');
                    }
                    
                    $clusterId = intval($_POST['cluster_id']);
                    $customRateFlag = isset($_POST['custom_currency_rate']) ? 1 : 0;
                    
                    if ($clusterId <= 0) {
                        throw new Exception('Invalid cluster ID');
                    }
                    
                    $stmt = $conn->prepare("UPDATE clusters SET custom_currency_rate = ? WHERE id = ?");
                    if ($stmt->execute([$customRateFlag, $clusterId])) {
                        $message = "Custom currency rate flag updated successfully";
                    } else {
                        throw new Exception('Failed to update custom currency rate flag');
                    }
                } catch (Exception $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all clusters
try {
    $clusterStmt = $conn->prepare("SELECT id, cluster_name, custom_currency_rate FROM clusters WHERE is_active = 1 ORDER BY cluster_name");
    $clusterStmt->execute();
    // Use PDO fetchAll instead of get_result
    $clusters = $clusterStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching clusters: " . $e->getMessage();
    $clusters = [];
}

// Fetch all currency rates with cluster information
try {
    $ratesStmt = $conn->prepare("SELECT cr.*, c.cluster_name, c.id as cluster_id, u.username 
                                FROM currency_rates cr 
                                JOIN clusters c ON cr.cluster_id = c.id 
                                LEFT JOIN users u ON cr.updated_by = u.id 
                                WHERE cr.is_active = 1 
                                ORDER BY c.cluster_name, cr.from_currency, cr.to_currency");
    $ratesStmt->execute();
    // Use PDO fetchAll instead of get_result
    $currencyRates = $ratesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching currency rates: " . $e->getMessage();
    $currencyRates = [];
}
?>

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="flex flex-col flex-1 min-w-0">
    <!-- Header -->
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
        <div class="flex items-center">
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Currency Management</h2>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
        <div class="max-w-6xl mx-auto space-y-6">
            
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Add New Currency Rate Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Currency Rate</h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <input type="hidden" name="action" value="add_rate">
                    
                    <div>
                        <label for="cluster_id" class="block text-sm font-medium text-gray-700">Cluster</label>
                        <select id="cluster_id" name="cluster_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Cluster</option>
                            <?php foreach ($clusters as $cluster): ?>
                                <option value="<?php echo $cluster['id']; ?>"><?php echo htmlspecialchars($cluster['cluster_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="from_currency" class="block text-sm font-medium text-gray-700">From Currency</label>
                        <select id="from_currency" name="from_currency" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Currency</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="ETB">ETB - Ethiopian Birr</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="to_currency" class="block text-sm font-medium text-gray-700">To Currency</label>
                        <select id="to_currency" name="to_currency" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Currency</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="ETB">ETB - Ethiopian Birr</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="exchange_rate" class="block text-sm font-medium text-gray-700">Exchange Rate</label>
                        <input type="number" id="exchange_rate" name="exchange_rate" step="0.0001" min="0.0001" required
                               placeholder="e.g., 300.0000"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            Add Rate
                        </button>
                    </div>
                </form>
            </div>

            <!-- Custom Currency Rate Flag Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Custom Currency Rate Settings</h3>
                <p class="text-sm text-gray-600 mb-4">Enable custom currency rates for specific clusters. When enabled, users in that cluster will be able to enter custom exchange rates for transactions.</p>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cluster</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Custom Rate Enabled</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($clusters as $cluster): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($cluster['custom_currency_rate']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Enabled
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Disabled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="update_custom_rate_flag">
                                            <input type="hidden" name="cluster_id" value="<?php echo $cluster['id']; ?>">
                                            <?php if ($cluster['custom_currency_rate']): ?>
                                                <input type="hidden" name="custom_currency_rate" value="0">
                                                <button type="submit" 
                                                        class="text-red-600 hover:text-red-900"
                                                        onclick="return confirm('Disable custom currency rates for <?php echo htmlspecialchars($cluster['cluster_name']); ?>?')">
                                                    Disable
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="custom_currency_rate" value="1">
                                                <button type="submit" 
                                                        class="text-green-600 hover:text-green-900"
                                                        onclick="return confirm('Enable custom currency rates for <?php echo htmlspecialchars($cluster['cluster_name']); ?>?')">
                                                    Enable
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Currency Rates Table -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Current Currency Rates</h3>
                    <a href="admin.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Back to Admin
                    </a>
                </div>
                
                <?php if (empty($currencyRates)): ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No currency rates</h3>
                        <p class="mt-1 text-sm text-gray-500">No currency rates have been configured yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cluster</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Currency</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Currency</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exchange Rate</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated By</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($currencyRates as $rate): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($rate['cluster_name']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($rate['from_currency']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($rate['to_currency']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo number_format($rate['exchange_rate'], 4); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($rate['last_updated'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($rate['username'] ?? 'System'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="editRate(<?php echo $rate['id']; ?>, <?php echo $rate['cluster_id']; ?>, '<?php echo htmlspecialchars($rate['cluster_name']); ?>', '<?php echo $rate['from_currency']; ?>', '<?php echo $rate['to_currency']; ?>', <?php echo $rate['exchange_rate']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 mr-3">
                                                Edit
                                            </button>
                                            <button onclick="deleteRate(<?php echo $rate['id']; ?>, '<?php echo htmlspecialchars($rate['cluster_name']); ?>', '<?php echo $rate['from_currency']; ?>', '<?php echo $rate['to_currency']; ?>')" 
                                                    class="text-red-600 hover:text-red-900">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Edit Modal -->
            <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Currency Rate</h3>
                        <form method="POST" id="editForm">
                            <input type="hidden" name="action" value="add_rate">
                            <input type="hidden" name="rate_id" id="edit_rate_id">
                            <!-- Hidden fields to store values when editing -->
                            <input type="hidden" name="cluster_id" id="edit_cluster_id">
                            <input type="hidden" name="from_currency" id="edit_from_currency_code">
                            <input type="hidden" name="to_currency" id="edit_to_currency_code">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Cluster</label>
                                <div id="edit_cluster_name" class="mt-1 text-sm text-gray-900 font-medium"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">From Currency</label>
                                <div id="edit_from_currency_display" class="mt-1 text-sm text-gray-900 font-medium"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">To Currency</label>
                                <div id="edit_to_currency_display" class="mt-1 text-sm text-gray-900 font-medium"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="edit_exchange_rate" class="block text-sm font-medium text-gray-700">Exchange Rate</label>
                                <input type="number" id="edit_exchange_rate" name="exchange_rate" step="0.0001" min="0.0001" required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeModal()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Currency Rate</h3>
                        <p class="text-sm text-gray-500 mb-4">Are you sure you want to delete this currency rate?</p>
                        <div id="delete_rate_info" class="mb-4 p-3 bg-gray-100 rounded"></div>
                        
                        <form method="POST" id="deleteForm">
                            <input type="hidden" name="action" value="delete_rate">
                            <input type="hidden" name="rate_id" id="delete_rate_id">
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeDeleteModal()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                    Delete
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function editRate(rateId, clusterId, clusterName, fromCurrency, toCurrency, exchangeRate) {
    document.getElementById('edit_rate_id').value = rateId;
    document.getElementById('edit_cluster_name').textContent = clusterName;
    document.getElementById('edit_from_currency_display').textContent = fromCurrency;
    document.getElementById('edit_to_currency_display').textContent = toCurrency;
    // Set hidden input values
    document.getElementById('edit_cluster_id').value = clusterId;
    document.getElementById('edit_from_currency_code').value = fromCurrency;
    document.getElementById('edit_to_currency_code').value = toCurrency;
    document.getElementById('edit_exchange_rate').value = exchangeRate;
    document.getElementById('editModal').classList.remove('hidden');
}

function deleteRate(rateId, clusterName, fromCurrency, toCurrency) {
    document.getElementById('delete_rate_id').value = rateId;
    document.getElementById('delete_rate_info').innerHTML = 
        `<strong>Cluster:</strong> ${clusterName}<br>
         <strong>Rate:</strong> 1 ${fromCurrency} = ${toCurrency}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modals when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php include 'message_system.php'; ?>