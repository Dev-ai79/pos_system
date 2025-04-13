<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="sidebar">
    <h3>Menu</h3>
    <a href="dashboard.php" class="orange-btn <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
    <a href="sell.php" class="orange-btn <?php echo $current_page === 'sell.php' ? 'active' : ''; ?>">Sell Products</a>
    <a href="inventory.php" class="orange-btn <?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>">Manage Inventory</a>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="users.php" class="orange-btn <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">Manage Users</a>
    <?php endif; ?>
    <a href="reports.php" class="orange-btn <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">Reports</a>
</div>
</body>
</html>