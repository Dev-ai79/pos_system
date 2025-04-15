<?php
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <h3>POS System</h3>
    <a href="dashboard.php" class="orange-btn <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="sell.php" class="orange-btn <?php echo $current_page == 'sell.php' ? 'active' : ''; ?>">
        <i class="fas fa-cash-register"></i> Sell Products
    </a>
    <?php if (in_array($role, ['admin', 'manager'])): ?>
        <a href="inventory.php" class="orange-btn <?php echo $current_page == 'inventory.php' || $current_page == 'edit_product.php' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i> Manage Inventory
        </a>
    <?php endif; ?>
    <?php if ($role == 'admin'): ?>
        <a href="users.php" class="orange-btn <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Manage Users
        </a>
    <?php endif; ?>
    <a href="reports.php" class="orange-btn <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
        <span class="btn-content"><i class="fas fa-chart-line"></i> Reports</span>
    </a>
    <a href="logout.php" class="orange-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    sidebar.classList.toggle('active');
    mainContent.classList.toggle('shifted');
}
</script>