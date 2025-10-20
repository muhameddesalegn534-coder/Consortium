<?php
// Database setup and connection handler
// When INCLUDED_SETUP is defined, acts as connection provider for other files
// When accessed directly, performs full database setup

// Detect execution mode
$included = defined('INCLUDED_SETUP');

if (!$included) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Setup</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            .success { color: #28a745; }
            .error { color: #dc3545; }
            h1 { color: #007bff; }
            ul { background: #e9ecef; padding: 20px; border-radius: 5px; }
            li { margin: 10px 0; }
            a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            a:hover { background: #0056b3; }
        </style>
    </head>
    <body>
    <div class="container">
    <?php
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP password
$dbname = "consortium_hub";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    if (!$included) {
        die("<div class='error'>Connection failed: " . $conn->connect_error . "</div></div></body></html>");
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Database '$dbname' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating database: " . $conn->error . "</p>";
}

// Select database
$conn->select_db($dbname);

// Create budget_preview table with the correct schema
$sql = "CREATE TABLE IF NOT EXISTS budget_preview (
    PreviewID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    BudgetHeading VARCHAR(255) DEFAULT NULL,
    Outcome VARCHAR(255) DEFAULT NULL,
    Activity VARCHAR(255) DEFAULT NULL,
    BudgetLine VARCHAR(255) DEFAULT NULL,
    Description TEXT DEFAULT NULL,
    Partner VARCHAR(255) DEFAULT NULL,
    EntryDate DATE DEFAULT NULL,
    Amount DECIMAL(18,2) DEFAULT NULL,
    PVNumber VARCHAR(50) DEFAULT NULL,
    Documents VARCHAR(255) DEFAULT NULL,
    DocumentPaths TEXT DEFAULT NULL,
    DocumentTypes VARCHAR(500) DEFAULT NULL,
    OriginalNames VARCHAR(500) DEFAULT NULL,
    QuarterPeriod VARCHAR(10) DEFAULT NULL,
    CategoryName VARCHAR(255) DEFAULT NULL,
    OriginalBudget DECIMAL(18,2) DEFAULT NULL,
    RemainingBudget DECIMAL(18,2) DEFAULT NULL,
    ActualSpent DECIMAL(18,2) DEFAULT NULL,
    VariancePercentage DECIMAL(5,2) DEFAULT NULL,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    cluster VARCHAR(255) DEFAULT NULL,
    budget_id INT(11) DEFAULT NULL,
    ForecastAmount DECIMAL(18,2) DEFAULT NULL,
    KEY budget_id (budget_id)
)";

if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Table 'budget_preview' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating table 'budget_preview': " . $conn->error . "</p>";
}

// Create budget_data table with the correct schema
$sql = "CREATE TABLE IF NOT EXISTS budget_data (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    year INT(11) NOT NULL,
    category_name VARCHAR(255) NOT NULL,
    period_name VARCHAR(50) NOT NULL,
    budget DECIMAL(10,2) DEFAULT NULL,
    actual DECIMAL(10,2) DEFAULT NULL,
    forecast DECIMAL(10,2) DEFAULT NULL,
    actual_plus_forecast DECIMAL(10,2) DEFAULT NULL,
    variance_percentage DECIMAL(5,2) DEFAULT NULL,
    quarter_number TINYINT(4) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    certified ENUM('certified','uncertified') DEFAULT 'uncertified',
    cluster VARCHAR(100) DEFAULT NULL,
    year2 INT(11) DEFAULT NULL
)";

if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Table 'budget_data' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating table 'budget_data': " . $conn->error . "</p>";
}

// Create users table with the correct schema
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','finance_officer') NOT NULL DEFAULT 'finance_officer',
    cluster_name VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Table 'users' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating table 'users': " . $conn->error . "</p>";
}

// Create clusters table
$sql = "CREATE TABLE IF NOT EXISTS clusters (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    cluster_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Table 'clusters' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating table 'clusters': " . $conn->error . "</p>";
}

