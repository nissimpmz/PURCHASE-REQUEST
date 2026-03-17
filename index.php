<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Request System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="script.js" defer></script>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h2><i class="fas fa-shopping-cart"></i> PR System</h2>
            </div>
            <ul class="nav-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="new-pr.php"><i class="fas fa-file-invoice"></i> New Purchase Request</a></li>
                <li><a href="new-supplier.php"><i class="fas fa-user-tie"></i> New Supplier</a></li>
                <li><a href="view-table.php"><i class="fas fa-table"></i> View Table</a></li>
            </ul>
            <div class="sidebar-footer">
                <p>© <?php echo date('Y'); ?> PhilFIDA Region VII</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Purchase Request Dashboard</h1>
                <div class="header-actions">
                    <button class="header-btn" id="exportBtn">
                        <i class="fas fa-file-excel"></i>
                        <span>Export to Excel</span>
                    </button>
                    <button class="header-btn" id="printBtn">
                        <i class="fas fa-print"></i>
                        <span>Print</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-cards">
                <a href="view-table.php" style="text-decoration: none; color: inherit;">
                    <div class="card">
                        <div class="card-icon" style="background-color: #4CAF50;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="card-info">
                            <h3>Total PRs</h3>
                            <?php
                            $conn = getConnection();
                            $stmt = $conn->query("SELECT COUNT(*) as count FROM purchase_requests");
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            echo "<p>" . $result['count'] . "</p>";
                            ?>
                        </div>
                    </div>
                </a>

                <a href="new-supplier.php" style="text-decoration: none; color: inherit;">
                    <div class="card">
                        <div class="card-icon" style="background-color: #2196F3;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-info">
                            <h3>Suppliers</h3>
                            <?php
                            $stmt = $conn->query("SELECT COUNT(*) as count FROM suppliers");
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            echo "<p>" . $result['count'] . "</p>";
                            ?>
                        </div>
                    </div>
                </a>

                <div class="card">
                    <div class="card-icon" style="background-color: #FF9800;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Amount</h3>
                        <?php
                        $stmt = $conn->query("SELECT SUM(amount) as total FROM purchase_requests");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo "<p>₱" . number_format($result['total'] ?? 0, 2) . "</p>";
                        ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-icon" style="background-color: #9C27B0;">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Contract Amount</h3>
                        <?php
                        $stmt = $conn->query("SELECT SUM(contract_amount) as total FROM purchase_requests");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo "<p>₱" . number_format($result['total'] ?? 0, 2) . "</p>";
                        ?>
                    </div>
                </div>
            </div>

            <div class="recent-prs">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <h2>Recent Purchase Requests</h2>
                    <div style="display: flex; gap: 10px;">
                        <a href="new-pr.php" class="btn btn-success" style="text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-plus-circle"></i> New PR
                        </a>
                        <a href="view-table.php" class="btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>PR Number</th>
                            <th>Particulars</th>
                            <th>Amount</th>
                            <th>Supplier(s)</th>
                            <th>PO Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get only the latest 10 PRs for dashboard
                        $sql = "SELECT pr.*, GROUP_CONCAT(s.name SEPARATOR ', ') as supplier_names
                                FROM purchase_requests pr
                                LEFT JOIN pr_suppliers ps ON pr.id = ps.pr_id
                                LEFT JOIN suppliers s ON ps.supplier_id = s.id
                                GROUP BY pr.id
                                ORDER BY 
                                    CAST(SUBSTRING(pr.pr_number, 1, 4) AS UNSIGNED) DESC,
                                    CAST(SUBSTRING(pr.pr_number, 6, 2) AS UNSIGNED) DESC,
                                    CAST(SUBSTRING(pr.pr_number, 9, 4) AS UNSIGNED) DESC
                                LIMIT 10";
                        $stmt = $conn->query($sql);
                        $prs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($prs) > 0):
                            foreach ($prs as $pr): 
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($pr['date'])); ?></td>
                            <td><?php echo htmlspecialchars($pr['pr_number']); ?></td>
                            <td><?php echo htmlspecialchars(substr($pr['particulars'], 0, 50)) . (strlen($pr['particulars']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo $pr['amount'] ? '₱' . number_format($pr['amount'], 2) : ''; ?></td>
                            <td><?php echo htmlspecialchars($pr['supplier_names'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($pr['po_number'] ?? ''); ?></td>
                        </tr>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                No purchase requests found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for export and print buttons
            document.getElementById('exportBtn').addEventListener('click', function() {
                showExportOptionsModal();
            });
            
            document.getElementById('printBtn').addEventListener('click', function() {
                showPrintOptionsModal();
            });
        });
    </script>
</body>
</html>