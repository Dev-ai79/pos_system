<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: index.php');
    exit;
}

// Fetch products
try {
    $stmt = $pdo->query("SELECT id, name, stock FROM products ORDER BY name");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    echo '<p class="error">Error fetching products: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Stock - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Add Stock</h1>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <form class="add-stock-form" method="POST" action="add_stock.php">
                <select name="product_id" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name']); ?> (Current Stock: <?php echo isset($product['stock']) ? $product['stock'] : 'N/A'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="quantity" placeholder="Quantity to Add" min="1" required>
                <button type="submit" class="orange-btn">Add Stock</button>
            </form>
            <input type="text" class="stock-input" id="stockSearch" placeholder="Search products..." onkeyup="searchStock()">
            <div class="table-responsive">
                <table id="stockTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo isset($product['stock']) ? $product['stock'] : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    function searchStock() {
        let input = document.getElementById('stockSearch').value.toLowerCase();
        let table = document.getElementById('stockTable');
        let rows = table.getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            let cells = rows[i].getElementsByTagName('td');
            let match = cells[0].textContent.toLowerCase().includes(input);
            rows[i].style.display = match ? '' : 'none';
        }
    }
    </script>
</body>
</html>