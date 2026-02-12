<?php
// file: account.php
session_start();
include 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user/dashboard.php");
    exit;
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'login';
$error = '';
$success = '';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = "Please enter email/username and password";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
            $_SESSION['user_email'] = $user['email'];
            
            // Remember me (30 days)
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
                // Store token in database (you can implement this later)
            }
            
            // Redirect to dashboard or previous page
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'user/dashboard.php';
            header("Location: " . $redirect);
            exit;
        } else {
            $error = "Invalid email/username or password";
        }
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $terms = isset($_POST['terms']);

    // Validation
    $errors = [];
    
    if (empty($username)) $errors[] = "Username is required";
    elseif (strlen($username) < 3) $errors[] = "Username must be at least 3 characters";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = "Username can only contain letters, numbers, and underscore";
    
    if (empty($email)) $errors[] = "Email is required";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    
    if (empty($password)) $errors[] = "Password is required";
    elseif (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    
    if (!$terms) $errors[] = "You must agree to the Terms & Conditions";

    // Check if username/email exists
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Username or email already exists";
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $phone);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Auto login
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $full_name ?: $username;
            $_SESSION['user_email'] = $email;
            
            $success = "Registration successful! Redirecting to dashboard...";
            header("refresh:2;url=user/dashboard.php");
            $active_tab = 'login';
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
        $active_tab = 'register';
    }
}

// Handle Password Reset Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $reset_email = trim($_POST['reset_email']);
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $reset_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expires, $reset_email);
        $stmt->execute();
        
        // In a real application, send email here
        // For demo, we'll just show success message
        $success = "Password reset link has been sent to your email. (Demo: token=$token)";
    } else {
        $error = "No account found with that email address";
    }
    $active_tab = 'forgot';
}

// Handle Order Tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_order'])) {
    $tracking_number = trim($_POST['tracking_number']);
    $track_email = trim($_POST['track_email']);
    
    $stmt = $conn->prepare("
        SELECT o.*, u.full_name, u.email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE (o.tracking_number = ? OR o.id = ?) AND o.customer_email = ?
    ");
    $stmt->bind_param("sis", $tracking_number, $tracking_number, $track_email);
    $stmt->execute();
    $tracked_order = $stmt->get_result()->fetch_assoc();
    
    if ($tracked_order) {
        // Store in session for display
        $_SESSION['tracked_order'] = $tracked_order;
        header("Location: account.php?tab=track&success=found");
        exit;
    } else {
        $error = "No order found with this tracking number and email combination";
    }
    $active_tab = 'track';
}

