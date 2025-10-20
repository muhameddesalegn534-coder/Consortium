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

// Get document ID
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($document_id <= 0) {
    echo "<p>Invalid document ID</p>";
    exit();
}

// Fetch document details
$sql = "SELECT * FROM project_documents WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p>Document not found</p>";
    exit();
}

$document = $result->fetch_assoc();

// Check if this is an AJAX request for modal content
$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check if download all request
if (isset($_GET['download_all'])) {
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        echo "<p>ZipArchive is not available on this server</p>";
        exit();
    }
    
    // Create a text document with all text content
    $textContent = "";
    
    // Add document header information
    $textContent .= "Document Details\n";
    $textContent .= "================\n\n";
    
    if (!empty($document['custom_document_name'])) {
        $textContent .= "Document Name: " . $document['custom_document_name'] . "\n";
    } elseif (!empty($document['progress_title'])) {
        $textContent .= "Progress Title: " . $document['progress_title'] . "\n";
    } elseif (!empty($document['challenge_title'])) {
        $textContent .= "Challenge Title: " . $document['challenge_title'] . "\n";
    } elseif (!empty($document['success_title'])) {
        $textContent .= "Success Title: " . $document['success_title'] . "\n";
    } elseif (!empty($document['other_title'])) {
        $textContent .= "Other Title: " . $document['other_title'] . "\n";
    } else {
        $textContent .= "Document ID: " . $document['id'] . "\n";
    }
    
    $textContent .= "Cluster: " . $document['cluster'] . "\n";
    $textContent .= "Uploaded by: " . $document['uploaded_by'] . "\n";
    $textContent .= "Uploaded at: " . date('M j, Y \\a\\t g:i A', strtotime($document['uploaded_at'])) . "\n\n";
    
    // Add text content sections
    $textContent .= "Text Content\n";
    $textContent .= "============\n\n";
    
    // Progress Report Section
    if (!empty($document['progress_title']) || !empty($document['progress_date'])) {
        $textContent .= "Progress Report\n";
        $textContent .= "---------------\n";
        if (!empty($document['progress_title'])) {
            $textContent .= "Title: " . $document['progress_title'] . "\n";
        }
        if (!empty($document['progress_date'])) {
            $textContent .= "Date: " . date('M j, Y', strtotime($document['progress_date'])) . "\n";
        }
        $textContent .= "\n";
    }
    
    // Summary of Achievements
    if (!empty($document['summary_achievements'])) {
        $textContent .= "Summary of Achievements\n";
        $textContent .= "-----------------------\n";
        $textContent .= $document['summary_achievements'] . "\n\n";
    }
    
    // Operating Context
    if (!empty($document['operating_context'])) {
        $textContent .= "Operating Context\n";
        $textContent .= "-----------------\n";
        $textContent .= $document['operating_context'] . "\n\n";
    }
    
    // Outcomes and Outputs
    if (!empty($document['outcomes_outputs'])) {
        $textContent .= "Outcomes and Outputs\n";
        $textContent .= "--------------------\n";
        $textContent .= $document['outcomes_outputs'] . "\n\n";
    }
    
    // Challenges and Risks
    if (!empty($document['challenges_description']) || !empty($document['mitigation_measures'])) {
        $textContent .= "Challenges and Risks\n";
        $textContent .= "--------------------\n";
        if (!empty($document['challenges_description'])) {
            $textContent .= "Challenges Description:\n";
            $textContent .= $document['challenges_description'] . "\n";
        }
        if (!empty($document['mitigation_measures'])) {
            $textContent .= "Mitigation Measures:\n";
            $textContent .= $document['mitigation_measures'] . "\n";
        }
        $textContent .= "\n";
    }
    
    // Good Practices and Lessons Learnt
    if (!empty($document['good_practices'])) {
        $textContent .= "Good Practices and Lessons Learnt\n";
        $textContent .= "---------------------------------\n";
        $textContent .= $document['good_practices'] . "\n\n";
    }
    
    // Spotlight Narrative
    if (!empty($document['spotlight_narrative'])) {
        $textContent .= "Spotlight Narrative\n";
        $textContent .= "-------------------\n";
        $textContent .= $document['spotlight_narrative'] . "\n\n";
    }
    
    // Challenge Section
    if (!empty($document['challenge_title']) || !empty($document['challenge_description'])) {
        $textContent .= "Challenge\n";
        $textContent .= "---------\n";
        if (!empty($document['challenge_title'])) {
            $textContent .= "Title: " . $document['challenge_title'] . "\n";
        }
        if (!empty($document['challenge_description'])) {
            $textContent .= "Description: " . $document['challenge_description'] . "\n";
        }
        if (!empty($document['challenge_impact'])) {
            $textContent .= "Impact: " . $document['challenge_impact'] . "\n";
        }
        if (!empty($document['proposed_solution'])) {
            $textContent .= "Proposed Solution: " . $document['proposed_solution'] . "\n";
        }
        $textContent .= "\n";
    }
    
    // Success Story Section
    if (!empty($document['success_title']) || !empty($document['success_description'])) {
        $textContent .= "Success Story\n";
        $textContent .= "-------------\n";
        if (!empty($document['success_title'])) {
            $textContent .= "Title: " . $document['success_title'] . "\n";
        }
        if (!empty($document['success_description'])) {
            $textContent .= "Description: " . $document['success_description'] . "\n";
        }
        if (!empty($document['beneficiaries'])) {
            $textContent .= "Beneficiaries: " . number_format($document['beneficiaries']) . "\n";
        }
        if (!empty($document['success_date'])) {
            $textContent .= "Date: " . date('M j, Y', strtotime($document['success_date'])) . "\n";
        }
        $textContent .= "\n";
    }
    
    // Other Document Section
    if (!empty($document['other_title']) || !empty($document['other_date'])) {
        $textContent .= "Other Document\n";
        $textContent .= "--------------\n";
        if (!empty($document['other_title'])) {
            $textContent .= "Title: " . $document['other_title'] . "\n";
        }
        if (!empty($document['other_date'])) {
            $textContent .= "Date: " . date('M j, Y', strtotime($document['other_date'])) . "\n";
        }
        $textContent .= "\n";
    }
    
    // Expenditure Issues
    if (!empty($document['expenditure_issues'])) {
        $textContent .= "Expenditure Issues\n";
        $textContent .= "------------------\n";
        $textContent .= $document['expenditure_issues'] . "\n\n";
    }
    
    // Get file information
    $docFiles = json_decode($document['document_file_names'], true);
    $docPaths = json_decode($document['document_file_paths'], true);
    $imageFiles = json_decode($document['image_file_names'], true);
    $imagePaths = json_decode($document['image_file_paths'], true);
    $financialFiles = json_decode($document['financial_report_file_names'], true);
    $financialPaths = json_decode($document['financial_report_file_paths'], true);
    $resultsFrameworkFiles = json_decode($document['results_framework_file_names'], true);
    $resultsFrameworkPaths = json_decode($document['results_framework_file_paths'], true);
    $riskMatrixFiles = json_decode($document['risk_matrix_file_names'], true);
    $riskMatrixPaths = json_decode($document['risk_matrix_file_paths'], true);
    $spotlightPhotoFiles = json_decode($document['spotlight_photo_file_names'], true);
    $spotlightPhotoPaths = json_decode($document['spotlight_photo_file_paths'], true);
    $otherFiles = json_decode($document['other_file_names'], true);
    $otherPaths = json_decode($document['other_file_paths'], true);
    
    // Filter out any null or empty values
    if (is_array($docFiles)) {
        $docFiles = array_filter($docFiles, function($file) { return !empty($file); });
    }
    if (is_array($docPaths)) {
        $docPaths = array_filter($docPaths, function($path) { return !empty($path) && file_exists($path); });
    }
    if (is_array($imageFiles)) {
        $imageFiles = array_filter($imageFiles, function($file) { return !empty($file); });
    }
    if (is_array($imagePaths)) {
        $imagePaths = array_filter($imagePaths, function($path) { return !empty($path) && file_exists($path); });
    }
    if (is_array($financialFiles)) {
        $financialFiles = array_filter($financialFiles, function($file) { return !empty($file); });
    }
    if (is_array($financialPaths)) {
        $financialPaths = array_filter($financialPaths, function($path) { return !empty($path) && file_exists($path); });
    }
    if (is_array($resultsFrameworkFiles)) {
        $resultsFrameworkFiles = array_filter($resultsFrameworkFiles, function($file) { return !empty($file); });
    }
    if (is_array($resultsFrameworkPaths)) {
        $resultsFrameworkPaths = array_filter($resultsFrameworkPaths, function($path) { return !empty($path) && file_exists($path); });
    }
    if (is_array($riskMatrixFiles)) {
        $riskMatrixFiles = array_filter($riskMatrixFiles, function($file) { return !empty($file); });
    }
    if (is_array($riskMatrixPaths)) {
        $riskMatrixPaths = array_filter($riskMatrixPaths, function($path) { return !empty($path) && file_exists($path); });
    }
    if (is_array($spotlightPhotoFiles)) {
        $spotlightPhotoFiles = array_filter($spotlightPhotoFiles, function($file) { return !empty($file); });
    }
    if (is_array($spotlightPhotoPaths)) {
        $spotlightPhotoPaths = array_filter($spotlightPhotoPaths, function($path) { return !empty($path) && file_exists($path); });
    }
    if (is_array($otherFiles)) {
        $otherFiles = array_filter($otherFiles, function($file) { return !empty($file); });
    }
    if (is_array($otherPaths)) {
        $otherPaths = array_filter($otherPaths, function($path) { return !empty($path) && file_exists($path); });
    }
    
    // Combine all files
    $allFiles = array_merge(
        is_array($docFiles) ? $docFiles : [],
        is_array($imageFiles) ? $imageFiles : [],
        is_array($financialFiles) ? $financialFiles : [],
        is_array($resultsFrameworkFiles) ? $resultsFrameworkFiles : [],
        is_array($riskMatrixFiles) ? $riskMatrixFiles : [],
        is_array($spotlightPhotoFiles) ? $spotlightPhotoFiles : [],
        is_array($otherFiles) ? $otherFiles : []
    );
    
    $allPaths = array_merge(
        is_array($docPaths) ? $docPaths : [],
        is_array($imagePaths) ? $imagePaths : [],
        is_array($financialPaths) ? $financialPaths : [],
        is_array($resultsFrameworkPaths) ? $resultsFrameworkPaths : [],
        is_array($riskMatrixPaths) ? $riskMatrixPaths : [],
        is_array($spotlightPhotoPaths) ? $spotlightPhotoPaths : [],
        is_array($otherPaths) ? $otherPaths : []
    );
    
    // Add file list to text content
    if (count($allFiles) > 0) {
        $textContent .= "Files Included\n";
        $textContent .= "==============\n";
        foreach ($allFiles as $file) {
            $textContent .= "- " . $file . "\n";
        }
        $textContent .= "\n";
    }
    
    // Create zip with all files and the text document
    if (count($allFiles) > 0 && count($allFiles) === count($allPaths)) {
        $zipName = tempnam(sys_get_temp_dir(), 'all_files_') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipName, ZipArchive::CREATE) === TRUE) {
            // Add the text content document
            $textFileName = "document_summary.txt";
            $zip->addFromString($textFileName, $textContent);
            
            // Add all other files
            for ($i = 0; $i < count($allFiles); $i++) {
                if (file_exists($allPaths[$i])) {
                    $zip->addFile($allPaths[$i], basename($allFiles[$i]));
                }
            }
            
            $zip->close();
            
            // Send the zip file to the browser
            if (file_exists($zipName)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="all_files_with_summary.zip"');
                header('Content-Length: ' . filesize($zipName));
                readfile($zipName);
                unlink($zipName); // Delete the temporary zip file
                exit();
            }
        }
    } else if (!empty($textContent)) {
        // If there are no files but there is text content, just download the text file
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="document_summary.txt"');
        echo $textContent;
        exit();
    }
    
    echo "<p>No files available for download</p>";
    exit();
}

