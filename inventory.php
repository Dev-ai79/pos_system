<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check user role
$is_admin = $_SESSION['role'] === 'admin';
$is_manager = $_SESSION['role'] === 'manager';
$can_add_stock = $is_admin || $is_manager;

// Handle AJAX live search request
if (isset($_GET['q'])) {
    $query = trim($_GET['q']);
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.cost_price, p.selling_price, i.quantity 
        FROM products p 
        LEFT JOIN inventory i ON p.id = i.product_id 
        WHERE p.name LIKE ? 
        ORDER BY p.name ASC
    ");
    $stmt->execute(["%$query%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}

// Handle initial page load (non-AJAX)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT p.id, p.name, p.cost_price, p.selling_price, i.quantity 
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

// Handle stock addition (admin and manager only)
if ($can_add_stock && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id']) && isset($_POST['add_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $add_stock = (int)$_POST['add_stock'];

    if ($add_stock > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO inventory (product_id, quantity) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + ?
        ");
        $stmt->execute([$product_id, $add_stock, $add_stock]);

        $success = "Stock updated successfully!";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } else {
        $error = "Please enter a valid stock quantity.";
    }
}

// Handle reset all data (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_all'])) {
    if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes') {
        try {
            $pdo->exec("DELETE FROM inventory"); // Clear child table first
            $pdo->exec("DELETE FROM sales");     // Clear other child table
            $pdo->exec("DELETE FROM products");  // Clear parent table last
            $success = "All data has been reset!";
            $products = [];
        } catch (PDOException $e) {
            $error = "Error resetting data: " . $e->getMessage();
        }
    }
}

// Handle product deletion (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['delete_product'];
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $stmt = $pdo->prepare("DELETE FROM sales WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $success = "Product deleted successfully!";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
}

// Handle adding new product (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $cost_price = (float)$_POST['cost_price'];
    $selling_price = (float)$_POST['selling_price'];
    $stock = (int)$_POST['stock'];

    if (!empty($name) && $cost_price >= 0 && $selling_price >= 0 && $stock >= 0) {
        $stmt = $pdo->prepare("INSERT INTO products (name, cost_price, selling_price) VALUES (?, ?, ?)");
        $stmt->execute([$name, $cost_price, $selling_price]);
        $new_product_id = $pdo->lastInsertId();

        // Add to inventory
        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, quantity) VALUES (?, ?)");
        $stmt->execute([$new_product_id, $stock]);

        $success = "Product added successfully!";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } else {
        $error = "Please fill all fields with valid values.";
    }
}

