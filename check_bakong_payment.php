<?php
session_start();
include 'db.php';

$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;
if (!$order_id || !isset($_SESSION['bakong_md5'])) {
    echo json_encode(['success' => false]);
    exit;
}

$md5_hash = $_SESSION['bakong_md5'];

// ---------- BAKONG VERIFICATION ----------
$dev_token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkYXRhIjp7ImlkIjoiNzc1YjQ4NjgzZjY0NGVhMiJ9LCJpYXQiOjE3NzEwMzUzOTIsImV4cCI6MTc3ODgxMTM5Mn0.F8_KCHDgqkgHJcl7ZCTSkBoDwJCU9N976qDPwEt0GEY';
$base_url = "https://api-bakong.nbc.gov.kh"; // match environment used in generation
$api_url = $base_url . "/v1/khqr/check_transaction_by_md5";

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
    // Adjust the condition based on actual API response
    // Common success indicators: "status":"PAID" or "responseCode":0
    if (isset($result['status']) && $result['status'] === 'PAID') {
        // Update order
        $conn->query("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = $order_id");
        unset($_SESSION['bakong_pending'], $_SESSION['bakong_md5']);
        sendTelegramPaymentNotification($order_id);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);

function sendTelegramPaymentNotification($order_id) {
    $botToken = "8581941364:AAGS9iL46AWJ3Bfa0TVPnn9RrLNihJ8eubY";
    $chatID = "1299806559";
    $message = "✅ *Payment Received*\nOrder #{$order_id}\nMethod: Bakong KHQR\nStatus: Paid";
    $ch = curl_init("https://api.telegram.org/bot$botToken/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatID,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}
?>