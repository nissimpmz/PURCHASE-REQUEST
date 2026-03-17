<?php
require_once 'config.php';

// Pagination settings
$records_per_page = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Sorting settings - change default to descending order
$sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'pr_number';
$sort_order = (isset($_GET['order']) && $_GET['order'] === 'asc') ? 'asc' : 'desc'; // Default to desc

// Search settings
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validate page size
$valid_page_sizes = [10, 25, 50, 100];
if (!in_array($records_per_page, $valid_page_sizes)) {
    $records_per_page = 10;
}

$offset = ($page - 1) * $records_per_page;

$conn = getConnection();

// Build WHERE clause for search
$where_clauses = [];
$params = [];

if (!empty($search_term)) {
    $search_term_like = '%' . $search_term . '%';
    $where_clauses[] = "(pr.particulars LIKE :search_term OR s.name LIKE :search_term)";
    $params[':search_term'] = $search_term_like;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total records count with search
$count_sql = "SELECT COUNT(DISTINCT pr.id) as total 
              FROM purchase_requests pr
              LEFT JOIN pr_suppliers ps ON pr.id = ps.pr_id
              LEFT JOIN suppliers s ON ps.supplier_id = s.id
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Ensure page is within valid range
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$offset = ($page - 1) * $records_per_page; // Recalculate offset after page validation

// Determine sorting - use if/else instead of match for compatibility
$order_by = "";
if ($sort_field === 'date') {
    $order_by = "pr.date " . $sort_order . ", pr.pr_number DESC"; // Always put newer PR numbers first when sorting by date
} elseif ($sort_field === 'pr_number') {
    // Parse PR number components for proper numeric sorting with DESC as default
    if ($sort_order === 'desc') {
        $order_by = "
            CAST(SUBSTRING(pr.pr_number, 1, 4) AS UNSIGNED) DESC,
            CAST(SUBSTRING(pr.pr_number, 6, 2) AS UNSIGNED) DESC,
            CAST(SUBSTRING(pr.pr_number, 9, 4) AS UNSIGNED) DESC
        ";
    } else {
        $order_by = "
            CAST(SUBSTRING(pr.pr_number, 1, 4) AS UNSIGNED) ASC,
            CAST(SUBSTRING(pr.pr_number, 6, 2) AS UNSIGNED) ASC,
            CAST(SUBSTRING(pr.pr_number, 9, 4) AS UNSIGNED) ASC
        ";
    }
} else {
    $order_by = "pr.pr_number DESC"; // Default to descending
}

// Get paginated purchase requests with search
$sql = "SELECT pr.*, GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as supplier_names
        FROM purchase_requests pr
        LEFT JOIN pr_suppliers ps ON pr.id = ps.pr_id
        LEFT JOIN suppliers s ON ps.supplier_id = s.id
        $where_sql
        GROUP BY pr.id
        ORDER BY $order_by
        LIMIT :offset, :records_per_page";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$purchaseRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle delete operations (same as before)
if (isset($_GET['delete'])) {
    try {
        $conn = getConnection();
        $sql = "DELETE FROM purchase_requests WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['delete']]);
        
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Purchase Request deleted successfully!'
            ]);
            exit;
        } else {
            $_SESSION['toast_message'] = 'Purchase Request deleted successfully!';
            $_SESSION['toast_type'] = 'success';
            header('Location: view-table.php' . (!empty($search_term) ? '?search=' . urlencode($search_term) : ''));
            exit;
        }
        
    } catch (Exception $e) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
            exit;
        } else {
            $_SESSION['toast_message'] = 'Error: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error';
            header('Location: view-table.php' . (!empty($search_term) ? '?search=' . urlencode($search_term) : ''));
            exit;
        }
    }
}

