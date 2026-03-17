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

            <div class="form-group">
                <label for="edit_supplier_select">Suppliers</label>
                <select id="edit_supplier_select">
                    <option value="">-- Select Supplier --</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>">
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <div class="selected-suppliers" id="selectedSuppliersContainer">
                    <!-- Selected suppliers will appear here -->
                </div>
                <input type="hidden" id="edit_suppliers" name="suppliers">
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
        let selectedSuppliers = [];
        let supplierMap = {};
        
        // Get PR ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const prId = urlParams.get('id');
        
        // Store supplier map
        const supplierSelect = document.getElementById('edit_supplier_select');
        Array.from(supplierSelect.options).forEach(option => {
            if (option.value) {
                supplierMap[option.value] = option.text;
            }
        });
        
        // Load PR data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (prId) {
                loadPRData(prId);
            } else {
                document.getElementById('loading').innerHTML = '<p style="color: #e74c3c;">Error: No PR ID specified</p>';
            }
        });
        
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
                    
                    // Set selected suppliers
                    selectedSuppliers = pr.supplier_ids || [];
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
        
        // Handle supplier selection
        document.getElementById('edit_supplier_select').addEventListener('change', function() {
            const supplierId = this.value;
            if (supplierId && !selectedSuppliers.includes(supplierId)) {
                selectedSuppliers.push(supplierId);
                updateSelectedSuppliersDisplay();
                this.value = '';
            }
        });
        
        function updateSelectedSuppliersDisplay() {
            const container = document.getElementById('selectedSuppliersContainer');
            container.innerHTML = '';
            
            selectedSuppliers.forEach(supplierId => {
                const supplierName = supplierMap[supplierId] || `Supplier #${supplierId}`;
                const tag = document.createElement('div');
                tag.className = 'supplier-tag';
                tag.innerHTML = `
                    ${supplierName}
                    <button type="button" class="remove-supplier" onclick="removeSupplier('${supplierId}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(tag);
            });
            
            // Update hidden input
            document.getElementById('edit_suppliers').value = selectedSuppliers.join(',');
        }
        
        function removeSupplier(supplierId) {
            selectedSuppliers = selectedSuppliers.filter(id => id !== supplierId);
            updateSelectedSuppliersDisplay();
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
            
            try {
                const response = await fetch('update-pr.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Send message to opener window if it exists
                    if (window.opener && !window.opener.closed) {
                        window.opener.location.reload();
                    }
                    window.close();
                } else {
                    alert('Error: ' + data.message);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving data: ' + error.message);
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
    </script>
</body>
</html>