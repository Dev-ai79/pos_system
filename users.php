<?php
// users.php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$error = $success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $valid_roles = ['admin', 'manager', 'cashier', 'waiter'];

    if (!empty($username) && !empty($password) && in_array($role, $valid_roles)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() == 0) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            try {
                $stmt->execute([$username, $hashed_password, $role]);
                $success = "User '$username' added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding user: " . $e->getMessage();
            }
        } else {
            $error = "Username '$username' already exists.";
        }
    } else {
        $error = "Please provide a valid username, password, and role.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id !== $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        try {
            $stmt->execute([$user_id]);
            $success = "User deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    } else {
        $error = "You cannot delete your own account.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_password = trim($_POST['new_password'] ?? '');
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        try {
            $stmt->execute([$hashed_password, $user_id]);
            $success = "Password reset successfully!";
        } catch (PDOException $e) {
            $error = "Error resetting password: " . $e->getMessage();
        }
    } else {
        $error = "Please provide a new password.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['company_logo'])) {
    $upload_dir = 'Uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $file = $_FILES['company_logo'];
    $file_name = 'company_logo.png';
    $target_path = $upload_dir . $file_name;
    if ($file['type'] == 'image/png' && $file['size'] <= 2 * 1024 * 1024 && $file['error'] == 0) {
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $success = "Logo uploaded successfully! It will appear on receipts.";
        } else {
            $error = "Failed to upload logo.";
        }
    } else {
        $error = "Please upload a PNG image smaller than 2MB.";
    }
}

$stmt = $pdo->prepare("SELECT id, username, role FROM users ORDER BY username ASC");
$stmt->execute();
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - POS System</title>
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
            <h1>Manage Users</h1>
            <?php if ($success): ?>
                <p class="success"><?php echo $success; ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <h3>Add New User</h3>
            <form method="POST" class="add-user-form">
                <input type="text" name="username" placeholder="Username" required class="search-input">
                <input type="password" name="password" placeholder="Password" required class="search-input">
                <select name="role" required class="search-input">
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="cashier">Cashier</option>
                    <option value="waiter">Waiter</option>
                </select>
                <button type="submit" name="add_user" value="1" class="orange-btn">Add User</button>
            </form>
            <h3>Upload Company Logo</h3>
            <form method="POST" enctype="multipart/form-data" class="logo-form">
                <input type="file" name="company_logo" accept="image/png" required class="search-input">
                <button type="submit" class="orange-btn">Upload Logo</button>
            </form>
            <h3>User List</h3>
            <div class="table-responsive">
                <table id="user-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="3">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" class="reset-password-form">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="password" name="new_password" placeholder="New Password" class="small-input">
                                            <button type="submit" name="reset_password" value="1" class="orange-btn">Reset Password</button>
                                        </form>
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" value="1" class="orange-btn">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>