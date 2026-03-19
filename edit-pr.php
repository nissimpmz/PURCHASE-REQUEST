<?php
require_once 'config.php';

// Get suppliers for the dropdown
$suppliers = getSuppliers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Purchase Request</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2c3e50;
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .selected-suppliers {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        
        .supplier-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border: 1px solid #bbdefb;
        }
        
        .supplier-tag.new {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #a5d6a7;
        }
        
        .remove-supplier {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-cancel {
            background: #e74c3c;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #c0392b;
        }
        
        .btn-save {
            background: #27ae60;
            color: white;
        }
        
        .btn-save:hover {
            background: #219653;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .loading i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #3498db;
        }
        
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

        /* SO# field specific styles */
        .so-number-field {
            background-color: #f9f9f9;
        }
        
        .so-number-field:focus {
            background-color: #fff;
        }
        
        .field-hint {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-edit"></i> Edit Purchase Request</h1>
        
        <div id="loading" class="loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading data...</p>
        </div>
        
        <form id="editForm" style="display: none;">
            <input type="hidden" id="edit_id" name="id">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_date">Date *</label>
                    <input type="date" id="edit_date" name="date" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_pr_number">PR Number</label>
                    <input type="text" id="edit_pr_number" name="pr_number" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_particulars">Particulars *</label>
                <textarea id="edit_particulars" name="particulars" required></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_amount">Amount (Optional)</label>
                    <input type="number" id="edit_amount" name="amount" step="0.01" min="0">
                </div>
                
                <div class="form-group">
                    <label for="edit_contract_amount">Contract Amount</label>
                    <input type="number" id="edit_contract_amount" name="contract_amount" step="0.01" min="0">
                </div>
            </div>

            <!-- Supplier Section with Autocomplete -->
            <div class="form-group">
                <label for="edit_suppliers">Suppliers</label>
                
                <!-- Selected suppliers will appear here -->
                <div class="selected-suppliers" id="selectedSuppliersContainer">
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
                    <button type="button" class="btn btn-primary" onclick="addSupplierFromInput()" style="background-color: #3498db; color: white;">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                
                <small>Type supplier name - suggestions will appear. Press Enter to add as new supplier.</small>
                
                <!-- Hidden inputs for selected suppliers -->
                <input type="hidden" id="edit_suppliers" name="suppliers">
                <div id="newSupplierHiddenInputs"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_po_number">PO Number</label>
                    <input type="text" id="edit_po_number" name="po_number">
                </div>
                
                <div class="form-group">
                    <label for="edit_po_date">PO Date</label>
                    <input type="date" id="edit_po_date" name="po_date">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_iar_number">IAR #</label>
                    <input type="text" id="edit_iar_number" name="iar_number">
                </div>
                
                <div class="form-group">
                    <label for="edit_iar_date">IAR Date</label>
                    <input type="date" id="edit_iar_date" name="iar_date">
                </div>
            </div>

            <!-- SO# Field - Add this section -->
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_so_number">
                        <i class="fas fa-hashtag" style="color: #3498db;"></i> SO # (Special Order)
                    </label>
                    <input type="text" 
                           id="edit_so_number" 
                           name="so_number" 
                           class="so-number-field"
                           placeholder="Enter Special Order Number"
                           value="">
                    <div class="field-hint">
                        <i class="fas fa-info-circle"></i> Enter the Sales Order number manually
                    </div>
                </div>
                <div class="form-group">
                    <!-- Empty column for spacing -->
                </div>
            </div>

            <div class="btn-group">
                <button type="button" id="cancelBtn" class="btn btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <script>
        // Data structures
        let selectedSuppliers = []; // Array of objects: { id: number, name: string, isNew: boolean }
        let allSuppliers = <?php echo json_encode($suppliers); ?>;
        let supplierMap = {};
        
        // Store supplier map
        allSuppliers.forEach(supplier => {
            supplierMap[supplier.id] = supplier.name;
            supplierMap[supplier.name.toLowerCase()] = supplier.id;
        });
        
        // Autocomplete functionality
        const supplierInput = document.getElementById('supplierInput');
        const suggestionsContainer = document.getElementById('supplierSuggestions');
        let currentFocus = -1;
        
        // Get PR ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const prId = urlParams.get('id');
        
        // Load PR data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (prId) {
                loadPRData(prId);
            } else {
                document.getElementById('loading').innerHTML = '<p style="color: #e74c3c;">Error: No PR ID specified</p>';
            }
            
            // Autocomplete event listeners
            setupAutocomplete();
        });
        
        function setupAutocomplete() {
            supplierInput.addEventListener('input', function() {
                const input = this.value.toLowerCase().trim();
                if (input.length < 1) {
                    suggestionsContainer.classList.remove('active');
                    return;
                }
                
                // Filter suppliers
                const matches = allSuppliers.filter(supplier => 
                    supplier.name.toLowerCase().includes(input) &&
                    !selectedSuppliers.some(selected => 
                        !selected.isNew && selected.id === supplier.id
                    )
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
            
            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!supplierInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.classList.remove('active');
                }
            });
        }
        
        function updateActiveSuggestion(suggestions) {
            suggestions.forEach((suggestion, index) => {
                if (index === currentFocus) {
                    suggestion.classList.add('active');
                } else {
                    suggestion.classList.remove('active');
                }
            });
        }
        
        async function loadPRData(id) {
            try {
                const response = await fetch(`get-pr-data.php?id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    // Hide loading, show form
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('editForm').style.display = 'block';
                    
                    // Populate form fields
                    const pr = data.pr;
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
                    
                    // IMPORTANT: Set the SO# value - this ensures it displays when editing
                    document.getElementById('edit_so_number').value = pr.so_number || '';
                    
                    // Log for debugging (remove in production)
                    console.log('Loaded SO#:', pr.so_number);
                    
                    // Set selected suppliers - convert existing supplier IDs to proper objects
                    if (pr.supplier_ids && pr.supplier_ids.length > 0) {
                        selectedSuppliers = pr.supplier_ids.map(id => ({
                            id: parseInt(id),
                            name: supplierMap[id] || `Supplier #${id}`,
                            isNew: false
                        }));
                    } else {
                        selectedSuppliers = [];
                    }
                    
                    updateSelectedSuppliersDisplay();
                    
                } else {
                    document.getElementById('loading').innerHTML = 
                        `<p style="color: #e74c3c;">Error: ${data.message}</p>`;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('loading').innerHTML = 
                    `<p style="color: #e74c3c;">Error loading data: ${error.message}</p>`;
            }
        }
        
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
                const tempId = 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                
                selectedSuppliers.push({
                    id: tempId,
                    name: name,
                    isNew: true
                });
                
                updateSelectedSuppliersDisplay();
                
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
        }
        
        // Function to update the display of selected suppliers
        function updateSelectedSuppliersDisplay() {
            const container = document.getElementById('selectedSuppliersContainer');
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
            
            // Update hidden inputs
            updateHiddenInputs();
        }
        
        // Function to update hidden inputs
        function updateHiddenInputs() {
            const existingContainer = document.getElementById('edit_suppliers');
            const newContainer = document.getElementById('newSupplierHiddenInputs');
            
            // For existing suppliers, we need to send IDs
            const existingIds = selectedSuppliers
                .filter(s => !s.isNew)
                .map(s => s.id)
                .join(',');
            
            existingContainer.value = existingIds;
            
            // For new suppliers, we need to send names
            newContainer.innerHTML = '';
            selectedSuppliers
                .filter(s => s.isNew)
                .forEach(supplier => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'new_suppliers[]';
                    input.value = supplier.name;
                    newContainer.appendChild(input);
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
        
        // Handle form submission
        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Show loading on submit button
            const submitBtn = this.querySelector('.btn-save');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            
            // Log the SO# value being sent (for debugging)
            console.log('Submitting SO#:', formData.get('so_number'));
            
            try {
                const response = await fetch('update-pr.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    // Send message to opener window if it exists
                    if (window.opener && !window.opener.closed) {
                        window.opener.location.reload();
                    }
                    setTimeout(() => window.close(), 800);
                } else {
                    showToast('Error: ' + data.message, 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error saving data: ' + error.message, 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Handle cancel
        document.getElementById('cancelBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                window.close();
            }
        });
        
        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                    window.close();
                }
            }
        });
        
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
    </script>
</body>
</html>