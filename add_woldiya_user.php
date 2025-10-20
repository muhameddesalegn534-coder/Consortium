<?php
// Script to add a sample Woldiya user for testing

require_once 'config.php';

try {
    // Check if Woldiya user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['finance@woldiya.com']);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Insert Woldiya finance officer user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, cluster_name, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'woldiya_finance',
            'finance@woldiya.com',
            '1234', // Plain text password for testing
            'finance_officer',
            'Woldiya',
            1
        ]);
        echo "Woldiya finance officer user created successfully.\n";
        echo "Email: finance@woldiya.com\n";
        echo "Password: 1234\n";
    } else {
        echo "Woldiya finance officer user already exists.\n";
    }
    
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
    $stmt->execute(['admin@gmail.com', 'admin']);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Insert admin user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            'admin',
            'admin@gmail.com',
            '1234', // Plain text password for testing
            'admin',
            1
        ]);
        echo "Admin user created successfully.\n";
        echo "Email: admin@gmail.com\n";
        echo "Password: 1234\n";
    } else {
        echo "Admin user already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>