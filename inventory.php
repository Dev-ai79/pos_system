<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: index.php');
    exit;
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $buying_price = floatval($_POST['buying_price']);
    $selling_price = floatval($_POST['selling_price']);
    $stock = intval($_POST['stock']);
    
    if (empty($name) || $selling_price <= 0 || $buying_price < 0 || $stock < 0) {
        $_SESSION['error'] = "Please fill in all fields correctly.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, selling_price, buying_price, stock) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $selling_price, $buying_price, $stock]);
            $_SESSION['success'] = "Product added successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding product: " . $e->getMessage();
        }
    }
    header('Location: inventory.php');
    exit;
}

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = intval($_POST['product_id']);
    $stock_change = intval($_POST['stock_change']);
    
    if ($stock_change != 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$stock_change, $product_id]);
            $_SESSION['success'] = "Stock updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating stock: " . $e->getMessage();
        }
    }
    header('Location: inventory.php');
    exit;
}

// Handle product edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $product_id = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $buying_price = floatval($_POST['buying_price']);
    $selling_price = floatval($_POST['selling_price']);
    $stock = intval($_POST['stock']);
    
    if (empty($name) || $selling_price <= 0 || $buying_price < 0 || $stock < 0) {
        $_SESSION['error'] = "Please fill in all fields correctly.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, selling_price = ?, buying_price = ?, stock = ? WHERE id = ?");
            $stmt->execute([$name, $selling_price, $buying_price, $stock, $product_id]);
            $_SESSION['success'] = "Product updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating product: " . $e->getMessage();
        }
    }
    header('Location: inventory.php');
    exit;
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $_SESSION['success'] = "Product deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
    }
    header('Location: inventory.php');
    exit;
}

// Fetch products
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = $search ? "WHERE name LIKE ?" : "";
$query = "SELECT * FROM products $where ORDER BY name";
$stmt = $pdo->prepare($query);
if ($search) {
    $stmt->execute(["%$search%"]);
} else {
    $stmt->execute();
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Inventory - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Inventory</h1>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            
            <!-- Add Product Form -->
            <form method="POST" class="add-product-form">
                <h2>Add New Product</h2>
                <input type="text" name="name" placeholder="Product Name" required>
                <input type="number" name="buying_price" placeholder="Buying Price (Ksh)" step="0.01" min="0" required>
                <input type="number" name="selling_price" placeholder="Selling Price (Ksh)" step="0.01" min="0.01" required>
                <input type="number" name="stock" placeholder="Initial Stock" min="0" required>
                <button type="submit" name="add_product" class="orange-btn">Add Product</button>
            </form>
            
            <!-- Search Products -->
            <form method="GET">
                <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="orange-btn">Search</button>
            </form>
            
            <!-- Product List -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Buying Price (Ksh)</th>
                            <th>Selling Price (Ksh)</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo number_format($product['buying_price'], 2); ?></td>
                                <td><?php echo number_format($product['selling_price'], 2); ?></td>
                                <td><?php echo $product['stock']; ?></td>
                                <td>
                                    <!-- Edit Product Form -->
                                    <form method="POST" class="add-product-form" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                        <input type="number" name="buying_price" value="<?php echo $product['buying_price']; ?>" step="0.01" min="0" required>
                                        <input type="number" name="selling_price" value="<?php echo $product['selling_price']; ?>" step="0.01" min="0.01" required>
                                        <input type="number" name="stock" value="<?php echo $product['stock']; ?>" min="0" required>
                                        <button type="submit" name="edit_product" class="orange-btn">Update</button>
                                    </form>
                                    <!-- Delete Product Form -->
                                    <form method="POST" class="delete-form" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="delete_product" class="reset-btn">Delete</button>
                                    </form>
                                    <!-- Update Stock Form -->
                                    <form method="POST" class="add-stock-form-inline">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="number" name="stock_change" class="small-input" placeholder="Stock change">
                                        <button type="submit" name="update_stock" class="orange-btn">Adjust Stock</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>