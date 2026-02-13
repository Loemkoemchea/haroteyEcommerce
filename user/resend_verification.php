<?php
// file: user/resend_verification.php
session_start();
include '../db.php';
require_once '../email_config.php';

$email = $_GET['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    $stmt = $conn->prepare("
        SELECT id, username, full_name, email_verified 
        FROM users 
        WHERE email = ? AND email_verified = 0
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $update = $conn->prepare("
            UPDATE users 
            SET verification_token = ?, verification_expires = ? 
            WHERE id = ?
        ");
        $update->bind_param("ssi", $token, $expires, $user['id']);
        $update->execute();
        $update->close();

        // Send email
        $name = $user['full_name'] ?: $user['username'];
        $sent = sendVerificationEmail($email, $name, $token);
        
        if ($sent) {
            $_SESSION['success'] = "Verification email resent. Please check your inbox.";
            header("Location: register_thankyou.php?email=" . urlencode($email));
            exit;
        } else {
            $error = "Failed to send email. Please try again later.";
        }
    } else {
        $error = "Email not found or already verified.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .btn { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 30px; width: 100%; font-weight: 600; cursor: pointer; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color: #28a745;">Resend Verification Email</h1>
        <?php if (!empty($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Your Email Address</label>
                <input type="email" name="email" id="email" class="form-control" 
                       value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <button type="submit" class="btn">Send Verification Email</button>
            <p style="margin-top: 20px;">
                <a href="login.php" style="color: #666;">← Back to Login</a>
            </p>
        </form>
    </div>
</body>
</html>