<?php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierName = trim($_POST['name']);
    
    if (empty($supplierName)) {
        $message = 'Supplier name is required!';
        $messageType = 'error';
    } else {
        try {
            $conn = getConnection();
            
            // Check if supplier already exists
            $checkSql = "SELECT id FROM suppliers WHERE name = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$supplierName]);
            
            if ($checkStmt->rowCount() > 0) {
                $message = 'Supplier already exists!';
                $messageType = 'error';
            } else {
                $sql = "INSERT INTO suppliers (name) VALUES (?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$supplierName]);
                
                $message = 'Supplier added successfully!';
                $messageType = 'success';
                
                // Clear the form field on success
                $_POST['name'] = '';
            }
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle supplier deletion
if (isset($_GET['delete'])) {
    try {
        $conn = getConnection();
        
        // Check if supplier is used in any purchase requests
        $checkSql = "SELECT COUNT(*) as count FROM pr_suppliers WHERE supplier_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$_GET['delete']]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $message = 'Cannot delete supplier. It is associated with existing purchase requests.';
            $messageType = 'error';
        } else {
            // Delete the supplier
            $deleteSql = "DELETE FROM suppliers WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->execute([$_GET['delete']]);
            
            if ($deleteStmt->rowCount() > 0) {
                $message = 'Supplier deleted successfully!';
                $messageType = 'success';
            } 
        }
        
    } catch (Exception $e) {
        $message = "Error deleting supplier: " . $e->getMessage();
        $messageType = 'error';
    }
}

$suppliers = getSuppliers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Supplier</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="script.js" defer></script>
    <style>
        .supplier-list {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .supplier-list h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .supplier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .supplier-item {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .supplier-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        
        .supplier-actions {
            display: flex;
            gap: 5px;
        }
        
        .delete-supplier-btn {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .delete-supplier-btn:hover {
            background-color: #ffebee;
            transform: scale(1.1);
        }
        
        .empty-suppliers {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .form-actions .btn {
            flex: 1;
            min-width: 150px;
        }

        /* Delete confirmation modal styles */
        .delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .delete-modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 400px;
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .delete-modal h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .delete-modal p {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .delete-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-btn-cancel {
            background-color: #95a5a6;
            color: white;
        }
        
        .modal-btn-cancel:hover {
            background-color: #7f8c8d;
        }
        
        .modal-btn-delete {
            background-color: #e74c3c;
            color: white;
        }
        
        .modal-btn-delete:hover {
            background-color: #c0392b;
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
                <h1>New Supplier</h1>
            </header>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" id="supplierForm">
                    <div class="form-group">
                        <label for="name">Supplier Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                               placeholder="Enter supplier name" required>
                        <small>Enter the name of the supplier/company</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Supplier
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='new-pr.php'">
                            <i class="fas fa-file-invoice"></i> Create PR
                        </button>
                        <button type="button" class="btn btn-danger" onclick="window.location.href='index.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>

            <div class="supplier-list">
                <h3>Existing Suppliers (<?php echo count($suppliers); ?>)</h3>
                
                <?php if (count($suppliers) > 0): ?>
                    <div class="supplier-grid">
                        <?php foreach ($suppliers as $supplier): ?>
                            <div class="supplier-item">
                                <div class="supplier-info">
                                    <i class="fas fa-building" style="color: #3498db;"></i>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                    <span style="color: #95a5a6; font-size: 0.9rem;">
                                        #<?php echo $supplier['id']; ?>
                                    </span>
                                </div>
                                <div class="supplier-actions">
                                    <button type="button" class="delete-supplier-btn" 
                                            onclick="showDeleteModal(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars(addslashes($supplier['name'])); ?>')"
                                            title="Delete Supplier">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-suppliers">
                        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px; color: #bdc3c7;"></i>
                        <p>No suppliers added yet. Add your first supplier above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <p>Are you sure you want to delete supplier: <strong id="deleteSupplierName"></strong>?</p>
            <p class="text-danger"><small>This action cannot be undone.</small></p>
            <div class="delete-modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="modal-btn modal-btn-delete" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script>
    // Auto-focus on the supplier name field
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('name').focus();
        
        // Clear message after 5 seconds
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            }, 5000);
        }
        
        // Form validation
        const form = document.getElementById('supplierForm');
        form.addEventListener('submit', function(e) {
            const nameInput = document.getElementById('name');
            if (nameInput.value.trim().length < 2) {
                e.preventDefault();
                alert('Supplier name must be at least 2 characters long.');
                nameInput.focus();
                return false;
            }
        });

        // Setup delete confirmation button
        document.getElementById('confirmDeleteBtn').addEventListener('click', deleteSupplier);
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
            }
        });
    });
    
    // Show delete confirmation modal
    function showDeleteModal(supplierId, supplierName) {
        document.getElementById('deleteSupplierName').textContent = supplierName;
        document.getElementById('deleteModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Store supplier ID in a data attribute
        document.getElementById('confirmDeleteBtn').setAttribute('data-supplier-id', supplierId);
    }
    
    // Close delete modal
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        document.getElementById('confirmDeleteBtn').removeAttribute('data-supplier-id');
    }
    
    // Delete supplier function
    async function deleteSupplier() {
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        const supplierId = deleteBtn.getAttribute('data-supplier-id');
        
        if (!supplierId) {
            alert('No supplier selected for deletion.');
            return;
        }
        
        try {
            // Show loading state
            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            deleteBtn.disabled = true;
            
            // Redirect to delete the supplier
            window.location.href = `new-supplier.php?delete=${supplierId}`;
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error deleting supplier: ' + error.message);
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
            deleteBtn.disabled = false;
        }
    }
    
    // Quick add supplier and go to PR
    function quickAddAndGo() {
        const nameInput = document.getElementById('name');
        if (nameInput.value.trim().length < 2) {
            alert('Please enter a valid supplier name first.');
            nameInput.focus();
            return;
        }
        
        // Submit the form first
        document.getElementById('supplierForm').submit();
    }
    
    function exportToExcel() {
        showExportOptionsModal();
    }
    </script>
</body>
</html>