<?php
// database.php - Database connection and setup

function getConnection() {
    $host = 'localhost';
    $dbname = 'purchase_system';
    $username = 'root';
    $password = '';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Create tables if they don't exist
function initializeDatabase() {
    $conn = getConnection();
    
    // Create purchase_requests table with SO# column
    $sql = "CREATE TABLE IF NOT EXISTS purchase_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        pr_number VARCHAR(20) UNIQUE NOT NULL,
        particulars TEXT NOT NULL,
        amount DECIMAL(12,2),
        po_number VARCHAR(20),
        po_date DATE,
        contract_amount DECIMAL(12,2),
        iar_number VARCHAR(20),
        iar_date DATE,
        so_number VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);
    
    // Check if so_number column exists, add if not
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM purchase_requests LIKE 'so_number'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE purchase_requests ADD COLUMN so_number VARCHAR(50) NULL AFTER iar_date");
        }
    } catch (Exception $e) {
        // Column might already exist or table doesn't exist yet
    }
    
    // Create suppliers table (SIMPLIFIED - only name)
    $sql = "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);
    
    // Create pr_suppliers table (many-to-many relationship)
    $sql = "CREATE TABLE IF NOT EXISTS pr_suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pr_id INT NOT NULL,
        supplier_id INT NOT NULL,
        FOREIGN KEY (pr_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
        UNIQUE KEY unique_pr_supplier (pr_id, supplier_id)
    )";
    
    $conn->exec($sql);
}
?>