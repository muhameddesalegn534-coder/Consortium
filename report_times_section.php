<?php 
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (only when accessed directly)
$is_standalone = basename($_SERVER['PHP_SELF']) === 'report_times_section.php';
if ($is_standalone && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if this file is being included or accessed directly
$included = defined('INCLUDED_FROM_INDEX') || isset($GLOBALS['in_index_context']);

// Include database configuration for database access
include 'config.php';

?>

<?php
// Get user cluster information
$userCluster = $_SESSION['cluster_name'] ?? null;

// Fetch reporting periods from budget_data table based on user's cluster
$reportingPeriods = [];
if ($userCluster && isset($conn)) {
    $periodQuery = "SELECT DISTINCT period_name, start_date, end_date, quarter_number 
                    FROM budget_data 
                    WHERE cluster = ? AND start_date IS NOT NULL AND end_date IS NOT NULL
                    ORDER BY quarter_number";
    $stmt = $conn->prepare($periodQuery);
    $stmt->bindParam(1, $userCluster);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($result as $row) {
        $reportingPeriods[] = $row;
    }
}
?>

<?php if (!$included): ?>
    <?php
    // When accessed directly, include the header to get the proper structure
    $GLOBALS['in_index_context'] = true;
    include 'header.php';
    ?>
    <!-- Main content area -->
    <!-- Added min-w-0 to allow this flex item to shrink correctly -->
    <div class="flex flex-col flex-1 min-w-0">
        <!-- Header -->
        <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
            <div class="flex items-center">
                <!-- Hamburger menu for small screens -->
                <button id="mobileMenuToggle"
                    class="text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-2 lg:hidden">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                            d="M4 6h16M4 12h16M4 18h16">
                        </path>
                    </svg>
                </button>
                <h2 id="mainContentTitle" class="ml-4 text-2xl font-semibold text-gray-800">Report Times</h2>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Notification bell -->
                <button class="relative p-2 text-gray-500 hover:text-primary-600 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span class="absolute top-0 right-0 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-600"></span>
                    </span>
                </button>
                
                <!-- Help button -->
                <button class="p-2 text-gray-500 hover:text-primary-600 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Content Area -->
        <main id="mainContentArea" class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
            <div class="flex-1 flex flex-col overflow-hidden">
                <div id="reportTimesSection" class="bg-white p-8 rounded-xl shadow-lg max-w-3xl mx-auto w-full animate-fadeIn card-hover flex-1 overflow-y-auto mt-6">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2 text-center">Report Deadlines</h3>
                    <p class="text-gray-500 text-center mb-8">Stay on track with upcoming reporting requirements</p>
                    
                    <div class="space-y-4">
                        <?php if (!empty($reportingPeriods)): ?>
                            <?php foreach ($reportingPeriods as $period): ?>
                                <div class="border border-gray-200 p-5 rounded-lg hover:border-primary-300 transition duration-200">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($period['period_name']); ?> Report:</p>
                                            <p class="text-gray-600 mt-1">Reporting Period: <?php echo date('F j, Y', strtotime($period['start_date'])); ?> - <?php echo date('F j, Y', strtotime($period['end_date'])); ?></p>
                                        </div>
                                        <?php 
                                        $today = new DateTime();
                                        $endDate = new DateTime($period['end_date']);
                                        $startDate = new DateTime($period['start_date']);
                                        $interval = $today->diff($endDate);
                                        $daysRemaining = $interval->days;
                                        
                                        // Determine status based on date
                                        if ($today > $endDate) {
                                            $status = 'Overdue';
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $progress = 100;
                                        } elseif ($today < $startDate) {
                                            $status = 'Upcoming';
                                            $statusClass = 'bg-blue-100 text-blue-800';
                                            $progress = 10;
                                        } else {
                                            $totalPeriod = $startDate->diff($endDate)->days;
                                            $elapsed = $startDate->diff($today)->days;
                                            $progress = min(100, max(0, ($elapsed / $totalPeriod) * 100));
                                            
                                            if ($progress > 75) {
                                                $status = 'Pending';
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                            } else {
                                                $status = 'In Progress';
                                                $statusClass = 'bg-green-100 text-green-800';
                                            }
                                        }
                                        ?>
                                        <span class="px-3 py-1 text-xs font-medium <?php echo $statusClass; ?> rounded-full"><?php echo $status; ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                                        <div class="bg-primary-600 h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-500">
                                        <span><?php echo $daysRemaining; ?> days remaining</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="border border-gray-200 p-5 rounded-lg text-center">
                                <p class="text-gray-600">No reporting periods found for your cluster.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="bg-primary-50 border border-primary-200 p-4 rounded-lg mt-6">
                            <p class="text-sm text-primary-800 text-center">
                                <span class="font-medium">Note:</span> This section shows report times based on your cluster's budget periods. Reports should be submitted within the specified date ranges.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Mobile sidebar toggle functionality for standalone page
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        
        // Toggle sidebar on hamburger menu click (mobile)
        if (mobileMenuToggle && sidebar) {
            mobileMenuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
            });
        }
        
        // Hide sidebar when clicking outside on mobile
        document.addEventListener('click', (event) => {
            if (window.innerWidth < 1024 && sidebar && !sidebar.contains(event.target) && 
                mobileMenuToggle && !mobileMenuToggle.contains(event.target) && 
                !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('-translate-x-full');
            }
        });
    });
    </script>
    
