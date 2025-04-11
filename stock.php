<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $stock = (int)$_POST['stock'];
    $selling_price = (float)$_POST['selling_price'];
    $cost_price = (float)$_POST['cost_price'];

    if (!empty($name) && $stock > 0 && $selling_price > 0 && $cost_price >= 0) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE name = ?");
        $stmt->execute([$name]);
        $product = $stmt->fetch();

        if ($product) {
            $new_stock = $product['stock'] + $stock;
            $pdo->prepare("UPDATE products SET stock = ? WHERE name = ?")
                ->execute([$new_stock, $name]);
            $success = "Added $stock more {$name}(s) to stock. New total: $new_stock.";
        } else {
            $pdo->prepare("INSERT INTO products (name, stock, selling_price, cost_price) VALUES (?, ?, ?, ?)")
                ->execute([$name, $stock, $selling_price, $cost_price]);
            $success = "Added $name with $stock units. Buying Price: Ksh $cost_price, Selling Price: Ksh $selling_price.";
        }
    } else {
        $error = "Please fill all fields with valid values!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Stock</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Add Stock</h1>
        <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="name" placeholder="Product Name" required><br>
            <input type="number" name="stock" min="1" placeholder="Stock Quantity" required><br>
            <input type="number" name="cost_price" step="0.01" min="0" placeholder="Buying Price (Ksh)" required><br>
            <input type="number" name="selling_price" step="0.01" min="0.01" placeholder="Selling Price (Ksh)" required><br>
            <button type="submit" class="orange-btn">Add to Stock</button>
        </form>
        <a href="dashboard.php" class="orange-btn">Back to Dashboard</a>
    </div>
</body>
</html>