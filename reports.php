<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch sales data grouped by date
try {
    $stmt = $pdo->query("SELECT DATE(timestamp) as sale_date, SUM(total) as daily_total 
                         FROM sales 
                         GROUP BY DATE(timestamp) 
                         ORDER BY sale_date DESC");
    $sales = $stmt->fetchAll();
} catch (PDOException $e) {
    $sales = [];
    echo '<p class="error">Error fetching sales: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Reports</h1>
            <div class="table-responsive">
                <table id="salesTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Sales (Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                                <td><?php echo number_format($sale['daily_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>