<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<div class="sidebar" id="sidebar">
    <h3><i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</h3>
    <h3>Menu</h3>
    <a href="dashboard.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a>
    <a href="sell.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'sell.php' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Sell Products</a>
    <a href="inventory.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>"><i class="fas fa-boxes"></i> Manage Inventory</a>
    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
        <a href="stock.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'stock.php' ? 'active' : ''; ?>"><i class="fas fa-plus-circle"></i> Add Stock</a>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="users.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Manage Users</a>
    <?php endif; ?>
    <h3 class="reports-toggle" onclick="toggleReports()"><i class="fas fa-chart-bar"></i> Reports <i class="fas fa-chevron-down toggle-icon"></i></h3>
    <div class="reports-menu" id="reportsMenu">
        <a href="reports.php?period=daily" class="orange-btn reports-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'daily' ? 'active' : ''; ?>"><i class="fas fa-calendar-day"></i> Daily Report</a>
        <a href="reports.php?period=weekly" class="orange-btn reports-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'weekly' ? 'active' : ''; ?>"><i class="fas fa-calendar-week"></i> Weekly Report</a>
        <a href="reports.php?period=monthly" class="orange-btn reports-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'monthly' ? 'active' : ''; ?>"><i class="fas fa-calendar-month"></i> Monthly Report</a>
        <a href="reports.php?period=yearly" class="orange-btn reports-btn <?php echo isset($_GET['period']) && $_GET['period'] === 'yearly' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Yearly Report</a>
    </div>
    <a href="logout.php" class="orange-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div class="sidebar-icons" id="sidebarIcons">
    <a href="dashboard.php" class="icon-btn <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" title="Dashboard"><i class="fas fa-home"></i></a>
    <a href="sell.php" class="icon-btn <?php echo basename($_SERVER['PHP_SELF']) === 'sell.php' ? 'active' : ''; ?>" title="Sell Products"><i class="fas fa-shopping-cart"></i></a>
    <a href="inventory.php" class="icon-btn <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>" title="Manage Inventory"><i class="fas fa-boxes"></i></a>
    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
        <a href="stock.php" class="icon-btn <?php echo basename($_SERVER['PHP_SELF']) === 'stock.php' ? 'active' : ''; ?>" title="Add Stock"><i class="fas fa-plus-circle"></i></a>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="users.php" class="icon-btn <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>" title="Manage Users"><i class="fas fa-users"></i></a>
    <?php endif; ?>
    <a href="reports.php?period=daily" class="icon-btn <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>" title="Reports"><i class="fas fa-chart-bar"></i></a>
    <a href="logout.php" class="icon-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
</div>
<script>
function toggleReports() {
    const menu = document.getElementById('reportsMenu');
    const icon = document.querySelector('.toggle-icon');
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        menu.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarIcons = document.getElementById('sidebarIcons');
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        sidebar.style.display = sidebar.style.display === 'block' ? 'none' : 'block';
        sidebarIcons.style.display = 'none';
    } else {
        if (sidebar.style.display === 'block' || sidebar.style.display === '') {
            sidebar.style.display = 'none';
            sidebarIcons.style.display = 'block';
        } else {
            sidebar.style.display = 'block';
            sidebarIcons.style.display = 'none';
        }
    }
}
</script>