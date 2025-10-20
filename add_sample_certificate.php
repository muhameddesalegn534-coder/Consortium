<?php
// Include database configuration
define('INCLUDED_SETUP', true);
include 'setup_database.php';

// Insert sample certificate data
$sql = "INSERT INTO certificates_simple (cluster_name, year, certificate_path, uploaded_by) VALUES 
        ('Woldiya', 2025, 'admin/uploads/certificates/sample_certificate_2025.pdf', 'admin'),
        ('Mekele', 2025, 'admin/uploads/certificates/sample_certificate_mekele_2025.pdf', 'admin'),
        ('Woldiya', 2024, 'admin/uploads/certificates/sample_certificate_2024.pdf', 'admin')";

if ($conn->query($sql) === TRUE) {
    echo "Sample certificate data inserted successfully<br>";
    echo "Inserted ID: " . $conn->insert_id . "<br>";
} else {
    echo "Error inserting sample data: " . $conn->error . "<br>";
}

// Check if data exists
$checkSql = "SELECT * FROM certificates_simple";
$result = $conn->query($checkSql);

if ($result && $result->num_rows > 0) {
    echo "<h3>Certificate Data:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Cluster</th><th>Year</th><th>Path</th><th>Uploaded Date</th><th>Uploaded By</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['cluster_name'] . "</td>";
        echo "<td>" . $row['year'] . "</td>";
        echo "<td>" . $row['certificate_path'] . "</td>";
        echo "<td>" . $row['uploaded_date'] . "</td>";
        echo "<td>" . $row['uploaded_by'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "No certificate data found<br>";
}
?>