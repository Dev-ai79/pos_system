<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Fetch all users
$stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle adding a new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (!empty($username) && !empty($password) && in_array($role, ['admin', 'manager', 'cashier', 'waiter'])) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$username, $hashed_password, $role]);
            $success = "User '$username' added successfully!";
            $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Error: Username '$username' already exists.";
        }
    } else {
        $error = "Please fill all fields with valid values.";
    }
}

// Handle editing a user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $username = trim($_POST['username']);
    $role = $_POST['role'];

    if ($user_id != $_SESSION['user_id'] && !empty($username) && in_array($role, ['admin', 'manager', 'cashier', 'waiter'])) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        try {
            $stmt->execute([$username, $role, $user_id]);
            $success = "User updated successfully!";
            $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Error: Username '$username' already exists.";
        }
    } else {
        $error = "Cannot edit your own account here or invalid data provided.";
    }
}

// Handle deleting a user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['delete_user'];
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $success = "User deleted successfully!";
        $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "You cannot delete your own account.";
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        $success = "Password reset successfully!";
    } else {
        $error = "Please enter a new password.";
    }
}

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['company_logo'])) {
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['company_logo'];
    $file_name = 'company_logo.png'; // Fixed name to overwrite previous logo
    $target_path = $upload_dir . $file_name;

    // Validate file
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; // 2MB
    if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size && $file['error'] == 0) {
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $success = "Logo uploaded successfully! It will appear on receipts.";
        } else {
            $error = "Failed to upload logo.";
        }
    } else {
        $error = "Invalid file. Use PNG/JPG, max 2MB.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        function confirmDelete() {
            return confirm("Are you sure you want to delete this user?");
        }
    </script>
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (Admin)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <div class="sidebar">
            <h3>Menu</h3>
            <a href="dashboard.php" class="orange-btn">Dashboard</a>
            <a href="sell.php" class="orange-btn">Sell Products</a>
            <a href="inventory.php" class="orange-btn">Manage Inventory</a>
            <a href="users.php" class="orange-btn active">Manage Users</a>
            <h3>Reports</h3>
            <a href="reports.php?period=daily" class="orange-btn">Daily Report</a>
            <a href="reports.php?period=weekly" class="orange-btn">Weekly Report</a>
            <a href="reports.php?period=monthly" class="orange-btn">Monthly Report</a>
            <a href="reports.php?period=yearly" class="orange-btn">Yearly Report</a>
        </div>
        <div class="main-content">
            <h1>User Management</h1>
            <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

            <!-- Logo Upload Form -->
            <form method="POST" enctype="multipart/form-data" class="logo-upload-form">
                <h3>Upload Company Logo</h3>
                <input type="file" name="company_logo" accept=".png,.jpg,.jpeg" required>
                <button type="submit" class="orange-btn">Upload Logo</button>
                <p>Upload a PNG or JPG file (max 2MB). This logo will appear on receipts.</p>
            </form>

            <!-- Add New User Form -->
            <form method="POST" class="add-user-form">
                <h3>Add New User</h3>
                <input type="text" name="username" placeholder="Username" required class="user-input">
                <input type="password" name="password" placeholder="Password" required class="user-input">
                <select name="role" class="user-input" required>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="cashier">Cashier</option>
                    <option value="waiter">Waiter</option>
                </select>
                <button type="submit" name="add_user" value="1" class="orange-btn">Add User</button>
            </form>

            <!-- User List -->
            <?php if (empty($users)): ?>
                <p class="error">No users found. Add a user above.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo ucfirst($user['role']); ?></td>
                            <td>
                                <!-- Edit Form -->
                                <form method="POST" class="edit-user-form" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="text" name="username" value="<?php echo $user['username']; ?>" required class="user-input small-input">
                                    <select name="role" class="user-input small-input">
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="cashier" <?php echo $user['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                        <option value="waiter" <?php echo $user['role'] === 'waiter' ? 'selected' : ''; ?>>Waiter</option>
                                    </select>
                                    <button type="submit" name="edit_user" value="1" class="orange-btn">Save</button>
                                </form>
                                <!-- Reset Password Form -->
                                <form method="POST" class="reset-password-form" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="password" name="new_password" placeholder="New Password" class="user-input small-input">
                                    <button type="submit" name="reset_password" value="1" class="orange-btn">Reset Password</button>
                                </form>
                                <!-- Delete Form -->
                                <form method="POST" action="users.php" onsubmit="return confirmDelete();" class="delete-form" style="display: inline;">
                                    <input type="hidden" name="delete_user" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="orange-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>