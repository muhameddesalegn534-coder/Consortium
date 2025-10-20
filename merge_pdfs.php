<?php
// merge_pdfs.php - Script to merge PDF documents and provide download or inline viewing

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if we want to view inline (preview) or download
$inlineView = isset($_GET['view']) && $_GET['view'] === 'inline';

// Get transaction ID from URL parameter
$transactionId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($transactionId)) {
    header("Location: history.php");
    exit();
}

// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Try to include FPDI/TCPDF library
$fpdiAvailable = false;
try {
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        if (class_exists('setasign\Fpdi\TcpdfFpdi')) {
            $fpdiAvailable = true;
        }
    }
} catch (Exception $e) {
    $fpdiAvailable = false;
}

// Get user role and cluster
$userRole = $_SESSION['role'] ?? '';
$userCluster = $_SESSION['cluster_name'] ?? null;

// Build WHERE clause for cluster filtering
$whereConditions = ["bp.PreviewID = ?"];
$params = [$transactionId];
$paramTypes = "i";

// Add cluster filter for non-admin users
if ($userRole !== 'admin' && $userCluster) {
    $whereConditions[] = "bp.cluster = ?";
    $params[] = $userCluster;
    $paramTypes .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Fetch transaction details with documents
$transactionQuery = "SELECT 
    bp.DocumentPaths,
    bp.DocumentTypes,
    bp.OriginalNames
FROM budget_preview bp 
WHERE " . $whereClause;

$stmt = $conn->prepare($transactionQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$transactionsResult = $stmt->get_result();

// Collect all PDF documents
$pdfDocuments = [];

while ($row = $transactionsResult->fetch_assoc()) {
    // Process document paths, types and original names
    $documentPaths = !empty($row['DocumentPaths']) ? explode(',', $row['DocumentPaths']) : [];
    $documentTypes = !empty($row['DocumentTypes']) ? explode(',', $row['DocumentTypes']) : [];
    $originalNames = !empty($row['OriginalNames']) ? explode(',', $row['OriginalNames']) : [];
    
    // Combine documents
    for ($i = 0; $i < count($documentPaths); $i++) {
        if (!empty($documentPaths[$i])) {
            $ext = pathinfo($documentPaths[$i], PATHINFO_EXTENSION);
            // Only include PDF files
            if (strtolower($ext) === 'pdf') {
                $pdfDocuments[] = [
                    'path' => $documentPaths[$i],
                    'originalName' => isset($originalNames[$i]) ? $originalNames[$i] : 'Unknown.pdf'
                ];
            }
        }
    }
}

// If no PDF documents found
if (empty($pdfDocuments)) {
    if ($inlineView) {
        // Return a simple PDF with error message for inline viewing
        header('Content-Type: application/pdf');
        echo createErrorPdf("No PDF documents found for this transaction.");
        exit();
    } else {
        header("Location: transaction_detail.php?id=" . $transactionId);
        exit();
    }
}

// Merge PDFs using FPDI/TCPDF if available
if ($fpdiAvailable) {
    $pdfData = mergePdfsUsingFpdi($pdfDocuments, $transactionId);
    
    if ($pdfData !== false) {
        // Send the merged PDF data to the browser
        header('Content-Type: application/pdf');
        if (!$inlineView) {
            header('Content-Disposition: attachment; filename="merged_documents_' . $transactionId . '.pdf"');
        } else {
            header('Content-Disposition: inline; filename="merged_documents_' . $transactionId . '.pdf"');
        }
        header('Content-Length: ' . strlen($pdfData));
        
        echo $pdfData;
        exit();
    }
}

// Fallback if FPDI is not available
if ($inlineView) {
    // Return a simple PDF with error message for inline viewing
    header('Content-Type: application/pdf');
    echo createErrorPdf("PDF merging library not available. Please install FPDI/TCPDF library.");
    exit();
} else {
    header("Location: transaction_detail.php?id=" . $transactionId . "&error=fpdi_not_available");
}

// Function to merge PDFs using FPDI/TCPDF and return PDF data
function mergePdfsUsingFpdi($pdfDocuments, $transactionId) {
    try {
        // Create a new FPDI instance
        $pdf = new setasign\Fpdi\TcpdfFpdi();
        
        // Process each PDF document
        foreach ($pdfDocuments as $doc) {
            $filePath = $doc['path'];
            
            // Make sure the file exists and is readable
            if (file_exists($filePath) && is_readable($filePath)) {
                // Get the number of pages in the PDF
                $pageCount = $pdf->setSourceFile($filePath);
                
                // Import and add each page
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    
                    // Create a new page with the same size as the template
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    
                    // Use the imported page as the template
                    $pdf->useTemplate($templateId);
                }
            }
        }
        
        // Return the merged PDF as a string
        return $pdf->Output('', 'S');
    } catch (Exception $e) {
        // Log error and return false
        error_log("PDF merging error: " . $e->getMessage());
        return false;
    }
}

// Function to create a simple error PDF
function createErrorPdf($message) {
    try {
        // Create a new TCPDF instance
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Error');
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', 'B', 16);
        
        // Print a message
        $pdf->Cell(0, 10, 'PDF Merging Error', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(0, 10, $message, 0, 'C');
        
        // Return the PDF as a string
        return $pdf->Output('', 'S');
    } catch (Exception $e) {
        // If TCPDF is not available, return a simple text response
        return "%PDF-1.4\n%\nstream\nBT\n/F1 12 Tf\n100 700 Td\n(Error: $message) Tj\nET\nendstream\nendobj\n";
    }
}
?>