// Check if custom_currency_rate column exists in clusters table, add it if missing
$checkColumn = "SHOW COLUMNS FROM clusters LIKE 'custom_currency_rate'";
$result = $conn->query($checkColumn);
if ($result->num_rows == 0) {
    $addColumn = "ALTER TABLE clusters ADD COLUMN custom_currency_rate TINYINT(1) DEFAULT 0";
    if ($conn->query($addColumn) === TRUE) {
        if (!$included) echo "<p class='success'>Added custom_currency_rate column to clusters table</p>";
    } else {
        if (!$included) echo "<p class='error'>Error adding custom_currency_rate column: " . $conn->error . "</p>";
    }
}

// Create predefined_fields table with cluster_name column
$sql = "CREATE TABLE IF NOT EXISTS predefined_fields (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('dropdown', 'input') NOT NULL,
    field_values TEXT,
    is_active TINYINT(1) DEFAULT 1,
    cluster_name VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_field_cluster (field_name, cluster_name)
)";

if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Table 'predefined_fields' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating table 'predefined_fields': " . $conn->error . "</p>";
}

// Check if cluster_name column exists in predefined_fields table, add it if missing
$checkColumn = "SHOW COLUMNS FROM predefined_fields LIKE 'cluster_name'";
$result = $conn->query($checkColumn);
if ($result->num_rows == 0) {
    $addColumn = "ALTER TABLE predefined_fields ADD COLUMN cluster_name VARCHAR(100) DEFAULT NULL, ADD UNIQUE KEY unique_field_cluster (field_name, cluster_name)";
    if ($conn->query($addColumn) === TRUE) {
        if (!$included) echo "<p class='success'>Added cluster_name column to predefined_fields table</p>";
    } else {
        if (!$included) echo "<p class='error'>Error adding cluster_name column: " . $conn->error . "</p>";
    }
}

// Create certificates_simple table for storing only certificate paths and metadata
$sql = "CREATE TABLE IF NOT EXISTS certificates_simple (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cluster_name VARCHAR(100) NOT NULL,
    year INT(4) NOT NULL,
    certificate_path VARCHAR(500) NOT NULL,
    uploaded_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(255) DEFAULT 'admin'
)";

if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Table 'certificates_simple' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating table 'certificates_simple': " . $conn->error . "</p>";
}

// Create checklist_items table for dynamic checklist management
$sql = "CREATE TABLE IF NOT EXISTS checklist_items (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(255) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    sort_order INT(11) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category_document (category, document_name)
)";

if ($conn->query($sql) === TRUE) {
    if (!$included) echo "<p class='success'>Table 'checklist_items' created/verified successfully</p>";
} else {
    if (!$included) echo "<p class='error'>Error creating table 'checklist_items': " . $conn->error . "</p>";
}

// Insert default checklist items if table is empty
$checkItems = "SELECT COUNT(*) as count FROM checklist_items";
$result = $conn->query($checkItems);
$itemCount = $result->fetch_assoc()['count'];

