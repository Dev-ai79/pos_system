<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    error_log("sell.php: No user_id, redirecting to index.php");
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, name, selling_price, stock FROM products WHERE stock > 0");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("sell.php: Fetched " . count($products) . " products");
    if (empty($products)) {
        $_SESSION['warning'] = "No products with stock available. Add products in Manage Inventory.";
    }
} catch (PDOException $e) {
    error_log("sell.php: Query error - " . $e->getMessage());
    $_SESSION['error'] = "Failed to fetch products. Check database.";
    $products = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sell Products - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .table-wrapper {
            display: block;
            width: 100%;
            overflow-x: auto;
        }
        #product-table {
            display: table;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        #product-table thead, #product-table tbody {
            display: table-row-group;
        }
        #product-table tr {
            display: table-row;
        }
        #product-table th, #product-table td {
            display: table-cell;
            border: 1px solid #ccc;
            padding: 8px;
            vertical-align: middle;
            box-sizing: border-box;
        }
        #product-table th {
            background-color: #f5a623;
            color: white;
            font-weight: bold;
            text-align: center;
            min-width: 100px;
        }
        #product-table .col-product {
            min-width: 250px;
            max-width: 300px;
            text-align: left;
        }
        #product-table .col-quantity {
            min-width: 80px;
            max-width: 100px;
            text-align: center;
        }
        #product-table .col-price {
            min-width: 120px;
            max-width: 150px;
            text-align: right;
        }
        #product-table .col-total {
            min-width: 120px;
            max-width: 150px;
            text-align: right;
        }
        #product-table .col-action {
            min-width: 80px;
            max-width: 100px;
            text-align: center;
        }
        #product-table input[type="text"],
        #product-table input[type="number"] {
            width: 100%;
            padding: 4px;
            box-sizing: border-box;
            font-size: 14px;
            border: 1px solid #ccc;
        }
        #product-table input[type="number"] {
            text-align: right;
        }
        #product-table .total-display {
            display: block;
            text-align: right;
            font-size: 14px;
            padding: 4px;
        }
        #product-table button {
            width: 70px;
            padding: 5px;
            font-size: 14px;
            background-color: #ff4d4d;
            color: white;
            border: none;
            cursor: pointer;
        }
        #product-table button:hover {
            background-color: #cc0000;
        }
        .receipt-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .receipt-content {
            background-color: white;
            padding: 20px;
            width: 400px;
            max-width: 90%;
            border-radius: 5px;
            text-align: center;
        }
        .receipt-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .receipt-content th, .receipt-content td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }
        .receipt-content th {
            background-color: #f5a623;
            color: white;
        }
        .receipt-btn {
            background-color: #f5a623;
            color: white;
            padding: 8px 16px;
            border: none;
            cursor: pointer;
            margin: 5px;
        }
        .receipt-btn:hover {
            background-color: #d48f1e;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-content, .receipt-content * {
                visibility: visible;
            }
            .receipt-content {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }
            .receipt-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Sell Products</h1>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['warning'])): ?>
                <p class="warning"><?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <form method="POST" action="process_sale.php" id="sale-form" onsubmit="return validateForm()">
                <div class="search-container">
                    <select id="product-search" class="search-input" onchange="handleProductSelect(this)">
                        <option value="" disabled selected>Select a product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-id="<?php echo $product['id']; ?>"
                                    data-price="<?php echo $product['selling_price']; ?>"
                                    data-stock="<?php echo $product['stock']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> (in stock: <?php echo $product['stock']; ?>pcs, Sell price: Ksh <?php echo number_format($product['selling_price'], 2); ?>)
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?>
                            <option value="Test Product"
                                    data-id="0"
                                    data-price="100.00"
                                    data-stock="10">
                                Test Product (in stock: 10pcs, Sell price: Ksh 100.00)
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="table-wrapper">
                    <table id="product-table">
                        <thead>
                            <tr>
                                <th class="col-product" data-column="product">Product</th>
                                <th class="col-quantity" data-column="quantity">Quantity</th>
                                <th class="col-price" data-column="price">Price (Ksh)</th>
                                <th class="col-total" data-column="total">Total (Ksh)</th>
                                <th class="col-action" data-column="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows added dynamically -->
                        </tbody>
                    </table>
                </div>
                <button type="button" class="orange-btn add-product-btn" onclick="addProductRow()">Add Product</button>
                <button type="submit" class="orange-btn">Complete Sale</button>
            </form>
            <?php if (isset($_SESSION['receipt_data'])): ?>
                <button class="orange-btn receipt-btn" onclick="showReceipt()">View Receipt</button>
                <div class="receipt-modal" id="receipt-modal">
                    <div class="receipt-content">
                        <h2>POS System Receipt</h2>
                        <p>Transaction ID: <?php echo htmlspecialchars($_SESSION['receipt_data']['transaction_id']); ?></p>
                        <p>Date: <?php echo htmlspecialchars($_SESSION['receipt_data']['date']); ?></p>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Price (Ksh)</th>
                                    <th>Total (Ksh)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['receipt_data']['items'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo number_format($item['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p><strong>Grand Total: Ksh <?php echo number_format($_SESSION['receipt_data']['grand_total'], 2); ?></strong></p>
                        <button class="receipt-btn" onclick="downloadPDF()">Download PDF</button>
                        <button class="receipt-btn" onclick="printReceipt()">Print</button>
                        <button class="receipt-btn" onclick="closeReceipt()">Close</button>
                    </div>
                </div>
                <?php unset($_SESSION['receipt_data']); ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function addProductRow(product = null) {
            const tbody = document.querySelector('#product-table tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="col-product" data-column="product">
                    <input type="text" name="products[]" placeholder="Select or type product" 
                           value="${product ? product.name : ''}" list="product-list">
                    <input type="hidden" name="product_ids[]" value="${product ? product.id : ''}">
                </td>
                <td class="col-quantity" data-column="quantity">
                    <input type="number" name="quantities[]" 
                           placeholder="Qty" min="1" required 
                           value="${product ? 1 : ''}">
                </td>
                <td class="col-price" data-column="price">
                    <input type="number" name="prices[]" 
                           step="0.01" min="0.01" placeholder="Price (Ksh)" required 
                           value="${product ? product.price : ''}">
                </td>
                <td class="col-total" data-column="total">
                    <span class="total-display">${product ? (1 * product.price).toFixed(2) : '0.00'}</span>
                </td>
                <td class="col-action" data-column="action">
                    <button type="button" onclick="this.parentElement.parentElement.remove()">Remove</button>
                </td>
            `;
            tbody.appendChild(row);
            console.log('Row added:', row.innerHTML);
            attachInputListeners(row);
            attachProductInputListener(row);
        }

        function handleProductSelect(select) {
            if (!select.value) return;
            const option = select.querySelector(`option[value="${select.value}"]`);
            if (option) {
                const product = {
                    id: option.getAttribute('data-id'),
                    name: select.value,
                    price: option.getAttribute('data-price'),
                    stock: option.getAttribute('data-stock')
                };
                console.log('Selected:', product);
                const tbody = document.querySelector('#product-table tbody');
                const existingRow = Array.from(tbody.querySelectorAll('input[name="product_ids[]"]'))
                    .find(input => input.value === product.id);
                if (existingRow) {
                    alert(`${product.name} is already selected. Please increase quantity in the existing row.`);
                    const quantityInput = existingRow.parentElement.parentElement.querySelector('input[name="quantities[]"]');
                    quantityInput.focus();
                    select.value = '';
                    return;
                }
                const firstRow = tbody.querySelector('tr');
                if (!firstRow || firstRow.querySelector('input[name="products[]"]').value) {
                    addProductRow(product);
                } else {
                    firstRow.querySelector('input[name="products[]"]').value = product.name;
                    firstRow.querySelector('input[name="product_ids[]"]').value = product.id;
                    firstRow.querySelector('input[name="quantities[]"]').value = 1;
                    firstRow.querySelector('input[name="prices[]"]').value = product.price;
                    firstRow.querySelector('.total-display').textContent = (1 * product.price).toFixed(2);
                    firstRow.querySelector('td[data-column="product"]').setAttribute('data-column', 'product');
                    firstRow.querySelector('td[data-column="quantity"]').setAttribute('data-column', 'quantity');
                    firstRow.querySelector('td[data-column="price"]').setAttribute('data-column', 'price');
                    firstRow.querySelector('td[data-column="total"]').setAttribute('data-column', 'total');
                    firstRow.querySelector('td[data-column="action"]').setAttribute('data-column', 'action');
                    attachInputListeners(firstRow);
                    attachProductInputListener(firstRow);
                }
                select.value = '';
            }
        }

        function validateForm() {
            const tbody = document.querySelector('#product-table tbody');
            if (tbody.children.length === 0) {
                alert('Please select at least one product to complete the sale.');
                return false;
            }
            return true;
        }

        function attachInputListeners(row) {
            const quantityInput = row.querySelector('input[name="quantities[]"]');
            const priceInput = row.querySelector('input[name="prices[]"]');
            const totalDisplay = row.querySelector('.total-display');

            function updateTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const total = (quantity * price).toFixed(2);
                totalDisplay.textContent = total;
            }

            quantityInput.addEventListener('input', updateTotal);
            priceInput.addEventListener('input', updateTotal);
        }

        function attachProductInputListener(row) {
            const productInput = row.querySelector('input[name="products[]"]');
            productInput.addEventListener('change', function() {
                const value = this.value;
                const option = document.querySelector(`#product-list option[value="${value}"]`);
                if (option) {
                    const product = {
                        id: option.getAttribute('data-id'),
                        name: value,
                        price: option.getAttribute('data-price'),
                        stock: option.getAttribute('data-stock')
                    };
                    console.log('Input selected:', product);
                    const tbody = document.querySelector('#product-table tbody');
                    const existingRow = Array.from(tbody.querySelectorAll('input[name="product_ids[]"]'))
                        .find(input => input.value === product.id && input !== row.querySelector('input[name="product_ids[]"]'));
                    if (existingRow) {
                        alert(`${product.name} is already selected in another row. Please increase quantity there.`);
                        this.value = '';
                        row.querySelector('input[name="product_ids[]"]').value = '';
                        row.querySelector('input[name="quantities[]"]').value = '';
                        row.querySelector('input[name="prices[]"]').value = '';
                        row.querySelector('.total-display').textContent = '0.00';
                        return;
                    }
                    row.querySelector('input[name="product_ids[]"]').value = product.id;
                    row.querySelector('input[name="quantities[]"]').value = 1;
                    row.querySelector('input[name="prices[]"]').value = product.price;
                    row.querySelector('.total-display').textContent = (1 * product.price).toFixed(2);
                } else {
                    alert('Please select a valid product from the list.');
                    this.value = '';
                    row.querySelector('input[name="product_ids[]"]').value = '';
                    row.querySelector('input[name="quantities[]"]').value = '';
                    row.querySelector('input[name="prices[]"]').value = '';
                    row.querySelector('.total-display').textContent = '0.00';
                }
            });
        }

        // Clear success message and receipt button on search bar or add product click
        function clearMessagesAndReceipt() {
            const successMessage = document.querySelector('p.success');
            const receiptButton = document.querySelector('.receipt-btn');
            if (successMessage) {
                successMessage.remove();
            }
            if (receiptButton) {
                receiptButton.remove();
            }
        }

        document.getElementById('product-search').addEventListener('click', clearMessagesAndReceipt);
        document.querySelector('.add-product-btn').addEventListener('click', clearMessagesAndReceipt);

        // Receipt functions
        function showReceipt() {
            const modal = document.getElementById('receipt-modal');
            modal.style.display = 'flex';
        }

        function closeReceipt() {
            const modal = document.getElementById('receipt-modal');
            modal.style.display = 'none';
        }

        function printReceipt() {
            window.print();
        }

        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const receiptContent = document.querySelector('.receipt-content');
            
            // Header
            doc.setFontSize(16);
            doc.text('POS System Receipt', 20, 20);
            doc.setFontSize(12);
            doc.text(`Transaction ID: ${receiptContent.querySelector('p:nth-child(2)').textContent.split(': ')[1]}`, 20, 30);
            doc.text(`Date: ${receiptContent.querySelector('p:nth-child(3)').textContent.split(': ')[1]}`, 20, 40);

            // Table headers
            const headers = ['Product', 'Qty', 'Price (Ksh)', 'Total (Ksh)'];
            let y = 50;
            doc.setFillColor(245, 166, 35); // #f5a623
            doc.rect(20, y, 170, 10, 'F');
            doc.setTextColor(255, 255, 255);
            headers.forEach((header, i) => {
                doc.text(header, 20 + i * 42.5, y + 7);
            });

            // Table rows
            doc.setTextColor(0, 0, 0);
            const rows = receiptContent.querySelectorAll('tbody tr');
            y += 10;
            rows.forEach((row, index) => {
                const cells = row.querySelectorAll('td');
                doc.rect(20, y, 170, 10);
                doc.text(cells[0].textContent, 20, y + 7);
                doc.text(cells[1].textContent, 62.5, y + 7);
                doc.text(cells[2].textContent, 105, y + 7);
                doc.text(cells[3].textContent, 147.5, y + 7);
                y += 10;
            });

            // Grand Total
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.text(`Grand Total: Ksh ${receiptContent.querySelector('p strong').textContent.split('Ksh ')[1]}`, 20, y + 10);
            doc.setFont('helvetica', 'normal');

            // Save PDF
            const transactionId = receiptContent.querySelector('p:nth-child(2)').textContent.split(': ')[1];
            doc.save(`receipt_${transactionId}.pdf`);
        }
    </script>
    <datalist id="product-list">
        <?php foreach ($products as $product): ?>
            <option value="<?php echo htmlspecialchars($product['name']); ?>"
                    data-id="<?php echo $product['id']; ?>"
                    data-price="<?php echo $product['selling_price']; ?>"
                    data-stock="<?php echo $product['stock']; ?>">
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
            <option value="Test Product"
                    data-id="0"
                    data-price="100.00"
                    data-stock="10">
        <?php endif; ?>
    </datalist>
</body>
</html>