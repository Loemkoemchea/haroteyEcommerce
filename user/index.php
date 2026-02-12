

<?php
// file: user/index.php – Redirect to unified login
session_start();
if (isset($_SESSION['user_id'])) {
    // Already logged in – redirect to dashboard
    header("Location: dashboard.php");
    exit;
}
$redirect = isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '';
header("Location: ../login.php$redirect");
exit;