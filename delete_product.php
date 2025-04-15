<?php
session_start();
require 'config.php';

// Restrict access to admin or manager roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Validate product ID
    if ($product_id <= 0) {
        $_SESSION['error'] = "Invalid product ID.";
        header('Location: inventory.php');
        exit;
    }

    try {
        // Verify product exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = "Product not found.";
            header('Location: inventory.php');
            exit;
        }

        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);

        $_SESSION['success'] = "Product deleted successfully.";
        header('Location: inventory.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting product: " . htmlspecialchars($e->getMessage());
        header('Location: inventory.php');
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header('Location: inventory.php');
    exit;
}
?>