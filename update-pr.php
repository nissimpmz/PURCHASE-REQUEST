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
    
    // Update purchase request
    $sql = "UPDATE purchase_requests SET 
            date = ?, 
            particulars = ?, 
            amount = ?, 
            contract_amount = ?, 
            po_number = ?, 
            po_date = ?, 
            iar_number = ?, 
            iar_date = ? 
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
        $_POST['id']
    ]);
    
    // Delete existing supplier associations
    $sql = "DELETE FROM pr_suppliers WHERE pr_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_POST['id']]);
    
    // Add new supplier associations
    if (!empty($_POST['suppliers'])) {
        $suppliers = explode(',', $_POST['suppliers']);
        
        if (!empty($suppliers)) {
            $sql = "INSERT INTO pr_suppliers (pr_id, supplier_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($suppliers as $supplierId) {
                $supplierId = trim($supplierId);
                if (!empty($supplierId) && is_numeric($supplierId)) {
                    $stmt->execute([$_POST['id'], $supplierId]);
                }
            }
        }
    }
    
    // After successful update
    $_SESSION['toast_message'] = 'Purchase Request updated successfully!';
    $_SESSION['toast_type'] = 'success';
    header('Location: view-table.php');
    exit;
    
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