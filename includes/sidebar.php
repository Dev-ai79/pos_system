<?php
// No session_start(); handled by including pages
?>

<div class="sidebar">
    <h2>POS System</h2>
    <p class="user-details">
        Logged in as <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?>
        (<?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'N/A'; ?>)
    </p>
    <ul>
        <li><a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="sell.php" class="sidebar-link"><i class="fas fa-cash-register"></i> Sell Products</a></li>
        <li><a href="inventory.php" class="sidebar-link"><i class="fas fa-boxes"></i> Manage Inventory</a></li>
        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> Manage Users</a></li>
        <li><a href="reports.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <li><a href="settings.php" class="sidebar-link"><i class="fas fa-cog"></i> Settings</a></li>
        <?php endif; ?>
        <li><a href="logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>