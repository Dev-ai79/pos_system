<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: inventory.php');
    exit;
}

$product_id = $_GET['id'];

// Fetch product
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: inventory.php');
        exit;
    }
} catch (PDOException $e) {
    echo '<p class="error">Error fetching product: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    if (empty($name) || $price < 0 || $stock < 0) {
        $error = "Please fill all fields with valid values.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ? WHERE id = ?");
            $stmt->execute([$name, $price, $stock, $product_id]);
            $_SESSION['success'] = "Product updated successfully.";
            header('Location: inventory.php');
            exit;
        } catch (PDOException $e) {
            $error = "Error updating product: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Edit Product</h1>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form class="add-product-form" method="POST">
                <input type="text" name="name" placeholder="Product Name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                <input type="number" name="price" placeholder="Price (Ksh)" step="0.01" value="<?php echo isset($product['price']) ? $product['price'] : 0; ?>" min="0" required>
                <input type="number" name="stock" placeholder="Stock" value="<?php echo isset($product['stock']) ? $product['stock'] : 0; ?>" min="0" required>
                <button type="submit" class="orange-btn">Save Changes</button>
                <a href="inventory.php" class="orange-btn">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>