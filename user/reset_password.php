<?php
session_start();
include '../db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Verify token exists and not expired
if ($token) {
    $stmt = $conn->prepare("SELECT id, email, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $error = "Invalid or expired reset link.";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $error = "This reset link has expired. Please request a new one.";
        // Clear expired token
        $clear = $conn->prepare("UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $clear->bind_param("i", $user['id']);
        $clear->execute();
        $clear->close();
    }
} else {
    $error = "No reset token provided.";
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $user && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $update->bind_param("si", $hashed, $user['id']);
        $update->execute();
        $update->close();

        $_SESSION['success'] = "Your password has been reset successfully. You can now log in.";
        header("Location: index.php");
        exit;
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
    <title>Reset Password - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; }
        .btn { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { background: #218838; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color: #28a745;">🔐 Reset Password</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
            <p><a href="forgot_password.php" style="color: #28a745;">Request new reset link</a></p>
        <?php elseif ($user): ?>
            <p>Enter your new password below.</p>
            <form method="POST">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <small style="color: #666;">Minimum 6 characters</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password" class="btn">Update Password</button>
            </form>
        <?php endif; ?>

        <p style="margin-top: 25px;">
            <a href="index.php" style="color: #28a745;">← Back to Login</a>
        </p>
    </div>
</body>
</html>