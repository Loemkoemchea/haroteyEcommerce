<?php
session_start();
$email = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Success - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .thankyou-container { max-width: 600px; margin: 50px auto; text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .btn { background: #28a745; color: white; padding: 12px 30px; border-radius: 30px; text-decoration: none; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="thankyou-container">
        <div style="font-size: 60px; margin-bottom: 20px;">📧</div>
        <h1 style="color: #28a745;">Registration Successful!</h1>
        <p style="font-size: 18px; margin: 20px 0;">We've sent a verification email to:</p>
        <p style="font-size: 20px; font-weight: bold; color: #333;"><?= htmlspecialchars($email) ?></p>
        <p style="color: #666; margin: 30px 0;">Please check your inbox and click the verification link to activate your account.</p>
        <a href="index.php" class="btn">Return to Login</a>
        <p style="margin-top: 25px;">
            <a href="resend_verification.php?email=<?= urlencode($email) ?>" style="color: #28a745;">Didn't receive the email? Resend</a>
        </p>
    </div>
</body>
</html>