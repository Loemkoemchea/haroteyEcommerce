<?php
// file: login.php – Unified login for customers and admins
session_start();

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    // Check role from session or database
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}

include 'db.php';

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        // Fetch user by username or email
        $stmt = $conn->prepare("
            SELECT id, username, email, password, full_name, role, status 
            FROM users 
            WHERE (username = ? OR email = ?) AND status = 'active'
        ");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'] ?: $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Remember me (30 days) – optional, store token in DB
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + (86400 * 30));
                $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->bind_param("si", $token, $user['id']);
                $stmt->execute();
                $stmt->close();
                setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
            }

            // Redirect based on role
            if ($user['role'] === 'admin') {
                // Set admin-specific session for backward compatibility
                $_SESSION['admin'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $redirect_url = !empty($redirect) ? $redirect : 'admin/dashboard.php';
            } else {
                // Customer
                $redirect_url = !empty($redirect) ? $redirect : 'user/dashboard.php';
            }
            header("Location: " . $redirect_url);
            exit;
        } else {
            $error = "Invalid username/email or password, or account is inactive.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Harotey Shop</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .login-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #28a745;
            font-size: 32px;
            margin: 0;
        }
        .logo p {
            color: #666;
            margin: 5px 0 0;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.2s;
        }
        .form-group input:focus {
            border-color: #28a745;
            outline: none;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.1);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            cursor: pointer;
        }
        .btn-login {
            width: 100%;
            padding: 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: #218838;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
        }
        .register-link a {
            color: #28a745;
            font-weight: 600;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #999;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🛍️ Harotey Shop</h1>
            <p>Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" 
                       placeholder="Enter your username or email" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password" required>
            </div>
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="forgot-password.php" style="color: #28a745; text-decoration: none; font-size: 14px;">
                    Forgot password?
                </a>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Create one now</a>
        </div>
        <a href="index.php" class="back-link">← Continue Shopping</a>
    </div>
</body>
</html>