$suppliers = getSuppliers(); // Get suppliers for the dropdown
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Purchase Requests</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="script.js" defer></script>
    <style>
        /* Search bar styles */
        .search-container {
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .search-info {
            color: #7f8c8d;
            font-size: 13px;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }
        
        .clear-search {
            color: #e74c3c;
            text-decoration: none;
            font-size: 13px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .clear-search:hover {
            background-color: #fee;
        }
        
        /* Sorting styles */
        .sortable {
            cursor: pointer;
            position: relative;
            padding-right: 25px !important;
            user-select: none;
            transition: background-color 0.2s;
        }
        
        .sortable:hover {
            background-color: #e9ecef;
        }
        
        .sortable i {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 14px;
        }
        
        .sortable.active-sort i {
            color: #3498db;
        }
        
        .sortable.active-sort i.fa-sort-up,
        .sortable.active-sort i.fa-sort-down {
            color: #3498db;
            font-weight: bold;
        }
        
        /* Highlight search term */
        .search-highlight {
            background-color: #fff3cd;
            font-weight: 500;
            padding: 0 2px;
            border-radius: 2px;
        }
        
        /* Additional pagination styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid #dee2e6;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 14px;
        }
        
        .pagination-controls {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .page-link {
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .page-link:hover:not(.disabled) {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
            transform: translateY(-1px);
        }
        
        .page-link.disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        .page-current {
            padding: 8px 12px;
            background: #3498db;
            color: white;
            border: 1px solid #3498db;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .page-size-selector {
            padding: 8px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .page-size-selector:hover {
            border-color: #3498db;
        }
        
        .page-size-selector:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
        }
        
        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .pagination-info {
                text-align: center;
            }
            
            .pagination-controls {
                justify-content: center;
            }
            
            .page-size-selector {
                width: 100%;
            }
            
            .search-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-info, .clear-search {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
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

        <div class="main-content">
            <header>
                <h1>Purchase Requests Table</h1>
                <div class="header-actions">
                    <button class="header-btn" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i>
                        <span>Export to Excel</span>
                    </button>
                    <button class="header-btn" onclick="showPrintOptionsModal()">
                        <i class="fas fa-print"></i>
                        <span>Print</span>
                    </button>
                </div>
            </header>

            <!-- Toast Container -->
            <div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

            <!-- Search Bar -->
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <form id="searchForm" method="GET" style="display: flex; width: 100%;">
                        <input type="text" 
                            name="search" 
                            id="searchInput"
                            placeholder="Search in particulars or suppliers..." 
                            value="<?php echo htmlspecialchars($search_term); ?>"
                            autocomplete="off"
                            style="flex: 1; border-top-right-radius: 0; border-bottom-right-radius: 0;">
                        <button type="submit" class="btn btn-primary" style="border-top-left-radius: 0; border-bottom-left-radius: 0; padding: 12px 20px;">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                </div>
                
                <!-- New PR Button -->
                <a href="new-pr.php" class="btn btn-success" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px; white-space: nowrap;">
                    <i class="fas fa-plus-circle"></i>
                    <span>New PR</span>
                </a>
                
                <?php if (!empty($search_term)): ?>
                    <span class="search-info">
                        <i class="fas fa-filter"></i>
                        Found <?php echo $total_records; ?> result(s) for "<?php echo htmlspecialchars($search_term); ?>"
                    </span>
                    <a href="view-table.php?sort=<?php echo urlencode($sort_field); ?>&order=<?php echo urlencode($sort_order); ?>&per_page=<?php echo $records_per_page; ?>" class="clear-search">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>


            <!-- Edit Modal (same as before) -->
            <div id="editModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center;">
                <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #2c3e50;">
                            <i class="fas fa-edit" style="margin-right: 10px;"></i>
                            Edit Purchase Request
                        </h2>
                        <button onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d;">&times;</button>
                    </div>
                    
                    <form id="editForm" method="POST">
                        <input type="hidden" id="edit_id" name="id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_date">Date *</label>
                                <input type="date" id="edit_date" name="date" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_pr_number">PR Number</label>
                                <input type="text" id="edit_pr_number" name="pr_number" readonly class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_particulars">Particulars *</label>
                            <textarea id="edit_particulars" name="particulars" required class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_amount">Amount *</label>
                                <input type="number" id="edit_amount" name="amount" step="0.01" min="0" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_contract_amount">Contract Amount</label>
                                <input type="number" id="edit_contract_amount" name="contract_amount" step="0.01" min="0" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_suppliers">Suppliers</label>
                            <div id="edit_suppliers_list" class="multi-select-container" style="margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 5px;">
                                <!-- Selected suppliers will appear here -->
                            </div>
                            <select id="edit_supplier_select" class="form-control">
                                <option value="">-- Add Supplier --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="edit_suppliers_input" name="suppliers">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_po_number">PO Number</label>
                                <input type="text" id="edit_po_number" name="po_number" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_po_date">PO Date</label>
                                <input type="date" id="edit_po_date" name="po_date" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_iar_number">IAR #</label>
                                <input type="text" id="edit_iar_number" name="iar_number" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_iar_date">IAR Date</label>
                                <input type="date" id="edit_iar_date" name="iar_date" class="form-control">
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                            <button type="button" onclick="closeEditModal()" class="btn btn-danger">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal (same as before) -->
            <div id="deleteModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                <div class="modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 10px;">
                    <div class="modal-header" style="margin-bottom: 20px;">
                        <h3 style="margin: 0; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                    </div>
                    <div class="modal-body" style="margin-bottom: 20px;">
                        <p>Are you sure you want to delete PR: <strong id="deletePRNumber"></strong>?</p>
                        <p class="text-danger"><small>This action cannot be undone.</small></p>
                    </div>
                    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()" style="padding: 8px 16px;">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn" style="padding: 8px 16px;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <?php 
            // Check for success message in URL
            if (isset($_GET['success'])) {
                $_SESSION['toast_message'] = htmlspecialchars($_GET['success']);
                $_SESSION['toast_type'] = 'success';
            }

            // Check for error message in URL
            if (isset($_GET['error'])) {
                $_SESSION['toast_message'] = htmlspecialchars($_GET['error']);
                $_SESSION['toast_type'] = 'error';
            }
            ?>
            
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th class="sortable <?php echo $sort_field === 'date' ? 'active-sort' : ''; ?>" 
                                onclick="sortTable('date')">
                                Date
                                <i class="fas fa-sort<?php echo $sort_field === 'date' ? ($sort_order === 'asc' ? '-up' : '-down') : ''; ?>"></i>
                            </th>
                            <th class="sortable <?php echo $sort_field === 'pr_number' ? 'active-sort' : ''; ?>" 
                                onclick="sortTable('pr_number')">
                                PR Number
                                <i class="fas fa-sort<?php echo $sort_field === 'pr_number' ? ($sort_order === 'asc' ? '-up' : '-down') : '-down'; ?>"></i>
                            </th>
                            <th>Particulars</th>
                            <th>Amount</th>
                            <th>Supplier(s)</th>
                            <th>PO Number</th>
                            <th>PO Date</th>
                            <th>Contract Amount</th>
                            <th>IAR #</th>
                            <th>IAR Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchaseRequests as $pr): ?>
                        <tr id="pr-row-<?php echo $pr['id']; ?>">
                            <td><?php echo date('Y-m-d', strtotime($pr['date'])); ?></td>
                            <td><?php echo htmlspecialchars($pr['pr_number']); ?></td>
                            <td title="<?php echo htmlspecialchars($pr['particulars']); ?>">
                                <?php 
                                $particulars = htmlspecialchars($pr['particulars']);
                                $display_particulars = strlen($particulars) > 30 ? substr($particulars, 0, 30) . '...' : $particulars;
                                
                                if (!empty($search_term)) {
                                    // Highlight only in the displayed text
                                    $pattern = '/' . preg_quote($search_term, '/') . '/i';
                                    $display_particulars = preg_replace($pattern, '<span class="search-highlight">$0</span>', $display_particulars);
                                }
                                echo $display_particulars;
                                ?>
                            </td>
                            <td><?php echo $pr['amount'] ? '₱' . number_format($pr['amount'], 2) : ''; ?></td>
                            <td>
                                <?php 
                                $supplier_html = formatSuppliers($pr['supplier_names'] ?? '');
                                if (!empty($search_term)) {
                                    $pattern = '/' . preg_quote($search_term, '/') . '/i';
                                    $supplier_html = preg_replace($pattern, '<span class="search-highlight">$0</span>', $supplier_html);
                                }
                                echo $supplier_html; 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($pr['po_number'] ?? 'N/A'); ?></td>
                            <td><?php echo $pr['po_date'] ? date('Y-m-d', strtotime($pr['po_date'])) : ''; ?></td>
                            <td><?php echo $pr['contract_amount'] ? '₱' . number_format($pr['contract_amount'], 2) : ''; ?></td>
                            <td><?php echo htmlspecialchars($pr['iar_number'] ?? ''); ?></td>
                            <td><?php echo $pr['iar_date'] ? date('Y-m-d', strtotime($pr['iar_date'])) : ''; ?></td>
                            <td style="white-space: nowrap;">
                                <div style="display: flex; gap: 8px; align-items: center; justify-content: center;">
                                    <button onclick="window.open('edit-pr.php?id=<?php echo $pr['id']; ?>', 'EditPR', 'width=900,height=700,resizable=yes,scrollbars=yes')" 
                                        class="btn btn-primary" style="padding: 8px 12px; font-size: 13px; min-width: 70px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="openDeleteModal(<?php echo $pr['id']; ?>, '<?php echo addslashes($pr['pr_number']); ?>')" 
                                            class="btn btn-danger" style="padding: 8px 12px; font-size: 13px; min-width: 70px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                            <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($purchaseRequests) === 0): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 50px; color: #7f8c8d;">
                                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                                <p style="font-size: 1.1rem;">No purchase requests found</p>
                                <?php if (!empty($search_term)): ?>
                                    <p>Try adjusting your search criteria</p>
                                    <a href="view-table.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">
                                        <i class="fas fa-times"></i> Clear Search
                                    </a>
                                <?php else: ?>
                                    <a href="new-pr.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">
                                        <i class="fas fa-plus"></i> Create New Purchase Request
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                        <?php if (!empty($search_term)): ?>
                            for "<?php echo htmlspecialchars($search_term); ?>"
                        <?php endif; ?>
                    </div>
                    
                    <div class="pagination-controls">
                        <!-- Previous Page -->
                        <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $records_per_page; ?>&sort=<?php echo urlencode($sort_field); ?>&order=<?php echo urlencode($sort_order); ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                           class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Previous Page">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="page-current">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?>&sort=<?php echo urlencode($sort_field); ?>&order=<?php echo urlencode($sort_order); ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                                   class="page-link">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $records_per_page; ?>&sort=<?php echo urlencode($sort_field); ?>&order=<?php echo urlencode($sort_order); ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                           class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" title="Next Page">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    
                    <div>
                        <select class="page-size-selector" onchange="changePageSize(this.value)">
                            <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10 per page</option>
                            <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25 per page</option>
                            <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50 per page</option>
                            <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100 per page</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let editSelectedSuppliers = [];
        let currentDeleteId = null;

        // Toast notification functions
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.style.cssText = `
                background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                margin-bottom: 10px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                min-width: 300px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                animation: slideIn 0.3s ease;
            `;
            
            // Set content
            toast.innerHTML = `
                <div class="toast-content">${message}</div>
                <button class="toast-close" onclick="this.parentElement.remove()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
            `;
            
            // Add to container
            container.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }
        
        function changePageSize(size) {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', size);
            urlParams.set('page', 1); // Reset to first page
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }

        // Sort table function
        function sortTable(field) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort') || 'pr_number'; // Changed default
            const currentOrder = urlParams.get('order') || 'asc'; // Changed default to 'asc'
            
            // Toggle order if same field, otherwise default to asc
            let newOrder = 'asc';
            if (field === currentSort) {
                newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            }
            
            urlParams.set('sort', field);
            urlParams.set('order', newOrder);
            urlParams.set('page', 1); // Reset to first page
            
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }

        // Simple edit function that opens in new window
        function editPR(id) {
            const width = 900;
            const height = 700;
            const left = (window.innerWidth - width) / 2;
            const top = (window.innerHeight - height) / 2;
            
            window.open(`edit-pr.php?id=${id}`, 'editPR', 
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);
        }

        // Show toast from PHP session
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['toast_message'])): ?>
                showToast("<?php echo addslashes($_SESSION['toast_message']); ?>", "<?php echo $_SESSION['toast_type'] ?? 'info'; ?>");
                <?php 
                // Clear the session message after showing
                unset($_SESSION['toast_message']);
                unset($_SESSION['toast_type']);
                ?>
            <?php endif; ?>
            
            // Also handle success/error messages from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const successMessage = urlParams.get('success');
            const errorMessage = urlParams.get('error');
            
            if (successMessage) {
                showToast(successMessage, 'success');
                // Remove success parameter from URL without reloading
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, '', newUrl);
            }
            
            if (errorMessage) {
                showToast(errorMessage, 'error');
                // Remove error parameter from URL without reloading
                urlParams.delete('error');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, '', newUrl);
            }
            
            // Set up delete confirmation button
            document.getElementById('confirmDeleteBtn').addEventListener('click', deletePR);
            
            // Handle edit form submission
            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveEdit();
            });
            
            // Handle supplier select change
            document.getElementById('edit_supplier_select').addEventListener('change', function() {
                addEditSupplier();
            });
            
            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                const editModal = document.getElementById('editModal');
                const deleteModal = document.getElementById('deleteModal');
                
                if (event.target == editModal) {
                    closeEditModal();
                }
                if (event.target == deleteModal) {
                    closeDeleteModal();
                }
            });
            
            // Close modals with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeEditModal();
                    closeDeleteModal();
                }
            });
            
        });

        async function openEditModal(id) {
            try {
                // Show loading state
                document.getElementById('editModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                // Show loading indicator
                const modalContent = document.querySelector('#editModal .modal-content');
                const originalContent = modalContent.innerHTML;
                modalContent.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin fa-2x" style="color: #3498db; margin-bottom: 20px;"></i>
                        <p>Loading data...</p>
                    </div>
                `;
                
                // Fetch PR data
                const response = await fetch(`get-pr-data.php?id=${encodeURIComponent(id)}`);
                const data = await response.json();
                
                // Restore modal content
                modalContent.innerHTML = originalContent;
                
                if (data.success) {
                    const pr = data.pr;
                    
                    // Fill form fields
                    document.getElementById('edit_id').value = pr.id;
                    document.getElementById('edit_date').value = pr.date;
                    document.getElementById('edit_pr_number').value = pr.pr_number;
                    document.getElementById('edit_particulars').value = pr.particulars;
                    document.getElementById('edit_amount').value = pr.amount;
                    document.getElementById('edit_contract_amount').value = pr.contract_amount || '';
                    document.getElementById('edit_po_number').value = pr.po_number || '';
                    document.getElementById('edit_po_date').value = pr.po_date || '';
                    document.getElementById('edit_iar_number').value = pr.iar_number || '';
                    document.getElementById('edit_iar_date').value = pr.iar_date || '';
                    
                    // Load suppliers
                    editSelectedSuppliers = pr.supplier_ids || [];
                    
                    // Update suppliers display
                    updateEditSuppliersDisplay(pr.supplier_names);
                    updateEditSuppliersHiddenInput();
                    
                    // Re-attach event listeners
                    document.getElementById('edit_supplier_select').addEventListener('change', function() {
                        addEditSupplier();
                    });
                    
                } else {
                    showToast('Error loading PR data: ' + data.message, 'error');
                    closeEditModal();
                }
                
            } catch (error) {
                console.error('Error:', error);
                showToast('Error loading PR data: ' + error.message, 'error');
                closeEditModal();
            } 
        }

        // Open delete confirmation modal
        function openDeleteModal(id, prNumber) {
            currentDeleteId = id;
            document.getElementById('deletePRNumber').textContent = prNumber;
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Reset form and suppliers
            document.getElementById('editForm').reset();
            editSelectedSuppliers = [];
            updateEditSuppliersDisplay();
            updateEditSuppliersHiddenInput();
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            currentDeleteId = null;
        }

        // Add supplier to edit form
        function addEditSupplier() {
            const select = document.getElementById('edit_supplier_select');
            const supplierId = parseInt(select.value);
            const supplierName = select.options[select.selectedIndex].text;
            
            if (supplierId && !editSelectedSuppliers.includes(supplierId)) {
                editSelectedSuppliers.push(supplierId);
                updateEditSuppliersDisplay();
                updateEditSuppliersHiddenInput();
            }
            
            // Reset select
            select.value = '';
        }

        // Remove supplier from edit form
        function removeEditSupplier(supplierId) {
            editSelectedSuppliers = editSelectedSuppliers.filter(id => id !== supplierId);
            updateEditSuppliersDisplay();
            updateEditSuppliersHiddenInput();
        }

        // Update edit suppliers display
        function updateEditSuppliersDisplay(supplierNames = '') {
            const container = document.getElementById('edit_suppliers_list');
            container.innerHTML = '';
            
            editSelectedSuppliers.forEach(supplierId => {
                const supplierName = getSupplierNameById(supplierId) || `Supplier #${supplierId}`;
                addSupplierToDisplay(container, supplierId, supplierName);
            });
        }

        // Helper function to get supplier name by ID
        function getSupplierNameById(supplierId) {
            const select = document.getElementById('edit_supplier_select');
            const option = select.querySelector(`option[value="${supplierId}"]`);
            return option ? option.text : null;
        }

        // Helper function to add supplier to display
        function addSupplierToDisplay(container, supplierId, supplierName) {
            const div = document.createElement('div');
            div.className = 'selected-supplier';
            div.style.cssText = `
                background: #e9ecef;
                padding: 5px 10px;
                border-radius: 20px;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 14px;
                margin: 2px;
            `;
            div.innerHTML = `
                ${supplierName}
                <button type="button" class="remove-supplier" onclick="removeEditSupplier(${supplierId})" style="background: none; border: none; color: #666; cursor: pointer; font-size: 12px;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(div);
        }

        // Update hidden input with supplier IDs
        function updateEditSuppliersHiddenInput() {
            document.getElementById('edit_suppliers_input').value = editSelectedSuppliers.join(',');
        }

        // Save edited PR
        async function saveEdit() {
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            
            // Validate required fields
            if (!formData.get('date') || !formData.get('particulars') || !formData.get('amount')) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            try {
                // Show loading
                const saveBtn = document.querySelector('#editForm .btn-success');
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveBtn.disabled = true;
                
                // Send update request
                const response = await fetch('update-pr.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    closeEditModal();
                    
                    // Update the table row
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    showToast(data.message || 'Failed to update PR', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error updating PR: ' + error.message, 'error');
            } finally {
                // Reset button
                const saveBtn = document.querySelector('#editForm .btn-success');
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                saveBtn.disabled = false;
            }
        }

        // Delete PR
        async function deletePR() {
            if (!currentDeleteId) return;
            
            try {
                // Show loading
                const deleteBtn = document.getElementById('confirmDeleteBtn');
                const originalText = deleteBtn.innerHTML;
                deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                deleteBtn.disabled = true;
                
                // Use AJAX delete endpoint
                const response = await fetch(`delete-pr.php?id=${currentDeleteId}`);
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    closeDeleteModal();
                    
                    // Remove the row from the table
                    const row = document.getElementById(`pr-row-${currentDeleteId}`);
                    if (row) {
                        // Add fade-out animation
                        row.style.transition = 'opacity 0.3s ease, height 0.3s ease, margin 0.3s ease';
                        row.style.opacity = '0';
                        row.style.height = '0';
                        row.style.margin = '0';
                        row.style.overflow = 'hidden';
                        
                        // Remove after animation
                        setTimeout(() => {
                            row.remove();
                            
                            // If no rows left, show message
                            const tbody = document.querySelector('tbody');
                            if (tbody && tbody.children.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 20px;">No purchase requests found</td></tr>';
                            } else {
                                // Refresh the page to update pagination
                                window.location.reload();
                            }
                        }, 300);
                    } else {
                        // If row not found, reload the page
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                } else {
                    showToast(data.message || 'Failed to delete PR', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error deleting PR: ' + error.message, 'error');
            } finally {
                // Reset button
                const deleteBtn = document.getElementById('confirmDeleteBtn');
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
                deleteBtn.disabled = false;
            }
        }
    </script>
</body>
</html>