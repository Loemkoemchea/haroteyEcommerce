<?php
session_start();
include '../db.php';

$email = $_GET['email'] ?? $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    $stmt = $conn->prepare("SELECT id, full_name, username, email_verified FROM users WHERE email = ? AND email_verified = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $new_code = sprintf("%06d", mt_rand(1, 999999));
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $update = $conn->prepare("UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?");
        $update->bind_param("ssi", $new_code, $expires, $user['id']);
        $update->execute();
        $update->close();

        require_once '../email_config.php';
        $sent = sendVerificationCode($email, $user['full_name'] ?: $user['username'], $new_code);

        if ($sent) {
            $_SESSION['success'] = "A new verification code has been sent.";
            header("Location: verify_code.php?email=" . urlencode($email));
            exit;
        }
    }
    $error = "Unable to resend code. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Code - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; }
        .btn { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 30px; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color: #28a745;">📧 Resend Verification Code</h1>
        <?php if (!empty($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Your email address" required style="width: 100%; padding: 12px; margin: 20px 0; border: 2px solid #ddd; border-radius: 8px;">
            <button type="submit" class="btn">Send New Code</button>
            <p style="margin-top: 20px;"><a href="verify_code.php?email=<?= urlencode($email) ?>" style="color: #666;">← Back to verification</a></p>
        </form>
    </div>
</body>
</html>