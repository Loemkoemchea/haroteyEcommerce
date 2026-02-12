<?php
include 'db.php';

$username = 'admin3';
$password_attempt = 'admin123';

$stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    $hash = $user['password'];
    echo "Hash length: " . strlen($hash) . "<br>";
    echo "Hash hex: " . bin2hex($hash) . "<br>";
    if (password_verify($password_attempt, $hash)) {
        echo "<span style='color:green;font-weight:bold'>✅ Password WORKS! You can login.</span>";
    } else {
        echo "<span style='color:red;font-weight:bold'>❌ Password FAILED. Hash is corrupted.</span>";
    }
} else {
    echo "❌ User not found.";
}
?>