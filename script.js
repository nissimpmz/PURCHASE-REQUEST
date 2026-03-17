// script.js - Main JavaScript file

// Initialize date pickers with current date
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates for date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value && input.id !== 'date') {
            input.valueAsDate = new Date();
        }
    });
    
    // Currency formatting
    const amountInputs = document.querySelectorAll('input[name="amount"], input[name="contract_amount"]');
    amountInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e74c3c';
                    
                    // Add error message
                    let errorMsg = field.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                        errorMsg = document.createElement('small');
                        errorMsg.className = 'error-message';
                        errorMsg.style.color = '#e74c3c';
                        errorMsg.textContent = 'This field is required';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                } else {
                    field.style.borderColor = '#ddd';
                    const errorMsg = field.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('error-message')) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
});

// Print functionality
function printTable() {
    const printContent = document.querySelector('.recent-prs').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Purchase Requests Print</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #000; padding: 8px; }
                    th { background-color: #f2f2f2; }
                    h2 { text-align: center; margin-bottom: 20px; }
                    .print-date { text-align: right; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <h2>Purchase Requests Report</h2>
                <div class="print-date">Printed on: ${new Date().toLocaleDateString()}</div>
                ${printContent}
            </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Search functionality for tables
function searchTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.querySelector('table');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < td.length; j++) {
            if (td[j]) {
                const txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = found ? '' : 'none';
    }
}

// Export to Excel using TableExport library fallback
function exportToExcelFallback() {
    const table = document.querySelector('table');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Clean text
            let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            // Escape commas
            text = text.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = `purchase_requests_${new Date().toISOString().split('T')[0]}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Confirm before delete
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Date manipulation
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Generate unique ID
function generateId() {
    return 'id_' + Math.random().toString(36).substr(2, 9);
}

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export to Excel function
function exportToExcel() {
    // Show export options modal
    showExportOptionsModal();
}

// Export to Excel with options
function exportFilteredPRs(startDate, endDate) {
    let url = 'export.php';
    const params = new URLSearchParams();
    
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    
    if (params.toString()) {
        url += '?' + params.toString();
    }
    
    window.location.href = url;
}

// Show export options modal
function showExportOptionsModal() {
    const modal = document.createElement('div');
    modal.id = 'exportOptionsModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 400px;
        ">
            <h3 style="margin-top: 0; color: #2c3e50;">
                <i class="fas fa-file-excel" style="margin-right: 10px; color: #21a366;"></i>
                Export to Excel Options
            </h3>
            
            <div style="margin: 20px 0;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                    <input type="radio" name="exportOption" value="all" checked style="margin-right: 8px;">
                    Export All Records
                </label>
                
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                    <input type="radio" name="exportOption" value="range" style="margin-right: 8px;">
                    Export Date Range
                </label>
                
                <div id="exportDateRangeOptions" style="margin-top: 15px; display: none; padding-left: 25px;">
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px; font-size: 14px;">From:</label>
                        <input type="date" id="exportStartDate" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 14px;">To:</label>
                        <input type="date" id="exportEndDate" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeExportModal()" style="
                    padding: 10px 20px;
                    background: #e74c3c;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                ">
                    Cancel
                </button>
                <button onclick="processExport()" style="
                    padding: 10px 20px;
                    background: #21a366;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                ">
                    <i class="fas fa-file-excel" style="margin-right: 5px;"></i>
                    Export
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listeners for radio buttons
    const radioButtons = modal.querySelectorAll('input[name="exportOption"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            const dateRangeOptions = document.getElementById('exportDateRangeOptions');
            dateRangeOptions.style.display = this.value === 'range' ? 'block' : 'none';
        });
    });
    
    // Set default dates (current month)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('exportStartDate').value = formatDateForInput(firstDay);
    document.getElementById('exportEndDate').value = formatDateForInput(lastDay);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

// Close export modal
function closeExportModal() {
    const modal = document.getElementById('exportOptionsModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
    }
}


// Process export based on selected options
function processExport() {
    const selectedOption = document.querySelector('input[name="exportOption"]:checked').value;
    
    if (selectedOption === 'all') {
        window.location.href = 'export.php';
    } else if (selectedOption === 'range') {
        const startDate = document.getElementById('exportStartDate').value;
        const endDate = document.getElementById('exportEndDate').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates.');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert('Start date cannot be after end date.');
            return;
        }
        
        exportFilteredPRs(startDate, endDate);
    }
    
    closeExportModal();
}

// Print functionality for purchase requests
function printPRs() {
    fetch('get-print-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                generatePrintDocument(data.requests, null, null);
            } else {
                alert('Error loading data for printing: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading data for printing');
        });
}

// Helper function for date formatting
function formatDateForPrint(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return '';
    }
}


function printFilteredPRs(startDate, endDate) {
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    
    fetch(`get-print-data.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                generatePrintDocument(data.requests, startDate, endDate);
            } else {
                alert('Error loading data for printing: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading data for printing');
        });
}

