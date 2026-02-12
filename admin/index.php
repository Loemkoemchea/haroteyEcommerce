

<?php
// file: admin/index.php – Redirect to unified login
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Already logged in as admin
    header("Location: dashboard.php");
    exit;
}
$redirect = isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '';
header("Location: ../login.php$redirect");
exit;