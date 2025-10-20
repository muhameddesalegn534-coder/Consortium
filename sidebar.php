<!-- Sidebar -->
 
<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-primary-800 to-primary-900 text-white transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col lg:translate-x-0 lg:static lg:inset-0 rounded-br-xl shadow-xl overflow-y-auto lg:overflow-visible h-full lg:h-auto">
    <div class="flex items-center justify-between h-20 bg-primary-900 rounded-br-xl shadow-md flex-shrink-0 px-4">
        <h1 class="text-2xl font-bold tracking-wide">Consortium Hub</h1>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-2 mt-4 overflow-y-auto">
        
        <!-- Upload Report -->
        <a href="upload_report_section.php"
            class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
            <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5m0 0l5 5m-5-5v12"/>
            </svg>
            <span class="group-hover:text-white">Upload Report</span>
        </a>

        <!-- Report Times -->
        <a href="report_times_section.php"
            class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
            <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="group-hover:text-white">Report Times</span>
        </a>

        <!-- Financial Report -->
        <a href="financial_report_section.php"
            class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
            <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10v1m0 10v1m-6-9h12"/>
            </svg>
            <span class="group-hover:text-white">Transaction</span>
        </a>

        <!-- Forecast -->
        <a href="forecast_budget_table.php"
            class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
            <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 17v-2m3 2v-4m3 4v-6M4 21h16"/>
            </svg>
            <span class="group-hover:text-white">Forecast</span>
        </a>
<a href="history.php"
   class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
    <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span class="group-hover:text-white">Transaction History</span>
</a>

        <!-- Bank Reconciliation -->
        <a href="bank_reconciliation.php"
            class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
            <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            <span class="group-hover:text-white">Bank Reconciliation</span>
        </a>

        <!-- Admin Section - Only visible to admins -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="mt-6 pt-4 border-t border-primary-700">
            <p class="px-4 mb-2 text-xs font-semibold text-primary-300 uppercase tracking-wider">Administration</p>
            
            <!-- Admin - Messages -->
            <?php
            // Initialize unread count
            $unread_count = 0;
            
            // Only try to count messages if we have a database connection
            if (isset($conn)) {
                try {
                    // For PDO connection (config.php)
                    if ($conn instanceof PDO) {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE is_read = 0");
                        $stmt->execute();
                        $unread_count = $stmt->fetchColumn();
                    } 
                    // For MySQLi connection (setup_database.php)
                    else if ($conn instanceof mysqli) {
                        $result = $conn->query("SELECT COUNT(*) as count FROM messages WHERE is_read = 0");
                        if ($result) {
                            $row = $result->fetch_assoc();
                            $unread_count = $row['count'];
                        }
                    }
                } catch (Exception $e) {
                    // Silently fail if there's an error - we don't want to break the sidebar
                    $unread_count = 0;
                }
            }
            ?>
            
            <!-- Admin - Predefined Fields -->
            <a href="admin_predefined_fields.php"
                class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
                <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="group-hover:text-white">Predefined Fields</span>
            </a>
            
            <!-- Admin - Checklist Management -->
       
            <!-- Admin - Budget Management -->
            <a href="admin_budget_management.php"
                class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
                <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span class="group-hover:text-white">Budget Management</span>
            </a>
            
            <!-- Admin - Project Documents -->
            <a href="admin_project_documents.php"
                class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
                <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span class="group-hover:text-white">Project Documents</span>
            </a>
            
            <!-- Admin - Certificates -->
            <a href="admin_certificates.php"
                class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
                <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="group-hover:text-white">Approved Budgets</span>
            </a>
            
            <!-- Admin - Bank Reconciliation -->
            <a href="admin_bank_reconciliation.php"
                class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
                <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <span class="group-hover:text-white">Bank Reconciliation</span>
            </a>

<a href="admin_currency_management.php"
   class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
    <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3 
                 3-1.343 3-3-1.343-3-3-3zm0 9v2m0-18v2m-7 7H3m18 0h-2
                 M5.64 5.64l-1.42-1.42m14.14 14.14l-1.42-1.42M18.36 5.64l1.42-1.42
                 M5.64 18.36l-1.42 1.42"/>
    </svg>
    <span class="group-hover:text-white">Currency</span>
</a>


            <a href="admin_messages.php"
                class="flex items-center w-full px-4 py-3 text-primary-100 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200 group">
                <svg class="w-5 h-5 mr-3 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                </svg>
                <span class="group-hover:text-white">Messages</span>
                <?php if ($unread_count > 0): ?>
                    <span class="ml-auto inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 bg-red-600 rounded-full"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            
        </div>
        <?php endif; ?>
    </nav>

    <!-- User Section -->
    <div class="flex-shrink-0 p-4 border-t border-primary-700 pb-6">
        <div class="flex items-center px-2 py-3">
            <img class="w-10 h-10 rounded-full border-2 border-primary-600"
                src="https://placehold.co/40x40/1e40af/ffffff?text=<?php echo isset($_SESSION['username']) ? substr($_SESSION['username'], 0, 2) : 'U'; ?>" alt="User Avatar">
            <div class="ml-3 flex-1">
                <?php if (isset($_SESSION['username'])): ?>
                    <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <p class="text-xs text-primary-300"><?php echo isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'User'; ?></p>
                <?php else: ?>
                    <p class="text-sm font-medium text-white">Guest</p>
                    <p class="text-xs text-primary-300">User</p>
                <?php endif; ?>
            </div>
            <?php if (isset($_SESSION['username'])): ?>
                <a href="logout.php" class="ml-2 text-primary-300 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <br class="lg:hidden">
<br class="lg:hidden">
</aside>