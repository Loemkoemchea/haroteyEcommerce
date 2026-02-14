<?php
// telegram_bot_webhook.php
include 'db.php';

// Your bot token
$botToken = "8581941364:AAGS9iL46AWJ3Bfa0TVPnn9RrLNihJ8eubY";

// Get the incoming update from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    http_response_code(400);
    exit;
}

// Only process messages
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $from = $message['from'] ?? [];

    // Optional: Whitelist the ABA PayWay sender (you'll need to get its user ID)
    // $aba_sender_id = 123456789; // Replace with actual ID
    // if ($from['id'] != $aba_sender_id) {
    //     http_response_code(200);
    //     exit;
    // }

    // --- Parse the payment notification ---
    // We'll try to extract amount (starting with ៛) and transaction ID (after "លេខប្រតិបត្តិការ:")
    $amount = null;
    $tran_id = null;

    // 1. Extract amount: pattern like "៛100" (Khmer Riel symbol followed by digits)
    if (preg_match('/៛([\d,]+)/u', $text, $amount_match)) {
        $amount_str = str_replace(',', '', $amount_match[1]); // remove thousand separators
        $amount = (float) $amount_str;
    }

    // 2. Extract transaction ID: after "លេខប្រតិបត្តិការ:" (may include spaces or colons)
    if (preg_match('/លេខប្រតិបត្តិការ:\s*([^\s]+)/u', $text, $tran_match)) {
        $tran_id = trim($tran_match[1]);
    }

    // If we have both, try to find the order by tran_id
    if ($tran_id && $amount) {
        // Look up order by tran_id (you stored it during checkout)
        $stmt = $conn->prepare("SELECT id, total_amount FROM orders WHERE tran_id = ?");
        $stmt->bind_param("s", $tran_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($order) {
            if ($amount >= $order['total_amount']) {
                // Success
                $update_stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing', paid_amount = ?, paid_at = NOW() WHERE id = ?");
                $update_stmt->bind_param("di", $amount, $order['id']);
                $update_stmt->execute();
                $update_stmt->close();
                sendTelegramMessage($chat_id, "✅ Order #{$order['id']} marked as paid.");
            } else {
                // Underpayment
                $conn->query("UPDATE orders SET payment_status = 'failed', status = 'cancelled' WHERE id = {$order['id']}");
                sendTelegramMessage($chat_id, "❌ Underpayment for Order #{$order['id']}. Expected: {$order['total_amount']}, received: $amount");
            }
        } else {
            sendTelegramMessage($chat_id, "⚠️ No order found with transaction ID: $tran_id");
        }
    } else {
        // Could not parse – log for debugging (optional)
        // file_put_contents('unparsed_messages.log', $text . PHP_EOL, FILE_APPEND);
    }
}

http_response_code(200);

// Helper to send messages back to the group
function sendTelegramMessage($chat_id, $text) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}
?>