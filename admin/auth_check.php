<?php
// file: admin/auth_check.php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Not logged in or not admin – redirect to unified login
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header("Location: ../login.php?redirect=$redirect");
    exit;
}

include '../db.php';

// Optional: fetch fresh user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' AND status = 'active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    // User is no longer admin or inactive – destroy session
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// For backward compatibility, set old admin session vars
$_SESSION['admin'] = $user_id;
$_SESSION['admin_username'] = $user['username'];
?>