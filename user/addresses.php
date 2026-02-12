<?php
// file: user/addresses.php (DEBUG VERSION – REMOVE ERROR DISPLAY AFTER FIX)
require_once 'auth_check.php';

// Turn on full error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ------------------------------------------------------------
// VERIFY TABLE EXISTS – if not, create it automatically
// ------------------------------------------------------------
$conn->query("
    CREATE TABLE IF NOT EXISTS `user_addresses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `address_type` ENUM('shipping', 'billing', 'both') DEFAULT 'both',
        `full_name` VARCHAR(100),
        `phone` VARCHAR(20),
        `address_line1` VARCHAR(255) NOT NULL,
        `address_line2` VARCHAR(255),
        `city` VARCHAR(100) NOT NULL,
        `state` VARCHAR(100),
        `postal_code` VARCHAR(20),
        `country` VARCHAR(100) DEFAULT 'Bangladesh',
        `is_default` BOOLEAN DEFAULT FALSE,
        `delivery_instructions` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$message = '';
$error   = '';

// ------------------------------------------------------------
// SAFE PREPARE WRAPPER – shows MySQL error if prepare fails
// ------------------------------------------------------------
function safePrepare($conn, $sql) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("❌ MySQL prepare error: " . $conn->error . "<br>SQL: " . $sql);
    }
    return $stmt;
}

// ------------------------------------------------------------
// HANDLE ACTIONS
// ------------------------------------------------------------
if (isset($_GET['set_default'])) {
    $address_id = (int)$_GET['set_default'];
    
    // Verify address belongs to user
    $check = safePrepare($conn, "SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $address_id, $user_id);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        // Remove default from all
        $reset = safePrepare($conn, "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $reset->bind_param("i", $user_id);
        $reset->execute();
        $reset->close();
        // Set new default
        $set = safePrepare($conn, "UPDATE user_addresses SET is_default = 1 WHERE id = ?");
        $set->bind_param("i", $address_id);
        $set->execute();
        $set->close();
        $_SESSION['message'] = "Default address updated.";
    }
    $check->close();
    header("Location: addresses.php");
    exit;
}

if (isset($_GET['delete'])) {
    $address_id = (int)$_GET['delete'];
    $stmt = safePrepare($conn, "DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Address deleted.";
    } else {
        $_SESSION['error'] = "Address not found.";
    }
    $stmt->close();
    header("Location: addresses.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name      = trim($_POST['full_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $address_line1  = trim($_POST['address_line1'] ?? '');
    $address_line2  = trim($_POST['address_line2'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $postal_code    = trim($_POST['postal_code'] ?? '');
    $country        = trim($_POST['country'] ?? 'Bangladesh');
    $is_default     = isset($_POST['is_default']) ? 1 : 0;
    $address_type   = 'both';
    $address_id     = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;

    $errors = [];
    if (empty($full_name)) $errors[] = "Recipient name is required.";
    if (empty($address_line1)) $errors[] = "Address line 1 is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";

    if (empty($errors)) {
        if ($address_id > 0) {
            // UPDATE
            $sql = "UPDATE user_addresses SET
                        full_name = ?, phone = ?, address_line1 = ?, address_line2 = ?,
                        city = ?, postal_code = ?, country = ?, address_type = ?
                    WHERE id = ? AND user_id = ?";
            $stmt = safePrepare($conn, $sql);
            $stmt->bind_param(
                "ssssssssii",
                $full_name, $phone, $address_line1, $address_line2,
                $city, $postal_code, $country, $address_type,
                $address_id, $user_id
            );
            if ($stmt->execute()) {
                $_SESSION['message'] = "Address updated.";
                if ($is_default) {
                    $reset = safePrepare($conn, "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                    $reset->bind_param("i", $user_id);
                    $reset->execute();
                    $reset->close();
                    $set = safePrepare($conn, "UPDATE user_addresses SET is_default = 1 WHERE id = ?");
                    $set->bind_param("i", $address_id);
                    $set->execute();
                    $set->close();
                }
            }
            $stmt->close();
        } else {
            // INSERT
            // Check if first address
            $count_stmt = safePrepare($conn, "SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $count_stmt->bind_result($cnt);
            $count_stmt->fetch();
            $count_stmt->close();
            $is_first = ($cnt == 0);
            if ($is_first) $is_default = 1;

            $sql = "INSERT INTO user_addresses
                    (user_id, address_type, full_name, phone, address_line1, address_line2,
                     city, postal_code, country, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = safePrepare($conn, $sql);
            $stmt->bind_param(
                "issssssssi",
                $user_id, $address_type, $full_name, $phone, $address_line1, $address_line2,
                $city, $postal_code, $country, $is_default
            );
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                $_SESSION['message'] = "Address added.";
                if ($is_default) {
                    $reset = safePrepare($conn, "UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND id != ?");
                    $reset->bind_param("ii", $user_id, $new_id);
                    $reset->execute();
                    $reset->close();
                }
            }
            $stmt->close();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST;
    }
    header("Location: addresses.php");
    exit;
}

// ------------------------------------------------------------
// FETCH ADDRESSES FOR DISPLAY
// ------------------------------------------------------------
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$addresses_result = null;
$addr_stmt = safePrepare($conn, "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addresses = $addr_stmt->get_result();

// For editing
$edit_address = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_stmt = safePrepare($conn, "SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $edit_stmt->bind_param("ii", $edit_id, $user_id);
    $edit_stmt->execute();
    $edit_address = $edit_stmt->get_result()->fetch_assoc();
    $edit_stmt->close();
}

$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>​
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Addresses - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .addresses-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .address-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 20px;
            position: relative;
            border: 1px solid #eee;
        }
        .address-card.default {
            border: 2px solid #28a745;
            background: #f0fff4;
        }
        .default-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .address-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .address-detail {
            margin-bottom: 5px;
            color: #555;
            line-height: 1.5;
        }
        .address-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background: #218838;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-outline {
            background: white;
            border: 1px solid #28a745;
            color: #28a745;
        }
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 25px;
            margin-top: 30px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
        }
        .form-group input:focus {
            border-color: #28a745;
            outline: none;
        }
        .full-width {
            grid-column: span 2;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-group input {
            width: auto;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="addresses-container">
        <div class="header">
            <h1>📮 Manage Your Addresses</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Address List -->
        <?php if ($addresses->num_rows > 0): ?>
            <h2>Your Saved Addresses</h2>
            <div class="address-grid">
                <?php while ($addr = $addresses->fetch_assoc()): ?>
                    <div class="address-card <?= $addr['is_default'] ? 'default' : '' ?>">
                        <?php if ($addr['is_default']): ?>
                            <span class="default-badge">✓ DEFAULT</span>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($addr['full_name'] ?? $user['full_name'] ?? 'Recipient') ?></h3>
                        <div class="address-detail">
                            <?= htmlspecialchars($addr['address_line1']) ?><br>
                            <?= $addr['address_line2'] ? htmlspecialchars($addr['address_line2']) . '<br>' : '' ?>
                            <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['postal_code'] ?? '') ?><br>
                            <?= htmlspecialchars($addr['country']) ?><br>
                            <strong>Phone:</strong> <?= htmlspecialchars($addr['phone']) ?>
                        </div>
                        <div class="address-actions">
                            <a href="?edit=<?= $addr['id'] ?>" class="btn btn-warning">✏️ Edit</a>
                            <?php if (!$addr['is_default']): ?>
                                <a href="?set_default=<?= $addr['id'] ?>" class="btn btn-outline">⭐ Set Default</a>
                            <?php endif; ?>
                            <a href="?delete=<?= $addr['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this address?')">🗑️ Delete</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 60px; margin-bottom: 20px;">📍</div>
                <h2>No addresses saved yet</h2>
                <p style="color: #666; margin-bottom: 25px;">Add your first shipping address below.</p>
            </div>
        <?php endif; ?>

        <!-- Add / Edit Address Form -->
        <div class="form-card">
            <h2><?= $edit_address ? '✏️ Edit Address' : '➕ Add New Address' ?></h2>
            <form method="POST" action="addresses.php">
                <?php if ($edit_address): ?>
                    <input type="hidden" name="address_id" value="<?= $edit_address['id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name (Recipient) *</label>
                        <input type="text" id="full_name" name="full_name" required
                               value="<?= htmlspecialchars($edit_address['full_name'] ?? $form_data['full_name'] ?? $user['full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required
                               value="<?= htmlspecialchars($edit_address['phone'] ?? $form_data['phone'] ?? $user['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="address_line1">Address Line 1 *</label>
                        <input type="text" id="address_line1" name="address_line1" required
                               value="<?= htmlspecialchars($edit_address['address_line1'] ?? $form_data['address_line1'] ?? '') ?>"
                               placeholder="House number, street name">
                    </div>
                    <div class="form-group full-width">
                        <label for="address_line2">Address Line 2 (Optional)</label>
                        <input type="text" id="address_line2" name="address_line2"
                               value="<?= htmlspecialchars($edit_address['address_line2'] ?? $form_data['address_line2'] ?? '') ?>"
                               placeholder="Apartment, suite, unit, etc.">
                    </div>
                    <div class="form-group">
                        <label for="city">City *</label>
                        <input type="text" id="city" name="city" required
                               value="<?= htmlspecialchars($edit_address['city'] ?? $form_data['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code"
                               value="<?= htmlspecialchars($edit_address['postal_code'] ?? $form_data['postal_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country"
                               value="<?= htmlspecialchars($edit_address['country'] ?? $form_data['country'] ?? 'Bangladesh') ?>">
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_default" name="is_default"
                               <?= ($edit_address && $edit_address['is_default']) || (!$edit_address && ($addresses->num_rows == 0 || isset($form_data['is_default']))) ? 'checked' : '' ?>>
                        <label for="is_default" style="font-weight: normal;">Set as default address</label>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_address ? '✏️ Update Address' : '➕ Save Address' ?>
                    </button>
                    <?php if ($edit_address): ?>
                        <a href="addresses.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <p style="text-align: center; margin-top: 30px;">
            <a href="profile.php" style="color: #28a745;">← Back to Profile</a>
        </p>
    </div>
</body>
</html>