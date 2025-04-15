<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    error_log("process_sale.php: No user_id, redirecting to index.php");
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header('Location: sell.php');
    exit;
}

try {
    $product_ids = $_POST['product_ids'] ?? [];
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $prices = $_POST['prices'] ?? [];

    if (empty($product_ids)) {
        $_SESSION['error'] = "No products selected for sale.";
        header('Location: sell.php');
        exit;
    }

    $pdo->beginTransaction();
    $transaction_id = uniqid('TXN_');
    $grand_total = 0;
    $receipt_items = [];

    for ($i = 0; $i < count($product_ids); $i++) {
        $product_id = $product_ids[$i];
        $quantity = (int)$quantities[$i];
        $price = (float)$prices[$i];
        $total = $quantity * $price;
        $product_name = $products[$i];

        if ($quantity <= 0 || $price <= 0) {
            throw new Exception("Invalid quantity or price for product: $product_name");
        }

        // Verify stock
        $stmt = $pdo->prepare("SELECT stock, name FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product || $product['stock'] < $quantity) {
            throw new Exception("Insufficient stock for product: $product_name");
        }

        // Insert sale
        $stmt = $pdo->prepare("
            INSERT INTO sales (product_id, quantity, total, selling_price, transaction_id, user_id, timestamp)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$product_id, $quantity, $total, $price, $transaction_id, $_SESSION['user_id']]);

        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$quantity, $product_id]);

        // Store receipt data
        $receipt_items[] = [
            'name' => $product_name,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $total
        ];
        $grand_total += $total;
    }

    $pdo->commit();

    // Store receipt data in session
    $_SESSION['receipt_data'] = [
        'transaction_id' => $transaction_id,
        'items' => $receipt_items,
        'grand_total' => $grand_total,
        'date' => date('Y-m-d H:i:s')
    ];
    $_SESSION['success'] = "Sale completed successfully.";
    header('Location: sell.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("process_sale.php: Error - " . $e->getMessage());
    $_SESSION['error'] = "Sale failed: " . $e->getMessage();
    header('Location: sell.php');
    exit;
}
?>