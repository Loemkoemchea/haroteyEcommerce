<?php
// file: user/profile.php
require_once 'auth_check.php';

$message = '';
$error = '';

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    // Validate (optional)
    if (empty($full_name)) {
        $error = "Full name cannot be empty.";
    } else {
        // Update only full_name and phone – address moved to separate table
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $phone, $user_id);

        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            // Refresh session name
            $_SESSION['user_name'] = $full_name;
            // Refresh user data
            $user['full_name'] = $full_name;
            $user['phone'] = $phone;
        } else {
            $error = "Error updating profile.";
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }

    if (empty($errors)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
        } else {
            $error = "Error changing password.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .profile-container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
        .profile-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; }
        .form-group input:focus { border-color: #28a745; outline: none; box-shadow: 0 0 0 3px rgba(40,167,69,0.1); }
        .form-group input[readonly] { background: #f8f9fa; cursor: not-allowed; }
        .btn-save { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { background: #218838; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .address-link { display: inline-block; margin-top: 15px; color: #28a745; text-decoration: none; font-weight: 600; }
        .address-link:hover { text-decoration: underline; }
        hr { margin: 30px 0; border: none; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="profile-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>👤 My Profile</h1>
            <a href="dashboard.php" class="btn" style="background: #6c757d;">← Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div class="profile-card">
            <h2 style="margin-top: 0; margin-bottom: 25px;">Profile Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" 
                           placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                           placeholder="01XXXXXXXXX">
                </div>
                <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
            </form>

            <!-- Link to Address Management -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                <a href="addresses.php" class="address-link">📮 Manage Your Addresses →</a>
            </div>
        </div>

        <!-- Change Password -->
        <div class="profile-card">
            <h2 style="margin-top: 0; margin-bottom: 25px;">🔐 Change Password</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small style="color: #666;">Minimum 6 characters</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-save">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>