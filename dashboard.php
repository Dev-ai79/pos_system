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
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Dashboard</h1>
            <p class="dashboard-welcome">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's your overview:</p>
            <div class="summary-box">
                <h3>General Stats</h3>
                <p>Total Products: <?php echo $total_products; ?></p>
                <p>Total Sales (All Time): Ksh <?php echo number_format($total_sales, 2); ?></p>
            </div>
        </div>
    </div>
</body>
</html>