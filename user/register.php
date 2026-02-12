<?php
// file: user/register.php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

include '../db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
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
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, city) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $email, $hashed_password, $full_name, $phone, $address, $city);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Auto login
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $full_name ?: $username;
            $_SESSION['user_email'] = $email;
            
            $success = "Registration successful! Redirecting...";
            header("refresh:2;url=dashboard.php");
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
        .auth-container {
            max-width: 550px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-header h1 {
            color: #28a745;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input, 
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #28a745;
            outline: none;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.1);
        }
        .btn-register {
            width: 100%;
            padding: 14px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-register:hover {
            background: #218838;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #28a745;
        }
        .password-strength {
            margin-top: 8px;
            height: 5px;
            background: #e0e0e0;
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
            color: #666;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>🛍️ Harotey Shop</h1>
            <h2>Create Account</h2>
            <p>Join us for a better shopping experience</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="full_name">Full Name <span style="color: #666; font-weight: normal;">(Optional)</span></label>
                <input type="text" id="full_name" name="full_name" 
                       placeholder="Enter your full name"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Choose username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Minimum 6 characters">
                    <div class="password-strength">
                        <div id="strengthBar" class="strength-bar"></div>
                    </div>
                    <div id="strengthText" class="strength-text"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Re-enter password">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="01XXXXXXXXX"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="city">City</label>
                    <select id="city" name="city">
                        <option value="">Select City</option>
                        <option value="Dhaka" <?= ($_POST['city'] ?? '') == 'Dhaka' ? 'selected' : '' ?>>Dhaka</option>
                        <option value="Chittagong" <?= ($_POST['city'] ?? '') == 'Chittagong' ? 'selected' : '' ?>>Chittagong</option>
                        <option value="Khulna" <?= ($_POST['city'] ?? '') == 'Khulna' ? 'selected' : '' ?>>Khulna</option>
                        <option value="Rajshahi" <?= ($_POST['city'] ?? '') == 'Rajshahi' ? 'selected' : '' ?>>Rajshahi</option>
                        <option value="Sylhet" <?= ($_POST['city'] ?? '') == 'Sylhet' ? 'selected' : '' ?>>Sylhet</option>
                        <option value="Barisal" <?= ($_POST['city'] ?? '') == 'Barisal' ? 'selected' : '' ?>>Barisal</option>
                        <option value="Rangpur" <?= ($_POST['city'] ?? '') == 'Rangpur' ? 'selected' : '' ?>>Rangpur</option>
                        <option value="Mymensingh" <?= ($_POST['city'] ?? '') == 'Mymensingh' ? 'selected' : '' ?>>Mymensingh</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="address">Delivery Address</label>
                <textarea id="address" name="address" placeholder="House, Road, Area..."><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>

            <div class="form-check" style="margin-bottom: 25px;">
                <input type="checkbox" id="terms" name="terms" required <?= isset($_POST['terms']) ? 'checked' : '' ?>>
                <label for="terms" style="font-weight: normal;">
                    I agree to the <a href="#" style="color: #28a745;">Terms & Conditions</a> and 
                    <a href="#" style="color: #28a745;">Privacy Policy</a> *
                </label>
            </div>

            <button type="submit" class="btn-register">Create Account</button>

            <div class="auth-footer">
                <p>Already have an account? <a href="index.php">Login Here</a></p>
                <p style="margin-top: 10px;">
                    <a href="../index.php">← Continue Shopping</a>
                </p>
            </div>
        </form>
    </div>

    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
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

        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#28a745';
            }
        });
    </script>
</body>
</html>