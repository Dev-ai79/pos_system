<?php
// sell.php
session_start();
require 'config.php';
require 'fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = null;
$receipt = null;
$products = [];
$customer_email = '';

$query = "SELECT p.id, p.name, p.selling_price, COALESCE(i.quantity, 0) AS stock 
          FROM products p 
          LEFT JOIN inventory i ON p.id = i.product_id 
          WHERE COALESCE(i.quantity, 0) > 0";
$stmt = $pdo->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sell'])) {
    $product_ids = $_POST['product_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $custom_selling_prices = $_POST['custom_selling_prices'] ?? [];
    $customer_email = trim($_POST['customer_email'] ?? '');
    $grand_total = 0;
    $transaction_id = uniqid('txn_');
    $timestamp = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'];
    $receipt = ['items' => [], 'grand_total' => 0, 'transaction_id' => $transaction_id, 'timestamp' => $timestamp];

    foreach ($product_ids as $index => $product_id) {
        $quantity = (int)($quantities[$index] ?? 0);
        $custom_selling_price = (float)($custom_selling_prices[$index] ?? 0);
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.selling_price, COALESCE(i.quantity, 0) AS stock 
                               FROM products p 
                               LEFT JOIN inventory i ON p.id = i.product_id 
                               WHERE p.id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if ($product && $quantity > 0 && $quantity <= ($product['stock'] ?? PHP_INT_MAX) && $custom_selling_price > 0) {
            $total = $quantity * $custom_selling_price;
            $grand_total += $total;

            $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity, selling_price, total, timestamp, transaction_id, user_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $quantity, $custom_selling_price, $total, $timestamp, $transaction_id, $user_id]);

            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?");
            $stmt->execute([$quantity, $product_id]);

            $receipt['items'][] = [
                'product_name' => $product['name'],
                'quantity' => $quantity,
                'selling_price' => $custom_selling_price,
                'total' => $total
            ];
        } else {
            $errors[] = "Invalid data for '{$product['name']}': Quantity ($quantity) exceeds stock (" . ($product['stock'] ?? 'N/A') . ") or price ($custom_selling_price) is invalid.";
        }
    }

    if (empty($errors) && !empty($receipt['items'])) {
        $receipt['grand_total'] = $grand_total;
        $success = "Sale processed successfully! Transaction ID: $transaction_id";

        if (!empty($customer_email)) {
            $body = "Thank you for your purchase!\n\n";
            $body .= "Transaction ID: $transaction_id\n";
            $body .= "Date: $timestamp\n\n";
            foreach ($receipt['items'] as $item) {
                $body .= "Product: {$item['product_name']}\n";
                $body .= "Quantity: {$item['quantity']}\n";
                $body .= "Price per Unit: Ksh " . number_format($item['selling_price'], 2) . "\n";
                $body .= "Total: Ksh " . number_format($item['total'], 2) . "\n\n";
            }
            $body .= "Grand Total: Ksh " . number_format($grand_total, 2) . "\n\n";
            $body .= "Regards,\nYour POS Team";
            mail($customer_email, "Your Receipt from POS System", $body, "From: no-reply@pos-system.com");
        }

        if (isset($_POST['download_pdf']) && $_POST['download_pdf'] == '1') {
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, 'POS System Receipt', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, "Transaction ID: $transaction_id", 0, 1);
            $pdf->Cell(0, 10, "Date: $timestamp", 0, 1);
            $pdf->Ln(10);
            $pdf->Cell(80, 10, 'Product', 1);
            $pdf->Cell(30, 10, 'Qty', 1);
            $pdf->Cell(40, 10, 'Unit Price', 1);
            $pdf->Cell(40, 10, 'Total', 1);
            $pdf->Ln();
            foreach ($receipt['items'] as $item) {
                $pdf->Cell(80, 10, $item['product_name'], 1);
                $pdf->Cell(30, 10, $item['quantity'], 1);
                $pdf->Cell(40, 10, number_format($item['selling_price'], 2), 1);
                $pdf->Cell(40, 10, number_format($item['total'], 2), 1);
                $pdf->Ln();
            }
            $pdf->Cell(150, 10, 'Grand Total', 1);
            $pdf->Cell(40, 10, number_format($grand_total, 2), 1);
            $pdf->Output('D', "receipt_$transaction_id.pdf");
            exit;
        }
    }
}

