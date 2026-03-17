<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid PR ID');
    }
    
    $id = intval($_GET['id']);
    
    $conn = getConnection();
    $sql = "SELECT pr.*, 
                   GROUP_CONCAT(DISTINCT s.id ORDER BY s.id SEPARATOR ',') as supplier_ids,
                   GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as supplier_names
            FROM purchase_requests pr
            LEFT JOIN pr_suppliers ps ON pr.id = ps.pr_id
            LEFT JOIN suppliers s ON ps.supplier_id = s.id
            WHERE pr.id = ?
            GROUP BY pr.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        throw new Exception('Purchase Request not found');
    }
    
    echo json_encode([
        'success' => true,
        'pr' => [
            'id' => $pr['id'],
            'date' => $pr['date'],
            'pr_number' => $pr['pr_number'],
            'particulars' => $pr['particulars'],
            'amount' => $pr['amount'],
            'contract_amount' => $pr['contract_amount'],
            'po_number' => $pr['po_number'],
            'po_date' => $pr['po_date'],
            'iar_number' => $pr['iar_number'],
            'iar_date' => $pr['iar_date'],
            'so_number' => $pr['so_number'],
            'supplier_ids' => $pr['supplier_ids'] ? explode(',', $pr['supplier_ids']) : [],
            'supplier_names' => $pr['supplier_names']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>