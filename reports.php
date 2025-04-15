<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Reports</h1>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <div class="report-buttons">
                <a href="reports.php?period=day" class="report-btn">Day</a>
                <a href="reports.php?period=week" class="report-btn">Week</a>
                <a href="reports.php?period=month" class="report-btn">Month</a>
                <a href="reports.php?period=year" class="report-btn">Year</a>
            </div>
            <div class="report-content">
                <p>Select a period above to view the report.</p>
            </div>
        </div>
    </div>
</body>
</html>