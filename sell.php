<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch products for dropdown
try {
    $stmt = $pdo->query("SELECT id, name, price, stock FROM products WHERE stock > 0");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    echo '<p class="error">Error fetching products: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sell Products - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <script src="sell.js?v=<?php echo filemtime('sell.js'); ?>"></script>
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Sell Products</h1>
            <form id="sell-form" method="POST" action="process_sale.php">
                <table id="product-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price (Ksh)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="product-rows">
                        <tr class="product-row">
                            <td>
                                <select name="products[]" onchange="updatePrice(this)">
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" data-price="<?php echo isset($product['price']) ? $product['price'] : 0; ?>" data-stock="<?php echo isset($product['stock']) ? $product['stock'] : 0; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?> (Stock: <?php echo isset($product['stock']) ? $product['stock'] : 'N/A'; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="quantities[]" min="1" value="1" onchange="updatePrice(this)"></td>
                            <td><input type="number" name="prices[]" readonly></td>
                            <td><button type="button" class="orange-btn remove-btn" onclick="removeRow(this)">Remove</button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="orange-btn" onclick="addRow()">Add Product</button>
                <button type="submit" class="orange-btn">Complete Sale</button>
            </form>
        </div>
    </div>
</body>
</html>