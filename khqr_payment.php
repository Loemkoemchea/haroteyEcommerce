<?php
session_start();
include 'db.php';

if (!isset($_SESSION['khqr_order'])) {
    header("Location: index.php");
    exit;
}

$order_data = $_SESSION['khqr_order'];
$order_id = $order_data['order_id'];
$amount = $order_data['amount']; // assume USD

// Verify order belongs to current user
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    unset($_SESSION['khqr_order']);
    die("Order not found.");
}

// ---------- BAKONG CONFIGURATION ----------
$bakong_account = 'mkoemchea_loem@acleda';   // Your Bakong ID
$dev_token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkYXRhIjp7ImlkIjoiNzc1YjQ4NjgzZjY0NGVhMiJ9LCJpYXQiOjE3NzEwMzUzOTIsImV4cCI6MTc3ODgxMTM5Mn0.F8_KCHDgqkgHJcl7ZCTSkBoDwJCU9N976qDPwEt0GEY';

// Environment selection (uncomment one)
// $base_url = "https://sit-api-bakong.nbc.gov.kh"; // sandbox
$base_url = "https://api-bakong.nbc.gov.kh"; // production

$api_url = $base_url . "/v1/khqr/generate";

// Prepare payload
$khqr_data = [
    'bakongAccountID' => $bakong_account,
    'merchantName'    => 'Harotey Shop',
    'merchantCity'    => 'Siem Reap',
    'amount'          => (int) round($amount * 4100), // Convert to KHR integer
    'currency'        => 'KHR',
    'billNumber'      => (string)$order_id,
    'storeLabel'      => 'Harotey Shop',
    'terminalLabel'   => 'Web Payment',
    'expiryTime'      => time() + 600, // 10 minutes
];

// Headers
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $dev_token,
    'User-Agent: HaroteyShop/1.0'
];

// cURL request
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($khqr_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Handle errors
if ($http_code != 200) {
    error_log("KHQR API error: HTTP $http_code - Response: $response - cURL: $curl_error");
    echo "<h3>❌ Bakong API Error</h3>";
    echo "<p><strong>HTTP Code:</strong> $http_code</p>";
    echo "<p><strong>cURL Error:</strong> " . htmlspecialchars($curl_error) . "</p>";
    echo "<p><strong>Response:</strong> <pre>" . htmlspecialchars($response) . "</pre></p>";
    echo "<p>Check your token, account ID, and environment (sandbox vs production).</p>";
    exit;
}

$qr_data = json_decode($response, true);
if (!isset($qr_data['qrImage'])) {
    error_log("KHQR API invalid response: " . $response);
    echo "<h3>❌ Invalid API Response</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    exit;
}

$qr_image_base64 = $qr_data['qrImage'];
$md5_hash = $qr_data['md5Hash'] ?? null;
if (!$md5_hash) {
    die("No MD5 hash returned. Cannot verify payment.");
}
$_SESSION['khqr_md5'] = $md5_hash;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan KHQR to Pay - Harotey Shop</title>
    <style>
        .qr-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .qr-image {
            width: 300px;
            height: 300px;
            margin: 20px auto;
            border: 2px solid #28a745;
            border-radius: 12px;
            padding: 10px;
        }
        .amount {
            font-size: 24px;
            color: #28a745;
            font-weight: bold;
        }
        .timer {
            color: #dc3545;
            margin: 15px 0;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #28a745;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="qr-container">
        <h1>📱 Scan with KHQR App</h1>
        <p>Use any banking app (ABA, Bakong, ACLEDA) to scan this code</p>
        
        <?php if ($qr_image_base64): ?>
            <div class="qr-image">
                <img src="data:image/png;base64,<?= htmlspecialchars($qr_image_base64) ?>" width="100%">
            </div>
        <?php else: ?>
            <p>QR code generation failed. Please try again.</p>
        <?php endif; ?>
        
        <p class="amount">Amount: <?= number_format($amount * 4100) ?> KHR</p>
        <p>Order #: <?= $order_id ?></p>
        <p class="timer">⏱️ This code expires in 10 minutes</p>
        
        <div class="loader"></div>
        <p id="status">Waiting for payment...</p>
        
        <p><a href="cancel_order.php?id=<?= $order_id ?>" style="color: #666;">← Cancel and choose another method</a></p>
    </div>

    <script>
        const orderId = <?= $order_id ?>;
        
        function checkPayment() {
            fetch('verify_khqr.php?order=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('status').innerHTML = '✅ Payment successful! Redirecting...';
                        setTimeout(() => {
                            window.location.href = 'order_confirmation.php?id=' + orderId;
                        }, 2000);
                    } else {
                        console.log('Not paid yet');
                    }
                })
                .catch(err => console.error('Error checking payment', err));
        }
        
        setInterval(checkPayment, 3000);
    </script>
</body>
</html>