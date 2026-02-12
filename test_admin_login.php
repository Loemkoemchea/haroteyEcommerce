<?php
include 'db.php';

$email = 'admin_fresh@example.com';  // ← change to YOUR admin email
$password_attempt = 'admin123'; // ← change to YOUR password

// Fetch user
$stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("❌ User not found.");
}

echo "✅ User found: ID {$user['id']}, Username: {$user['username']}, Role: {$user['role']}, Status: {$user['status']}<br>";
echo "Stored password hash: " . $user['password'] . "<br>";
echo "Hash length: " . strlen($user['password']) . " characters<br>";

if (password_verify($password_attempt, $user['password'])) {
    echo "<span style='color:green;font-weight:bold'>✅ Password CORRECT – login should work.</span>";
} else {
    echo "<span style='color:red;font-weight:bold'>❌ Password WRONG – verification failed.</span>";
}
?>