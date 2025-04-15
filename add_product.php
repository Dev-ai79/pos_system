<?php
session_start();
require 'config.php';

// Restrict access to admin or manager roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    // Validate input
    if (empty($name) || $price < 0 || $stock < 0) {
        $_SESSION['error'] = "Please fill all fields with valid values.";
        header('Location: inventory.php');
        exit;
    }

    try {
        // Check if product name already exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "A product with this name already exists.";
            header('Location: inventory.php');
            exit;
        }

        // Insert new product
        $stmt = $pdo->prepare("INSERT INTO products (name, price, stock) VALUES (?, ?, ?)");
        $stmt->execute([$name, $price, $stock]);
        $_SESSION['success'] = "Product added successfully.";
        header('Location: inventory.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding product: " . htmlspecialchars($e->getMessage());
        header('Location: inventory.php');
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header('Location: inventory.php');
    exit;
}
?>