// Handle editing product (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $product_id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $cost_price = (float)$_POST['cost_price'];
    $selling_price = (float)$_POST['selling_price'];
    $stock = (int)$_POST['stock'];

    if (!empty($name) && $cost_price >= 0 && $selling_price >= 0 && $stock >= 0) {
        // Update products table
        $stmt = $pdo->prepare("UPDATE products SET name = ?, cost_price = ?, selling_price = ? WHERE id = ?");
        $stmt->execute([$name, $cost_price, $selling_price, $product_id]);

        // Update inventory table
        $stmt = $pdo->prepare("
            INSERT INTO inventory (product_id, quantity) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE quantity = ?
        ");
        $stmt->execute([$product_id, $stock, $stock]);

        $success = "Product updated successfully!";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } else {
        $error = "Please fill all fields with valid values.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <style>
        .search-container { position: relative; margin-bottom: 20px; }
        #search { width: 300px; padding: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .price-align, .stock-align { text-align: right; }
        .edit-form input { margin: 2px 0; width: 100%; }
        .edit-form button { margin: 2px 5px 0 0; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function confirmReset() {
            return confirm("WARNING: This will delete ALL products, sales, and inventory data permanently. Are you sure you want to proceed?");
        }

        function confirmDelete() {
            return confirm("Are you sure you want to delete this product?");
        }

        function showEditForm(productId, name, costPrice, sellingPrice, stock) {
            const tbody = $('#product-table tbody');
            const row = $(`#row-${productId}`);
            row.hide();
            const editRow = `
                <tr id="edit-row-${productId}">
                    <td>${productId}</td>
                    <td colspan="<?php echo $is_admin ? 6 : ($can_add_stock ? 5 : 4); ?>">
                        <form method="POST" action="inventory.php" class="edit-form">
                            <input type="hidden" name="product_id" value="${productId}">
                            <input type="text" name="name" value="${name}" required>
                            <input type="number" name="cost_price" value="${costPrice}" step="0.01" min="0" required class="price-align">
                            <input type="number" name="selling_price" value="${sellingPrice}" step="0.01" min="0" required class="price-align">
                            <input type="number" name="stock" value="${stock}" min="0" required class="stock-align">
                            <button type="submit" name="edit_product" value="1" class="orange-btn">Save</button>
                            <button type="button" class="orange-btn" onclick="cancelEdit(${productId})">Cancel</button>
                        </form>
                    </td>
                </tr>
            `;
            tbody.find(`#edit-row-${productId}`).remove(); // Remove any existing edit row
            row.after(editRow);
        }

        function cancelEdit(productId) {
            $(`#edit-row-${productId}`).remove();
            $(`#row-${productId}`).show();
        }

        function liveSearch() {
            clearTimeout(window.searchTimeout);
            const query = $('#search').val();
            const tbody = $('#product-table tbody');

            if (query.length > 0) {
                window.searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: 'inventory.php',
                        type: 'GET',
                        data: { q: query },
                        dataType: 'json',
                        success: function(data) {
                            tbody.empty();
                            if (data.length > 0) {
                                data.forEach(function(product) {
                                    const row = `
                                        <tr id="row-${product.id}">
                                            <td>${product.id}</td>
                                            <td>${product.name}</td>
                                            <td class="price-align">${Number(product.cost_price).toFixed(2)}</td>
                                            <td class="price-align">${Number(product.selling_price).toFixed(2)}</td>
                                            <td class="stock-align">${product.quantity || '0'}</td>
                                            <?php if ($can_add_stock): ?>
                                            <td>
                                                <form method="POST" action="inventory.php" class="add-stock-form">
                                                    <input type="hidden" name="product_id" value="${product.id}">
                                                    <input type="number" name="add_stock" min="1" placeholder="Qty" class="stock-input">
                                                    <button type="submit" class="orange-btn">Add</button>
                                                </form>
                                            </td>
                                            <?php endif; ?>
                                            <?php if ($is_admin): ?>
                                            <td>
                                                <button type="button" class="orange-btn" onclick="showEditForm(${product.id}, '${product.name}', ${product.cost_price}, ${product.selling_price}, ${product.quantity || 0})">Edit</button>
                                                <form method="POST" action="inventory.php" onsubmit="return confirmDelete();" class="delete-form" style="display:inline;">
                                                    <input type="hidden" name="delete_product" value="${product.id}">
                                                    <button type="submit" class="orange-btn">Delete</button>
                                                </form>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    `;
                                    tbody.append(row);
                                });
                            } else {
                                tbody.append('<tr><td colspan="<?php echo $is_admin ? 7 : ($can_add_stock ? 6 : 5); ?>" class="error">No products found matching "' + query + '".</td></tr>');
                            }
                        }
                    });
                }, 300); // Debounce delay
            } else {
                // Reload full list via AJAX
                $.ajax({
                    url: 'inventory.php',
                    type: 'GET',
                    data: { q: '' },
                    dataType: 'json',
                    success: function(data) {
                        tbody.empty();
                        data.forEach(function(product) {
                            const row = `
                                <tr id="row-${product.id}">
                                    <td>${product.id}</td>
                                    <td>${product.name}</td>
                                    <td class="price-align">${Number(product.cost_price).toFixed(2)}</td>
                                    <td class="price-align">${Number(product.selling_price).toFixed(2)}</td>
                                    <td class="stock-align">${product.quantity || '0'}</td>
                                    <?php if ($can_add_stock): ?>
                                    <td>
                                        <form method="POST" action="inventory.php" class="add-stock-form">
                                            <input type="hidden" name="product_id" value="${product.id}">
                                            <input type="number" name="add_stock" min="1" placeholder="Qty" class="stock-input">
                                            <button type="submit" class="orange-btn">Add</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($is_admin): ?>
                                    <td>
                                        <button type="button" class="orange-btn" onclick="showEditForm(${product.id}, '${product.name}', ${product.cost_price}, ${product.selling_price}, ${product.quantity || 0})">Edit</button>
                                        <form method="POST" action="inventory.php" onsubmit="return confirmDelete();" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="delete_product" value="${product.id}">
                                            <button type="submit" class="orange-btn">Delete</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                    }
                });
            }
        }

        $(document).ready(function() {
            $('#search').on('input', liveSearch);
        });
    </script>
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <div class="sidebar">
            <h3>Menu</h3>
            <a href="dashboard.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="sell.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'sell.php' ? 'active' : ''; ?>">Sell Products</a>
            <a href="inventory.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">Manage Inventory</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="users.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">Manage Users</a>
            <?php endif; ?>
            <h3>Reports</h3>
            <a href="reports.php?period=daily" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'daily' ? 'active' : ''; ?>">Daily Report</a>
            <a href="reports.php?period=weekly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'weekly' ? 'active' : ''; ?>">Weekly Report</a>
            <a href="reports.php?period=monthly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'monthly' ? 'active' : ''; ?>">Monthly Report</a>
            <a href="reports.php?period=yearly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'yearly' ? 'active' : ''; ?>">Yearly Report</a>
        </div>
        <div class="main-content">
            <h1>Manage Inventory</h1>
            <div class="search-container">
                <input type="text" id="search" placeholder="Search by product name..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                <a href="inventory.php" class="orange-btn">Clear</a>
            </div>
            <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <?php if ($is_admin): ?>
                <form method="POST" class="add-product-form">
                    <h3>Add New Product</h3>
                    <input type="text" name="name" placeholder="Product Name" required class="product-input">
                    <input type="number" name="cost_price" placeholder="Cost Price (Ksh)" step="0.01" min="0" required class="product-input">
                    <input type="number" name="selling_price" placeholder="Selling Price (Ksh)" step="0.01" min="0" required class="product-input">
                    <input type="number" name="stock" placeholder="Initial Stock" min="0" required class="product-input">
                    <button type="submit" name="add_product" value="1" class="orange-btn">Add Product</button>
                </form>
                <form method="POST" onsubmit="return confirmReset();" class="reset-form">
                    <input type="hidden" name="reset_all" value="1">
                    <input type="hidden" name="confirm_reset" value="yes">
                    <button type="submit" class="orange-btn reset-btn">Reset All Data</button>
                    <p class="error">WARNING: This will delete ALL products, sales, and inventory data!</p>
                </form>
            <?php endif; ?>
            <?php if (empty($products)): ?>
                <p class="error">No products found<?php echo $search ? " matching '$search'" : ''; ?>. <?php echo $is_admin ? 'Add a new product above.' : 'Contact an admin to add products.'; ?></p>
            <?php else: ?>
                <table id="product-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Cost Price (Ksh)</th>
                            <th>Selling Price (Ksh)</th>
                            <th>Stock</th>
                            <?php if ($can_add_stock): ?>
                                <th>Add Stock</th>
                            <?php endif; ?>
                            <?php if ($is_admin): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr id="row-<?php echo $product['id']; ?>">
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo $product['name']; ?></td>
                                <td class="price-align"><?php echo number_format($product['cost_price'], 2); ?></td>
                                <td class="price-align"><?php echo number_format($product['selling_price'], 2); ?></td>
                                <td class="stock-align"><?php echo $product['quantity'] ?? '0'; ?></td>
                                <?php if ($can_add_stock): ?>
                                    <td>
                                        <form method="POST" action="inventory.php" class="add-stock-form">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="number" name="add_stock" min="1" placeholder="Qty" class="stock-input">
                                            <button type="submit" class="orange-btn">Add</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                                <?php if ($is_admin): ?>
                                    <td>
                                        <button type="button" class="orange-btn" onclick="showEditForm(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['cost_price']; ?>, <?php echo $product['selling_price']; ?>, <?php echo $product['quantity'] ?? 0; ?>)">Edit</button>
                                        <form method="POST" action="inventory.php" onsubmit="return confirmDelete();" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="delete_product" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="orange-btn">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>