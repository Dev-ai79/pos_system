<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'];

// Total products
$stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products");
$total_products = $stmt->fetch()['total_products'];

// Total sales (all time)
$stmt = $pdo->query("SELECT SUM(total) as total_sales FROM sales");
$total_sales = $stmt->fetch()['total_sales'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $role; ?>)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <div class="sidebar">
            <h3>Menu</h3>
            <a href="dashboard.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="sell.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'sell.php' ? 'active' : ''; ?>">Sell Products</a>
            <a href="inventory.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">Manage Inventory</a>
            <?php if ($role === 'admin'): ?>
                <a href="users.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">Manage Users</a>
            <?php endif; ?>
            <h3>Reports</h3>
            <a href="reports.php?period=daily" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'daily' ? 'active' : ''; ?>">Daily Report</a>
            <a href="reports.php?period=weekly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'weekly' ? 'active' : ''; ?>">Weekly Report</a>
            <a href="reports.php?period=monthly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'monthly' ? 'active' : ''; ?>">Monthly Report</a>
            <a href="reports.php?period=yearly" class="orange-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'yearly' ? 'active' : ''; ?>">Yearly Report</a>
        </div>
        <div class="main-content">
            <h1>Dashboard</h1>
            <p class="dashboard-welcome">Welcome back, <?php echo $_SESSION['username']; ?>! Here's your overview:</p>
            <div class="summary-box">
                <h3>General Stats</h3>
                <p>Total Products: <?php echo $total_products; ?></p>
                <p>Total Sales (All Time): Ksh <?php echo number_format($total_sales, 2); ?></p>
            </div>
        </div>
    </div>
</body>
</html>