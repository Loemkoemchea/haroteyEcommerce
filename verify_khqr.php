<?php
session_start();
include 'db.php';

$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;
if (!$order_id) {
    echo json_encode(['success' => false]);
    exit;
}

// Get MD5 hash from session
if (!isset($_SESSION['khqr_md5'])) {
    echo json_encode(['success' => false, 'error' => 'No MD5 hash']);
    exit;
}
$md5_hash = $_SESSION['khqr_md5'];

// ---------- BAKONG VERIFICATION ----------
$dev_token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkYXRhIjp7ImlkIjoiNzc1YjQ4NjgzZjY0NGVhMiJ9LCJpYXQiOjE3NzEwMzUzOTIsImV4cCI6MTc3ODgxMTM5Mn0.F8_KCHDgqkgHJcl7ZCTSkBoDwJCU9N976qDPwEt0GEY'; // same token

// Same environment as in khqr_payment.php
// $base_url = "https://sit-api-bakong.nbc.gov.kh"; // sandbox
$base_url = "https://api-bakong.nbc.gov.kh"; // production

$api_url = $base_url . "/v1/khqr/check_transaction";

$payload = ['md5' => $md5_hash];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $dev_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $result = json_decode($response, true);
    // Check for success – adjust based on actual API response
    // Expected success response might contain "responseCode":0 or "status":"PAID"
    if (isset($result['status']) && $result['status'] === 'PAID') {
        // Update order status
        $conn->query("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = $order_id");
        // Clear session
        unset($_SESSION['khqr_order'], $_SESSION['khqr_md5']);
        // Send Telegram notification
        sendTelegramPaymentNotification($order_id);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);

// ---------- TELEGRAM NOTIFICATION FUNCTION ----------
function sendTelegramPaymentNotification($order_id) {
    $botToken = "8581941364:AAGS9iL46AWJ3Bfa0TVPnn9RrLNihJ8eubY";
    $chatID   = "1299806559";

    $message = "✅ *Payment Received*\n";
    $message .= "Order #{$order_id}\n";
    $message .= "Method: KHQR\n";
    $message .= "Status: Paid";

    $ch = curl_init("https://api.telegram.org/bot$botToken/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatID,
        'text'    => $message,
        'parse_mode' => 'Markdown'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}
?>