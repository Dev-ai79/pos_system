<?php
session_start();
require 'config.php';
require 'fpdf.php'; // Assuming fpdf.php is in C:\xampp\htdocs\pos_system\

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle AJAX live search request
if (isset($_GET['q'])) {
    $query = trim($_GET['q']);
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.selling_price, i.quantity AS stock 
        FROM products p 
        LEFT JOIN inventory i ON p.id = i.product_id 
        WHERE p.name LIKE ? AND (i.quantity > 0 OR i.quantity IS NULL)
        ORDER BY p.name ASC 
        LIMIT 10
    ");
    $stmt->execute(["%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Handle initial search (non-AJAX)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT p.id, p.name, p.selling_price, i.quantity AS stock 
          FROM products p 
          LEFT JOIN inventory i ON p.id = i.product_id 
          WHERE (i.quantity > 0 OR i.quantity IS NULL)";
$params = [];

if (!empty($search)) {
    $query .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Handle sale and receipt generation
$receipt = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sell'])) {
    $product_ids = $_POST['product_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $custom_selling_prices = $_POST['custom_selling_prices'] ?? [];
    $customer_email = trim($_POST['customer_email'] ?? '');

    $errors = [];
    $items = [];
    $grand_total = 0;
    $timestamp = date('Y-m-d H:i:s');
    $transaction_id = time(); // Unique transaction ID for this batch

    foreach ($product_ids as $index => $product_id) {
        $product_id = (int)$product_id;
        $quantity = (int)$quantities[$index];
        $custom_selling_price = (float)$custom_selling_prices[$index];

        $stmt = $pdo->prepare("SELECT p.name, p.selling_price, i.quantity AS stock 
                               FROM products p 
                               LEFT JOIN inventory i ON p.id = i.product_id 
                               WHERE p.id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if ($product && $quantity > 0 && ($product['stock'] === null || $quantity <= $product['stock']) && $custom_selling_price > 0) {
            $total = $quantity * $custom_selling_price;
            $grand_total += $total;

            // Record the sale with transaction_id
            $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity, selling_price, total, timestamp, transaction_id) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $quantity, $custom_selling_price, $total, $timestamp, $transaction_id]);

            // Update inventory
            $stmt = $pdo->prepare("
                INSERT INTO inventory (product_id, quantity) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE quantity = GREATEST(quantity - ?, 0)
            ");
            $stmt->execute([$product_id, -$quantity, $quantity]);

            $items[] = [
                'product_name' => $product['name'],
                'quantity' => $quantity,
                'selling_price' => $custom_selling_price,
                'total' => $total,
            ];
        } else {
            $errors[] = "Invalid data for '{$product['name']}': Quantity ($quantity) exceeds stock (" . ($product['stock'] ?? 'N/A') . ") or price ($custom_selling_price) is invalid.";
        }
    }

    if (empty($errors) && !empty($items)) {
        $success = "Sale recorded successfully! Grand Total: Ksh " . number_format($grand_total, 2);
        $receipt = [
            'items' => $items,
            'grand_total' => $grand_total,
            'timestamp' => $timestamp,
            'transaction_id' => $transaction_id,
        ];

        // Email receipt if provided
        if (!empty($customer_email)) {
            $subject = "Your Receipt from POS System";
            $message = "Thank you for your purchase!\n\n";
            $message .= "Transaction ID: {$receipt['transaction_id']}\n";
            $message .= "Date: {$receipt['timestamp']}\n\n";
            foreach ($receipt['items'] as $item) {
                $message .= "Product: {$item['product_name']}\n";
                $message .= "Quantity: {$item['quantity']}\n";
                $message .= "Price per Unit: Ksh " . number_format($item['selling_price'], 2) . "\n";
                $message .= "Total: Ksh " . number_format($item['total'], 2) . "\n\n";
            }
            $message .= "Grand Total: Ksh " . number_format($receipt['grand_total'], 2) . "\n\n";
            $message .= "Regards,\nYour POS Team";
            $headers = "From: no-reply@pos-system.com";
            mail($customer_email, $subject, $message, $headers);
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } else {
        $error = implode("<br>", $errors) ?: "No valid items submitted.";
    }
}

// Handle PDF download with FPDF
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['download_pdf']) && !empty($_POST['receipt_data'])) {
    $receipt = json_decode($_POST['receipt_data'], true);

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    $logo_path = 'uploads/company_logo.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 85, 10, 40); // Centered logo, 40mm wide
        $pdf->Ln(50);
    }

    $pdf->Cell(0, 10, 'Receipt', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Transaction ID: {$receipt['transaction_id']}", 0, 1, 'C');
    $pdf->Cell(0, 10, "Date: {$receipt['timestamp']}", 0, 1, 'C');
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(10);

    foreach ($receipt['items'] as $item) {
        $pdf->Cell(0, 10, "Product: {$item['product_name']}", 0, 1, 'L');
        $pdf->Cell(0, 10, "Quantity: {$item['quantity']}", 0, 1, 'L');
        $pdf->Cell(0, 10, "Price per Unit: Ksh " . number_format($item['selling_price'], 2), 0, 1, 'L');
        $pdf->Cell(0, 10, "Total: Ksh " . number_format($item['total'], 2), 0, 1, 'L');
        $pdf->Ln(5);
    }
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(10);
    $pdf->Cell(0, 10, "Grand Total: Ksh " . number_format($receipt['grand_total'], 2), 0, 1, 'L');
    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Thank you for your purchase!', 0, 1, 'C');

    $pdf->Output('D', "receipt_{$receipt['transaction_id']}.pdf");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sell Products</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        #product-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        #product-table th, #product-table td { padding: 8px; text-align: left; }
        #product-table th { background-color: #f2f2f2; }
        .remove-btn { background-color: #ff4444; }
        .search-container { position: relative; margin-bottom: 20px; }
        .search-input { width: 300px; padding: 8px; }
        #search-results { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            width: 300px; 
            background: white; 
            border: 1px solid #ddd; 
            max-height: 200px; 
            overflow-y: auto; 
            display: none; 
            z-index: 1000;
        }
        #search-results div { padding: 8px; cursor: pointer; }
        #search-results div:hover { background: #f0f0f0; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function updateTotal(row) {
            const price = parseFloat(row.querySelector('input[name="custom_selling_prices[]"]').value) || 0;
            const qty = parseInt(row.querySelector('input[name="quantities[]"]').value) || 0;
            const totalSpan = row.querySelector('.total-price');
            totalSpan.textContent = (price * qty).toFixed(2);
            updateGrandTotal();
        }

        function updateGrandTotal() {
            let grandTotal = 0;
            document.querySelectorAll('.total-price').forEach(span => {
                grandTotal += parseFloat(span.textContent) || 0;
            });
            document.getElementById('grand-total').textContent = grandTotal.toFixed(2);
        }

        function addRow() {
            const tbody = document.querySelector('#product-table tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select name="product_ids[]" onchange="updateTotal(this.closest('tr'))" required class="product-select">
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['selling_price']; ?>">
                                <?php echo $product['name'] . " (Suggested: Ksh " . number_format($product['selling_price'], 2) . ", Stock: " . ($product['stock'] ?? 'N/A') . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="quantities[]" min="1" placeholder="Quantity" oninput="updateTotal(this.closest('tr'))" required></td>
                <td><input type="number" name="custom_selling_prices[]" step="0.01" min="0" placeholder="Selling Price (Ksh)" oninput="updateTotal(this.closest('tr'))" required></td>
                <td>Total: Ksh <span class="total-price">0.00</span></td>
                <td><button type="button" class="orange-btn remove-btn" onclick="removeRow(this)">Remove</button></td>
            `;
            tbody.appendChild(row);
            return row.querySelector('.product-select'); // Return the new select for targeting
        }

        function removeRow(button) {
            const row = button.closest('tr');
            row.remove();
            updateGrandTotal();
        }

        function liveSearch() {
            clearTimeout(window.searchTimeout);
            const query = $('#search').val();
            const results = $('#search-results');

            if (query.length > 0) {
                window.searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: 'sell.php',
                        type: 'GET',
                        data: { q: query },
                        dataType: 'json',
                        success: function(data) {
                            results.empty().show();
                            if (data.length > 0) {
                                data.forEach(function(product) {
                                    const div = $('<div>').text(
                                        `${product.name} (Ksh ${product.selling_price}, Stock: ${product.stock || 'N/A'})`
                                    ).on('click', function() {
                                        // Find the last select with no value or add a new row
                                        let targetSelect = $('.product-select').filter(function() {
                                            return !$(this).val();
                                        }).last()[0];
                                        if (!targetSelect) {
                                            targetSelect = addRow();
                                        }
                                        // Update the target select
                                        const optionExists = targetSelect.querySelector(`option[value="${product.id}"]`);
                                        if (!optionExists) {
                                            $(targetSelect).append(
                                                `<option value="${product.id}" data-price="${product.selling_price}" selected>
                                                    ${product.name} (Suggested: Ksh ${product.selling_price}, Stock: ${product.stock || 'N/A'})
                                                </option>`
                                            );
                                        } else {
                                            $(targetSelect).val(product.id);
                                        }
                                        $(targetSelect).trigger('change');
                                        results.hide();
                                        $('#search').val(''); // Clear search bar
                                    });
                                    results.append(div);
                                });
                            } else {
                                results.append('<div>No products found</div>');
                            }
                        }
                    });
                }, 300); // Debounce delay
            } else {
                results.hide();
                // Reset all selects to full list if search is cleared (optional)
                $('.product-select').each(function() {
                    if (!$(this).val()) {
                        $(this).html(`
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['selling_price']; ?>">
                                    <?php echo $product['name'] . " (Suggested: Ksh " . number_format($product['selling_price'], 2) . ", Stock: " . ($product['stock'] ?? 'N/A') . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        `);
                    }
                });
            }
        }

        function printReceipt() {
            const receipt = document.querySelector('.receipt-box');
            const originalBody = document.body.innerHTML;
            document.body.innerHTML = receipt.outerHTML;
            window.print();
            document.body.innerHTML = originalBody;
        }

        function clearReceipt() {
            const receiptBox = document.querySelector('.receipt-box');
            if (receiptBox) receiptBox.style.display = 'none';
            document.querySelector('#product-table tbody').innerHTML = '';
            addRow(); // Add one row back
            document.querySelector('input[name="customer_email"]').value = '';
            document.getElementById('grand-total').textContent = '0.00';
        }

        // Initialize with one row
        $(document).ready(function() {
            addRow();
            $('#search').on('input', liveSearch);
            $(document).click(function(e) {
                if (!$(e.target).closest('.search-container').length) {
                    $('#search-results').hide();
                }
            });
        });
    </script>
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <div class="sidebar">
            <h3>Menu</h3>
            <a href="dashboard.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="sell.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'sell.php' ? 'active' : ''; ?>">Sell Products</a>
            <a href="inventory.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">Manage Inventory</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="users.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">Manage Users</a>
            <?php endif; ?>
            <h3>Reports</h3>
            <a href="reports.php?period=daily" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'daily' ? 'active' : ''; ?>">Daily Report</a>
            <a href="reports.php?period=weekly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'weekly' ? 'active' : ''; ?>">Weekly Report</a>
            <a href="reports.php?period=monthly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'monthly' ? 'active' : ''; ?>">Monthly Report</a>
            <a href="reports.php?period=yearly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'yearly' ? 'active' : ''; ?>">Yearly Report</a>
        </div>
        <div class="main-content">
            <h1>Sell Products</h1>
            <div class="search-container">
                <form method="GET" action="sell.php" class="search-form" style="display: inline;">
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by product name..." class="search-input">
                    <button type="submit" class="orange-btn">Search</button>
                    <a href="sell.php" class="orange-btn">Clear</a>
                </form>
                <div id="search-results"></div>
            </div>
            <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <?php if (empty($products)): ?>
                <p class="error">No products available for sale<?php echo $search ? " matching '$search'" : ''; ?>. Add stock in Inventory.</p>
            <?php else: ?>
                <form method="POST">
                    <table id="product-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Selling Price</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="orange-btn" onclick="addRow()">Add Item</button>
                    <p>Grand Total: Ksh <span id="grand-total">0.00</span></p>
                    <input type="email" name="customer_email" placeholder="Customer Email (optional)">
                    <button type="submit" name="sell" value="1" class="orange-btn">Sell</button>
                </form>
            <?php endif; ?>

            <!-- Receipt Display -->
            <?php if ($receipt): ?>
                <div class="receipt-box" id="receipt-box">
                    <?php
                    $logo_path = 'uploads/company_logo.png';
                    if (file_exists($logo_path)) {
                        echo "<img src='$logo_path' alt='Company Logo' class='receipt-logo'>";
                    }
                    ?>
                    <h2>Receipt</h2>
                    <p><strong>Transaction ID:</strong> <?php echo $receipt['transaction_id']; ?></p>
                    <p><strong>Date:</strong> <?php echo $receipt['timestamp']; ?></p>
                    <hr>
                    <?php foreach ($receipt['items'] as $item): ?>
                        <p><strong>Product:</strong> <?php echo $item['product_name']; ?></p>
                        <p><strong>Quantity:</strong> <?php echo $item['quantity']; ?></p>
                        <p><strong>Price per Unit:</strong> Ksh <?php echo number_format($item['selling_price'], 2); ?></p>
                        <p><strong>Total:</strong> Ksh <?php echo number_format($item['total'], 2); ?></p>
                        <hr>
                    <?php endforeach; ?>
                    <p><strong>Grand Total:</strong> Ksh <?php echo number_format($receipt['grand_total'], 2); ?></p>
                    <p>Thank you for your purchase!</p>
                    <button onclick="printReceipt()" class="orange-btn">Print Receipt</button>
                    <form method="POST" style="display: inline;" class="pdf-form">
                        <input type="hidden" name="receipt_data" value="<?php echo htmlspecialchars(json_encode($receipt)); ?>">
                        <button type="submit" name="download_pdf" value="1" class="orange-btn">Download PDF</button>
                    </form>
                    <button onclick="clearReceipt()" class="orange-btn">Clear</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>