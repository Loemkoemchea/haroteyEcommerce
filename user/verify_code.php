<?php
session_start();
include '../db.php';

$email = $_GET['email'] ?? $_SESSION['temp_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Find user with this email, unverified, code matches, not expired
    $stmt = $conn->prepare("
        SELECT id, verification_expires FROM users 
        WHERE email = ? AND email_verified = 0 AND verification_token = ?
    ");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        if (strtotime($user['verification_expires']) < time()) {
            $error = "Verification code has expired. Please request a new one.";
        } else {
            // Mark as verified
            $update = $conn->prepare("
                UPDATE users SET email_verified = 1, verification_token = NULL, verification_expires = NULL 
                WHERE id = ?
            ");
            $update->bind_param("i", $user['id']);
            $update->execute();
            $update->close();

            $_SESSION['success'] = "Email verified successfully! You can now log in.";
            unset($_SESSION['temp_user_id'], $_SESSION['temp_email']);
            header("Location: index.php");
            exit;
        }
    } else {
        $error = "Invalid verification code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .code-input { font-size: 24px; letter-spacing: 8px; text-align: center; padding: 12px; border: 2px solid #ddd; border-radius: 8px; width: 100%; }
        .btn { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #218838; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color: #28a745;">📧 Verify Your Email</h1>
        <p>We've sent a 6‑digit verification code to:</p>
        <p style="font-size: 18px; font-weight: bold;"><?= htmlspecialchars($email) ?></p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <div style="margin: 30px 0;">
                <input type="text" name="code" class="code-input" maxlength="6" placeholder="000000" required>
            </div>
            <button type="submit" class="btn">Verify Email</button>
            <p style="margin-top: 25px;">
                <a href="resend_code.php?email=<?= urlencode($email) ?>" style="color: #28a745;">Didn't receive the code? Resend</a>
            </p>
            <p style="margin-top: 10px;">
                <a href="register.php" style="color: #666;">← Back to registration</a>
            </p>
        </form>
    </div>
</body>
</html>