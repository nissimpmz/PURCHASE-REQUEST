<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     try {
        $conn = getConnection();
        $conn->beginTransaction();
        
        $date = $_POST['date'];
        
        // Generate PR number based on whether it's historical
        if (isset($_POST['historical_record']) && !empty($_POST['manual_pr_number'])) {
            $manualBase = trim($_POST['manual_pr_number']);
            // Ensure it's in correct format (YYYY-MM-NNN)
            if (preg_match('/^\d{4}-\d{2}-\d{3}$/', $manualBase)) {
                $prNumber = generatePRNumber($date, $manualBase);
            } else {
                throw new Exception("Invalid PR base number format. Use YYYY-MM-NNN (e.g., 2025-01-007)");
            }
        } else {
            $prNumber = generatePRNumber($date);
        }
        
        $poNumber = !empty($_POST['po_number']) ? $_POST['po_number'] : generatePONumber($date);
        
        // FIRST: Insert the main purchase request
        $sql = "INSERT INTO purchase_requests (
                    date, pr_number, particulars, amount, contract_amount, 
                    po_number, po_date, iar_number, iar_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $date,
            $prNumber,
            $_POST['particulars'],
            !empty($_POST['amount']) ? $_POST['amount'] : null,
            !empty($_POST['contract_amount']) ? $_POST['contract_amount'] : null,
            $poNumber,
            !empty($_POST['po_date']) ? $_POST['po_date'] : null,
            !empty($_POST['iar_number']) ? $_POST['iar_number'] : null,
            !empty($_POST['iar_date']) ? $_POST['iar_date'] : null
        ]);
        
        // Get the ID of the newly inserted PR
        $prId = $conn->lastInsertId();
        
        // SECOND: Insert selected suppliers (only if we have a valid PR ID)
        if (isset($_POST['suppliers']) && is_array($_POST['suppliers'])) {
            // Validate that all supplier IDs exist before inserting
            $validSupplierIds = [];
            
            // Check each supplier ID
            foreach ($_POST['suppliers'] as $supplierId) {
                $supplierId = intval(trim($supplierId));
                
                if ($supplierId > 0) {
                    // Verify supplier exists
                    $checkSql = "SELECT id FROM suppliers WHERE id = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->execute([$supplierId]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        $validSupplierIds[] = $supplierId;
                    } else {
                        error_log("Invalid supplier ID: $supplierId");
                    }
                }
            }
            
            // Only insert if we have valid suppliers
            if (!empty($validSupplierIds)) {
                $sql = "INSERT INTO pr_suppliers (pr_id, supplier_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                
                foreach ($validSupplierIds as $supplierId) {
                    $stmt->execute([$prId, $supplierId]);
                }
            }
        }
        
        $conn->commit();
        
        // Redirect with success message using session instead of URL parameter
        $_SESSION['toast_message'] = 'Purchase Request created successfully!';
        $_SESSION['toast_type'] = 'success';
        header('Location: view-table.php');
        exit;
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

