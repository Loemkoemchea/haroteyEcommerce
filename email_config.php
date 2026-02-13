<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';   // ✅ Composer autoloader

// ---------- OTP Verification Email ----------
function sendVerificationCode($email, $name, $code) {
    $mail = new PHPMailer(true);
    try {
        // 🔁 REPLACE WITH YOUR SMTP CREDENTIALS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'loemkoemchea@gmail.com';   // YOUR GMAIL
        $mail->Password   = 'vijx wtbu fjkv ntbi';   // YOUR APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your-shop-email@gmail.com', 'Harotey Shop');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Your Verification Code - Harotey Shop';
        $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2 style='color: #28a745;'>Welcome to Harotey Shop, $name!</h2>
                <p>Your verification code is:</p>
                <div style='background: #f8f9fa; padding: 20px; text-align: center; font-size: 36px; font-weight: bold; letter-spacing: 8px; border-radius: 8px;'>
                    $code
                </div>
                <p>This code expires in <strong>10 minutes</strong>.</p>
            </body>
            </html>
        ";
        $mail->AltBody = "Your verification code is: $code. Expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer OTP Error: " . $mail->ErrorInfo);
        return false;
    }
}

// ---------- Password Reset Email ----------
function sendPasswordResetEmail($email, $name, $token) {
    $mail = new PHPMailer(true);
    try {
        // 🔁 USE THE SAME SMTP CREDENTIALS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'loemkoemchea@gmail.com';   // YOUR GMAIL
        $mail->Password   = 'vijx wtbu fjkv ntbi';   // YOUR APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your-shop-email@gmail.com', 'Harotey Shop');
        $mail->addAddress($email, $name);

        $reset_link = "http://{$_SERVER['HTTP_HOST']}/testEcommerce/testEcommerce/user/reset_password.php?token=" . urlencode($token);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Harotey Shop';
        $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2 style='color: #28a745;'>Password Reset Request</h2>
                <p>Hello $name,</p>
                <p>Click the button below to reset your password:</p>
                <a href='$reset_link' style='display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                <p style='margin-top: 25px;'>Or copy this link:<br><a href='$reset_link'>$reset_link</a></p>
                <p style='color: #666;'>This link expires in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
            </body>
            </html>
        ";
        $mail->AltBody = "Password reset link: $reset_link (expires in 1 hour)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Reset Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>