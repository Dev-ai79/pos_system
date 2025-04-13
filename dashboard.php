<?php
// dashboard.php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$total_products = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total) AS total_sales FROM sales");
$total_sales = $stmt->fetch()['total_sales'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - POS System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
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