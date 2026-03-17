<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     try {
        $conn = getConnection();
        
        // Check if this PR number already exists (prevents duplicates)
        $prNumber = $_POST['pr_number'];
        $checkSql = "SELECT id FROM purchase_requests WHERE pr_number = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$prNumber]);
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception("PR Number {$prNumber} already exists. Please refresh the page and try again.");
        }
        
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
        
        // Process suppliers - handle both selected and new suppliers
        $supplierIds = [];
        
        // Handle existing selected suppliers
        if (isset($_POST['suppliers']) && is_array($_POST['suppliers'])) {
            foreach ($_POST['suppliers'] as $supplierId) {
                $supplierId = intval(trim($supplierId));
                if ($supplierId > 0) {
                    $supplierIds[] = $supplierId;
                }
            }
        }
        
        // Handle new suppliers typed in
        if (isset($_POST['new_suppliers']) && is_array($_POST['new_suppliers'])) {
            foreach ($_POST['new_suppliers'] as $newSupplierName) {
                $newSupplierName = trim($newSupplierName);
                if (!empty($newSupplierName)) {
                    // Check if supplier already exists
                    $checkSql = "SELECT id FROM suppliers WHERE name = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->execute([$newSupplierName]);
                    $existingSupplier = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingSupplier) {
                        // Use existing supplier
                        $supplierIds[] = $existingSupplier['id'];
                    } else {
                        // Insert new supplier
                        $insertSql = "INSERT INTO suppliers (name) VALUES (?)";
                        $insertStmt = $conn->prepare($insertSql);
                        $insertStmt->execute([$newSupplierName]);
                        $supplierIds[] = $conn->lastInsertId();
                    }
                }
            }
        }
        
        // Insert supplier associations (even if empty)
        if (!empty($supplierIds)) {
            $sql = "INSERT INTO pr_suppliers (pr_id, supplier_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach (array_unique($supplierIds) as $supplierId) {
                $stmt->execute([$prId, $supplierId]);
            }
        }
        
        $conn->commit();
        
        // Redirect to view-table.php with success message
        header('Location: view-table.php?success=Purchase Request created successfully!');
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
    <style>
        /* Autocomplete styles */
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
        }
        
        .autocomplete-suggestions.active {
            display: block;
        }
        
        .autocomplete-suggestion {
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .autocomplete-suggestion:hover {
            background-color: #f5f5f5;
        }
        
        .autocomplete-suggestion.active {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .supplier-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 5px;
            border: 1px solid #bbdefb;
            font-size: 14px;
        }
        
        .supplier-tag.new {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #a5d6a7;
        }
        
        .supplier-tag i {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .remove-supplier {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: 14px;
            padding: 0 5px;
            transition: transform 0.2s;
        }
        
        .remove-supplier:hover {
            transform: scale(1.2);
        }
        
        .supplier-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .supplier-input-group input {
            flex: 1;
        }
        
        .supplier-input-group button {
            padding: 12px 20px;
            white-space: nowrap;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }

        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
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
                <h1>New Purchase Request</h1>  
            </header>

            <div class="form-containers">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
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

                    <!-- Supplier Section with Autocomplete -->
                    <div class="form-group">
                        <label>Suppliers</label>
                        
                        <!-- Selected suppliers will appear here -->
                        <div id="selectedSuppliers" class="multi-select-container" style="margin-bottom: 15px;">
                            <!-- Selected suppliers appear here -->
                        </div>
                        
                        <!-- Input for adding new suppliers with autocomplete -->
                        <div class="supplier-input-group">
                            <div class="autocomplete-container" style="flex: 1;">
                                <input type="text" 
                                       id="supplierInput" 
                                       class="autocomplete-input" 
                                       placeholder="Type supplier name (select existing or create new)" 
                                       autocomplete="off">
                                <div id="supplierSuggestions" class="autocomplete-suggestions"></div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addSupplierFromInput()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        
                        <small>Type supplier name - suggestions will appear. Press Enter to add as new supplier.</small>
                        
                        <!-- Hidden inputs for selected suppliers -->
                        <div id="supplierHiddenInputs"></div>
                        
                        <!-- Hidden inputs for new suppliers -->
                        <div id="newSupplierHiddenInputs"></div>
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

    <script>
    // Data structures
    let selectedSuppliers = []; // Array of objects: { id: number, name: string, isNew: boolean }
    let allSuppliers = <?php echo json_encode($suppliers); ?>;
    
    // Create a map for quick lookup
    let supplierMap = {};
    allSuppliers.forEach(supplier => {
        supplierMap[supplier.name.toLowerCase()] = supplier.id;
    });
    
    // Autocomplete functionality
    const supplierInput = document.getElementById('supplierInput');
    const suggestionsContainer = document.getElementById('supplierSuggestions');
    let currentFocus = -1;
    
    supplierInput.addEventListener('input', function() {
        const input = this.value.toLowerCase().trim();
        if (input.length < 1) {
            suggestionsContainer.classList.remove('active');
            return;
        }
        
        // Filter suppliers
        const matches = allSuppliers.filter(supplier => 
            supplier.name.toLowerCase().includes(input) &&
            !selectedSuppliers.some(selected => selected.id === supplier.id && !selected.isNew)
        );
        
        // Show suggestions
        if (matches.length > 0) {
            suggestionsContainer.innerHTML = '';
            matches.forEach(supplier => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = supplier.name;
                div.setAttribute('data-id', supplier.id);
                div.setAttribute('data-name', supplier.name);
                div.addEventListener('click', function() {
                    selectSupplier(parseInt(this.dataset.id), this.dataset.name, false);
                });
                suggestionsContainer.appendChild(div);
            });
            suggestionsContainer.classList.add('active');
            currentFocus = -1;
        } else {
            // If no matches, show option to create new
            suggestionsContainer.innerHTML = '';
            const div = document.createElement('div');
            div.className = 'autocomplete-suggestion';
            div.innerHTML = `<i class="fas fa-plus-circle" style="color: #27ae60;"></i> Create new supplier: "${input}"`;
            div.addEventListener('click', function() {
                addNewSupplierFromInput(input);
            });
            suggestionsContainer.appendChild(div);
            suggestionsContainer.classList.add('active');
        }
    });
    
    // Keyboard navigation
    supplierInput.addEventListener('keydown', function(e) {
        const suggestions = document.querySelectorAll('.autocomplete-suggestion');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentFocus++;
            if (currentFocus >= suggestions.length) currentFocus = 0;
            updateActiveSuggestion(suggestions);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentFocus--;
            if (currentFocus < 0) currentFocus = suggestions.length - 1;
            updateActiveSuggestion(suggestions);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentFocus > -1 && suggestions[currentFocus]) {
                suggestions[currentFocus].click();
            } else if (supplierInput.value.trim()) {
                addNewSupplierFromInput(supplierInput.value.trim());
            }
        } else if (e.key === 'Escape') {
            suggestionsContainer.classList.remove('active');
        }
    });
    
    function updateActiveSuggestion(suggestions) {
        suggestions.forEach((suggestion, index) => {
            if (index === currentFocus) {
                suggestion.classList.add('active');
            } else {
                suggestion.classList.remove('active');
            }
        });
    }
    
    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!supplierInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.classList.remove('active');
        }
    });
    
    // Function to select existing supplier
    function selectSupplier(id, name, isNew = false) {
        // Check if already selected
        if (selectedSuppliers.some(s => s.id === id && s.isNew === isNew)) {
            showToast('Supplier already added', 'warning');
            supplierInput.value = '';
            suggestionsContainer.classList.remove('active');
            return;
        }
        
        selectedSuppliers.push({
            id: id,
            name: name,
            isNew: isNew
        });
        
        updateSelectedSuppliersDisplay();
        updateHiddenInputs();
        
        supplierInput.value = '';
        suggestionsContainer.classList.remove('active');
    }
    
    // Function to add new supplier from input
    function addNewSupplierFromInput(name) {
        if (!name || name.trim() === '') return;
        
        name = name.trim();
        
        // Check if it's already selected as a new supplier
        if (selectedSuppliers.some(s => s.name.toLowerCase() === name.toLowerCase() && s.isNew)) {
            showToast('Supplier already added', 'warning');
            supplierInput.value = '';
            suggestionsContainer.classList.remove('active');
            return;
        }
        
        // Check if it exists in database
        const existingSupplier = allSuppliers.find(s => s.name.toLowerCase() === name.toLowerCase());
        
        if (existingSupplier) {
            // Use existing supplier
            selectSupplier(existingSupplier.id, existingSupplier.name, false);
        } else {
            // Create a temporary ID (negative for new suppliers)
            const tempId = Date.now() + Math.random(); // Unique temporary ID
            
            selectedSuppliers.push({
                id: tempId,
                name: name,
                isNew: true
            });
            
            updateSelectedSuppliersDisplay();
            updateHiddenInputs();
            
            supplierInput.value = '';
            suggestionsContainer.classList.remove('active');
            
            showToast(`New supplier "${name}" will be created when saved`, 'info');
        }
    }
    
    // Function to add supplier from input (called by Add button)
    function addSupplierFromInput() {
        const name = supplierInput.value.trim();
        if (name) {
            addNewSupplierFromInput(name);
        }
    }
    
    // Function to remove supplier
    function removeSupplier(id, isNew) {
        selectedSuppliers = selectedSuppliers.filter(s => !(s.id === id && s.isNew === isNew));
        updateSelectedSuppliersDisplay();
        updateHiddenInputs();
    }
    
    // Function to update the display of selected suppliers
    function updateSelectedSuppliersDisplay() {
        const container = document.getElementById('selectedSuppliers');
        container.innerHTML = '';
        
        selectedSuppliers.forEach(supplier => {
            const tag = document.createElement('span');
            tag.className = `supplier-tag ${supplier.isNew ? 'new' : ''}`;
            
            if (supplier.isNew) {
                tag.innerHTML = `
                    ${supplier.name} 
                    <i class="fas fa-plus-circle" title="New supplier (will be created)"></i>
                    <button type="button" class="remove-supplier" onclick="removeSupplier('${supplier.id}', true)">
                        <i class="fas fa-times"></i>
                    </button>
                `;
            } else {
                tag.innerHTML = `
                    ${supplier.name}
                    <button type="button" class="remove-supplier" onclick="removeSupplier(${supplier.id}, false)">
                        <i class="fas fa-times"></i>
                    </button>
                `;
            }
            
            container.appendChild(tag);
        });
    }
    
    // Function to update hidden inputs
    function updateHiddenInputs() {
        const existingContainer = document.getElementById('supplierHiddenInputs');
        const newContainer = document.getElementById('newSupplierHiddenInputs');
        
        existingContainer.innerHTML = '';
        newContainer.innerHTML = '';
        
        selectedSuppliers.forEach(supplier => {
            if (!supplier.isNew) {
                // Existing supplier
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'suppliers[]';
                input.value = supplier.id;
                existingContainer.appendChild(input);
            } else {
                // New supplier
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'new_suppliers[]';
                input.value = supplier.name;
                newContainer.appendChild(input);
            }
        });
    }
    
    // Toast notification function
    function showToast(message, type = 'info') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
            `;
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
            color: ${type === 'warning' ? '#212529' : 'white'};
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
        
        toast.innerHTML = `
            <div>${message}</div>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 20px; cursor: pointer;">&times;</button>
        `;
        
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    }
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    // Historical record toggle
    document.getElementById('historical_record').addEventListener('change', function() {
        const options = document.getElementById('historical_options');
        const dateInput = document.getElementById('date');
        
        if (this.checked) {
            options.style.display = 'block';
            dateInput.addEventListener('change', updateHistoricalPRNumber);
        } else {
            options.style.display = 'none';
            document.getElementById('manual_pr_number').value = '';
            dateInput.removeEventListener('change', updateHistoricalPRNumber);
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
        
        if (!/^\d{4}-\d{2}-\d{3}$/.test(manualBase)) {
            alert('Please enter PR base number in format: YYYY-MM-NNN (e.g., 2025-01-007)');
            return;
        }
        
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
    
    async function updatePRNumber() {
        const date = document.getElementById('date').value;
        if (!date) return;
        
        try {
            const response = await fetch('generate-numbers.php?date=' + date);
            const data = await response.json();
            document.getElementById('pr_number').value = data.pr_number;
            
            const poInput = document.getElementById('po_number');
            if (!poInput.value) {
                poInput.placeholder = 'Auto: ' + data.po_number;
            }
        } catch (error) {
            console.error('Error updating numbers:', error);
        }
    }

    // Update the form submission handler
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('prForm');
        let isSubmitting = false;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default submission first
            
            if (isSubmitting) {
                showToast('Form is already being submitted. Please wait...', 'warning');
                return false;
            }
            
            isSubmitting = true;
            
            // Disable the submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }
            
            // Now submit the form
            form.submit();
            
            return true;
        });
        
        // Prevent double-click on the button itself
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.addEventListener('dblclick', function(e) {
                e.preventDefault();
                showToast('Please click only once', 'warning');
            });
        }
    });
    </script>
</body>
</html>