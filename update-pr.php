<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Validate required fields
    $required = ['id', 'date', 'particulars', 'amount'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Required field '$field' is missing");
        }
    }
    
    $conn = getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update purchase request - add so_number to the field list
    $sql = "UPDATE purchase_requests SET 
            date = ?, 
            particulars = ?, 
            amount = ?, 
            contract_amount = ?, 
            po_number = ?, 
            po_date = ?, 
            iar_number = ?, 
            iar_date = ?,
            so_number = ? 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $_POST['date'],
        $_POST['particulars'],
        !empty($_POST['amount']) ? $_POST['amount'] : null,
        !empty($_POST['contract_amount']) ? $_POST['contract_amount'] : null,
        !empty($_POST['po_number']) ? $_POST['po_number'] : null,
        !empty($_POST['po_date']) ? $_POST['po_date'] : null,
        !empty($_POST['iar_number']) ? $_POST['iar_number'] : null,
        !empty($_POST['iar_date']) ? $_POST['iar_date'] : null,
        !empty($_POST['so_number']) ? $_POST['so_number'] : null, // Add this line
        $_POST['id']
    ]);
    
    // Delete existing supplier associations (this removes ALL current suppliers)
    $sql = "DELETE FROM pr_suppliers WHERE pr_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_POST['id']]);
    
    // Process suppliers - handle both selected and new suppliers
    $supplierIds = [];
    
    // Handle existing selected suppliers from the comma-separated string
    if (!empty($_POST['suppliers'])) {
        $suppliers = explode(',', $_POST['suppliers']);
        foreach ($suppliers as $supplierId) {
            $supplierId = intval(trim($supplierId));
            if ($supplierId > 0) {
                $supplierIds[] = $supplierId;
            }
        }
    }
    
    // Handle new suppliers typed in (from autocomplete)
    if (isset($_POST['new_suppliers']) && is_array($_POST['new_suppliers'])) {
        foreach ($_POST['new_suppliers'] as $newSupplierName) {
            $newSupplierName = trim($newSupplierName);
            if (!empty($newSupplierName)) {
                // Check if supplier already exists
                $checkSql = "SELECT id FROM suppliers WHERE name = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$newSupplierName]);
                $existingSupplier = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingSupplier) {
                    // Use existing supplier
                    $supplierIds[] = $existingSupplier['id'];
                } else {
                    // Insert new supplier
                    $insertSql = "INSERT INTO suppliers (name) VALUES (?)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->execute([$newSupplierName]);
                    $supplierIds[] = $conn->lastInsertId();
                }
            }
        }
    }
    
    // Insert supplier associations (only if there are suppliers to add)
    if (!empty($supplierIds)) {
        $sql = "INSERT INTO pr_suppliers (pr_id, supplier_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        
        // Use array_unique to prevent duplicate supplier IDs
        $uniqueSupplierIds = array_unique($supplierIds);
        
        foreach ($uniqueSupplierIds as $supplierId) {
            $stmt->execute([$_POST['id'], $supplierId]);
        }
        
        $addedCount = count($uniqueSupplierIds);
    } else {
        $addedCount = 0;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Request updated successfully!',
        'suppliers_added' => $addedCount ?? 0
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>