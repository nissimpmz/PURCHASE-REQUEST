<?php
// export.php
require_once 'config.php';

// Get date range parameters if provided
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Generate filename with date range if applicable
$filename = "purchase_requests_" . date('Y-m-d');
if ($startDate && $endDate) {
    $filename = "purchase_requests_" . $startDate . "_to_" . $endDate;
}
$filename .= ".xls";

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$conn = getConnection();

// Build the query with optional date filtering
$sql = "SELECT 
            pr.*, 
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as supplier_names,
            DATE_FORMAT(pr.date, '%Y-%m-%d') as pr_date,
            DATE_FORMAT(pr.po_date, '%Y-%m-%d') as po_date_formatted,
            DATE_FORMAT(pr.iar_date, '%Y-%m-%d') as iar_date_formatted
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
$purchaseRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { background-color: #2c3e50; color: white; text-align: center; padding: 15px; }
        .subtitle { text-align: center; font-size: 14px; margin: 10px 0; }
        .summary { background-color: #e8f4f8; padding: 10px; margin: 10px 0; }
        .total-row { background-color: #f8f9fa; font-weight: bold; }
        .date-filter { text-align: center; font-size: 12px; color: #666; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h2>PHILFIDA REGION VII - PURCHASE REQUESTS REPORT</h2>
    </div>
    
    <div class="subtitle">
        <h3>Purchase Request Abstract Report</h3>
    </div>
    
    <?php if ($startDate && $endDate): ?>
        <div class="date-filter">
            <strong>Date Range:</strong> <?php echo date('F j, Y', strtotime($startDate)); ?> to <?php echo date('F j, Y', strtotime($endDate)); ?>
        </div>
    <?php endif; ?>
    
    <div class="summary">
        <p><strong>Total Records:</strong> <?php echo count($purchaseRequests); ?> | 
           <strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>PR Date</th>
                <th>PR Number</th>
                <th>Particulars</th>
                <th>Amount</th>
                <th>Supplier(s)</th>
                <th>PO Number</th>
                <th>PO Date</th>
                <th>Contract Amount</th>
                <th>IAR #</th>
                <th>IAR Date</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalAmount = 0;
            $totalContractAmount = 0;
            ?>
            <?php foreach ($purchaseRequests as $pr): 
                $totalAmount += floatval($pr['amount'] ?? 0);
                $totalContractAmount += floatval($pr['contract_amount'] ?? 0);
            ?>
            <tr>
                <td><?php echo $pr['pr_date']; ?></td>
                <td><?php echo htmlspecialchars($pr['pr_number']); ?></td>
                <td><?php echo htmlspecialchars($pr['particulars']); ?></td>
                <td style="text-align: right;"><?php echo $pr['amount'] ? '₱' . number_format($pr['amount'], 2) : ''; ?></td>
               <td>
                    <?php 
                    if (!empty($pr['supplier_names'])) {
                        $suppliers = explode(', ', $pr['supplier_names']);
                        // Display all suppliers, each on a new line for clarity
                        foreach ($suppliers as $index => $supplier) {
                            if ($index > 0) echo "<br>";
                            echo htmlspecialchars($supplier);
                        }
                    } else {
                        echo 'No supplier';
                    }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($pr['po_number'] ?? ''); ?></td>
                <td><?php echo $pr['po_date_formatted'] ?? ''; ?></td>
                <td style="text-align: right;"><?php echo $pr['contract_amount'] ? '₱' . number_format($pr['contract_amount'], 2) : ''; ?></td>
                <td><?php echo htmlspecialchars($pr['iar_number'] ?? ''); ?></td>
                <td><?php echo $pr['iar_date_formatted'] ?? ''; ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (count($purchaseRequests) > 0): ?>
            <tr class="total-row">
                <td colspan="3" style="text-align: right;"><strong>TOTALS:</strong></td>
                <td style="text-align: right;"><strong>₱<?php echo number_format($totalAmount, 2); ?></strong></td>
                <td colspan="2"></td>
                <td style="text-align: right;"><strong>₱<?php echo number_format($totalContractAmount, 2); ?></strong></td>
                <td colspan="3"></td>
            </tr>
            <tr class="total-row">
                <td colspan="3" style="text-align: right;"><strong>GRAND TOTAL:</strong></td>
                <td colspan="7" style="text-align: left;"><strong>₱<?php echo number_format($totalAmount + $totalContractAmount, 2); ?></strong></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if (count($purchaseRequests) === 0): ?>
        <div style="text-align: center; padding: 20px; color: #666; font-style: italic;">
            No purchase requests found for the selected criteria.
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px; font-size: 11px; color: #666; text-align: center;">
        <p>Generated by PhilFIDA Region VII Purchase Request System</p>
        <p>Mezzanine Floor, LDM Building, M.J. Cuenco Avenue Corner Legaspi Street, Cebu City 6000</p>
    </div>
</body>
</html>