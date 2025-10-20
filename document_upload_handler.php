<?php
// Handle document uploads
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Include database connection
include 'config.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $documentType = $_POST['documentType'] ?? '';
    $customDocumentName = $_POST['customDocumentName'] ?? null;
    $userCluster = $_SESSION['cluster_name'] ?? 'No Cluster Assigned';
    
    // Progress report fields (all optional now)
    $progressTitle = $_POST['progressTitle'] ?? null;
    $progressDate = $_POST['progressDate'] ?? null;
    
    // New progress report fields
    $summaryAchievements = $_POST['summaryAchievements'] ?? null;
    $operatingContext = $_POST['operatingContext'] ?? null;
    $outcomesOutputs = $_POST['outcomesAndOutputs'] ?? null;
    $challengesDescription = $_POST['challengesDescription'] ?? null;
    $mitigationMeasures = $_POST['mitigationMeasures'] ?? null;
    $goodPractices = $_POST['goodPractices'] ?? null;
    $spotlightNarrative = $_POST['spotlightNarrative'] ?? null;
    
    // Financial report fields
    $expenditureIssues = $_POST['expenditureIssues'] ?? null;
    
    // Other document fields
    $otherTitle = $_POST['otherTitle'] ?? null;
    $otherDate = $_POST['otherDate'] ?? null;
    
    // Validate required fields
    if (empty($documentType)) {
        throw new Exception('Document type is required. Please select a document type from the dropdown.');
    }
    
    // Create upload directories if they don't exist
    $documentUploadDir = 'uploads/documents/';
    $imageUploadDir = 'uploads/images/';
    
    if (!is_dir($documentUploadDir)) {
        if (!mkdir($documentUploadDir, 0755, true)) {
            throw new Exception('Failed to create documents upload directory');
        }
    }
    
    if (!is_dir($imageUploadDir)) {
        if (!mkdir($imageUploadDir, 0755, true)) {
            throw new Exception('Failed to create images upload directory');
        }
    }
    
    // Handle progress document file uploads
    $progressDocumentFileNames = [];
    $progressDocumentFilePaths = [];
    
    if (isset($_FILES['progressDocumentFiles']) && is_array($_FILES['progressDocumentFiles']['name']) && count($_FILES['progressDocumentFiles']['name']) > 0) {
        for ($i = 0; $i < count($_FILES['progressDocumentFiles']['name']); $i++) {
            if ($_FILES['progressDocumentFiles']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue; // Skip if no file was uploaded
            }
            
            $fileName = $_FILES['progressDocumentFiles']['name'][$i];
            $fileTmpName = $_FILES['progressDocumentFiles']['tmp_name'][$i];
            $fileSize = $_FILES['progressDocumentFiles']['size'][$i];
            $fileError = $_FILES['progressDocumentFiles']['error'][$i];
            $fileType = $_FILES['progressDocumentFiles']['type'][$i];
            
            // Check for upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading progress document file: $fileName (Error code: $fileError)");
            }
            
            // Validate file size (10MB max)
            if ($fileSize > 10 * 1024 * 1024) {
                throw new Exception("Progress document file too large: $fileName (max 10MB)");
            }
            
            // Validate file type
            $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                             'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Invalid progress document file type for: $fileName. Allowed types: PDF, DOCX, XLSX");
            }
            
            // Generate unique file name
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $documentUploadDir . $newFileName;
            
            // Move uploaded file to destination
            if (!move_uploaded_file($fileTmpName, $filePath)) {
                throw new Exception("Failed to move uploaded progress document file: $fileName");
            }
            
            $progressDocumentFileNames[] = $fileName;
            $progressDocumentFilePaths[] = $filePath;
        }
    }
    
    // Handle image file uploads
    $imageFileNames = [];
    $imageFilePaths = [];
    $photoTitles = [];
    
    if (isset($_FILES['imageFiles']) && is_array($_FILES['imageFiles']['name']) && count($_FILES['imageFiles']['name']) > 0) {
        for ($i = 0; $i < count($_FILES['imageFiles']['name']); $i++) {
            if ($_FILES['imageFiles']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue; // Skip if no file was uploaded
            }
            
            $fileName = $_FILES['imageFiles']['name'][$i];
            $fileTmpName = $_FILES['imageFiles']['tmp_name'][$i];
            $fileSize = $_FILES['imageFiles']['size'][$i];
            $fileError = $_FILES['imageFiles']['error'][$i];
            $fileType = $_FILES['imageFiles']['type'][$i];
            
            // Check for upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading image file: $fileName (Error code: $fileError)");
            }
            
            // Validate file size (10MB max)
            if ($fileSize > 10 * 1024 * 1024) {
                throw new Exception("Image file too large: $fileName (max 10MB)");
            }
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Invalid image file type for: $fileName. Allowed types: JPG, JPEG, PNG");
            }
            
            // Generate unique file name
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $imageUploadDir . $newFileName;
            
            // Move uploaded file to destination
            if (!move_uploaded_file($fileTmpName, $filePath)) {
                throw new Exception("Failed to move uploaded image file: $fileName");
            }
            
            $imageFileNames[] = $fileName;
            $imageFilePaths[] = $filePath;
            $photoTitles[] = ''; // Store empty string as photo title for each image
        }
    }
    
    // Handle results framework file upload
    $resultsFrameworkFileNames = [];
    $resultsFrameworkFilePaths = [];
    
    if (isset($_FILES['resultsFrameworkFile']) && $_FILES['resultsFrameworkFile']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileName = $_FILES['resultsFrameworkFile']['name'];
        $fileTmpName = $_FILES['resultsFrameworkFile']['tmp_name'];
        $fileSize = $_FILES['resultsFrameworkFile']['size'];
        $fileError = $_FILES['resultsFrameworkFile']['error'];
        $fileType = $_FILES['resultsFrameworkFile']['type'];
        
        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception("Error uploading results framework file: $fileName (Error code: $fileError)");
        }
        
        // Validate file size (10MB max)
        if ($fileSize > 10 * 1024 * 1024) {
            throw new Exception("Results framework file too large: $fileName (max 10MB)");
        }
        
        // Validate file type
        $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Invalid results framework file type for: $fileName. Allowed types: PDF, DOCX, XLSX");
        }
        
        // Generate unique file name
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $documentUploadDir . $newFileName;
        
        // Move uploaded file to destination
        if (!move_uploaded_file($fileTmpName, $filePath)) {
            throw new Exception("Failed to move uploaded results framework file: $fileName");
        }
        
        $resultsFrameworkFileNames[] = $fileName;
        $resultsFrameworkFilePaths[] = $filePath;
    }
    
    // Handle risk matrix file upload
    $riskMatrixFileNames = [];
    $riskMatrixFilePaths = [];
    
    if (isset($_FILES['riskMatrixFile']) && $_FILES['riskMatrixFile']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileName = $_FILES['riskMatrixFile']['name'];
        $fileTmpName = $_FILES['riskMatrixFile']['tmp_name'];
        $fileSize = $_FILES['riskMatrixFile']['size'];
        $fileError = $_FILES['riskMatrixFile']['error'];
        $fileType = $_FILES['riskMatrixFile']['type'];
        
        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception("Error uploading risk matrix file: $fileName (Error code: $fileError)");
        }
        
        // Validate file size (10MB max)
        if ($fileSize > 10 * 1024 * 1024) {
            throw new Exception("Risk matrix file too large: $fileName (max 10MB)");
        }
        
        // Validate file type
        $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Invalid risk matrix file type for: $fileName. Allowed types: PDF, DOCX, XLSX");
        }
        
        // Generate unique file name
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $documentUploadDir . $newFileName;
        
        // Move uploaded file to destination
        if (!move_uploaded_file($fileTmpName, $filePath)) {
            throw new Exception("Failed to move uploaded risk matrix file: $fileName");
        }
        
        $riskMatrixFileNames[] = $fileName;
        $riskMatrixFilePaths[] = $filePath;
    }
    
    // Handle spotlight photos upload
    $spotlightPhotoFileNames = [];
    $spotlightPhotoFilePaths = [];
    
    if (isset($_FILES['spotlightPhotos']) && is_array($_FILES['spotlightPhotos']['name']) && count($_FILES['spotlightPhotos']['name']) > 0) {
        for ($i = 0; $i < count($_FILES['spotlightPhotos']['name']); $i++) {
            if ($_FILES['spotlightPhotos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue; // Skip if no file was uploaded
            }
            
            $fileName = $_FILES['spotlightPhotos']['name'][$i];
            $fileTmpName = $_FILES['spotlightPhotos']['tmp_name'][$i];
            $fileSize = $_FILES['spotlightPhotos']['size'][$i];
            $fileError = $_FILES['spotlightPhotos']['error'][$i];
            $fileType = $_FILES['spotlightPhotos']['type'][$i];
            
            // Check for upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading spotlight photo: $fileName (Error code: $fileError)");
            }
            
            // Validate file size (5MB max)
            if ($fileSize > 5 * 1024 * 1024) {
                throw new Exception("Spotlight photo too large: $fileName (max 5MB)");
            }
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Invalid spotlight photo type for: $fileName. Allowed types: JPG, JPEG, PNG, GIF");
            }
            
            // Generate unique file name
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $imageUploadDir . $newFileName;
            
            // Move uploaded file to destination
            if (!move_uploaded_file($fileTmpName, $filePath)) {
                throw new Exception("Failed to move uploaded spotlight photo: $fileName");
            }
            
            $spotlightPhotoFileNames[] = $fileName;
            $spotlightPhotoFilePaths[] = $filePath;
        }
    }
    
    // Handle financial report file upload
    $financialReportFileNames = [];
    $financialReportFilePaths = [];
    
    if (isset($_FILES['financialReportFile']) && $_FILES['financialReportFile']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileName = $_FILES['financialReportFile']['name'];
        $fileTmpName = $_FILES['financialReportFile']['tmp_name'];
        $fileSize = $_FILES['financialReportFile']['size'];
        $fileError = $_FILES['financialReportFile']['error'];
        $fileType = $_FILES['financialReportFile']['type'];
        
        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception("Error uploading financial report file: $fileName (Error code: $fileError)");
        }
        
        // Validate file size (10MB max)
        if ($fileSize > 10 * 1024 * 1024) {
            throw new Exception("Financial report file too large: $fileName (max 10MB)");
        }
        
        // Validate file type
        $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Invalid financial report file type for: $fileName. Allowed types: PDF, XLSX");
        }
        
        // Generate unique file name
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $documentUploadDir . $newFileName;
        
        // Move uploaded file to destination
        if (!move_uploaded_file($fileTmpName, $filePath)) {
            throw new Exception("Failed to move uploaded financial report file: $fileName");
        }
        
        $financialReportFileNames[] = $fileName;
        $financialReportFilePaths[] = $filePath;
    }
    
    // Handle other document files upload
    $otherFileNames = [];
    $otherFilePaths = [];
    
    if (isset($_FILES['otherFiles']) && is_array($_FILES['otherFiles']['name']) && count($_FILES['otherFiles']['name']) > 0) {
        for ($i = 0; $i < count($_FILES['otherFiles']['name']); $i++) {
            if ($_FILES['otherFiles']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue; // Skip if no file was uploaded
            }
            
            $fileName = $_FILES['otherFiles']['name'][$i];
            $fileTmpName = $_FILES['otherFiles']['tmp_name'][$i];
            $fileSize = $_FILES['otherFiles']['size'][$i];
            $fileError = $_FILES['otherFiles']['error'][$i];
            $fileType = $_FILES['otherFiles']['type'][$i];
            
            // Check for upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading other file: $fileName (Error code: $fileError)");
            }
            
            // Validate file size (10MB max)
            if ($fileSize > 10 * 1024 * 1024) {
                throw new Exception("Other file too large: $fileName (max 10MB)");
            }
            
            // Generate unique file name
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $documentUploadDir . $newFileName;
            
            // Move uploaded file to destination
            if (!move_uploaded_file($fileTmpName, $filePath)) {
                throw new Exception("Failed to move uploaded other file: $fileName");
            }
            
            $otherFileNames[] = $fileName;
            $otherFilePaths[] = $filePath;
        }
    }
    
    // Check if at least one field or file is filled
    $hasContent = !empty($progressDocumentFileNames) || !empty($imageFileNames) ||
                  !empty($progressTitle) || !empty($progressDate) ||
                  !empty($summaryAchievements) || !empty($operatingContext) || !empty($outcomesOutputs) ||
                  !empty($challengesDescription) || !empty($mitigationMeasures) ||
                  !empty($goodPractices) || !empty($spotlightNarrative) ||
                  !empty($expenditureIssues) ||
                  !empty($otherTitle) || !empty($otherDate) || !empty($otherFileNames);
    
    if (!$hasContent) {
        throw new Exception('At least one field or file must be filled to submit the report. Please upload at least one file or fill in at least one form field.');
    }
    
    // Insert record into database
    $sql = "INSERT INTO project_documents (
                document_type, 
                custom_document_name, 
                cluster, 
                document_file_names, 
                document_file_paths, 
                image_file_names, 
                image_file_paths, 
                photo_titles,
                progress_title,
                progress_date,
                summary_achievements,
                operating_context,
                outcomes_outputs,
                results_framework_file_names,
                results_framework_file_paths,
                challenges_description,
                mitigation_measures,
                risk_matrix_file_names,
                risk_matrix_file_paths,
                good_practices,
                spotlight_narrative,
                spotlight_photo_file_names,
                spotlight_photo_file_paths,
                financial_report_file_names,
                financial_report_file_paths,
                expenditure_issues,
                other_title,
                other_date,
                other_file_names,
                other_file_paths,
                uploaded_by
            ) VALUES (
                :document_type, 
                :custom_document_name, 
                :cluster, 
                :document_file_names, 
                :document_file_paths, 
                :image_file_names, 
                :image_file_paths, 
                :photo_titles,
                :progress_title,
                :progress_date,
                :summary_achievements,
                :operating_context,
                :outcomes_outputs,
                :results_framework_file_names,
                :results_framework_file_paths,
                :challenges_description,
                :mitigation_measures,
                :risk_matrix_file_names,
                :risk_matrix_file_paths,
                :good_practices,
                :spotlight_narrative,
                :spotlight_photo_file_names,
                :spotlight_photo_file_paths,
                :financial_report_file_names,
                :financial_report_file_paths,
                :expenditure_issues,
                :other_title,
                :other_date,
                :other_file_names,
                :other_file_paths,
                :uploaded_by
            )";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':document_type', $documentType);
    $stmt->bindParam(':custom_document_name', $customDocumentName);
    $stmt->bindParam(':cluster', $userCluster);
    
    // Store JSON encoded values in variables to avoid "Only variables should be passed by reference" error
    $jsonProgressDocumentFileNames = json_encode($progressDocumentFileNames);
    $jsonProgressDocumentFilePaths = json_encode($progressDocumentFilePaths);
    $jsonImageFileNames = json_encode($imageFileNames);
    $jsonImageFilePaths = json_encode($imageFilePaths);
    $jsonPhotoTitles = json_encode($photoTitles);
    $jsonResultsFrameworkFileNames = json_encode($resultsFrameworkFileNames);
    $jsonResultsFrameworkFilePaths = json_encode($resultsFrameworkFilePaths);
    $jsonRiskMatrixFileNames = json_encode($riskMatrixFileNames);
    $jsonRiskMatrixFilePaths = json_encode($riskMatrixFilePaths);
    $jsonSpotlightPhotoFileNames = json_encode($spotlightPhotoFileNames);
    $jsonSpotlightPhotoFilePaths = json_encode($spotlightPhotoFilePaths);
    $jsonFinancialReportFileNames = json_encode($financialReportFileNames);
    $jsonFinancialReportFilePaths = json_encode($financialReportFilePaths);
    $jsonOtherFileNames = json_encode($otherFileNames);
    $jsonOtherFilePaths = json_encode($otherFilePaths);
    
    $stmt->bindParam(':document_file_names', $jsonProgressDocumentFileNames);
    $stmt->bindParam(':document_file_paths', $jsonProgressDocumentFilePaths);
    $stmt->bindParam(':image_file_names', $jsonImageFileNames);
    $stmt->bindParam(':image_file_paths', $jsonImageFilePaths);
    $stmt->bindParam(':photo_titles', $jsonPhotoTitles);
    $stmt->bindParam(':results_framework_file_names', $jsonResultsFrameworkFileNames);
    $stmt->bindParam(':results_framework_file_paths', $jsonResultsFrameworkFilePaths);
    $stmt->bindParam(':risk_matrix_file_names', $jsonRiskMatrixFileNames);
    $stmt->bindParam(':risk_matrix_file_paths', $jsonRiskMatrixFilePaths);
    $stmt->bindParam(':spotlight_photo_file_names', $jsonSpotlightPhotoFileNames);
    $stmt->bindParam(':spotlight_photo_file_paths', $jsonSpotlightPhotoFilePaths);
    $stmt->bindParam(':financial_report_file_names', $jsonFinancialReportFileNames);
    $stmt->bindParam(':financial_report_file_paths', $jsonFinancialReportFilePaths);
    $stmt->bindParam(':other_file_names', $jsonOtherFileNames);
    $stmt->bindParam(':other_file_paths', $jsonOtherFilePaths);
    
    $stmt->bindParam(':progress_title', $progressTitle);
    $stmt->bindParam(':progress_date', $progressDate);
    $stmt->bindParam(':summary_achievements', $summaryAchievements);
    $stmt->bindParam(':operating_context', $operatingContext);
    $stmt->bindParam(':outcomes_outputs', $outcomesOutputs);
    $stmt->bindParam(':challenges_description', $challengesDescription);
    $stmt->bindParam(':mitigation_measures', $mitigationMeasures);
    $stmt->bindParam(':good_practices', $goodPractices);
    $stmt->bindParam(':spotlight_narrative', $spotlightNarrative);
    $stmt->bindParam(':expenditure_issues', $expenditureIssues);
    $stmt->bindParam(':other_title', $otherTitle);
    $stmt->bindParam(':other_date', $otherDate);
    $stmt->bindParam(':uploaded_by', $_SESSION['username']);
    
    if ($stmt->execute()) {
        // Set the correct content type for successful response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Project report uploaded successfully']);
    } else {
        throw new Exception('Failed to save document information to database');
    }
    
} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage()); // Log the error for debugging
    // Set the correct content type for error response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}