<?php
// file: user/verify.php
session_start();
include '../db.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Invalid verification link.");
}

// Find user with this token and not expired
$stmt = $conn->prepare("
    SELECT id, email, verification_expires 
    FROM users 
    WHERE verification_token = ? AND email_verified = 0
");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "Invalid or expired verification link.";
    header("Location: login.php");
    exit;
}

// Check expiry
if (strtotime($user['verification_expires']) < time()) {
    $_SESSION['error'] = "Verification link has expired. Please request a new one.";
    header("Location: resend_verification.php?email=" . urlencode($user['email']));
    exit;
}

// Mark email as verified
$stmt = $conn->prepare("
    UPDATE users 
    SET email_verified = 1, verification_token = NULL, verification_expires = NULL 
    WHERE id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $_SESSION['success'] = "Your email has been verified! You can now log in.";
    header("Location: index.php");
} else {
    $_SESSION['error'] = "Verification failed. Please try again.";
    header("Location: login.php");
}
$stmt->close();
exit;