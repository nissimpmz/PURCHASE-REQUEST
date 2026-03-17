<?php
// get-print-data.php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $conn = getConnection();
    
    // Get date range parameters if provided
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    // Build the query - SIMPLIFIED VERSION
    $sql = "SELECT 
                pr.id,
                pr.date,
                pr.pr_number,
                pr.particulars,
                pr.amount,
                pr.po_number,
                pr.po_date,
                pr.contract_amount,
                pr.iar_number,
                pr.iar_date,
                pr.created_at,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as supplier_names
            FROM purchase_requests pr
            LEFT JOIN pr_suppliers ps ON pr.id = ps.pr_id
            LEFT JOIN suppliers s ON ps.supplier_id = s.id";
    
    $whereClauses = [];
    $params = [];
    
    if ($startDate) {
        $whereClauses[] = "pr.date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereClauses[] = "pr.date <= ?";
        $params[] = $endDate;
    }
    
    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }
    
   $sql .= " GROUP BY pr.id 
          ORDER BY 
            CAST(SUBSTRING(pr.pr_number, 1, 4) AS UNSIGNED) ASC, -- Year
            CAST(SUBSTRING(pr.pr_number, 6, 2) AS UNSIGNED) ASC, -- Month
            CAST(SUBSTRING(pr.pr_number, 9, 4) AS UNSIGNED) ASC, -- Number
            pr.pr_number ASC -- Full string as fallback
          ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for printing
    $formattedRequests = array_map(function($request) {
        return [
            'id' => $request['id'],
            'date' => $request['date'],
            'pr_number' => $request['pr_number'],
            'particulars' => htmlspecialchars($request['particulars'] ?? ''),
            'amount' => $request['amount']  ?? '',
            'supplier_names' => $request['supplier_names'] ?? '',
            'po_number' => $request['po_number'],
            'po_date' => $request['po_date'],
            'contract_amount' => $request['contract_amount'],
            'iar_number' => $request['iar_number'],
            'iar_date' => $request['iar_date'],
            'created_at' => $request['created_at']
        ];
    }, $requests);
    
    echo json_encode([
        'success' => true,
        'count' => count($formattedRequests),
        'requests' => $formattedRequests
    ]);
    
} catch (Exception $e) {
    error_log("Print data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>