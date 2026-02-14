<?php
session_start();
include 'db.php';

// Enable error reporting (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check pending order
if (!isset($_SESSION['bakong_pending'])) {
    header("Location: index.php");
    exit;
}

$order_data = $_SESSION['bakong_pending'];
$order_id = $order_data['order_id'];
$amount = (float) $order_data['amount']; // USD

// Verify order belongs to user
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    unset($_SESSION['bakong_pending']);
    die("Order not found.");
}

// ---------- BAKONG API CONFIGURATION ----------
$dev_token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkYXRhIjp7ImlkIjoiNzc1YjQ4NjgzZjY0NGVhMiJ9LCJpYXQiOjE3NzEwMzUzOTIsImV4cCI6MTc3ODgxMTM5Mn0.F8_KCHDgqkgHJcl7ZCTSkBoDwJCU9N976qDPwEt0GEY';
$bakong_account = 'mkoemchea_loem@acleda'; // Your verified account ID

// Choose environment – use sandbox if your token is for sandbox
$base_url = "https://api-bakong.nbc.gov.kh"; // production
// $base_url = "https://sit-api-bakong.nbc.gov.kh"; // sandbox (uncomment if needed)

$api_url = $base_url . "/v1/khqr/generate";

// Prepare payload – all fields required by Bakong
$amount_khr = (int) round($amount * 4100); // convert USD to KHR integer
$payload = [
    'bakongAccountID' => $bakong_account,
    'merchantName'    => 'Harotey Shop',
    'merchantCity'    => 'Siem Reap',
    'amount'          => $amount_khr,
    'currency'        => 'KHR',
    'billNumber'      => (string)$order_id,
    'storeLabel'      => 'Harotey Shop',
    'terminalLabel'   => 'Web Payment',
    'expiryTime'      => time() + 600, // 10 minutes from now
];

$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $dev_token,
    'User-Agent: HaroteyShop/1.0'
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code != 200) {
    // Detailed error for debugging
    echo "<h3>❌ Bakong API Error</h3>";
    echo "<p><strong>HTTP Code:</strong> $http_code</p>";
    echo "<p><strong>cURL Error:</strong> " . htmlspecialchars($curl_error) . "</p>";
    echo "<p><strong>Response:</strong> <pre>" . htmlspecialchars($response) . "</pre></p>";
    exit;
}

$result = json_decode($response, true);
if (!isset($result['qrImage'])) {
    echo "<h3>❌ Invalid API Response</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    exit;
}

$qr_image_base64 = $result['qrImage'];
$md5_hash = $result['md5Hash'] ?? null;
if (!$md5_hash) {
    die("No MD5 hash returned. Cannot verify payment.");
}
$_SESSION['bakong_md5'] = $md5_hash;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan KHQR to Pay - Harotey Shop</title>
    <style>
        .qr-container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .qr-image { width: 300px; height: 300px; margin: 20px auto; }
        .amount { font-size: 24px; color: #28a745; font-weight: bold; }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #28a745; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .debug { margin-top: 20px; padding: 10px; background: #f8f9fa; font-size: 12px; text-align: left; }
    </style>
</head>
<body>
    <div class="qr-container">
        <h1>📱 Scan with Bakong/ABA App</h1>
        <p>Use any banking app to scan this code</p>
        
        <div class="qr-image">
            <img src="data:image/png;base64,<?= htmlspecialchars($qr_image_base64) ?>" width="100%">
        </div>
        
        <p class="amount">Amount: <?= number_format($amount_khr) ?> KHR</p>
        <p>Order #: <?= $order_id ?></p>
        
        <div class="loader"></div>
        <p id="status">Waiting for payment...</p>
        
        <p><a href="cancel_order.php?id=<?= $order_id ?>" style="color: #666;">← Cancel</a></p>
        
        <!-- Debug info – remove in production -->
        <div class="debug">
            <strong>MD5:</strong> <?= htmlspecialchars($md5_hash) ?><br>
        </div>
    </div>

    <script>
        const orderId = <?= $order_id ?>;
        
        function checkPayment() {
            fetch('check_bakong_payment.php?order=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('status').innerHTML = '✅ Payment successful! Redirecting...';
                        setTimeout(() => {
                            window.location.href = 'order_confirmation.php?id=' + orderId;
                        }, 2000);
                    }
                })
                .catch(err => console.error('Error:', err));
        }
        
        setInterval(checkPayment, 3000);
    </script>
</body>
</html>