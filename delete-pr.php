<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $id = $_GET['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        throw new Exception('Invalid purchase request ID');
    }
    
    $conn = getConnection();
    
    // First, check if the PR exists
    $checkSql = "SELECT pr_number FROM purchase_requests WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$id]);
    $pr = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        throw new Exception('Purchase request not found. It may have already been deleted.');
    }
    
    $prNumber = $pr['pr_number'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // First delete from pr_suppliers (due to foreign key constraints)
        $sql = "DELETE FROM pr_suppliers WHERE pr_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        
        // Then delete from purchase_requests
        $sql = "DELETE FROM purchase_requests WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        
        $conn->commit();
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Purchase request {$prNumber} deleted successfully!"
            ]);
        } else {
            throw new Exception('No rows were deleted. The purchase request may have already been removed.');
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400); // Bad request
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>