if (isset($_GET['q'])) {
    $search = '%' . trim($_GET['q']) . '%';
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.selling_price, COALESCE(i.quantity, 0) AS stock 
                           FROM products p 
                           LEFT JOIN inventory i ON p.id = i.product_id 
                           WHERE p.name LIKE ? AND COALESCE(i.quantity, 0) > 0");
    $stmt->execute([$search]);
    $results = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Products - POS System</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Sell Products</h1>
            <?php if ($success): ?>
                <p class="success"><?php echo $success; ?></p>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <p class="error"><?php echo $error; ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <form method="POST" id="sell-form">
                <div class="search-container">
                    <input type="text" id="search" placeholder="Search products..." class="search-input">
                    <div id="search-results"></div>
                </div>
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
                    <tbody id="product-rows">
                    </tbody>
                </table>
                <button type="button" id="add-row" class="orange-btn">Add Product</button>
                <div class="email-container">
                    <input type="email" name="customer_email" placeholder="Customer Email (Optional)" value="<?php echo htmlspecialchars($customer_email); ?>" class="search-input">
                </div>
                <button type="submit" name="sell" value="1" class="orange-btn">Process Sale</button>
            </form>
            <?php if ($receipt): ?>
                <div class="receipt-box" id="receipt-box">
                    <h2>Receipt</h2>
                    <p><strong>Transaction ID:</strong> <?php echo $receipt['transaction_id']; ?></p>
                    <p><strong>Date:</strong> <?php echo $receipt['timestamp']; ?></p>
                    <table>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                        <?php foreach ($receipt['items'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>Ksh <?php echo number_format($item['selling_price'], 2); ?></td>
                                <td>Ksh <?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3"><strong>Grand Total</strong></td>
                            <td><strong>Ksh <?php echo number_format($receipt['grand_total'], 2); ?></strong></td>
                        </tr>
                    </table>
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
    <script>
        function addRow() {
            const tbody = document.getElementById('product-rows');
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
            return row.querySelector('select');
        }

        function removeRow(btn) {
            btn.closest('tr').remove();
            updateGrandTotal();
        }

        function updateTotal(row) {
            const priceInput = row.querySelector('input[name="custom_selling_prices[]"]');
            const qty = parseInt(row.querySelector('input[name="quantities[]"]').value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const totalSpan = row.querySelector('.total-price');
            const select = row.querySelector('select');
            const selectedOption = select.options[select.selectedIndex];
            const suggestedPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            if (!priceInput.value) {
                priceInput.value = suggestedPrice.toFixed(2);
            }
            totalSpan.textContent = (price * qty).toFixed(2);
            updateGrandTotal();
        }

        function updateGrandTotal() {
            let grandTotal = 0;
            document.querySelectorAll('.total-price').forEach(span => {
                grandTotal += parseFloat(span.textContent) || 0;
            });
        }

        function liveSearch() {
            const query = $('#search').val().trim();
            const results = $('#search-results');
            if (query.length === 0) {
                results.hide();
                return;
            }
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
                                `${product.name} (Ksh ${Number(product.selling_price).toFixed(2)}, Stock: ${product.stock || 'N/A'})`
                            ).on('click', function() {
                                let targetSelect = $('.product-select').filter(function() {
                                    return !$(this).val();
                                }).last()[0];
                                if (!targetSelect) {
                                    targetSelect = addRow();
                                }
                                const optionExists = targetSelect.querySelector(`option[value="${product.id}"]`);
                                if (!optionExists) {
                                    $(targetSelect).append(
                                        `<option value="${product.id}" data-price="${product.selling_price}" selected>
                                            ${product.name} (Suggested: Ksh ${Number(product.selling_price).toFixed(2)}, Stock: ${product.stock || 'N/A'})
                                        </option>`
                                    );
                                } else {
                                    $(targetSelect).val(product.id);
                                }
                                $(targetSelect).trigger('change');
                                results.hide();
                                $('#search').val('');
                            });
                            results.append(div);
                        });
                    } else {
                        results.append('<div>No products found</div>');
                    }
                }
            });
        }

        function printReceipt() {
            const receiptBox = document.getElementById('receipt-box');
            const win = window.open('', '', 'width=800,height=600');
            win.document.write('<html><head><title>Receipt</title>');
            win.document.write('<link rel="stylesheet" href="styles.css">');
            win.document.write('</head><body>');
            win.document.write(receiptBox.innerHTML);
            win.document.write('</body></html>');
            win.document.close();
            win.print();
        }

        function clearReceipt() {
            document.getElementById('receipt-box').style.display = 'none';
            document.getElementById('sell-form').reset();
            document.getElementById('product-rows').innerHTML = '';
            addRow();
        }

        $(document).ready(function() {
            addRow();
            $('#search').on('input', liveSearch);
        });
    </script>
</body>
</html>