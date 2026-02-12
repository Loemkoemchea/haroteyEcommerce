<?php
// file: user/auth_check.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header("Location: ../login.php?redirect=$redirect");
    exit;
}

include '../db.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Optional: Restrict user pages to customers only (admins should use admin panel)
if ($user['role'] === 'admin') {
    // Redirect admin to admin dashboard
    header("Location: ../admin/dashboard.php");
    exit;
}

// For backward compatibility
$_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
$_SESSION['user_email'] = $user['email'];
?>