$suppliers = getSuppliers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Purchase Request</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <h1>New Purchase Request</h1>  
            </header>

            <div class="form-containers">
                <?php if (isset($error)): ?>
                    <div style="background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form id="prForm" method="POST">
                    <div class="form-row">
                        <label>
                            <input type="checkbox" id="historical_record" name="historical_record" value="1">
                            Insert as historical record (forgot to record earlier)
                        </label>
                    </div>

                    <div id="historical_options" style="display: none;">
                        <div class="form-group">
                            <label for="manual_pr_number">Desired PR Base Number</label>
                            <input type="text" id="manual_pr_number" name="manual_pr_number" 
                                placeholder="e.g., 2025-01-001" pattern="\d{4}-\d{2}-\d{3}">
                            <small>Enter the base PR number you want to use</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date *</label>
                            <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required
                                   onchange="updatePRNumber()">
                        </div>
                        
                        <div class="form-group">
                            <label for="pr_number">PR Number</label>
                            <input type="text" id="pr_number" name="pr_number" readonly
                                   value="<?php echo generatePRNumber(); ?>">
                            <small>Auto-generated based on date (year and month)</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="particulars">Particulars *</label>
                        <textarea id="particulars" name="particulars" required placeholder="Enter details of the purchase..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount">Amount *</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contract_amount">Contract Amount</label>
                            <input type="number" id="contract_amount" name="contract_amount" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="supplierSelect">Select Suppliers (Optional)</label>
                        <select id="supplierSelect" class="form-control">
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>">
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="addSupplier()" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Add Supplier
                        </button>
                        
                        <div id="selectedSuppliers" class="multi-select-container">
                            <!-- Selected suppliers will appear here -->
                        </div>
                        <input type="hidden" id="suppliers" name="suppliers[]">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="po_number">PO Number</label>
                            <input type="text" id="po_number" name="po_number">
                            <small>Leave blank to auto-generate</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="po_date">PO Date</label>
                            <input type="date" id="po_date" name="po_date">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="iar_number">IAR #</label>
                            <input type="text" id="iar_number" name="iar_number">
                        </div>
                        
                        <div class="form-group">
                            <label for="iar_date">IAR Date</label>
                            <input type="date" id="iar_date" name="iar_date">
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Purchase Request
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='view-table.php'">
                            <i class="fas fa-list"></i> View All PRs
                        </button>
                        <button type="button" class="btn btn-danger" onclick="window.location.href='index.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="script.js"></script>

    <script>
    let selectedSuppliers = [];
    let selectedSupplierNames = [];
    
    function addSupplier() {
        const select = document.getElementById('supplierSelect');
        const supplierId = select.value;
        const supplierName = select.options[select.selectedIndex].text;
        
        if (supplierId && !selectedSuppliers.includes(supplierId)) {
            selectedSuppliers.push(supplierId);
            selectedSupplierNames.push(supplierName);
            updateSelectedSuppliersDisplay();
            updateSuppliersHiddenInput();
        }
        
        select.value = '';
    }

    document.getElementById('historical_record').addEventListener('change', function() {
        const options = document.getElementById('historical_options');
        const dateInput = document.getElementById('date');
        const prNumberInput = document.getElementById('pr_number');
        
        if (this.checked) {
            options.style.display = 'block';
            // When historical is checked, disable auto-generation
            dateInput.addEventListener('change', updateHistoricalPRNumber);
            updateHistoricalPRNumber();
        } else {
            options.style.display = 'none';
            document.getElementById('manual_pr_number').value = '';
            // Remove historical event listener
            dateInput.removeEventListener('change', updateHistoricalPRNumber);
            // Re-enable normal PR generation
            updatePRNumber();
        }
    });
    
    function updateHistoricalPRNumber() {
        const date = document.getElementById('date').value;
        const manualBase = document.getElementById('manual_pr_number').value;
        
        if (!manualBase) {
            document.getElementById('pr_number').value = '';
            return;
        }
        
        // Validate manual base format
        if (!/^\d{4}-\d{2}-\d{3}$/.test(manualBase)) {
            alert('Please enter PR base number in format: YYYY-MM-NNN (e.g., 2025-01-007)');
            return;
        }
        
        // Fetch the next available PR number with suffix
        fetch('generate-historical-pr.php?date=' + encodeURIComponent(date) + 
            '&base=' + encodeURIComponent(manualBase))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('pr_number').value = data.pr_number;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function removeSupplier(supplierId) {
        const index = selectedSuppliers.indexOf(supplierId);
        if (index > -1) {
            selectedSuppliers.splice(index, 1);
            selectedSupplierNames.splice(index, 1);
            updateSelectedSuppliersDisplay();
            updateSuppliersHiddenInput();
        }
    }

    function updateSelectedSuppliersDisplay() {
        const container = document.getElementById('selectedSuppliers');
        container.innerHTML = '';
        
        selectedSupplierNames.forEach((supplierName, index) => {
            const supplierId = selectedSuppliers[index];
            const div = document.createElement('div');
            div.className = 'selected-supplier';
            div.innerHTML = `
                ${supplierName}
                <button type="button" class="remove-supplier" onclick="removeSupplier('${supplierId}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(div);
        });
    }

    function updateSuppliersHiddenInput() {
        // Clear any existing supplier inputs
        const existingInputs = document.querySelectorAll('input[name="suppliers[]"]');
        existingInputs.forEach(input => input.remove());
        
        // Create new hidden inputs for each supplier
        selectedSuppliers.forEach(supplierId => {
            if (supplierId && supplierId.trim() !== '') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'suppliers[]';
                input.value = supplierId.trim();
                document.getElementById('prForm').appendChild(input);
            }
        });
    }

    async function updatePRNumber() {
        const date = document.getElementById('date').value;
        if (!date) return;
        
        try {
            const response = await fetch('generate-numbers.php?date=' + date);
            const data = await response.json();
            
            document.getElementById('pr_number').value = data.pr_number;
            
            // Only auto-fill PO number if it's empty
            const poInput = document.getElementById('po_number');
            if (!poInput.value) {
                poInput.placeholder = 'Auto: ' + data.po_number;
                // Optionally auto-fill it:
                // poInput.value = data.po_number;
            }
        } catch (error) {
            console.error('Error updating numbers:', error);
        }
    }
    </script>
</body>
</html>