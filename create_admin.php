<?php
// file: create_admin_fresh.php – Generate fresh hash, bypass corruption
session_start();
include 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ----- CONFIGURE YOUR NEW ADMIN HERE -----
$new_username = 'admin_fresh';
$new_email    = 'admin_fresh@example.com';
$new_fullname = 'Fresh Administrator';
$plain_password = 'admin123'; // will be hashed by PHP
// -----------------------------------------

// 1. Generate a brand new bcrypt hash (cost 10)
$fresh_hash = password_hash($plain_password, PASSWORD_DEFAULT);
if (!$fresh_hash) {
    die("❌ Failed to generate hash.");
}

// 2. Verify the hash immediately – must return true
if (!password_verify($plain_password, $fresh_hash)) {
    die("❌ Generated hash failed self-verification – PHP password_verify is broken!");
}
echo "✅ Self-verification passed.<br>";

// 3. Check if username/email already exists
$check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$check->bind_param("ss", $new_username, $new_email);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if ($exists) {
    die("❌ Username or email already exists.");
}

// 4. Detect existing columns
$columns = [];
$placeholders = [];
$types = '';
$values = [];

// Base required columns
$base_columns = [
    'username' => $new_username,
    'email' => $new_email,
    'password' => $fresh_hash, // ✅ fresh hash, not copied
    'full_name' => $new_fullname,
    'role' => 'admin',
    'status' => 'active',
    'email_verified' => 1,
    'created_at' => date('Y-m-d H:i:s')
];

// Optional columns that might still exist
$optional_columns = ['phone', 'address', 'city', 'state', 'postal_code', 'country'];
$optional_defaults = [
    'phone' => '',
    'address' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => 'Bangladesh'
];

// Get actual table columns
$table_info = $conn->query("DESCRIBE users");
$existing_columns = [];
while ($row = $table_info->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

// Build INSERT dynamically
foreach ($base_columns as $col => $val) {
    if (in_array($col, $existing_columns)) {
        $columns[] = "`$col`";
        $placeholders[] = "?";
        $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
        $values[] = $val;
    }
}

foreach ($optional_columns as $col) {
    if (in_array($col, $existing_columns)) {
        $columns[] = "`$col`";
        $placeholders[] = "?";
        $types .= 's';
        $values[] = $optional_defaults[$col] ?? '';
    }
}

// 5. Execute INSERT
$sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    echo "<h2 style='color:green;'>✅ Admin user created successfully!</h2>";
    echo "<p>ID: $new_id</p>";
    echo "<p>Username: $new_username</p>";
    echo "<p>Email: $new_email</p>";
    
    // 6. Verify the stored hash
    $verify = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $verify->bind_param("i", $new_id);
    $verify->execute();
    $stored_hash = $verify->get_result()->fetch_assoc()['password'];
    $verify->close();
    
    if (password_verify($plain_password, $stored_hash)) {
        echo "<p style='color:green;font-weight:bold;'>✅ Stored password verification: SUCCESS – login with password '<strong>$plain_password</strong>'.</p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>❌ Stored password verification FAILED.</p>";
        echo "<p>Stored hash hex: " . bin2hex($stored_hash) . "</p>";
        echo "<p>Fresh hash hex: " . bin2hex($fresh_hash) . "</p>";
    }
} else {
    echo "<h2 style='color:red;'>❌ Failed to create admin.</h2>";
    echo "<p>Error: " . $conn->error . "</p>";
}

$stmt->close();
$conn->close();
?>