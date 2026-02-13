<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function sendVerificationCode($email, $name, $code) {
    $mail = new PHPMailer(true);
    try {
        // ---------- GMAIL SMTP (YOUR ACCOUNT) ----------
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'loemkoemchea@gmail.com';   // 🔁 YOUR SENDER EMAIL
        $mail->Password   = 'vijx wtbu fjkv ntbi';   // 🔁 YOUR APP PASSWORD
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
                <p style='font-size: 16px;'>Your verification code is:</p>
                <div style='background: #f8f9fa; padding: 20px; text-align: center; font-size: 36px; font-weight: bold; letter-spacing: 8px; border-radius: 8px;'>
                    $code
                </div>
                <p style='margin-top: 25px;'>Enter this code on the verification page. It expires in <strong>10 minutes</strong>.</p>
                <p style='color: #666;'>If you didn't request this, please ignore this email.</p>
                <hr>
                <p style='color: #999; font-size: 12px;'>Harotey Shop – Your trusted online store.</p>
            </body>
            </html>
        ";
        $mail->AltBody = "Your verification code is: $code. It expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>