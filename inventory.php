<?php
// inventory.php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$is_admin = $_SESSION['role'] === 'admin';
$can_add_stock = in_array($_SESSION['role'], ['admin', 'manager']);
$success = $error = null;
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin && isset($_POST['add_product'])) {
    $name = trim($_POST['name'] ?? '');
    if (!empty($name)) {
        $name = filter_var($name, FILTER_SANITIZE_STRING);
        $stmt = $pdo->prepare("INSERT INTO products (name, cost_price, selling_price) VALUES (?, 0, 0)");
        try {
            $stmt->execute([$name]);
            $success = "Product '$name' added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    } else {
        $error = "Please provide a product name.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin && isset($_POST['delete_product'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE product_id = ?");
    $stmt->execute([$product_id]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $success = "Product deleted successfully!";
    } else {
        $error = "Cannot delete product with sales history.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $can_add_stock && isset($_POST['add_stock'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    if ($quantity > 0) {
        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, quantity) 
                               VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt->execute([$product_id, $quantity, $quantity]);
        $success = "Stock added successfully!";
    } else {
        $error = "Please enter a valid quantity.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin && isset($_POST['edit_product'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    if (!empty($name) && $cost_price >= 0 && $selling_price > 0) {
        $stmt = $pdo->prepare("UPDATE products 
                               SET name = ?, cost_price = ?, selling_price = ? 
                               WHERE id = ?");
        $stmt->execute([$name, $cost_price, $selling_price, $product_id]);
        $success = "Product updated successfully!";
    } else {
        $error = "Please provide valid product details.";
    }
}

$products = [];
if (isset($_GET['q'])) {
    $search = '%' . trim($_GET['q']) . '%';
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.cost_price, p.selling_price, COALESCE(i.quantity, 0) AS quantity 
                           FROM products p 
                           LEFT JOIN inventory i ON p.id = i.product_id 
                           WHERE p.name LIKE ?");
    $stmt->execute([$search]);
    $products = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}

$query = "SELECT p.id, p.name, p.cost_price, p.selling_price, COALESCE(i.quantity, 0) AS quantity 
          FROM products p 
          LEFT JOIN inventory i ON p.id = i.product_id";
$params = [];
if (!empty($search)) {
    $query .= " WHERE p.name LIKE ?";
    $params[] = "%$search%";
}
$query .= " ORDER BY p.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory - POS System</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Inventory</h1>
            <?php if ($success): ?>
                <p class="success"><?php echo $success; ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <div class="search-container">
                <input type="text" id="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                <div id="search-results"></div>
            </div>
            <?php if ($is_admin): ?>
                <h3>Add New Product</h3>
                <form method="POST" class="add-product-form">
                    <input type="text" name="name" placeholder="Product Name" required class="search-input">
                    <button type="submit" name="add_product" value="1" class="orange-btn">Add Product</button>
                </form>
            <?php endif; ?>
            <h3>Product List</h3>
            <div class="table-responsive">
                <table id="product-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Stock</th>
                            <?php if ($is_admin || $can_add_stock): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="<?php echo ($is_admin || $can_add_stock) ? 5 : 4; ?>">No products found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>Ksh <?php echo number_format($product['cost_price'], 2); ?></td>
                                    <td>Ksh <?php echo number_format($product['selling_price'], 2); ?></td>
                                    <td><?php echo $product['quantity']; ?></td>
                                    <?php if ($is_admin || $can_add_stock): ?>
                                        <td>
                                            <?php if ($can_add_stock): ?>
                                                <form method="POST" style="display: inline;" class="add-stock-form">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <input type="number" name="quantity" min="1" placeholder="Qty" class="small-input" required>
                                                    <button type="submit" name="add_stock" value="1" class="orange-btn">Add Stock</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($is_admin): ?>
                                                <button onclick="openEditModal(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['cost_price']; ?>, <?php echo $product['selling_price']; ?>)" class="orange-btn">Edit</button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" name="delete_product" value="1" class="orange-btn">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if ($is_admin): ?>
        <div id="edit-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Edit Product</h3>
                <form method="POST" id="edit-product-form">
                    <input type="hidden" name="product_id" id="edit-product-id">
                    <label for="edit-name">Name:</label>
                    <input type="text" name="name" id="edit-name" required class="search-input">
                    <label for="edit-cost-price">Cost Price (Ksh):</label>
                    <input type="number" name="cost_price" id="edit-cost-price" step="0.01" min="0" required class="search-input">
                    <label for="edit-selling-price">Selling Price (Ksh):</label>
                    <input type="number" name="selling_price" id="edit-selling-price" step="0.01" min="0" required class="search-input">
                    <button type="submit" name="edit_product" value="1" class="orange-btn">Save Changes</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <script>
        function liveSearch() {
            const query = $('#search').val().trim();
            const results = $('#search-results');
            if (query.length === 0) {
                window.location.href = 'inventory.php';
                return;
            }
            $.ajax({
                url: 'inventory.php',
                type: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function(data) {
                    window.location.href = 'inventory.php?search=' + encodeURIComponent(query);
                }
            });
        }

        function openEditModal(id, name, cost_price, selling_price) {
            document.getElementById('edit-product-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-cost-price').value = cost_price;
            document.getElementById('edit-selling-price').value = selling_price;
            document.getElementById('edit-modal').style.display = 'block';
        }

        $(document).ready(function() {
            $('#search').on('input', liveSearch);
            $('.close').on('click', function() {
                $('#edit-modal').hide();
            });
            window.onclick = function(event) {
                if (event.target == document.getElementById('edit-modal')) {
                    $('#edit-modal').hide();
                }
            };
        });
    </script>
</body>
</html>