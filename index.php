<?php
require_once 'config.php';

// Get unique SO numbers for dropdown - now using the function from config.php
$so_numbers = getSONumbers();
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
    <style>
        /* Additional styles for filter dropdown */
        .filter-container {
            margin-bottom: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .filter-label i {
            color: #3498db;
            font-size: 1.1rem;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 250px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-select:hover {
            border-color: #3498db;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .clear-filter {
            padding: 8px 15px;
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .clear-filter:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.2);
        }
        
        .filter-badge {
            background-color: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .card.filtered {
            border-left: 4px solid #3498db;
        }
        
        .filter-info {
            background-color: #e8f4f8;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #3498db;
        }
        
        .filter-info i {
            color: #3498db;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .clear-filter {
                text-align: center;
                justify-content: center;
            }
        }
    </style>
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

            <!-- SO# Filter Dropdown -->
            <div class="filter-container">
                <div class="filter-label">
                    <i class="fas fa-filter"></i>
                    <span>Filter by SO #:</span>
                </div>
                
                <form method="GET" id="soFilterForm" style="flex: 1; display: flex; gap: 10px; flex-wrap: wrap;">
                    <select name="so_filter" id="so_filter" class="filter-select" onchange="document.getElementById('soFilterForm').submit()">
                        <option value="">All SO Numbers</option>
                        <?php foreach ($so_numbers as $so): ?>
                            <option value="<?php echo htmlspecialchars($so); ?>" 
                                <?php echo (isset($_GET['so_filter']) && $_GET['so_filter'] == $so) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($so); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if (isset($_GET['so_filter']) && !empty($_GET['so_filter'])): ?>
                        <a href="index.php" class="clear-filter">
                            <i class="fas fa-times"></i> Clear Filter
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Filter Info (shown when filter is active) -->
            <?php if (isset($_GET['so_filter']) && !empty($_GET['so_filter'])): ?>
                <div class="filter-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Currently showing data for SO#: <strong><?php echo htmlspecialchars($_GET['so_filter']); ?></strong></span>
                </div>
            <?php endif; ?>

            <div class="dashboard-cards">
                <?php
                $conn = getConnection();
                
                // Build filter conditions
                $filter_sql = "";
                $filter_params = [];
                
                if (isset($_GET['so_filter']) && !empty($_GET['so_filter'])) {
                    $filter_sql = "WHERE so_number = ?";
                    $filter_params[] = $_GET['so_filter'];
                }
                ?>
                
                <!-- Total PRs Card -->
                <a href="view-table.php<?php echo isset($_GET['so_filter']) ? '?search=' . urlencode($_GET['so_filter']) : ''; ?>" style="text-decoration: none; color: inherit;">
                    <div class="card <?php echo isset($_GET['so_filter']) ? 'filtered' : ''; ?>">
                        <div class="card-icon" style="background-color: #4CAF50;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="card-info">
                            <h3>
                                Total PRs
                                <?php if (isset($_GET['so_filter'])): ?>
                                    <span class="filter-badge">Filtered</span>
                                <?php endif; ?>
                            </h3>
                            <?php
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM purchase_requests $filter_sql");
                            $stmt->execute($filter_params);
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            echo "<p>" . $result['count'] . "</p>";
                            ?>
                        </div>
                    </div>
                </a>

                <!-- Suppliers Card -->
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

                <!-- Total Amount Card (Filtered) -->
                <div class="card <?php echo isset($_GET['so_filter']) ? 'filtered' : ''; ?>">
                    <div class="card-icon" style="background-color: #FF9800;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            Total Amount
                            <?php if (isset($_GET['so_filter'])): ?>
                                <span class="filter-badge">Filtered</span>
                            <?php endif; ?>
                        </h3>
                        <?php
                        $stmt = $conn->prepare("SELECT SUM(amount) as total FROM purchase_requests $filter_sql");
                        $stmt->execute($filter_params);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo "<p>₱" . number_format($result['total'] ?? 0, 2) . "</p>";
                        ?>
                    </div>
                </div>

                <!-- Total Contract Amount Card (Filtered) -->
                <div class="card <?php echo isset($_GET['so_filter']) ? 'filtered' : ''; ?>">
                    <div class="card-icon" style="background-color: #9C27B0;">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            Total Contract Amount
                            <?php if (isset($_GET['so_filter'])): ?>
                                <span class="filter-badge">Filtered</span>
                            <?php endif; ?>
                        </h3>
                        <?php
                        $stmt = $conn->prepare("SELECT SUM(contract_amount) as total FROM purchase_requests $filter_sql");
                        $stmt->execute($filter_params);
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
                
                <?php
                // Get only the latest 10 PRs for dashboard (with filter applied if any)
                $recent_sql = "SELECT pr.*, GROUP_CONCAT(s.name SEPARATOR ', ') as supplier_names
                                FROM purchase_requests pr
                                LEFT JOIN pr_suppliers ps ON pr.id = ps.pr_id
                                LEFT JOIN suppliers s ON ps.supplier_id = s.id";
                
                if (isset($_GET['so_filter']) && !empty($_GET['so_filter'])) {
                    $recent_sql .= " WHERE pr.so_number = " . $conn->quote($_GET['so_filter']);
                }
                
                $recent_sql .= " GROUP BY pr.id
                                ORDER BY 
                                    CAST(SUBSTRING(pr.pr_number, 1, 4) AS UNSIGNED) DESC,
                                    CAST(SUBSTRING(pr.pr_number, 6, 2) AS UNSIGNED) DESC,
                                    CAST(SUBSTRING(pr.pr_number, 9, 4) AS UNSIGNED) DESC
                                LIMIT 10";
                
                $stmt = $conn->query($recent_sql);
                $prs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>PR Number</th>
                            <th>Particulars</th>
                            <th>Amount</th>
                            <th>Supplier(s)</th>
                            <th>PO Number</th>
                            <th>SO #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($prs) > 0): ?>
                            <?php foreach ($prs as $pr): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($pr['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($pr['pr_number']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($pr['particulars'], 0, 50)) . (strlen($pr['particulars']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo $pr['amount'] ? '₱' . number_format($pr['amount'], 2) : ''; ?></td>
                                    <td><?php echo htmlspecialchars($pr['supplier_names'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($pr['po_number'] ?? ''); ?></td>
                                    <td>
                                        <?php if (!empty($pr['so_number'])): ?>
                                            <span class="supplier-tag" style="background-color: #e8f4f8; color: #2980b9;">
                                                <i class="fas fa-hashtag" style="font-size: 10px;"></i>
                                                <?php echo htmlspecialchars($pr['so_number']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                    <?php if (isset($_GET['so_filter'])): ?>
                                        No purchase requests found for SO#: <strong><?php echo htmlspecialchars($_GET['so_filter']); ?></strong>
                                    <?php else: ?>
                                        No purchase requests found
                                    <?php endif; ?>
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
            
            // Auto-submit filter when dropdown changes (already in HTML onchange)
            
            // Add animation to filter badge
            const filterBadge = document.querySelector('.filter-badge');
            if (filterBadge) {
                filterBadge.style.animation = 'pulse 2s infinite';
            }
        });

        // Add pulse animation for filter badge
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>