if ($itemCount == 0) {
    $defaultChecklistItems = [
        // Withholding Tax (WHT) Payments
        ["1 Withholding Tax (WHT) Payments", "Withholding Tax (WHT) Payment Request Form", 1],
        ["1 Withholding Tax (WHT) Payments", "Withholding Tax (WHT) Calculation Sheet", 2],
        ["1 Withholding Tax (WHT) Payments", "Payment Voucher", 3],
        ["1 Withholding Tax (WHT) Payments", "Bank Transfer Request Letter / Cheque Copy", 4],
        ["1 Withholding Tax (WHT) Payments", "Proof of Payment (Bank Transfer Confirmation / Cheque Copy)", 5],
        
        // Income Tax Payments
        ["2 Income Tax Payments", "Income Tax Payment Request Form", 1],
        ["2 Income Tax Payments", "Income Tax Calculation Sheet", 2],
        ["2 Income Tax Payments", "Payment Voucher", 3],
        ["2 Income Tax Payments", "Bank Transfer Request Letter / Cheque Copy", 4],
        ["2 Income Tax Payments", "Proof of Payment (Bank Transfer Confirmation / Cheque Copy)", 5],
        
        // Pension Contribution Payment
        ["3 Pension Contribution Payment", "Pension Calculation Sheet", 1],
        ["3 Pension Contribution Payment", "Pension Payment Slip / Receipt from Tax Authority", 2],
        ["3 Pension Contribution Payment", "Bank Confirmation of Pension Payment", 3],
        
        // Payroll Payments
        ["4 Payroll Payments", "Approved Timesheets / Attendance Records", 1],
        ["4 Payroll Payments", "Payroll Register Sheet ( For Each Project )", 2],
        ["4 Payroll Payments", "Master Payroll Register Sheet", 3],
        ["4 Payroll Payments", "Payslips / Pay Stubs (for each employee) ( If applicable)", 4],
        ["4 Payroll Payments", "Bank Transfer Request Letter", 5],
        ["4 Payroll Payments", "Proof Of Payment", 6],
        ["4 Payroll Payments", "Payment Voucher", 7],
        
        // Telecom Services Payments
        ["5 Telecom Services Payments", "Telecom Service Contract / Agreement (if applicable)", 1],
        ["5 Telecom Services Payments", "Monthly Telecom Bill / Invoice", 2],
        ["5 Telecom Services Payments", "Cost Pro-ration Sheet", 3],
        ["5 Telecom Services Payments", "Payment Request Form (Approved (by authorized person) )", 4],
        ["5 Telecom Services Payments", "Bank transfer Request Letter /Cheque copy", 5],
        ["5 Telecom Services Payments", "Proof of Payment (Bank transfer confirmation/Cheque copy)", 6],
        
        // Rent Payments
        ["6 Rent Payments", "Rental / Lease Agreement", 1],
        ["6 Rent Payments", "Landlord's Invoice / Payment Request", 2],
        ["6 Rent Payments", "Payment Request Form (Approved (by authorized person) )", 3],
        ["6 Rent Payments", "Cost Pro-ration Sheet", 4],
        ["6 Rent Payments", "Bank transfer Request Letter /Cheque copy", 5],
        ["6 Rent Payments", "Proof of Payment (Bank transfer Advice /Cheque copy)", 6],
        ["6 Rent Payments", "Withholding Tax (WHT) Receipt (if applicable)", 7],
        
        // Consultant Payments
        ["7 Consultant Payments", "Consultant Service Contract Agreement", 1],
        ["7 Consultant Payments", "Scope of Work (SOW) / Terms of Reference (TOR)", 2],
        ["7 Consultant Payments", "Consultant Invoice (if applicable)", 3],
        ["7 Consultant Payments", "Consultant Service accomplishment Activity report / Progress Report", 4],
        ["7 Consultant Payments", "Payment Request Form (Approved)", 5],
        ["7 Consultant Payments", "Proof of Payment (Bank transfer confirmation/Cheque copy)", 6],
        ["7 Consultant Payments", "Withholding Tax (WHT) Receipt (if applicable)", 7],
        ["7 Consultant Payments", "Paymnet Voucher", 8],
        
        // Freight Transportation
        ["8 Freight Transportation", "Purchase request", 1],
        ["8 Freight Transportation", "Quotation request (filled in and sent to suppliers)", 2],
        ["8 Freight Transportation", "Quotation (received back, signed and stamped)", 3],
        ["8 Freight Transportation", "Attached proforma invoices in a sealed envelope", 4],
        ["8 Freight Transportation", "Proformas with all formalities, including trade license", 5],
        ["8 Freight Transportation", "Competitive bid analysis (CBA) signed and approved", 6],
        ["8 Freight Transportation", "Contract agreement or purchase order", 7],
        ["8 Freight Transportation", "Payment request form", 8],
        ["8 Freight Transportation", "Original waybill", 9],
        ["8 Freight Transportation", "Goods received notes", 10],
        ["8 Freight Transportation", "Cash receipt invoice (with Organizational TIN)", 11],
        ["8 Freight Transportation", "Cheque copy or bank transfer letter from vendor", 12],
        ["8 Freight Transportation", "Payment voucher", 13],
        
        // Vehicle Rental
        ["9 Vehicle Rental", "Purchase request for rental service", 1],
        ["9 Vehicle Rental", "Quotation request (filled in and sent to suppliers)", 2],
        ["9 Vehicle Rental", "Quotation (received back, signed and stamped)", 3],
        ["9 Vehicle Rental", "Attached proforma invoices in a sealed envelope", 4],
        ["9 Vehicle Rental", "Proformas with all formalities, including trade license", 5],
        ["9 Vehicle Rental", "Competitive bid analysis (CBA) signed and approved", 6],
        ["9 Vehicle Rental", "Contract agreement or purchase order", 7],
        ["9 Vehicle Rental", "Payment request form", 8],
        ["9 Vehicle Rental", "Summary of payments sheet", 9],
        ["9 Vehicle Rental", "Signed and approved log book sheet", 10],
        ["9 Vehicle Rental", "Vehicle goods-outward inspection certificate", 11],
        ["9 Vehicle Rental", "Withholding receipt (for amounts over ETB 10,000)", 12],
        ["9 Vehicle Rental", "Cash receipt invoice (with Organizational TIN)", 13],
        ["9 Vehicle Rental", "Cheque copy or bank transfer letter from vendor", 14],
        ["9 Vehicle Rental", "Payment voucher", 15],
        
        // Training, Workshop and Related
        ["10 Training, Workshop and Related", "Training approved by Program Manager", 1],
        ["10 Training, Workshop and Related", "Participant invitation letters from government parties", 2],
        ["10 Training, Workshop and Related", "Fully completed attendance sheet", 3],
        ["10 Training, Workshop and Related", "Manager's signature", 4],
        ["10 Training, Workshop and Related", "Approved payment rate (or justified reason for a different rate)", 5],
        ["10 Training, Workshop and Related", "Letter from government for fuel (if applicable)", 6],
        ["10 Training, Workshop and Related", "Activity (training) report", 7],
        ["10 Training, Workshop and Related", "Cash receipt or bank advice (if refund applicable)", 8],
        ["10 Training, Workshop and Related", "Expense settlement sheet with all information", 9],
        ["10 Training, Workshop and Related", "All required signatures on templates", 10],
        ["10 Training, Workshop and Related", "All documents stamped \"paid\"", 11],
        ["10 Training, Workshop and Related", "All documents are original (or cross-referenced if not)", 12],
        ["10 Training, Workshop and Related", "TIN and company name on receipt", 13],
        ["10 Training, Workshop and Related", "Check dates and all information on receipts", 14],
        
        // Procurement of Services
        ["11 Procurement of Services", "Purchase requisition", 1],
        ["11 Procurement of Services", "Quotation request (filled in and sent to suppliers)", 2],
        ["11 Procurement of Services", "Quotation (received back, signed and stamped)", 3],
        ["11 Procurement of Services", "Attached proforma invoices in a sealed envelope", 4],
        ["11 Procurement of Services", "Proformas with all formalities, including trade license", 5],
        ["11 Procurement of Services", "Competitive bid analysis (CBA) signed and approved", 6],
        ["11 Procurement of Services", "Contract agreement or purchase order", 7],
        ["11 Procurement of Services", "Payment request form", 8],
        ["11 Procurement of Services", "Withholding receipt (for amounts over ETB 10,000)", 9],
        ["11 Procurement of Services", "Cash receipt invoice (with Organizational TIN)", 10],
        ["11 Procurement of Services", "Service accomplishment report", 11],
        ["11 Procurement of Services", "Cheque copy or bank transfer letter from vendor", 12],
        ["11 Procurement of Services", "Payment voucher", 13],
        
        // Procurement of Goods
        ["12 Procurement of Goods", "Purchase request", 1],
        ["12 Procurement of Goods", "Quotation request (filled in and sent to suppliers)", 2],
        ["12 Procurement of Goods", "Quotation (received back, signed and stamped)", 3],
        ["12 Procurement of Goods", "Attached proforma invoices in a sealed envelope", 4],
        ["12 Procurement of Goods", "Proformas with all formalities, including trade license", 5],
        ["12 Procurement of Goods", "Competitive bid analysis (CBA) signed and approved", 6],
        ["12 Procurement of Goods", "purchase order", 7],
        ["12 Procurement of Goods", "Contract agreement or Framework Agreement", 8],
        ["12 Procurement of Goods", "Payment request form", 9],
        ["12 Procurement of Goods", "Withholding receipt (for amounts over ETB 20,000)", 10],
        ["12 Procurement of Goods", "Cash receipt invoice (with Organizational TIN)", 11],
        ["12 Procurement of Goods", "Goods received note (GRN) or delivery note", 12],
        ["12 Procurement of Goods", "Cheque copy or bank transfer letter from vendor", 13],
        ["12 Procurement of Goods", "Payment voucher", 14]
    ];
    
    foreach ($defaultChecklistItems as $item) {
        $insertItem = "INSERT INTO checklist_items (category, document_name, sort_order) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertItem);
        $stmt->bind_param("ssi", $item[0], $item[1], $item[2]);
        
        if (!$stmt->execute()) {
            if (!$included) echo "<p class='error'>Error inserting checklist item: " . $conn->error . "</p>";
        }
    }
    
    if (!$included) echo "<p class='success'>Default checklist items inserted successfully</p>";
}

