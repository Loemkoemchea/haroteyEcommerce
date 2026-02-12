<?php
// file: clear_tracked_order.php
session_start();
unset($_SESSION['tracked_order']);
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>