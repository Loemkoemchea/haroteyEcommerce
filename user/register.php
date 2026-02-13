<?php
// file: user/register.php
session_start();
include '../db.php';                // ✅ Database connection
require_once '../email_config.php'; // ✅ Email function

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Define and sanitize all POST variables
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';          // ✅ defined here
    $confirm  = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $terms     = isset($_POST['terms']);

    // Validation
    $errors = [];

    if (empty($username)) $errors[] = "Username is required.";
    elseif (strlen($username) < 3) $errors[] = "Username must be at least 3 characters.";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = "Username can only contain letters, numbers, and underscores.";

    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";

    if (empty($password)) $errors[] = "Password is required.";
    elseif (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    if ($password !== $confirm) $errors[] = "Passwords do not match.";

    if (!$terms) $errors[] = "You must agree to the Terms & Conditions.";

    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Username or email already exists.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        // ✅ Generate 6-digit OTP
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        $verification_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'customer';
        $status = 'active';
        $email_verified = 0;

        $stmt = $conn->prepare("
            INSERT INTO users (
                username, email, password, full_name, phone, role, status,
                email_verified, verification_token, verification_expires, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "sssssssiss",
            $username,
            $email,
            $hashed_password,
            $full_name,
            $phone,
            $role,
            $status,
            $email_verified,
            $verification_code,
            $verification_expires
        );

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();

            // ✅ Send verification code via email
            $email_sent = sendVerificationCode($email, $full_name ?: $username, $verification_code);

            if ($email_sent) {
                $_SESSION['temp_user_id'] = $user_id;
                $_SESSION['temp_email'] = $email;
                header("Location: verify_code.php?email=" . urlencode($email));
                exit;
            } else {
                // Delete the user if email fails
                $conn->query("DELETE FROM users WHERE id = $user_id");
                $errors[] = "Failed to send verification code. Please try again.";
            }
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .auth-container { max-width: 550px; margin: 30px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; }
        .btn-register { width: 100%; padding: 14px; background: #28a745; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-register:hover { background: #218838; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1 style="text-align: center; color: #28a745;">🛍️ Harotey Shop</h1>
        <h2 style="text-align: center;">Create Account</h2>

        <?php if ($error): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="full_name">Full Name (Optional)</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="Choose a username">
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="your@email.com">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number (Optional)</label>
                <input type="tel" id="phone" name="phone"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="01XXXXXXXXX">
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required
                       placeholder="Minimum 6 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       placeholder="Re-enter password">
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="terms" required <?= isset($_POST['terms']) ? 'checked' : '' ?>>
                    <span>I agree to the <a href="#" style="color: #28a745;">Terms & Conditions</a> and <a href="#" style="color: #28a745;">Privacy Policy</a> *</span>
                </label>
            </div>

            <button type="submit" class="btn-register">Create Account</button>

            <div style="text-align: center; margin-top: 25px;">
                Already have an account? <a href="index.php" style="color: #28a745; font-weight: 600;">Login here</a>
            </div>
            <div style="text-align: center; margin-top: 15px;">
                <a href="../index.php" style="color: #666;">← Continue Shopping</a>
            </div>
        </form>
    </div>
</body>
</html>