// Insert default admin user if not exists
$adminEmail = "admin@gmail.com";
$adminPassword = "1234"; // Plain text password as requested
$checkUser = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($checkUser);
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $insertAdmin = "INSERT INTO users (username, email, password, role, cluster_name) VALUES (?, ?, ?, 'admin', NULL)";
    $stmt = $conn->prepare($insertAdmin);
    $adminUsername = "admin";
    $stmt->bind_param("sss", $adminUsername, $adminEmail, $adminPassword);
    
    if ($stmt->execute()) {
        if (!$included) echo "<p class='success'>Default admin user created successfully</p>";
    } else {
        if (!$included) echo "<p class='error'>Error creating default admin user: " . $conn->error . "</p>";
    }
} else {
    if (!$included) echo "<p class='success'>Default admin user already exists</p>";
}

// Insert default finance officer user for Woldiya cluster if not exists
$financeEmail = "finance@woldiya.com";
$financePassword = "1234"; // Plain text password as requested
$checkFinanceUser = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($checkFinanceUser);
$stmt->bind_param("s", $financeEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $insertFinance = "INSERT INTO users (username, email, password, role, cluster_name, is_active) VALUES (?, ?, ?, 'finance_officer', ?, 1)";
    $stmt = $conn->prepare($insertFinance);
    $financeUsername = "woldiya_finance";
    $clusterName = "Woldiya"; // Define the variable here
    $stmt->bind_param("ssss", $financeUsername, $financeEmail, $financePassword, $clusterName);
    
    if ($stmt->execute()) {
        if (!$included) echo "<p class='success'>Default finance officer user for Woldiya cluster created successfully</p>";
    } else {
        if (!$included) echo "<p class='error'>Error creating default finance officer user: " . $conn->error . "</p>";
    }
} else {
    // Update existing finance officer user to ensure all fields are correct
    $updateFinance = "UPDATE users SET is_active = 1, password = ?, cluster_name = ? WHERE email = ?";
    $stmt = $conn->prepare($updateFinance);
    $clusterName = "Woldiya"; // Define the variable here
    $stmt->bind_param("sss", $financePassword, $clusterName, $financeEmail);
    
    if ($stmt->execute()) {
        if (!$included) echo "<p class='success'>Default finance officer user updated successfully</p>";
    } else {
        if (!$included) echo "<p class='error'>Error updating default finance officer user: " . $conn->error . "</p>";
    }
}

// Update existing predefined_fields to have cluster_name = 'Woldiya' where it's NULL
$updateFields = "UPDATE predefined_fields SET cluster_name = 'Woldiya' WHERE cluster_name IS NULL";
if ($conn->query($updateFields) === TRUE) {
    if (!$included) echo "<p class='success'>Updated existing predefined fields with cluster 'Woldiya'</p>";
} else {
    if (!$included) echo "<p class='error'>Error updating predefined fields: " . $conn->error . "</p>";
}

if (!$included) {
    ?>
    </div>
    <div style="text-align: center; margin-top: 20px;">
        <a href="admin_predefined_fields.php">Go to Admin Page</a>
        <a href="financial_report_section.php">Go to Financial Report</a>
        <a href="login.php">Go to Login</a>
    </div>
    </body>
    </html>
    <?php
}
?>