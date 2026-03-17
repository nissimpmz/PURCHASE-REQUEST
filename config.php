<?php
// config.php - Configuration and helper functions
session_start();
require_once 'database.php';


// Detect if running on ngrok
$is_ngrok = (strpos($_SERVER['HTTP_HOST'] ?? '', 'ngrok') !== false);

// Set base URL based on environment
if ($is_ngrok) {
    // When on ngrok, use relative paths
    define('BASE_URL', '/');
} else {
    // Local development
    define('BASE_URL', 'http://localhost:8080/');
}

// Initialize database
initializeDatabase();


function generatePRNumber($date = null, $manualBase = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $conn = getConnection();
    $yearMonth = date('Y-m', strtotime($date));
    
    // If manual base is provided (for historical records)
    if ($manualBase) {
        // Check if this base number already exists
        $sql = "SELECT pr_number FROM purchase_requests 
                WHERE pr_number LIKE :base_pattern 
                ORDER BY pr_number ASC 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $basePattern = $manualBase . '%';
        $stmt->bindParam(':base_pattern', $basePattern);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $lastPR = $existing['pr_number'];
            // Extract letter suffix if exists
            $baseLength = strlen($manualBase);
            $suffix = substr($lastPR, $baseLength);
            
            if (empty($suffix)) {
                // No suffix yet, add 'A'
                return $manualBase . 'A';
            } else {
                // Has suffix, increment letter
                $lastChar = $suffix[0];
                if ($lastChar >= 'A' && $lastChar < 'Z') {
                    $nextChar = chr(ord($lastChar) + 1);
                    return $manualBase . $nextChar;
                } else {
                    // If Z, add another letter (AA, AB, etc.)
                    // For simplicity, we'll just add another A
                    return $manualBase . $lastChar . 'A';
                }
            }
        } else {
            // Base doesn't exist, use it as is
            return $manualBase;
        }
    }
    
    // Normal PR number generation (for current records)
    $year = date('Y', strtotime($date));
    
    // Get the highest PR number for this year
    $sql = "SELECT MAX(CAST(SUBSTRING(pr_number, 1, 4) AS UNSIGNED)) as max_year,
                   MAX(CAST(SUBSTRING(pr_number, 9, 3) AS UNSIGNED)) as max_number
            FROM purchase_requests 
            WHERE pr_number NOT LIKE '%-%[A-Z]'
            AND pr_number REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{3}$'";
    
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['max_year'] == $year && $result['max_number'] !== null) {
        $lastNumber = intval($result['max_number']);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $yearMonth . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

// Function to generate PO number (same logic as PR number)
function generatePONumber($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $conn = getConnection();
    $year = date('Y', strtotime($date));
    
    try {
        // Get the highest PO number for this year
        $sql = "SELECT MAX(CAST(SUBSTRING(po_number, 9, 3) AS UNSIGNED)) as max_number 
                FROM purchase_requests 
                WHERE po_number IS NOT NULL 
                AND po_number != ''
                AND po_number LIKE :year_pattern
                AND CAST(SUBSTRING(po_number, 1, 4) AS UNSIGNED) = :year";
        
        $stmt = $conn->prepare($sql);
        $yearPattern = $year . '-%';
        $stmt->bindParam(':year_pattern', $yearPattern);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['max_number'] !== null) {
            $lastNumber = intval($result['max_number']);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        $yearMonth = date('Y-m', strtotime($date));
        return $yearMonth . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        error_log("Error generating PO number: " . $e->getMessage());
        // Simple fallback
        $yearMonth = date('Y-m', strtotime($date));
        return $yearMonth . '-001';
    }
}

function getSuppliers() {
    $conn = getConnection();
    $sql = "SELECT id, name FROM suppliers ORDER BY name ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to format suppliers for display
function formatSuppliers($supplierNames) {
    if (empty($supplierNames)) {
        return '';
    }
    
    $suppliers = explode(', ', $supplierNames);
    $formatted = '';
    foreach ($suppliers as $supplier) {
        $formatted .= '<span class="supplier-tag">' . htmlspecialchars(trim($supplier)) . '</span><br>';
    }
    return $formatted;
}

// Function to format suppliers for plain text (for export/print)
function formatSuppliersPlain($supplierNames) {
    if (empty($supplierNames)) {
        return '';
    }
    return $supplierNames;
}
?>