// Add event listener for print in sidebar
document.addEventListener('DOMContentLoaded', function() {
    // Find the print link in sidebar and update its click handler
    const printLinks = document.querySelectorAll('a[href*="#"], a[onclick*="print"]');
    
    printLinks.forEach(link => {
        if (link.textContent.includes('Print') || link.innerHTML.includes('fa-print')) {
            link.onclick = function(e) {
                e.preventDefault();
                
                // Show print options modal
                showPrintOptionsModal();
            };
        }
    });
});

// Show print options modal
function showPrintOptionsModal() {
    const modal = document.createElement('div');
    modal.id = 'printOptionsModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 400px;
        ">
            <h3 style="margin-top: 0; color: #2c3e50;">
                <i class="fas fa-print" style="margin-right: 10px;"></i>
                Print Options
            </h3>
            
            <div style="margin: 20px 0;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                    <input type="radio" name="printOption" value="all" checked style="margin-right: 8px;">
                    Print All Records
                </label>
                
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                    <input type="radio" name="printOption" value="range" style="margin-right: 8px;">
                    Print Date Range
                </label>
                
                <div id="dateRangeOptions" style="margin-top: 15px; display: none; padding-left: 25px;">
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px; font-size: 14px;">From:</label>
                        <input type="date" id="startDate" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 14px;">To:</label>
                        <input type="date" id="endDate" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closePrintModal()" style="
                    padding: 10px 20px;
                    background: #e74c3c;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                ">
                    Cancel
                </button>
                <button onclick="processPrint()" style="
                    padding: 10px 20px;
                    background: #3498db;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                ">
                    <i class="fas fa-print" style="margin-right: 5px;"></i>
                    Print
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listeners for radio buttons
    const radioButtons = modal.querySelectorAll('input[name="printOption"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            const dateRangeOptions = document.getElementById('dateRangeOptions');
            dateRangeOptions.style.display = this.value === 'range' ? 'block' : 'none';
        });
    });
    
    // Set default dates (current month)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('startDate').value = formatDateForInput(firstDay);
    document.getElementById('endDate').value = formatDateForInput(lastDay);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';

        // Add event listener to show selected dates
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    function updateDateRangeDisplay() {
        if (startDateInput.value && endDateInput.value) {
            // You can optionally show a preview of the selected date range
            console.log('Selected range:', startDateInput.value, 'to', endDateInput.value);
        }
    }
    
    startDateInput.addEventListener('change', updateDateRangeDisplay);
    endDateInput.addEventListener('change', updateDateRangeDisplay);
}

