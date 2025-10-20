<?php
// Check if this is a standalone admin page
$adminPages = [
    'admin_predefined_fields.php',
    'admin.php',
    'admin_project_documents.php',
    'ladmin_bank_reconciliation.php',
    'admin_bank_reconciliation.php',
    'admin_certificates.php',
    'admin_dashboard.php',
    'admin_budget_management.php',
    'financial_reports.php',
];

$currentScript = basename($_SERVER['SCRIPT_FILENAME']);
$isStandaloneAdmin = in_array($currentScript, $adminPages);

// If it's a standalone admin page, close the layout wrapper
if ($isStandaloneAdmin) {
    ?>
    </div> <!-- Close .admin-content-area -->
    </div> <!-- Close .admin-layout-wrapper -->
    </body>
    </html>
    <?php
}
?>