// Handle individual file download
if (isset($_GET['download_file'])) {
    $fileType = $_GET['file_type']; // 'document', 'image', 'financial', 'results_framework', 'risk_matrix', 'spotlight_photo', 'other'
    $fileIndex = intval($_GET['file_index']);
    
    switch ($fileType) {
        case 'document':
            $files = json_decode($document['document_file_names'], true);
            $paths = json_decode($document['document_file_paths'], true);
            break;
        case 'image':
            $files = json_decode($document['image_file_names'], true);
            $paths = json_decode($document['image_file_paths'], true);
            break;
        case 'financial':
            $files = json_decode($document['financial_report_file_names'], true);
            $paths = json_decode($document['financial_report_file_paths'], true);
            break;
        case 'results_framework':
            $files = json_decode($document['results_framework_file_names'], true);
            $paths = json_decode($document['results_framework_file_paths'], true);
            break;
        case 'risk_matrix':
            $files = json_decode($document['risk_matrix_file_names'], true);
            $paths = json_decode($document['risk_matrix_file_paths'], true);
            break;
        case 'spotlight_photo':
            $files = json_decode($document['spotlight_photo_file_names'], true);
            $paths = json_decode($document['spotlight_photo_file_paths'], true);
            break;
        case 'other':
            $files = json_decode($document['other_file_names'], true);
            $paths = json_decode($document['other_file_paths'], true);
            break;
        default:
            echo "<p>Invalid file type</p>";
            exit();
    }
    
    if (is_array($files) && is_array($paths) && 
        isset($files[$fileIndex]) && isset($paths[$fileIndex]) && 
        file_exists($paths[$fileIndex])) {
        
        $filePath = $paths[$fileIndex];
        $fileName = basename($files[$fileIndex]);
        
        // Set appropriate headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit();
    }
    
    echo "<p>File not found</p>";
    exit();
}

