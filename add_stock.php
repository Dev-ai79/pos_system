<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

    if ($product_id <= 0 || $quantity <= 0) {
        $error = "Invalid product or quantity.";
    } else {
        try {
            // Verify product exists
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            if (!$stmt->fetch()) {
                $error = "Product not found.";
            } else {
                // Update stock
                $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
                header('Location: stock.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error updating stock: " . htmlspecialchars($e->getMessage());
        }
    }
} else {
    $error = "Invalid request.";
}

// If error, redirect to stock.php with message
$_SESSION['error'] = $error;
header('Location: stock.php');
exit;
?>