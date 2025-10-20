<?php
// Script to create the project_documents table

// Include the database setup
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Create project_documents table
$sql = "CREATE TABLE IF NOT EXISTS project_documents (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    document_type VARCHAR(100) NOT NULL,
    custom_document_name VARCHAR(255) DEFAULT NULL,
    cluster VARCHAR(100) NOT NULL,
    document_file_names TEXT,
    document_file_paths TEXT,
    image_file_names TEXT,
    image_file_paths TEXT,
    photo_titles TEXT,
    progress_title VARCHAR(255),
    progress_date DATE,
    progress_summary TEXT,
    progress_details TEXT,
    challenge_title VARCHAR(255),
    challenge_description TEXT,
    challenge_impact TEXT,
    proposed_solution TEXT,
    success_title VARCHAR(255),
    success_description TEXT,
    beneficiaries INT,
    success_date DATE,
    uploaded_by VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>Table 'project_documents' created/verified successfully</p>";
} else {
    echo "<p class='error'>Error creating table 'project_documents': " . $conn->error . "</p>";
}

// Close connection
$conn->close();
?>