// Get tracked order from session
$tracked_order = $_SESSION['tracked_order'] ?? null;
if (isset($_GET['success']) && $_GET['success'] == 'found') {
    $active_tab = 'track-result';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Harotey Shop</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Modern Account Page Styles */
        :root {
            --primary-color: #28a745;
            --primary-dark: #218838;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --border-color: #e9ecef;
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .account-wrapper {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Header */
        .account-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .account-header h1 {
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .account-header p {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
        }

        .store-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            color: white;
            font-size: 14px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        /* Main Card */
        .account-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            display: grid;
            grid-template-columns: 350px 1fr;
            min-height: 600px;
        }

        /* Sidebar */
        .account-sidebar {
            background: linear-gradient(145deg, #2c3e50, #1e2b37);
            color: white;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
        }

        .shop-logo {
            font-size: 48px;
            margin-bottom: 30px;
        }

        .sidebar-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .sidebar-description {
            color: rgba(255,255,255,0.7);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: rgba(255,255,255,0.9);
        }

        .feature-list i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
        }

        .trust-badge {
            margin-top: auto;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.2);
            display: flex;
            gap: 15px;
            color: rgba(255,255,255,0.7);
            font-size: 13px;
        }

        /* Main Content */
        .account-content {
            padding: 40px;
            background: white;
        }

        /* Tabs */
        .account-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn i {
            font-size: 18px;
        }

        .tab-btn:hover {
            background: var(--light-bg);
            color: var(--text-dark);
        }

        .tab-btn.active {
            background: var(--primary-color);
            color: white;
        }

        /* Forms */
        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .form-header {
            margin-bottom: 25px;
        }

        .form-header h2 {
            font-size: 24px;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 14px;
        }

        .form-group label i {
            margin-right: 8px;
            color: var(--primary-color);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(40,167,69,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .btn-block {
            width: 100%;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--text-dark);
        }

        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-weight: normal;
        }

        /* Links */
        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        /* Social Login */
        .social-login {
            margin-top: 25px;
            text-align: center;
        }

        .social-login p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 15px;
            position: relative;
        }

        .social-login p::before,
        .social-login p::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background: var(--border-color);
        }

        .social-login p::before {
            left: 0;
        }

        .social-login p::after {
            right: 0;
        }

        .social-buttons {
            display: flex;
            gap: 15px;
        }

        .social-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .social-btn:hover {
            background: var(--light-bg);
            border-color: var(--primary-color);
        }

        /* Order Tracking Result */
        .order-result {
            background: var(--light-bg);
            border-radius: 16px;
            padding: 25px;
            margin-top: 20px;
        }

        .tracking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .tracking-number {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            font-family: monospace;
        }

        .tracking-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }

        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        .progress-step:not(:last-child)::before {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }

        .progress-step.completed:not(:last-child)::before {
            background: var(--primary-color);
        }

        .step-icon {
            width: 32px;
            height: 32px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            margin: 0 auto 8px;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .progress-step.completed .step-icon {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .progress-step.active .step-icon {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .step-label {
            font-size: 12px;
            color: var(--text-muted);
        }

        .progress-step.completed .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        @media (max-width: 992px) {
            .account-card {
                grid-template-columns: 1fr;
            }
            
            .account-sidebar {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .social-buttons {
                flex-direction: column;
            }
            
            .account-content {
                padding: 30px 20px;
            }
        }

        /* Password Strength */
        .password-strength {
            margin-top: 10px;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="account-wrapper">
        <!-- Header -->
        <div class="account-header">
            <span class="store-badge">
                🛍️ Since 2024
            </span>
            <h1>Welcome to Harotey Shop</h1>
            <p>Your trusted online shopping destination</p>
        </div>

        <!-- Main Account Card -->
        <div class="account-card">
            <!-- Left Sidebar - Brand Info -->
            <div class="account-sidebar">
                <div class="shop-logo">🛒</div>
                <h2 class="sidebar-title">Shop Smart, Live Better</h2>
                <p class="sidebar-description">
                    Join millions of happy customers who shop with Harotey for the best deals, authentic products, and fast delivery.
                </p>
                
                <ul class="feature-list">
                    <li>
                        <i>✓</i> 100% Authentic Products
                    </li>
                    <li>
                        <i>✓</i> Free Shipping Over ৳1000
                    </li>
                    <li>
                        <i>✓</i> 7 Days Easy Return
                    </li>
                    <li>
                        <i>✓</i> 24/7 Customer Support
                    </li>
                    <li>
                        <i>✓</i> Secure Payments
                    </li>
                </ul>

                <div class="trust-badge">
                    <span>🔒 SSL Secured</span>
                    <span>⭐ Trusted by 50K+</span>
                </div>
            </div>

            <!-- Right Content - Account Forms -->
            <div class="account-content">
                <!-- Alert Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i>⚠️</i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i>✅</i>
                        <div><?= $success ?></div>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="account-tabs">
                    <button class="tab-btn <?= $active_tab == 'login' ? 'active' : '' ?>" onclick="switchTab('login')">
                        <i>🔐</i> Login
                    </button>
                    <button class="tab-btn <?= $active_tab == 'register' ? 'active' : '' ?>" onclick="switchTab('register')">
                        <i>📝</i> Register
                    </button>
                    <button class="tab-btn <?= $active_tab == 'track' || $active_tab == 'track-result' ? 'active' : '' ?>" onclick="switchTab('track')">
                        <i>📦</i> Track Order
                    </button>
                    <button class="tab-btn <?= $active_tab == 'forgot' ? 'active' : '' ?>" onclick="switchTab('forgot')">
                        <i>🔑</i> Forgot Password
                    </button>
                </div>

                <!-- Login Form -->
                <div id="login-tab" class="tab-pane <?= $active_tab == 'login' ? 'active' : '' ?>">
                    <div class="form-header">
                        <h2>Welcome Back! 👋</h2>
                        <p>Login to your account to manage orders and more</p>
                    </div>

                    <form method="POST" action="account.php?tab=login">
                        <div class="form-group">
                            <label>
                                <i>📧</i> Email or Username
                            </label>
                            <div class="input-group">
                                <span class="input-icon">👤</span>
                                <input type="text" name="email" class="form-control" 
                                       placeholder="Enter your email or username" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <i>🔒</i> Password
                            </label>
                            <div class="input-group">
                                <span class="input-icon">🔑</span>
                                <input type="password" name="password" class="form-control" 
                                       placeholder="Enter your password" required>
                            </div>
                        </div>

                        <div class="form-row" style="align-items: center;">
                            <div class="checkbox-group">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <div style="text-align: right;">
                                <a href="#" class="forgot-link" onclick="switchTab('forgot'); return false;">
                                    Forgot Password?
                                </a>
                            </div>
                        </div>

                        <button type="submit" name="login" class="btn btn-primary btn-block">
                            🔐 Login to Account
                        </button>

                        <div class="social-login">
                            <p>Or login with</p>
                            <div class="social-buttons">
                                <button type="button" class="social-btn" onclick="alert('Google login coming soon!')">
                                    <span style="color: #DB4437;">G</span> Google
                                </button>
                                <button type="button" class="social-btn" onclick="alert('Facebook login coming soon!')">
                                    <span style="color: #4267B2;">f</span> Facebook
                                </button>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                            <span style="color: var(--text-muted);">New to Harotey Shop?</span>
                            <a href="#" onclick="switchTab('register'); return false;" 
                               style="color: var(--primary-color); font-weight: 600; margin-left: 8px; text-decoration: none;">
                                Create Account →
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Register Form -->
                <div id="register-tab" class="tab-pane <?= $active_tab == 'register' ? 'active' : '' ?>">
                    <div class="form-header">
                        <h2>Create Account 🎉</h2>
                        <p>Join Harotey Shop for exclusive deals and faster checkout</p>
                    </div>

                    <form method="POST" action="account.php?tab=register" id="registerForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <i>👤</i> Username *
                                </label>
                                <div class="input-group">
                                    <span class="input-icon">@</span>
                                    <input type="text" name="username" class="form-control" 
                                           placeholder="Choose username" required
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i>📧</i> Email *
                                </label>
                                <div class="input-group">
                                    <span class="input-icon">📧</span>
                                    <input type="email" name="email" class="form-control" 
                                           placeholder="your@email.com" required
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <i>👤</i> Full Name (Optional)
                            </label>
                            <div class="input-group">
                                <span class="input-icon">📝</span>
                                <input type="text" name="full_name" class="form-control" 
                                       placeholder="Enter your full name"
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <i>📱</i> Phone Number (Optional)
                            </label>
                            <div class="input-group">
                                <span class="input-icon">📱</span>
                                <input type="tel" name="phone" class="form-control" 
                                       placeholder="01XXXXXXXXX"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <i>🔒</i> Password *
                                </label>
                                <div class="input-group">
                                    <span class="input-icon">🔑</span>
                                    <input type="password" id="reg_password" name="password" class="form-control" 
                                           placeholder="Minimum 6 characters" required>
                                </div>
                                <div class="password-strength">
                                    <div id="strengthBar" class="strength-bar"></div>
                                </div>
                                <div id="strengthText" class="strength-text"></div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i>✅</i> Confirm Password *
                                </label>
                                <div class="input-group">
                                    <span class="input-icon">🔒</span>
                                    <input type="password" id="reg_confirm_password" name="confirm_password" class="form-control" 
                                           placeholder="Re-enter password" required>
                                </div>
                            </div>
                        </div>

                        <div class="checkbox-group" style="margin-bottom: 25px;">
                            <input type="checkbox" id="terms" name="terms" required <?= isset($_POST['terms']) ? 'checked' : '' ?>>
                            <label for="terms">
                                I agree to the <a href="#" style="color: var(--primary-color);">Terms & Conditions</a> 
                                and <a href="#" style="color: var(--primary-color);">Privacy Policy</a> *
                            </label>
                        </div>

                        <button type="submit" name="register" class="btn btn-primary btn-block">
                            📝 Create Account
                        </button>

                        <div style="text-align: center; margin-top: 25px;">
                            <span style="color: var(--text-muted);">Already have an account?</span>
                            <a href="#" onclick="switchTab('login'); return false;" 
                               style="color: var(--primary-color); font-weight: 600; margin-left: 8px; text-decoration: none;">
                                Login Here →
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Track Order Form -->
                <div id="track-tab" class="tab-pane <?= $active_tab == 'track' ? 'active' : '' ?>">
                    <div class="form-header">
                        <h2>📦 Track Your Order</h2>
                        <p>Enter your tracking number and email to check order status</p>
                    </div>

                    <form method="POST" action="account.php?tab=track">
                        <div class="form-group">
                            <label>
                                <i>🔍</i> Tracking Number or Order ID
                            </label>
                            <div class="input-group">
                                <span class="input-icon">📦</span>
                                <input type="text" name="tracking_number" class="form-control" 
                                       placeholder="e.g., HAR001234BD or 12345" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <i>📧</i> Email Address
                            </label>
                            <div class="input-group">
                                <span class="input-icon">📧</span>
                                <input type="email" name="track_email" class="form-control" 
                                       placeholder="Enter your email" required>
                            </div>
                        </div>

                        <button type="submit" name="track_order" class="btn btn-primary btn-block">
                            🔍 Track Order
                        </button>

                        <div style="text-align: center; margin-top: 20px;">
                            <a href="user/orders.php" style="color: var(--primary-color); text-decoration: none;">
                                Login to view all orders →
                            </a>
                        </div>
                    </form>

                    <div style="margin-top: 30px; padding: 20px; background: var(--light-bg); border-radius: 12px;">
                        <h4 style="margin-bottom: 10px;">❓ How to find tracking number?</h4>
                        <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                            Your tracking number is sent to your email after order confirmation. 
                            It starts with "HAR" followed by 6 digits and ends with "BD".<br>
                            Example: <strong>HAR001234BD</strong>
                        </p>
                    </div>
                </div>

                <!-- Track Order Result -->
                <?php if ($tracked_order): ?>
                <div id="track-result-tab" class="tab-pane <?= $active_tab == 'track-result' ? 'active' : '' ?>">
                    <div class="form-header">
                        <h2>📋 Order Tracking Result</h2>
                        <p>Found order #<?= $tracked_order['id'] ?></p>
                    </div>

                    <div class="order-result">
                        <div class="tracking-header">
                            <div>
                                <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 5px;">Tracking Number</div>
                                <div class="tracking-number"><?= $tracked_order['tracking_number'] ?? 'HAR' . str_pad($tracked_order['id'], 6, '0', STR_PAD_LEFT) . 'BD' ?></div>
                            </div>
                            <span class="tracking-status status-<?= $tracked_order['status'] ?>" 
                                  style="background: <?= $tracked_order['status'] == 'pending' ? '#fff3cd' : ($tracked_order['status'] == 'processing' ? '#cce5ff' : ($tracked_order['status'] == 'completed' ? '#d4edda' : '#f8d7da')) ?>; 
                                         color: <?= $tracked_order['status'] == 'pending' ? '#856404' : ($tracked_order['status'] == 'processing' ? '#004085' : ($tracked_order['status'] == 'completed' ? '#155724' : '#721c24')) ?>;">
                                <?= strtoupper($tracked_order['status']) ?>
                            </span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 5px;">Order Date</div>
                                <div style="font-weight: 600;"><?= date('F j, Y', strtotime($tracked_order['created_at'])) ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);"><?= date('g:i A', strtotime($tracked_order['created_at'])) ?></div>
                            </div>
                            <div>
                                <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 5px;">Total Amount</div>
                                <div style="font-weight: 700; font-size: 24px; color: var(--primary-color);">৳<?= number_format($tracked_order['total_amount'], 2) ?></div>
                            </div>
                        </div>

                        <!-- Progress Tracker -->
                        <div class="progress-steps">
                            <?php
                            $steps = ['pending' => 'Order Placed', 'processing' => 'Processing', 'completed' => 'Shipped', 'delivered' => 'Delivered'];
                            $completed = true;
                            foreach ($steps as $status => $label):
                                $is_completed = $completed && in_array($tracked_order['status'], ['pending', 'processing', 'completed', 'delivered']) && 
                                                array_search($tracked_order['status'], array_keys($steps)) >= array_search($status, array_keys($steps));
                                if ($status == $tracked_order['status']) $completed = false;
                            ?>
                                <div class="progress-step <?= $is_completed ? 'completed' : '' ?> <?= $status == $tracked_order['status'] ? 'active' : '' ?>">
                                    <div class="step-icon"><?= $is_completed ? '✓' : ($status == $tracked_order['status'] ? '●' : '○') ?></div>
                                    <div class="step-label"><?= $label ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 25px; display: flex; gap: 15px;">
                            <a href="user/order_detail.php?id=<?= $tracked_order['id'] ?>" class="btn btn-primary" style="flex: 1; padding: 12px;">
                                📋 View Full Details
                            </a>
                            <button onclick="switchTab('track'); clearTrackedOrder();" class="btn btn-outline" style="flex: 0.5;">
                                🔍 Track Another
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Forgot Password Form -->
                <div id="forgot-tab" class="tab-pane <?= $active_tab == 'forgot' ? 'active' : '' ?>">
                    <div class="form-header">
                        <h2>🔑 Reset Password</h2>
                        <p>Enter your email address to receive password reset instructions</p>
                    </div>

                    <form method="POST" action="account.php?tab=forgot">
                        <div class="form-group">
                            <label>
                                <i>📧</i> Email Address
                            </label>
                            <div class="input-group">
                                <span class="input-icon">📧</span>
                                <input type="email" name="reset_email" class="form-control" 
                                       placeholder="Enter your registered email" required>
                            </div>
                        </div>

                        <button type="submit" name="reset_password" class="btn btn-primary btn-block">
                            📧 Send Reset Link
                        </button>

                        <div style="text-align: center; margin-top: 25px;">
                            <a href="#" onclick="switchTab('login'); return false;" 
                               style="color: var(--text-muted); text-decoration: none;">
                                ← Back to Login
                            </a>
                        </div>
                    </form>

                    <div style="margin-top: 30px; padding: 20px; background: var(--light-bg); border-radius: 12px;">
                        <h4 style="margin-bottom: 10px;">❓ Forgot your email?</h4>
                        <p style="color: var(--text-muted); font-size: 14px;">
                            Please contact our customer support at <strong>support@harotey.com</strong> 
                            or call <strong>+880 1234-567890</strong> for assistance.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; color: rgba(255,255,255,0.8); font-size: 14px;">
            <p>
                <a href="index.php" style="color: white; text-decoration: none; margin: 0 10px;">🏠 Home</a> |
                <a href="shop.php" style="color: white; text-decoration: none; margin: 0 10px;">🛍️ Shop</a> |
                <a href="contact.php" style="color: white; text-decoration: none; margin: 0 10px;">📞 Contact</a> |
                <a href="about.php" style="color: white; text-decoration: none; margin: 0 10px;">📖 About</a>
            </p>
            <p style="margin-top: 10px;">© 2024 Harotey Shop. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // Show selected tab pane
            if (tabName === 'track-result' && !document.getElementById('track-result-tab')) {
                tabName = 'track';
            }
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update active tab button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Find and activate the correct tab button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                if (btn.textContent.includes(tabName === 'track-result' ? 'Track' : 
                    tabName.charAt(0).toUpperCase() + tabName.slice(1))) {
                    btn.classList.add('active');
                }
            });
            
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // Clear tracked order from session
        function clearTrackedOrder() {
            fetch('clear_tracked_order.php', { method: 'POST' });
        }

        // Password Strength Checker
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('reg_password');
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strengthBar = document.getElementById('strengthBar');
                    const strengthText = document.getElementById('strengthText');
                    
                    let strength = 0;
                    
                    if (password.length >= 6) strength += 1;
                    if (password.length >= 8) strength += 1;
                    if (password.match(/[a-z]/)) strength += 1;
                    if (password.match(/[A-Z]/)) strength += 1;
                    if (password.match(/[0-9]/)) strength += 1;
                    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                    
                    const percentage = (strength / 6) * 100;
                    strengthBar.style.width = percentage + '%';
                    
                    if (strength <= 2) {
                        strengthBar.style.background = '#dc3545';
                        strengthText.textContent = 'Weak password';
                        strengthText.style.color = '#dc3545';
                    } else if (strength <= 4) {
                        strengthBar.style.background = '#ffc107';
                        strengthText.textContent = 'Medium password';
                        strengthText.style.color = '#856404';
                    } else {
                        strengthBar.style.background = '#28a745';
                        strengthText.textContent = 'Strong password';
                        strengthText.style.color = '#155724';
                    }
                });
            }

            // Password match checker
            const confirmInput = document.getElementById('reg_confirm_password');
            if (confirmInput) {
                confirmInput.addEventListener('input', function() {
                    const password = document.getElementById('reg_password').value;
                    const confirm = this.value;
                    
                    if (confirm && password !== confirm) {
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.style.borderColor = '#28a745';
                    }
                });
            }
        });

        // Auto-show tab based on URL parameter
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                switchTab(tab);
            }
        });
    </script>
</body>
</html>