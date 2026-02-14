<?php
// file: order.php – LOGIN REQUIRED + KHQR + TELEGRAM NOTIFICATION
session_start();
include 'db.php';

// ---------- 1. AUTHENTICATION CHECK ----------
if (!isset($_SESSION['user_id'])) {
    header("Location: user/index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
$user_id = $_SESSION['user_id'];

// ---------- 2. VALIDATE CART ----------
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// ---------- 3. GET AND SANITIZE POST DATA ----------
$customer_name  = trim($_POST['customer_name'] ?? '');
$customer_email = trim($_POST['customer_email'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$payment_method = $_POST['payment_method'] ?? 'cash_on_delivery';
$notes          = trim($_POST['notes'] ?? '');

// ---------- 4. BUILD SHIPPING ADDRESS ----------
$use_saved = isset($_POST['use_saved_address']) && $_POST['use_saved_address'] == '1';
$shipping_address = '';

if ($use_saved && isset($_POST['address_id'])) {
    $addr_id = (int)$_POST['address_id'];
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addr_id, $user_id);
    $stmt->execute();
    $addr = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($addr) {
        $shipping_address = $addr['address_line1'];
        if (!empty($addr['address_line2'])) $shipping_address .= ', ' . $addr['address_line2'];
        $shipping_address .= ', ' . $addr['city'];
        if (!empty($addr['postal_code'])) $shipping_address .= ' - ' . $addr['postal_code'];
        $shipping_address .= ', ' . $addr['country'];
        if (!empty($addr['full_name'])) $customer_name = $addr['full_name'];
        if (!empty($addr['phone'])) $customer_phone = $addr['phone'];
    }
} else {
    $line1   = trim($_POST['address_line1'] ?? '');
    $line2   = trim($_POST['address_line2'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $postal  = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? 'Bangladesh');
    $shipping_address = $line1;
    if (!empty($line2)) $shipping_address .= ', ' . $line2;
    $shipping_address .= ', ' . $city;
    if (!empty($postal)) $shipping_address .= ' - ' . $postal;
    $shipping_address .= ', ' . $country;
}
$billing_address = $shipping_address;

// ---------- 5. VERIFY STOCK & PREPARE ORDER ----------
$conn->begin_transaction();
try {
    $total = 0;
    $cart_items = [];
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $pid = (int)$pid;
        $qty = (int)$qty;
        $stmt = $conn->prepare("SELECT id, name, price, stock_quantity FROM products WHERE id = ? AND is_active = 1 FOR UPDATE");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$p) throw new Exception("Product ID $pid not found.");
        if ($p['stock_quantity'] < $qty) throw new Exception("Insufficient stock for {$p['name']}.");

        $cart_items[] = [
            'product_id' => $p['id'],
            'name'       => $p['name'],
            'price'      => $p['price'],
            'quantity'   => $qty,
            'subtotal'   => $p['price'] * $qty
        ];
        $total += $p['price'] * $qty;
    }

    // Generate order number
    $res = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'");
    $next_id = $res->fetch_assoc()['AUTO_INCREMENT'] ?? 1;
    $order_number = 'ORD-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

    // Insert order
    $payment_status = 'pending';
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, order_number, customer_name, customer_email, customer_phone,
            billing_address, shipping_address, subtotal, total_amount,
            payment_method, payment_status, customer_notes, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param(
        "isssssssdsss",
        $user_id,
        $order_number,
        $customer_name,
        $customer_email,
        $customer_phone,
        $billing_address,
        $shipping_address,
        $total,         // subtotal
        $total,         // total_amount
        $payment_method,
        $payment_status,
        $notes
    );
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    // Generate tracking number
    $tracking_number = "HAR" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "BD";
    $conn->query("UPDATE orders SET tracking_number = '{$tracking_number}' WHERE id = {$order_id}");

    // Insert order items & update stock
    $item_stmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, subtotal, total_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ?, sold_count = sold_count + ? WHERE id = ?");
    foreach ($cart_items as $item) {
        $item_stmt->bind_param(
            "iisiddi",
            $order_id,
            $item['product_id'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['subtotal'],
            $item['subtotal']
        );
        $item_stmt->execute();

        $stock_stmt->bind_param("iii", $item['quantity'], $item['quantity'], $item['product_id']);
        $stock_stmt->execute();
    }
    $item_stmt->close();
    $stock_stmt->close();

    // Status history
    $hist = $conn->prepare("INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?, 'pending', 'Order placed', ?)");
    $created_by = "User ID: $user_id";
    $hist->bind_param("is", $order_id, $created_by);
    $hist->execute();
    $hist->close();

    $conn->commit();

    // ---------- 6. CLEAR CART ----------
    unset($_SESSION['cart']);

    // ---------- 7. 📱 SEND TELEGRAM NOTIFICATION (order placed) ----------
    $botToken = "8581941364:AAGS9iL46AWJ3Bfa0TVPnn9RrLNihJ8eubY";
    $chatID   = "1299806559";

    $message  = "🛒 *NEW ORDER #{$order_id}*\n";
    $message .= "━━━━━━━━━━━━━━━\n";
    $message .= "👤 *Customer:* {$customer_name}\n";
    $message .= "📧 *Email:* {$customer_email}\n";
    $message .= "📞 *Phone:* {$customer_phone}\n";
    $message .= "📍 *Shipping:* {$shipping_address}\n";
    $message .= "💳 *Payment:* " . ucfirst(str_replace('_', ' ', $payment_method)) . "\n";
    $message .= "📦 *Tracking:* `{$tracking_number}`\n";
    $message .= "━━━━━━━━━━━━━━━\n";
    $message .= "*Items:*\n";
    foreach ($cart_items as $item) {
        $message .= "• {$item['name']} × {$item['quantity']} = $" . number_format($item['subtotal'], 2) . "\n";
    }
    $message .= "━━━━━━━━━━━━━━━\n";
    $message .= "💰 *TOTAL:* $" . number_format($total, 2);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$botToken}/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatID,
        'text'    => $message,
        'parse_mode' => 'Markdown'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("Telegram error: " . curl_error($ch));
    }
    curl_close($ch);

    // ---------- 8. REDIRECT BASED ON PAYMENT METHOD ----------
    // After order is created successfully (around line where you clear cart)
    if ($payment_method === 'bakong_khqr') {
    $_SESSION['bakong_pending'] = [
        'order_id' => $order_id,
        'amount' => $total,          // order total in USD
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone
    ];
    header("Location: bakong_payment.php");
    exit;
} else {
        // For other methods, go to orders list with success message
        $_SESSION['success'] = "Your order #{$order_id} has been placed successfully!";
        header("Location: user/orders.php");
        exit;
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Order failed: " . $e->getMessage());
    ?>
    <div style="max-width:600px; margin:50px auto; padding:30px; background:#f8d7da; color:#721c24; border-radius:8px;">
        <h2>❌ Order Failed</h2>
        <p><?= htmlspecialchars($e->getMessage()) ?></p>
        <a href="cart.php" style="display:inline-block; margin-top:20px; padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:4px;">← Back to Cart</a>
    </div>
    <?php
    exit;
}
?>