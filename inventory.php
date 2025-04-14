<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch products
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    echo '<p class="error">Error fetching products: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Inventory - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Inventory</h1>
            <form class="add-product-form" method="POST" action="add_product.php">
                <input type="text" name="name" placeholder="Product Name" required>
                <input type="number" name="price" placeholder="Price (Ksh)" step="0.01" required>
                <input type="number" name="stock" placeholder="Stock" required>
                <button type="submit" class="orange-btn">Add Product</button>
            </form>
            <input type="text" class="search-input" id="searchInput" placeholder="Search products..." onkeyup="searchProducts()">
            <div class="table-responsive">
                <table id="productTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price (Ksh)</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo isset($product['price']) ? number_format($product['price'], 2) : 'N/A'; ?></td>
                                <td><?php echo isset($product['stock']) ? $product['stock'] : 'N/A'; ?></td>
                                <td>
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="orange-btn">Edit</a>
                                    <form class="delete-form" method="POST" action="delete_product.php" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="orange-btn remove-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    function searchProducts() {
        let input = document.getElementById('searchInput').value.toLowerCase();
        let table = document.getElementById('productTable');
        let rows = table.getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            let cells = rows[i].getElementsByTagName('td');
            let match = false;
            for (let j = 0; j < cells.length - 1; j++) {
                if (cells[j].textContent.toLowerCase().includes(input)) {
                    match = true;
                    break;
                }
            }
            rows[i].style.display = match ? '' : 'none';
        }
    }
    </script>
</body>
</html>