<?php else: ?>
    <!-- This is the included version for index.php -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <div id="reportTimesSection" class="bg-white p-8 rounded-xl shadow-lg max-w-3xl mx-auto w-full animate-fadeIn card-hover flex-1 overflow-y-auto mt-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-2 text-center">Report Deadlines</h3>
            <p class="text-gray-500 text-center mb-8">Stay on track with upcoming reporting requirements</p>
            
            <div class="space-y-4">
                <?php if (!empty($reportingPeriods)): ?>
                    <?php foreach ($reportingPeriods as $period): ?>
                        <div class="border border-gray-200 p-5 rounded-lg hover:border-primary-300 transition duration-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($period['period_name']); ?> Report:</p>
                                    <p class="text-gray-600 mt-1">Reporting Period: <?php echo date('F j, Y', strtotime($period['start_date'])); ?> - <?php echo date('F j, Y', strtotime($period['end_date'])); ?></p>
                                </div>
                                <?php 
                                $today = new DateTime();
                                $endDate = new DateTime($period['end_date']);
                                $startDate = new DateTime($period['start_date']);
                                $interval = $today->diff($endDate);
                                $daysRemaining = $interval->days;
                                
                                // Determine status based on date
                                if ($today > $endDate) {
                                    $status = 'Overdue';
                                    $statusClass = 'bg-red-100 text-red-800';
                                    $progress = 100;
                                } elseif ($today < $startDate) {
                                    $status = 'Upcoming';
                                    $statusClass = 'bg-blue-100 text-blue-800';
                                    $progress = 10;
                                } else {
                                    $totalPeriod = $startDate->diff($endDate)->days;
                                    $elapsed = $startDate->diff($today)->days;
                                    $progress = min(100, max(0, ($elapsed / $totalPeriod) * 100));
                                    
                                    if ($progress > 75) {
                                        $status = 'Pending';
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                    } else {
                                        $status = 'In Progress';
                                        $statusClass = 'bg-green-100 text-green-800';
                                    }
                                }
                                ?>
                                <span class="px-3 py-1 text-xs font-medium <?php echo $statusClass; ?> rounded-full"><?php echo $status; ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                                <div class="bg-primary-600 h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                <span><?php echo $daysRemaining; ?> days remaining</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="border border-gray-200 p-5 rounded-lg text-center">
                        <p class="text-gray-600">No reporting periods found for your cluster.</p>
                    </div>
                <?php endif; ?>
                
                <div class="bg-primary-50 border border-primary-200 p-4 rounded-lg mt-6">
                    <p class="text-sm text-primary-800 text-center">
                        <span class="font-medium">Note:</span> This section shows report times based on your cluster's budget periods. Reports should be submitted within the specified date ranges.
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>