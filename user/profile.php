<?php
// file: user/profile.php
require_once 'auth_check.php';

// =============================================
// 1. HANDLE PROFILE UPDATE (with image upload)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if (empty($full_name)) {
        $_SESSION['error'] = "Full name cannot be empty.";
        header("Location: profile.php");
        exit;
    }

    $conn->begin_transaction();
    try {
        // Update basic info
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $phone, $user_id);
        $stmt->execute();
        $stmt->close();

        // ---------- PROFILE IMAGE UPLOAD ----------
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];

            // Validate file type
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                throw new Exception("Only JPG, JPEG, PNG, GIF, WEBP files are allowed.");
            }

            // Validate file size (max 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception("File size must be less than 2MB.");
            }

            // Create upload directory if not exists
            $upload_dir = '../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory.");
                }
            }

            // Ensure directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception("Upload directory is not writable.");
            }

            // Generate unique filename
            $new_filename = uniqid() . '_' . time() . '.' . $ext;
            $target_path = $upload_dir . $new_filename;
            $relative_path = 'uploads/profiles/' . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Get old image to delete later
                $old_image = $user['profile_image'] ?? 'default-avatar.png';

                // Update database with new image path
                $img_stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $img_stmt->bind_param("si", $relative_path, $user_id);
                $img_stmt->execute();
                $img_stmt->close();

                // Delete old image if not default
                if ($old_image !== 'default-avatar.png' && file_exists('../' . $old_image)) {
                    unlink('../' . $old_image);
                }

                // Update session
                $_SESSION['profile_image'] = $relative_path;
                $_SESSION['upload_success'] = "Uploaded: " . $new_filename;
            } else {
                throw new Exception("Failed to move uploaded file.");
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Profile updated successfully.";
        $_SESSION['user_name'] = $full_name;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header("Location: profile.php");
    exit;
}

// =============================================
// 2. HANDLE PASSWORD CHANGE
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (!password_verify($current, $user['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    if (strlen($new) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    }
    if ($new !== $confirm) {
        $errors[] = "New passwords do not match.";
    }

    if (empty($errors)) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Password changed successfully.";
        } else {
            $_SESSION['error'] = "Failed to change password.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    header("Location: profile.php");
    exit;
}

// =============================================
// 3. AUTO-FIX MISSING IMAGE FILES
// =============================================
$profile_image = $user['profile_image'] ?? 'default-avatar.png';
if ($profile_image !== 'default-avatar.png') {
    $full_path = '../' . $profile_image;
    if (!file_exists($full_path) || is_dir($full_path)) {
        // File missing – reset to default avatar
        $stmt = $conn->prepare("UPDATE users SET profile_image = 'default-avatar.png' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $profile_image = 'default-avatar.png';
        $_SESSION['info'] = "Profile image file was missing – reset to default.";
    }
}

// =============================================
// 4. RETRIEVE SESSION MESSAGES
// =============================================
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
$info    = $_SESSION['info'] ?? '';
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['info'], $_SESSION['upload_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .profile-container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .profile-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .profile-header h1 { margin: 0; color: #333; }
        .profile-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 30px; }
        .profile-card h2 { margin-top: 0; margin-bottom: 25px; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .avatar-section { display: flex; align-items: center; gap: 30px; margin-bottom: 30px; flex-wrap: wrap; }
        .current-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #28a745; background: #f8f9fa; }
        .avatar-placeholder { width: 120px; height: 120px; border-radius: 50%; background: #28a745; color: white; display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: bold; border: 3px solid #28a745; }
        .avatar-info { flex: 1; }
        .avatar-info h3 { margin: 0 0 5px 0; color: #333; }
        .avatar-info p { color: #666; margin-bottom: 15px; }
        .file-input-wrapper { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .btn-link { background: none; color: #28a745; border: 2px solid #28a745; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-link:hover { background: #28a745; color: white; }
        .btn-link-danger { border-color: #dc3545; color: #dc3545; }
        .btn-link-danger:hover { background: #dc3545; color: white; }
        .file-name { color: #666; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="tel"], .form-group input[type="password"] { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: border 0.2s; }
        .form-group input:focus { border-color: #28a745; outline: none; box-shadow: 0 0 0 3px rgba(40,167,69,0.1); }
        .form-group input[readonly] { background: #f8f9fa; cursor: not-allowed; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.2s; }
        .btn-primary { background: #28a745; color: white; }
        .btn-primary:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        hr { margin: 30px 0; border: none; border-top: 1px solid #eee; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .avatar-section { flex-direction: column; text-align: center; }
            .file-input-wrapper { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h1>👤 My Profile</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="alert alert-info"><?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <!-- Profile Information Form -->
        <div class="profile-card">
            <h2>📋 Profile Information</h2>
            <form method="POST" enctype="multipart/form-data">
                <!-- Avatar Section with Preview -->
                <div class="avatar-section">
                    <div id="avatar-preview-container">
                        <?php if ($profile_image === 'default-avatar.png'): ?>
                            <div class="avatar-placeholder" id="avatar-preview">
                                <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                            </div>
                        <?php else: ?>
                            <!-- ✅ FIXED: prepend ../ to stored path -->
                            <img src="<?= htmlspecialchars('../' . $profile_image) ?>" alt="Avatar" class="current-avatar" id="avatar-preview">
                        <?php endif; ?>
                    </div>
                    <div class="avatar-info">
                        <h3><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h3>
                        <p>Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
                        <div class="file-input-wrapper">
                            <label for="profile_image" class="btn-link">📸 Change Photo</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(event); updateFileName(this);">
                            <span class="file-name" id="file-name">No file chosen</span>
                        </div>
                        <?php if ($profile_image !== 'default-avatar.png'): ?>
                            <div style="margin-top: 10px;">
                                <a href="remove-avatar.php" class="btn-link btn-link-danger" onclick="return confirm('Remove profile picture?')">🗑️ Remove Photo</a>
                            </div>
                        <?php endif; ?>
                        <small style="color: #666; display: block; margin-top: 8px;">Allowed: JPG, PNG, GIF, WEBP. Max 2MB.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name"
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                               placeholder="01XXXXXXXXX">
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">
                    💾 Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="profile-card">
            <h2>🔐 Change Password</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                        <small style="color: #666;">Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">
                    🔑 Update Password
                </button>
            </form>
        </div>

        <!-- Address Management -->
        <div class="profile-card">
            <h2>📮 Address Book</h2>
            <p style="color: #666; margin-bottom: 20px;">Manage your shipping and billing addresses.</p>
            <a href="addresses.php" class="btn-link" style="display: inline-block;">📮 Manage Addresses →</a>
        </div>

        <!-- Account Actions -->
        <div style="text-align: center; margin-top: 20px;">
            <a href="orders.php" style="color: #28a745; text-decoration: none; margin: 0 10px;">📦 My Orders</a>
            <a href="wishlist.php" style="color: #28a745; text-decoration: none; margin: 0 10px;">❤️ Wishlist</a>
            <a href="logout.php" style="color: #dc3545; text-decoration: none; margin: 0 10px;" onclick="return confirm('Logout?')">🚪 Logout</a>
        </div>
    </div>

    <script>
        // Preview selected image before upload
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewContainer = document.getElementById('avatar-preview-container');
                    previewContainer.innerHTML = `<img src="${e.target.result}" alt="Avatar Preview" class="current-avatar" id="avatar-preview">`;
                }
                reader.readAsDataURL(file);
            }
        }

        // Update file name display
        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
        }
    </script>
</body>
</html>