// Generate print document
function generatePrintDocument(requests, startDate = null, endDate = null) {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Create a base64 encoded placeholder logo
    const placeholderLogo = 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/Official_PhilFIDA_Logo.png/1280px-Official_PhilFIDA_Logo.png?20240710055741';
    
    function formatDateForDisplay(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            // Check if date is valid
            if (isNaN(date.getTime())) {
                return dateString; // Return original string if can't parse
            }
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (e) {
            return dateString; // Return original string on error
        }
    }
    
    // Determine report period text
    let reportPeriodText;
    if (startDate && endDate) {
        reportPeriodText = formatDateForDisplay(startDate) + ' to ' + formatDateForDisplay(endDate);
    } else {
        reportPeriodText = 'All Records';
    }
    
    let printContent = `
        <html>
        <head>
            <title>Purchase Requests Report</title>
            <style>
                @page {
                    size: portrait;
                    margin: 0.2in 0.4in 0.4in 0.4in;
                }
                body {
                    font-family: 'Arial', sans-serif;
                    margin: 0;
                    padding: 15px;
                    font-size: 10.5px;
                }
                .header-container {
                    display: flex;
                    align-items: center;
                    margin-bottom: 10px;
                    padding-bottom: 5px;
                    border-bottom: 2px solid #000;
                }
                .logo-container {
                    flex: 0 0 auto;
                    margin-right: 15px;
                }
                .logo {
                    width: 80px;
                    height: 80px;
                    object-fit: contain;
                }
                .company-info {
                    flex: 1;
                }
                .company-title {
                    font-size: 14px;
                    font-weight: bold;
                    margin: 0 0 5px 0;
                    color: #2c3e50;
                }
                .company-details {
                    font-size: 9px;
                    margin: 0;
                    line-height: 1.3;
                    color: #666;
                }
                .print-info {
                    text-align: right;
                    font-size: 8px;
                    color: #666;
                    margin-bottom: 5px;
                }
                .report-title {
                    text-align: center;
                    margin: 8px 0;
                    font-size: 12px;
                    font-weight: bold;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    page-break-inside: auto;
                }
                th {
                    background-color: #f2f2f2;
                    color: #333;
                    font-weight: bold;
                    padding: 4px 3px;
                    text-align: left;
                    border: 1px solid #000;
                    font-size: 9px;
                }
                td {
                    padding: 3px 3px;
                    border: 1px solid #000;
                    font-size: 10px;
                    vertical-align: top;
                }
                tr {
                    page-break-inside: avoid;
                }
                .summary {
                    margin: 5px 0;
                    padding: 5px;
                    background-color: #f8f9fa;
                    border-radius: 3px;
                    font-size: 9px;
                }
                .summary p {
                    margin: 2px 0;
                }
                @media print {
                    body {
                        padding: 0;
                        font-size: 10px;
                    }
                    table {
                        font-size: 9px;
                    }
                    th, td {
                        padding: 2px 2px;
                    }
                }
                .signature-section {
                    margin-top: 15px;
                    padding-top: 8px;
                    border-top: 1px solid #ddd;
                    font-size: 10px;
                }
                .signature-box {
                    display: flex;
                    justify-content: space-between;
                }
                .signature-line {
                    margin-top: 20px;
                    text-align: center;
                }
                .signature-line p {
                    margin: 2px 0;
                }
                .page-break {
                    page-break-before: always;
                }
            </style>
        </head>
        <body>
            <div class="header-container">
                <div class="logo-container">
                    <img src="${placeholderLogo}" alt="Company Logo" class="logo">
                </div>
                <div class="company-info">
                    <h1 class="company-title">PHILIPPINE FIBER INDUSTRY DEVELOPMENT AUTHORITY REGION VII</h1>
                    <p class="company-details">
                        Mezzanine Floor, LDM Building, M.J. Cuenco Avenue Corner Legaspi Street, Cebu City 6000<br>
                        Tel. No.: (032) 256 1664 | Telefax: (032) 253 9643<br>
                        Email: rocebu@philfida.da.gov.ph | Website: www.philfida.da.gov.ph
                    </p>
                </div>
            </div>
            
            <div class="print-info">
                <strong>Date Printed:</strong> ${new Date().toLocaleString()}
            </div>
            
            <div class="report-title">
                PURCHASE REQUEST ABSTRACT OF CANVASS PURCHASE ORDER INSPECTION AND ACCEPTANCE REPORT
            </div>
            
            <div class="summary">
                <p><strong>Total PRs:</strong> ${requests.length} | <strong>Report Period:</strong> ${reportPeriodText}</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">PR Date</th>
                        <th style="width: 80px;">PR Number</th>
                        <th style="width: 120px;">Particulars</th>
                        <th style="width: 60px;">Amount</th>
                        <th style="width: 90px;">Supplier(s)</th>
                        <th style="width: 80px;">PO Number</th>
                        <th style="width: 60px;">PO Date</th>
                        <th style="width: 80px;">Contract Amount</th>
                        <th style="width: 60px;">IAR #</th>
                        <th style="width: 60px;">IAR Date</th>
                    </tr>
                </thead>
                <tbody>`;
    
    // Count for page breaks
    let rowCount = 0;
    const maxRowsPerPage = 50;
    
    requests.forEach((request, index) => {
        // Add page break if needed (every maxRowsPerPage rows)
        if (index > 0 && index % maxRowsPerPage === 0) {
            printContent += `
                </tbody>
                </table>
                <div class="page-break"></div>
                <!-- Repeat header on new page -->
                <div class="header-container">
                    <div class="logo-container">
                        <img src="${placeholderLogo}" alt="Company Logo" class="logo">
                    </div>
                    <div class="company-info">
                        <h1 class="company-title">PHILIPPINE FIBER INDUSTRY DEVELOPMENT AUTHORITY REGION VII</h1>
                        <p class="company-details">
                            Mezzanine Floor, LDM Building, M.J. Cuenco Avenue Corner Legaspi Street, Cebu City 6000<br>
                            Tel. No.: (032) 256 1664 | Telefax: (032) 253 9643<br>
                            Email: rocebu@philfida.da.gov.ph | Website: www.philfida.da.gov.ph
                        </p>
                    </div>
                </div>
                <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">PR Date</th>
                        <th style="width: 80px;">PR Number</th>
                        <th style="width: 120px;">Particulars</th>
                        <th style="width: 60px;">Amount</th>
                        <th style="width: 90px;">Supplier(s)</th>
                        <th style="width: 80px;">PO Number</th>
                        <th style="width: 60px;">PO Date</th>
                        <th style="width: 80px;">Contract Amount</th>
                        <th style="width: 60px;">IAR #</th>
                        <th style="width: 60px;">IAR Date</th>
                        <th style="width: 60px;">SO #</th>
                    </tr>
                </thead>
                <tbody>`;
        }
        
        printContent += `
            <tr>
                <td>${request.date || 'N/A'}</td>
                <td><strong>${request.pr_number || 'N/A'}</strong></td>
                <td>${request.particulars || 'N/A'}</td>
                <td style="text-align: right;">₱${request.amount ? parseFloat(request.amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') : '0.00'}</td>
                <td>${request.supplier_names ? request.supplier_names.split(', ').map(s => 
                    `<span style="display: block; margin: 1px 0; font-size: px;">${s}</span>`
                ).join('') : ''}</td>
                <td>${request.po_number || ''}</td>
                <td>${request.po_date || ''}</td>
                <td style="text-align: right;">${request.contract_amount ? '₱' + parseFloat(request.contract_amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') : ''}</td>
                <td>${request.iar_number || ''}</td>
                <td>${request.iar_date || ''}</td>
                <td>${request.so_number || ''}</td>
            </tr>`;
        
        rowCount++;
    });
    
    printContent += `
                </tbody>
            </table>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">
                        <p><strong>Prepared by:</strong></p><br><p>____________________________</p>
                        <p><em>Signature over Printed Name</em></p>
                    </div>
                    <div class="signature-line">
                        <p><strong>Reviewed by:</strong></p><br><p>____________________________</p>
                        <p><em>Signature over Printed Name</em></p>
                    </div>
                    <div class="signature-line">
                        <p><strong>Approved by:</strong></p><br><p>____________________________</p>
                        <p><em>Signature over Printed Name</em></p>
                    </div>
                </div>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                }
            <\/script>
        </body>
        </html>`;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
}

// Close print modal
function closePrintModal() {
    const modal = document.getElementById('printOptionsModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
    }
}

// Format date for input field (YYYY-MM-DD)
function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}


// Process print based on selected options
function processPrint() {
    const selectedOption = document.querySelector('input[name="printOption"]:checked').value;
    
    if (selectedOption === 'all') {
        printPRs();
    } else if (selectedOption === 'range') {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates.');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert('Start date cannot be after end date.');
            return;
        }
        
        printFilteredPRs(startDate, endDate);
    }
    
    closePrintModal();
}