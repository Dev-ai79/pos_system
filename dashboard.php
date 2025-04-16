<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("dashboard.php: No user session, redirecting to index.php");
    header('Location: index.php');
    exit;
}

// Fetch user info
try {
    $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("dashboard.php: User ID {$_SESSION['user_id']} not found, destroying session");
        session_destroy();
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("dashboard.php: Database error - " . $e->getMessage());
    $error = "An error occurred. Please try again.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Dashboard</h1>
            <p class="dashboard-welcome">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</p>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="summary-box">
                <h3>Quick Summary</h3>
                <p>Role: <?php echo htmlspecialchars($user['role']); ?></p>
                <!-- Add more stats like sales, inventory count if needed -->
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>