<?php
session_start();
include '../db.php';
require_once '../email_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, full_name, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $update->bind_param("ssi", $token, $expires, $user['id']);
            $update->execute();
            $update->close();

            // Send email
            $name = $user['full_name'] ?: $user['username'];
            $email_sent = sendPasswordResetEmail($email, $name, $token);

            if ($email_sent) {
                $success = "Password reset link has been sent to your email. Please check your inbox.";
            } else {
                $error = "Failed to send email. Please try again later.";
            }
        } else {
            // Don't reveal that email doesn't exist – show generic success message
            $success = "If this email is registered, you will receive a password reset link.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 15px; }
        .btn { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { background: #218838; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color: #28a745;">🔑 Forgot Password?</h1>
        <p style="color: #666; margin-bottom: 30px;">Enter your email address and we'll send you a link to reset your password.</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn">Send Reset Link</button>
        </form>

        <p style="margin-top: 25px;">
            <a href="index.php" style="color: #28a745;">← Back to Login</a>
        </p>
    </div>
</body>
</html>