if ($is_ajax_request) {
    // Output only for AJAX - partial content
    ?>
    <div class="space-y-6">
        <!-- Document Header -->
        <div class="document-header">
            <div>
                <h2>
                    <?php 
                    if (!empty($document['custom_document_name'])) {
                        echo htmlspecialchars($document['custom_document_name']);
                    } elseif (!empty($document['progress_title'])) {
                        echo htmlspecialchars($document['progress_title']);
                    } elseif (!empty($document['challenge_title'])) {
                        echo htmlspecialchars($document['challenge_title']);
                    } elseif (!empty($document['success_title'])) {
                        echo htmlspecialchars($document['success_title']);
                    } elseif (!empty($document['other_title'])) {
                        echo htmlspecialchars($document['other_title']);
                    } else {
                        echo "Document #" . $document['id'];
                    }
                    ?>
                </h2>
                <p>
                    Uploaded by <?php echo htmlspecialchars($document['uploaded_by']); ?> on 
                    <?php echo date('M j, Y \a\t g:i A', strtotime($document['uploaded_at'])); ?>
                </p>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                <span class="badge badge-primary">
                    <?php echo htmlspecialchars($document['cluster']); ?>
                </span>
                <span class="badge badge-<?php 
                    if ($document['document_type'] === 'financial_report') {
                        echo 'financial';
                    } elseif (!empty($document['document_file_names']) && $document['document_file_names'] !== '[]' && $document['document_file_names'] !== '') {
                        echo 'warning';
                    } elseif (!empty($document['image_file_names']) && $document['image_file_names'] !== '[]' && $document['image_file_names'] !== '') {
                        echo 'success';
                    } elseif (!empty($document['success_title'])) {
                        echo 'success';
                    } elseif (!empty($document['challenge_title'])) {
                        echo 'danger';
                    } elseif (!empty($document['progress_title'])) {
                        echo 'primary';
                    } else {
                        echo 'secondary';
                    }
                ?>">
                    <?php 
                    if ($document['document_type'] === 'financial_report') {
                        echo 'Financial Report';
                    } elseif (!empty($document['document_file_names']) && $document['document_file_names'] !== '[]' && $document['document_file_names'] !== '') {
                        echo 'Document';
                    } elseif (!empty($document['image_file_names']) && $document['image_file_names'] !== '[]' && $document['image_file_names'] !== '') {
                        echo 'Image';
                    } elseif (!empty($document['success_title'])) {
                        echo 'Success Story';
                    } elseif (!empty($document['challenge_title'])) {
                        echo 'Challenge';
                    } elseif (!empty($document['progress_title'])) {
                        echo 'Progress Report';
                    } else {
                        echo 'Other';
                    }
                    ?>
                </span>
                <?php 
                // Show download all button if there are files
                $docFiles = !empty($document['document_file_names']) ? json_decode($document['document_file_names'], true) : [];
                $imageFiles = !empty($document['image_file_names']) ? json_decode($document['image_file_names'], true) : [];
                $financialFiles = !empty($document['financial_report_file_names']) ? json_decode($document['financial_report_file_names'], true) : [];
                $resultsFrameworkFiles = !empty($document['results_framework_file_names']) ? json_decode($document['results_framework_file_names'], true) : [];
                $riskMatrixFiles = !empty($document['risk_matrix_file_names']) ? json_decode($document['risk_matrix_file_names'], true) : [];
                $spotlightPhotoFiles = !empty($document['spotlight_photo_file_names']) ? json_decode($document['spotlight_photo_file_names'], true) : [];
                $otherFiles = !empty($document['other_file_names']) ? json_decode($document['other_file_names'], true) : [];
                
                // Filter out empty values
                if (is_array($docFiles)) {
                    $docFiles = array_filter($docFiles, function($file) { return !empty($file); });
                }
                if (is_array($imageFiles)) {
                    $imageFiles = array_filter($imageFiles, function($file) { return !empty($file); });
                }
                if (is_array($financialFiles)) {
                    $financialFiles = array_filter($financialFiles, function($file) { return !empty($file); });
                }
                if (is_array($resultsFrameworkFiles)) {
                    $resultsFrameworkFiles = array_filter($resultsFrameworkFiles, function($file) { return !empty($file); });
                }
                if (is_array($riskMatrixFiles)) {
                    $riskMatrixFiles = array_filter($riskMatrixFiles, function($file) { return !empty($file); });
                }
                if (is_array($spotlightPhotoFiles)) {
                    $spotlightPhotoFiles = array_filter($spotlightPhotoFiles, function($file) { return !empty($file); });
                }
                if (is_array($otherFiles)) {
                    $otherFiles = array_filter($otherFiles, function($file) { return !empty($file); });
                }
                
                $hasFiles = (is_array($docFiles) && count($docFiles) > 0) ||
                            (is_array($imageFiles) && count($imageFiles) > 0) ||
                            (is_array($financialFiles) && count($financialFiles) > 0) ||
                            (is_array($resultsFrameworkFiles) && count($resultsFrameworkFiles) > 0) ||
                            (is_array($riskMatrixFiles) && count($riskMatrixFiles) > 0) ||
                            (is_array($spotlightPhotoFiles) && count($spotlightPhotoFiles) > 0) ||
                            (is_array($otherFiles) && count($otherFiles) > 0);
                
                if ($hasFiles): ?>
                    <a href="?id=<?php echo $document_id; ?>&download_all=1" class="btn-primary" target="_blank" download>
                        <i class="fas fa-download mr-2"></i> Download All Files
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Progress Report Section -->
                <?php if (!empty($document['progress_title']) || !empty($document['progress_date'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                            Progress Report
                        </h3>
                        <?php if (!empty($document['progress_title'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p style="font-size: 0.875rem; color: #64748b;">Title</p>
                                <p style="font-weight: 500;"><?php echo htmlspecialchars($document['progress_title']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['progress_date'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p style="font-size: 0.875rem; color: #64748b;">Date</p>
                                <p style="font-weight: 500;"><?php echo date('M j, Y', strtotime($document['progress_date'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Progress Report Details Sections -->
                <?php if (!empty($document['summary_achievements'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-star" style="color: #4cc9f0;"></i>
                            Summary of Achievements
                        </h3>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['summary_achievements'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($document['operating_context'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-globe" style="color: var(--warning);"></i>
                            Operating Context
                        </h3>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['operating_context'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($document['outcomes_outputs'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-check-circle" style="color: var(--primary);"></i>
                            Outcomes and Outputs
                        </h3>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['outcomes_outputs'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($document['challenges_description']) || !empty($document['mitigation_measures'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                            Challenges and Risks
                        </h3>
                        <?php if (!empty($document['challenges_description'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Challenges Description</p>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['challenges_description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['mitigation_measures'])): ?>
                            <div>
                                <p class="text-sm">Mitigation Measures</p>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['mitigation_measures'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($document['good_practices'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-lightbulb" style="color: var(--warning);"></i>
                            Good Practices and Lessons Learnt
                        </h3>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['good_practices'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($document['spotlight_narrative'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-bullhorn" style="color: #7209b7;"></i>
                            Spotlight Narrative
                        </h3>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['spotlight_narrative'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Challenge Section -->
                <?php if (!empty($document['challenge_title']) || !empty($document['challenge_description'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                            Challenge
                        </h3>
                        <?php if (!empty($document['challenge_title'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Title</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document['challenge_title']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['challenge_description'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Description</p>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['challenge_description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['challenge_impact'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Impact</p>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['challenge_impact'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['proposed_solution'])): ?>
                            <div>
                                <p class="text-sm">Proposed Solution</p>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['proposed_solution'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Success Story Section -->
                <?php if (!empty($document['success_title']) || !empty($document['success_description'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-trophy" style="color: var(--success);"></i>
                            Success Story
                        </h3>
                        <?php if (!empty($document['success_title'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Title</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document['success_title']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['success_description'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Description</p>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['success_description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['beneficiaries'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Beneficiaries</p>
                                <p class="font-medium"><?php echo number_format($document['beneficiaries']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['success_date'])): ?>
                            <div>
                                <p class="text-sm">Date</p>
                                <p class="font-medium"><?php echo date('M j, Y', strtotime($document['success_date'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Other Document Section -->
                <?php if (!empty($document['other_title']) || !empty($document['other_date'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-file" style="color: var(--text-secondary);"></i>
                            Other Document
                        </h3>
                        <?php if (!empty($document['other_title'])): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <p class="text-sm">Title</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document['other_title']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($document['other_date'])): ?>
                            <div>
                                <p class="text-sm">Date</p>
                                <p class="font-medium"><?php echo date('M j, Y', strtotime($document['other_date'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Document Files Section -->
                <?php if (!empty($document['document_file_names']) && $document['document_file_names'] !== '[]' && $document['document_file_names'] !== 'null'): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-file-alt" style="color: var(--warning);"></i>
                            Document Files
                        </h3>
                        <?php
                        $docFiles = json_decode($document['document_file_names'], true);
                        $docPaths = json_decode($document['document_file_paths'], true);
                        
                        if (is_array($docFiles) && is_array($docPaths) && count($docFiles) === count($docPaths)):
                        ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php for ($i = 0; $i < count($docFiles); $i++): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(248, 150, 30, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-file" style="color: var(--warning);"></i>
                                            </div>
                                            <div>
                                                <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($docFiles[$i]); ?></p>
                                                <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                    <?php 
                                                    $fileExt = pathinfo($docFiles[$i], PATHINFO_EXTENSION);
                                                    echo strtoupper($fileExt) . ' File';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=document&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                            <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                        </a>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">No document files available</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Financial Report Section -->
                <?php if (!empty($document['financial_report_file_names']) && $document['financial_report_file_names'] !== '[]' && $document['financial_report_file_names'] !== 'null'): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-money-bill-wave" style="color: var(--financial);"></i>
                            Financial Report
                        </h3>
                        <?php
                        $financialFiles = json_decode($document['financial_report_file_names'], true);
                        $financialPaths = json_decode($document['financial_report_file_paths'], true);
                        
                        if (is_array($financialFiles) && is_array($financialPaths) && count($financialFiles) === count($financialPaths)):
                        ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php for ($i = 0; $i < count($financialFiles); $i++): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(14, 165, 233, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-file-invoice" style="color: var(--financial);"></i>
                                            </div>
                                            <div>
                                                <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($financialFiles[$i]); ?></p>
                                                <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                    <?php 
                                                    $fileExt = pathinfo($financialFiles[$i], PATHINFO_EXTENSION);
                                                    echo strtoupper($fileExt) . ' File';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=financial&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                            <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                        </a>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">No financial report files available</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($document['expenditure_issues'])): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-comment-dollar" style="color: var(--financial);"></i>
                            Expenditure Issues
                        </h3>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['expenditure_issues'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Results Framework Section -->
                <?php if (!empty($document['results_framework_file_names']) && $document['results_framework_file_names'] !== '[]' && $document['results_framework_file_names'] !== 'null'): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-table" style="color: var(--primary);"></i>
                            Results Framework
                        </h3>
                        <?php
                        $resultsFrameworkFiles = json_decode($document['results_framework_file_names'], true);
                        $resultsFrameworkPaths = json_decode($document['results_framework_file_paths'], true);
                        
                        if (is_array($resultsFrameworkFiles) && is_array($resultsFrameworkPaths) && count($resultsFrameworkFiles) === count($resultsFrameworkPaths)):
                        ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php for ($i = 0; $i < count($resultsFrameworkFiles); $i++): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(67, 97, 238, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-file" style="color: var(--primary);"></i>
                                            </div>
                                            <div>
                                                <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($resultsFrameworkFiles[$i]); ?></p>
                                                <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                    <?php 
                                                    $fileExt = pathinfo($resultsFrameworkFiles[$i], PATHINFO_EXTENSION);
                                                    echo strtoupper($fileExt) . ' File';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=results_framework&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                            <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                        </a>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">No results framework files available</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Risk Matrix Section -->
                <?php if (!empty($document['risk_matrix_file_names']) && $document['risk_matrix_file_names'] !== '[]' && $document['risk_matrix_file_names'] !== 'null'): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-shield-alt" style="color: var(--danger);"></i>
                            Risk Matrix
                        </h3>
                        <?php
                        $riskMatrixFiles = json_decode($document['risk_matrix_file_names'], true);
                        $riskMatrixPaths = json_decode($document['risk_matrix_file_paths'], true);
                        
                        if (is_array($riskMatrixFiles) && is_array($riskMatrixPaths) && count($riskMatrixFiles) === count($riskMatrixPaths)):
                        ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php for ($i = 0; $i < count($riskMatrixFiles); $i++): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-file" style="color: var(--danger);"></i>
                                            </div>
                                            <div>
                                                <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($riskMatrixFiles[$i]); ?></p>
                                                <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                    <?php 
                                                    $fileExt = pathinfo($riskMatrixFiles[$i], PATHINFO_EXTENSION);
                                                    echo strtoupper($fileExt) . ' File';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=risk_matrix&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                            <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                        </a>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">No risk matrix files available</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Spotlight Photos Section -->
                <?php if (!empty($document['spotlight_photo_file_names']) && $document['spotlight_photo_file_names'] !== '[]' && $document['spotlight_photo_file_names'] !== 'null'): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-camera" style="color: #7209b7;"></i>
                            Spotlight Photos
                        </h3>
                        <?php
                        $spotlightPhotoFiles = json_decode($document['spotlight_photo_file_names'], true);
                        $spotlightPhotoPaths = json_decode($document['spotlight_photo_file_paths'], true);
                        
                        if (is_array($spotlightPhotoFiles) && is_array($spotlightPhotoPaths) && count($spotlightPhotoFiles) === count($spotlightPhotoPaths)):
                        ?>
                            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;">
                                <?php for ($i = 0; $i < count($spotlightPhotoFiles); $i++): ?>
                                    <div class="group">
                                        <div style="position: relative; overflow: hidden; border-radius: 12px; background: #f1f5f9; border: 1px solid rgba(0, 0, 0, 0.1);">
                                            <?php if (file_exists($spotlightPhotoPaths[$i])): ?>
                                                <img src="<?php echo htmlspecialchars($spotlightPhotoPaths[$i]); ?>" alt="Spotlight Photo" style="width: 100%; height: 8rem; object-fit: cover; transition: transform 0.3s ease;" class="group-hover:scale-105">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 8rem; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                                                    <i class="fas fa-image" style="color: #94a3b8; font-size: 1.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="margin-top: 0.5rem;">
                                            <p style="font-size: 0.875rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($spotlightPhotoFiles[$i]); ?></p>
                                        </div>
                                        <div style="margin-top: 0.5rem;">
                                            <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=spotlight_photo&file_index=<?php echo $i; ?>" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;" target="_blank" download>
                                                <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">No spotlight photos available</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Image Files Section -->
                <?php if (!empty($document['image_file_names']) && $document['image_file_names'] !== '[]' && $document['image_file_names'] !== 'null'): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-images" style="color: var(--success);"></i>
                            Images
                        </h3>
                        <?php
                        $imageFiles = json_decode($document['image_file_names'], true);
                        $imagePaths = json_decode($document['image_file_paths'], true);
                        $photoTitles = !empty($document['photo_titles']) ? json_decode($document['photo_titles'], true) : [];
                        
                        if (is_array($imageFiles) && is_array($imagePaths) && count($imageFiles) === count($imagePaths)):
                        ?>
                            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;">
                                <?php for ($i = 0; $i < count($imageFiles); $i++): ?>
                                    <div class="group">
                                        <div style="position: relative; overflow: hidden; border-radius: 12px; background: #f1f5f9; border: 1px solid rgba(0, 0, 0, 0.1);">
                                            <?php if (file_exists($imagePaths[$i])): ?>
                                                <img src="<?php echo htmlspecialchars($imagePaths[$i]); ?>" alt="<?php echo !empty($photoTitles[$i]) ? htmlspecialchars($photoTitles[$i]) : 'Project Image'; ?>" style="width: 100%; height: 8rem; object-fit: cover; transition: transform 0.3s ease;" class="group-hover:scale-105">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 8rem; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                                                    <i class="fas fa-image" style="color: #94a3b8; font-size: 1.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="margin-top: 0.5rem;">
                                            <p style="font-size: 0.875rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($imageFiles[$i]); ?></p>
                                            <?php if (!empty($photoTitles[$i])): ?>
                                                <p style="font-size: 0.75rem; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($photoTitles[$i]); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div style="margin-top: 0.5rem;">
                                            <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=image&file_index=<?php echo $i; ?>" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;" target="_blank" download>
                                                <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">No images available</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Other Files Section -->
                <?php if (!empty($document['other_file_names']) && $document['other_file_names'] !== '[]' && $document['other_file_names'] !== 'null'): ?>
                    <div class="glass-card" style="padding: 1.25rem;">
                        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-file" style="color: var(--text-secondary);"></i>
                            Other Files
                        </h3>
                        <?php
                        $otherFiles = json_decode($document['other_file_names'], true);
                        $otherPaths = json_decode($document['other_file_paths'], true);
                        
                        if (is_array($otherFiles) && is_array($otherPaths) && count($otherFiles) === count($otherPaths)):
                        ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php for ($i = 0; $i < count($otherFiles); $i++): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(100, 116, 139, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-file" style="color: var(--text-secondary);"></i>
                                            </div>
                                            <div>
                                                <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($otherFiles[$i]); ?></p>
                                                <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                    <?php 
                                                    $fileExt = pathinfo($otherFiles[$i], PATHINFO_EXTENSION);
                                                    echo strtoupper($fileExt) . ' File';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=other&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                            <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                        </a>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">No other files available</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add some subtle animations to elements as they come into view
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.glass-card');
            
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Details - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap');

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --accent: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --success: #10b981;
            --financial: #0ea5e9;
            --text-secondary: #64748b;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --mid-text: #64748b;
            --border: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e0e7ff 100%);
            color: var(--dark-text);
            min-height: 100vh;
            margin: 0;
        }

        .heading-font {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .main-content-flex {
            display: flex;
            justify-content: center;
            padding: 2rem 1rem;
            min-height: calc(100vh - 80px);
        }

        .content-container {
            width: 100%;
            max-width: 1400px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 1.75rem;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-hover {
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
        }

        .btn-primary {
            background: linear-gradient(to right, #4361ee, #3a56d4);
            color: white;
            border: none;
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.35);
        }

        .form-input, .form-select {
            transition: all 0.3s ease;
            border: 2px solid var(--border);
            border-radius: 1rem;
            padding: 0.75rem 1.25rem;
            width: 100%;
            font-size: 0.95rem;
            background: white;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
            background: #fff;
        }

        .section-title {
            position: relative;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60%;
            height: 4px;
            background: linear-gradient(to right, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .section-icon {
            width: 2.75rem;
            height: 2.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dbeafe, #a5b4fc);
            border-radius: 1rem;
            color: #2563eb;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15);
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background: white;
        }

        .data-table th {
            background: #f8fafc;
            padding: 1.25rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #334155;
            border-bottom: 1px solid var(--border);
        }

        .data-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            transition: background 0.2s;
        }

        .data-table tr:hover td {
            background: #f1f5f9;
        }

        .badge {
            padding: 0.4rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-primary { background: #eff6ff; color: #1d4ed8; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-financial { background: #ecfeff; color: #0891b2; }
        .badge-secondary { background: #f3f4f6; color: #6b7280; }

        .filter-container {
            background: white;
            border-radius: 1.5rem;
            padding: 1.75rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .document-preview {
            max-width: 80px;
            max-height: 80px;
            border-radius: 0.5rem;
            object-fit: cover;
            border: 1px solid var(--border);
            transition: transform 0.2s;
        }

        .document-preview:hover {
            transform: scale(1.05);
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal.open {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 1.5rem;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .modal.open .modal-content {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 1.75rem;
            border-bottom: 1px solid var(--border);
            background: #f8fafc;
            border-radius: 1.5rem 1.5rem 0 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 2rem;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--mid-text);
        }

        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
        }

        .document-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-text);
            margin: 0;
        }

        .document-header p {
            color: var(--mid-text);
            margin: 0.5rem 0 0 0;
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .data-table th, .data-table td {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
            .btn-primary, .btn-secondary {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

    <div class="main-content-flex">
        <div class="content-container">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold heading-font text-gray-900">📄 Document Details</h1>
                    <p class="mt-2 text-gray-600">View comprehensive details and files for this document.</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <a href="admin_documents.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Document Header -->
                <div class="document-header">
                    <div>
                        <h2>
                            <?php 
                            if (!empty($document['custom_document_name'])) {
                                echo htmlspecialchars($document['custom_document_name']);
                            } elseif (!empty($document['progress_title'])) {
                                echo htmlspecialchars($document['progress_title']);
                            } elseif (!empty($document['challenge_title'])) {
                                echo htmlspecialchars($document['challenge_title']);
                            } elseif (!empty($document['success_title'])) {
                                echo htmlspecialchars($document['success_title']);
                            } elseif (!empty($document['other_title'])) {
                                echo htmlspecialchars($document['other_title']);
                            } else {
                                echo "Document #" . $document['id'];
                            }
                            ?>
                        </h2>
                        <p>
                            Uploaded by <?php echo htmlspecialchars($document['uploaded_by']); ?> on 
                            <?php echo date('M j, Y \a\t g:i A', strtotime($document['uploaded_at'])); ?>
                        </p>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <span class="badge badge-primary">
                            <?php echo htmlspecialchars($document['cluster']); ?>
                        </span>
                        <span class="badge badge-<?php 
                            if ($document['document_type'] === 'financial_report') {
                                echo 'financial';
                            } elseif (!empty($document['document_file_names']) && $document['document_file_names'] !== '[]' && $document['document_file_names'] !== '') {
                                echo 'warning';
                            } elseif (!empty($document['image_file_names']) && $document['image_file_names'] !== '[]' && $document['image_file_names'] !== '') {
                                echo 'success';
                            } elseif (!empty($document['success_title'])) {
                                echo 'success';
                            } elseif (!empty($document['challenge_title'])) {
                                echo 'danger';
                            } elseif (!empty($document['progress_title'])) {
                                echo 'primary';
                            } else {
                                echo 'secondary';
                            }
                        ?>">
                            <?php 
                            if ($document['document_type'] === 'financial_report') {
                                echo 'Financial Report';
                            } elseif (!empty($document['document_file_names']) && $document['document_file_names'] !== '[]' && $document['document_file_names'] !== '') {
                                echo 'Document';
                            } elseif (!empty($document['image_file_names']) && $document['image_file_names'] !== '[]' && $document['image_file_names'] !== '') {
                                echo 'Image';
                            } elseif (!empty($document['success_title'])) {
                                echo 'Success Story';
                            } elseif (!empty($document['challenge_title'])) {
                                echo 'Challenge';
                            } elseif (!empty($document['progress_title'])) {
                                echo 'Progress Report';
                            } else {
                                echo 'Other';
                            }
                            ?>
                        </span>
                        <?php 
                        // Show download all button if there are files
                        $docFiles = !empty($document['document_file_names']) ? json_decode($document['document_file_names'], true) : [];
                        $imageFiles = !empty($document['image_file_names']) ? json_decode($document['image_file_names'], true) : [];
                        $financialFiles = !empty($document['financial_report_file_names']) ? json_decode($document['financial_report_file_names'], true) : [];
                        $resultsFrameworkFiles = !empty($document['results_framework_file_names']) ? json_decode($document['results_framework_file_names'], true) : [];
                        $riskMatrixFiles = !empty($document['risk_matrix_file_names']) ? json_decode($document['risk_matrix_file_names'], true) : [];
                        $spotlightPhotoFiles = !empty($document['spotlight_photo_file_names']) ? json_decode($document['spotlight_photo_file_names'], true) : [];
                        $otherFiles = !empty($document['other_file_names']) ? json_decode($document['other_file_names'], true) : [];
                        
                        // Filter out empty values
                        if (is_array($docFiles)) {
                            $docFiles = array_filter($docFiles, function($file) { return !empty($file); });
                        }
                        if (is_array($imageFiles)) {
                            $imageFiles = array_filter($imageFiles, function($file) { return !empty($file); });
                        }
                        if (is_array($financialFiles)) {
                            $financialFiles = array_filter($financialFiles, function($file) { return !empty($file); });
                        }
                        if (is_array($resultsFrameworkFiles)) {
                            $resultsFrameworkFiles = array_filter($resultsFrameworkFiles, function($file) { return !empty($file); });
                        }
                        if (is_array($riskMatrixFiles)) {
                            $riskMatrixFiles = array_filter($riskMatrixFiles, function($file) { return !empty($file); });
                        }
                        if (is_array($spotlightPhotoFiles)) {
                            $spotlightPhotoFiles = array_filter($spotlightPhotoFiles, function($file) { return !empty($file); });
                        }
                        if (is_array($otherFiles)) {
                            $otherFiles = array_filter($otherFiles, function($file) { return !empty($file); });
                        }
                        
                        $hasFiles = (is_array($docFiles) && count($docFiles) > 0) ||
                                    (is_array($imageFiles) && count($imageFiles) > 0) ||
                                    (is_array($financialFiles) && count($financialFiles) > 0) ||
                                    (is_array($resultsFrameworkFiles) && count($resultsFrameworkFiles) > 0) ||
                                    (is_array($riskMatrixFiles) && count($riskMatrixFiles) > 0) ||
                                    (is_array($spotlightPhotoFiles) && count($spotlightPhotoFiles) > 0) ||
                                    (is_array($otherFiles) && count($otherFiles) > 0);
                        
                        if ($hasFiles): ?>
                            <a href="?id=<?php echo $document_id; ?>&download_all=1" class="btn-primary" target="_blank" download>
                                <i class="fas fa-download mr-2"></i> Download All Files
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Document Content -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Progress Report Section -->
                        <?php if (!empty($document['progress_title']) || !empty($document['progress_date'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                                    Progress Report
                                </h3>
                                <?php if (!empty($document['progress_title'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p style="font-size: 0.875rem; color: #64748b;">Title</p>
                                        <p style="font-weight: 500;"><?php echo htmlspecialchars($document['progress_title']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['progress_date'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p style="font-size: 0.875rem; color: #64748b;">Date</p>
                                        <p style="font-weight: 500;"><?php echo date('M j, Y', strtotime($document['progress_date'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Progress Report Details Sections -->
                        <?php if (!empty($document['summary_achievements'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-star" style="color: #4cc9f0;"></i>
                                    Summary of Achievements
                                </h3>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['summary_achievements'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($document['operating_context'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-globe" style="color: var(--warning);"></i>
                                    Operating Context
                                </h3>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['operating_context'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($document['outcomes_outputs'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-check-circle" style="color: var(--primary);"></i>
                                    Outcomes and Outputs
                                </h3>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['outcomes_outputs'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($document['challenges_description']) || !empty($document['mitigation_measures'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                                    Challenges and Risks
                                </h3>
                                <?php if (!empty($document['challenges_description'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Challenges Description</p>
                                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['challenges_description'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['mitigation_measures'])): ?>
                                    <div>
                                        <p class="text-sm">Mitigation Measures</p>
                                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['mitigation_measures'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($document['good_practices'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-lightbulb" style="color: var(--warning);"></i>
                                    Good Practices and Lessons Learnt
                                </h3>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['good_practices'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($document['spotlight_narrative'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-bullhorn" style="color: #7209b7;"></i>
                                    Spotlight Narrative
                                </h3>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['spotlight_narrative'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Challenge Section -->
                        <?php if (!empty($document['challenge_title']) || !empty($document['challenge_description'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                                    Challenge
                                </h3>
                                <?php if (!empty($document['challenge_title'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Title</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($document['challenge_title']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['challenge_description'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Description</p>
                                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['challenge_description'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['challenge_impact'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Impact</p>
                                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['challenge_impact'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['proposed_solution'])): ?>
                                    <div>
                                        <p class="text-sm">Proposed Solution</p>
                                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['proposed_solution'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Success Story Section -->
                        <?php if (!empty($document['success_title']) || !empty($document['success_description'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-trophy" style="color: var(--success);"></i>
                                    Success Story
                                </h3>
                                <?php if (!empty($document['success_title'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Title</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($document['success_title']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['success_description'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Description</p>
                                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['success_description'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['beneficiaries'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Beneficiaries</p>
                                        <p class="font-medium"><?php echo number_format($document['beneficiaries']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['success_date'])): ?>
                                    <div>
                                        <p class="text-sm">Date</p>
                                        <p class="font-medium"><?php echo date('M j, Y', strtotime($document['success_date'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Other Document Section -->
                        <?php if (!empty($document['other_title']) || !empty($document['other_date'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-file" style="color: var(--text-secondary);"></i>
                                    Other Document
                                </h3>
                                <?php if (!empty($document['other_title'])): ?>
                                    <div style="margin-bottom: 0.75rem;">
                                        <p class="text-sm">Title</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($document['other_title']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($document['other_date'])): ?>
                                    <div>
                                        <p class="text-sm">Date</p>
                                        <p class="font-medium"><?php echo date('M j, Y', strtotime($document['other_date'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Right Column -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Document Files Section -->
                        <?php if (!empty($document['document_file_names']) && $document['document_file_names'] !== '[]' && $document['document_file_names'] !== 'null'): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-file-alt" style="color: var(--warning);"></i>
                                    Document Files
                                </h3>
                                <?php
                                $docFiles = json_decode($document['document_file_names'], true);
                                $docPaths = json_decode($document['document_file_paths'], true);
                                
                                if (is_array($docFiles) && is_array($docPaths) && count($docFiles) === count($docPaths)):
                                ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <?php for ($i = 0; $i < count($docFiles); $i++): ?>
                                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(248, 150, 30, 0.1); display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-file" style="color: var(--warning);"></i>
                                                    </div>
                                                    <div>
                                                        <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($docFiles[$i]); ?></p>
                                                        <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                            <?php 
                                                            $fileExt = pathinfo($docFiles[$i], PATHINFO_EXTENSION);
                                                            echo strtoupper($fileExt) . ' File';
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=document&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                                    <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                                </a>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--text-secondary);">No document files available</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Financial Report Section -->
                        <?php if (!empty($document['financial_report_file_names']) && $document['financial_report_file_names'] !== '[]' && $document['financial_report_file_names'] !== 'null'): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-money-bill-wave" style="color: var(--financial);"></i>
                                    Financial Report
                                </h3>
                                <?php
                                $financialFiles = json_decode($document['financial_report_file_names'], true);
                                $financialPaths = json_decode($document['financial_report_file_paths'], true);
                                
                                if (is_array($financialFiles) && is_array($financialPaths) && count($financialFiles) === count($financialPaths)):
                                ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <?php for ($i = 0; $i < count($financialFiles); $i++): ?>
                                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(14, 165, 233, 0.1); display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-file-invoice" style="color: var(--financial);"></i>
                                                    </div>
                                                    <div>
                                                        <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($financialFiles[$i]); ?></p>
                                                        <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                            <?php 
                                                            $fileExt = pathinfo($financialFiles[$i], PATHINFO_EXTENSION);
                                                            echo strtoupper($fileExt) . ' File';
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=financial&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                                    <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                                </a>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--text-secondary);">No financial report files available</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($document['expenditure_issues'])): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-comment-dollar" style="color: var(--financial);"></i>
                                    Expenditure Issues
                                </h3>
                                <p class="font-medium"><?php echo nl2br(htmlspecialchars($document['expenditure_issues'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Results Framework Section -->
                        <?php if (!empty($document['results_framework_file_names']) && $document['results_framework_file_names'] !== '[]' && $document['results_framework_file_names'] !== 'null'): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-table" style="color: var(--primary);"></i>
                                    Results Framework
                                </h3>
                                <?php
                                $resultsFrameworkFiles = json_decode($document['results_framework_file_names'], true);
                                $resultsFrameworkPaths = json_decode($document['results_framework_file_paths'], true);
                                
                                if (is_array($resultsFrameworkFiles) && is_array($resultsFrameworkPaths) && count($resultsFrameworkFiles) === count($resultsFrameworkPaths)):
                                ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <?php for ($i = 0; $i < count($resultsFrameworkFiles); $i++): ?>
                                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(67, 97, 238, 0.1); display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-file" style="color: var(--primary);"></i>
                                                    </div>
                                                    <div>
                                                        <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($resultsFrameworkFiles[$i]); ?></p>
                                                        <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                            <?php 
                                                            $fileExt = pathinfo($resultsFrameworkFiles[$i], PATHINFO_EXTENSION);
                                                            echo strtoupper($fileExt) . ' File';
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=results_framework&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                                    <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                                </a>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--text-secondary);">No results framework files available</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Risk Matrix Section -->
                        <?php if (!empty($document['risk_matrix_file_names']) && $document['risk_matrix_file_names'] !== '[]' && $document['risk_matrix_file_names'] !== 'null'): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-shield-alt" style="color: var(--danger);"></i>
                                    Risk Matrix
                                </h3>
                                <?php
                                $riskMatrixFiles = json_decode($document['risk_matrix_file_names'], true);
                                $riskMatrixPaths = json_decode($document['risk_matrix_file_paths'], true);
                                
                                if (is_array($riskMatrixFiles) && is_array($riskMatrixPaths) && count($riskMatrixFiles) === count($riskMatrixPaths)):
                                ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <?php for ($i = 0; $i < count($riskMatrixFiles); $i++): ?>
                                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-file" style="color: var(--danger);"></i>
                                                    </div>
                                                    <div>
                                                        <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($riskMatrixFiles[$i]); ?></p>
                                                        <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                            <?php 
                                                            $fileExt = pathinfo($riskMatrixFiles[$i], PATHINFO_EXTENSION);
                                                            echo strtoupper($fileExt) . ' File';
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=risk_matrix&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                                    <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                                </a>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--text-secondary);">No risk matrix files available</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Spotlight Photos Section -->
                        <?php if (!empty($document['spotlight_photo_file_names']) && $document['spotlight_photo_file_names'] !== '[]' && $document['spotlight_photo_file_names'] !== 'null'): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-camera" style="color: #7209b7;"></i>
                                    Spotlight Photos
                                </h3>
                                <?php
                                $spotlightPhotoFiles = json_decode($document['spotlight_photo_file_names'], true);
                                $spotlightPhotoPaths = json_decode($document['spotlight_photo_file_paths'], true);
                                
                                if (is_array($spotlightPhotoFiles) && is_array($spotlightPhotoPaths) && count($spotlightPhotoFiles) === count($spotlightPhotoPaths)):
                                ?>
                                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;">
                                        <?php for ($i = 0; $i < count($spotlightPhotoFiles); $i++): ?>
                                            <div class="group">
                                                <div style="position: relative; overflow: hidden; border-radius: 12px; background: #f1f5f9; border: 1px solid rgba(0, 0, 0, 0.1);">
                                                    <?php if (file_exists($spotlightPhotoPaths[$i])): ?>
                                                        <img src="<?php echo htmlspecialchars($spotlightPhotoPaths[$i]); ?>" alt="Spotlight Photo" style="width: 100%; height: 8rem; object-fit: cover; transition: transform 0.3s ease;" class="group-hover:scale-105">
                                                    <?php else: ?>
                                                        <div style="width: 100%; height: 8rem; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                                                            <i class="fas fa-image" style="color: #94a3b8; font-size: 1.5rem;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="margin-top: 0.5rem;">
                                                    <p style="font-size: 0.875rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($spotlightPhotoFiles[$i]); ?></p>
                                                </div>
                                                <div style="margin-top: 0.5rem;">
                                                    <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=spotlight_photo&file_index=<?php echo $i; ?>" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;" target="_blank" download>
                                                        <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--text-secondary);">No spotlight photos available</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Image Files Section -->
                        <?php if (!empty($document['image_file_names']) && $document['image_file_names'] !== '[]' && $document['image_file_names'] !== 'null'): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-images" style="color: var(--success);"></i>
                                    Images
                                </h3>
                                <?php
                                $imageFiles = json_decode($document['image_file_names'], true);
                                $imagePaths = json_decode($document['image_file_paths'], true);
                                $photoTitles = !empty($document['photo_titles']) ? json_decode($document['photo_titles'], true) : [];
                                
                                if (is_array($imageFiles) && is_array($imagePaths) && count($imageFiles) === count($imagePaths)):
                                ?>
                                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;">
                                        <?php for ($i = 0; $i < count($imageFiles); $i++): ?>
                                            <div class="group">
                                                <div style="position: relative; overflow: hidden; border-radius: 12px; background: #f1f5f9; border: 1px solid rgba(0, 0, 0, 0.1);">
                                                    <?php if (file_exists($imagePaths[$i])): ?>
                                                        <img src="<?php echo htmlspecialchars($imagePaths[$i]); ?>" alt="<?php echo !empty($photoTitles[$i]) ? htmlspecialchars($photoTitles[$i]) : 'Project Image'; ?>" style="width: 100%; height: 8rem; object-fit: cover; transition: transform 0.3s ease;" class="group-hover:scale-105">
                                                    <?php else: ?>
                                                        <div style="width: 100%; height: 8rem; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                                                            <i class="fas fa-image" style="color: #94a3b8; font-size: 1.5rem;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="margin-top: 0.5rem;">
                                                    <p style="font-size: 0.875rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($imageFiles[$i]); ?></p>
                                                    <?php if (!empty($photoTitles[$i])): ?>
                                                        <p style="font-size: 0.75rem; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($photoTitles[$i]); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="margin-top: 0.5rem;">
                                                    <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=image&file_index=<?php echo $i; ?>" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;" target="_blank" download>
                                                        <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--text-secondary);">No images available</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Other Files Section -->
                        <?php if (!empty($document['other_file_names']) && $document['other_file_names'] !== '[]' && $document['other_file_names'] !== 'null'): ?>
                            <div class="glass-card" style="padding: 1.25rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-file" style="color: var(--text-secondary);"></i>
                                    Other Files
                                </h3>
                                <?php
                                $otherFiles = json_decode($document['other_file_names'], true);
                                $otherPaths = json_decode($document['other_file_paths'], true);
                                
                                if (is_array($otherFiles) && is_array($otherPaths) && count($otherFiles) === count($otherPaths)):
                                ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <?php for ($i = 0; $i < count($otherFiles); $i++): ?>
                                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(248, 250, 252, 0.8); border-radius: 12px;">
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 12px; background: rgba(100, 116, 139, 0.1); display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-file" style="color: var(--text-secondary);"></i>
                                                    </div>
                                                    <div>
                                                        <p style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($otherFiles[$i]); ?></p>
                                                        <p style="font-size: 0.75rem; color: var(--text-secondary);">
                                                            <?php 
                                                            $fileExt = pathinfo($otherFiles[$i], PATHINFO_EXTENSION);
                                                            echo strtoupper($fileExt) . ' File';
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <a href="?id=<?php echo $document_id; ?>&download_file=1&file_type=other&file_index=<?php echo $i; ?>" class="btn-primary" target="_blank" download>
                                                    <i class="fas fa-download" style="margin-right: 0.25rem;"></i> Download
                                                </a>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--text-secondary);">No other files available</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some subtle animations to elements as they come into view
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.glass-card');
            
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>