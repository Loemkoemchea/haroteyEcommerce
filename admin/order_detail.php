<?php
// file: admin/order_detail.php
require_once 'auth_check.php';

// ---------- TELEGRAM CONFIGURATION ----------
define('TELEGRAM_BOT_TOKEN', '8581941364:AAGS9iL46AWJ3Bfa0TVPnn9RrLNihJ8eubY');
define('TELEGRAM_CHAT_ID', '1299806559');
// --------------------------------------------

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// =============================================
// 1. FETCH ORDER DETAILS
// =============================================
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result(); // ✅ get_result() returns mysqli_result
$order = $result->fetch_assoc(); // ✅ now fetch_assoc() works
$stmt->close();

if (!$order) {
    $_SESSION['error'] = "Order not found";
    header("Location: orders.php");
    exit;
}

// =============================================
// 2. FETCH ORDER ITEMS
// =============================================
$stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.sku
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result(); // ✅ get_result()
$stmt->close();

// =============================================
// 3. HANDLE STATUS UPDATE
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'] ?? '';
    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];

    if (in_array($new_status, $valid_statuses) && $new_status !== $order['status']) {
        $conn->begin_transaction();
        try {
            // Update order status
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
            $stmt->execute();
            $stmt->close();

            // Insert status history
            $admin_name = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Admin';
            $hist = $conn->prepare("
                INSERT INTO order_status_history (order_id, status, comment, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $comment = "Status updated via admin panel";
            $hist->bind_param("isss", $order_id, $new_status, $comment, $admin_name);
            $hist->execute();
            $hist->close();

            $conn->commit();

            // ---------- TELEGRAM NOTIFICATION ----------
            $message = "🛍️ *Order #{$order_id} Status Updated*\n";
            $message .= "━━━━━━━━━━━━━━━\n";
            $message .= "📦 *New Status:* " . strtoupper($new_status) . "\n";
            $message .= "👤 *Customer:* {$order['customer_name']}\n";
            $message .= "📧 *Email:* {$order['customer_email']}\n";
            $message .= "📞 *Phone:* {$order['customer_phone']}\n";
            $message .= "💰 *Total:* $" . number_format($order['total_amount'], 2) . "\n";
            $message .= "🔗 *Admin Link:* http://{$_SERVER['HTTP_HOST']}/admin/order_detail.php?id={$order_id}\n";
            $message .= "━━━━━━━━━━━━━━━\n";
            $message .= "🕐 " . date('Y-m-d H:i:s');

            $ch = curl_init("https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'chat_id' => TELEGRAM_CHAT_ID,
                'text'    => $message,
                'parse_mode' => 'Markdown'
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
            // ------------------------------------------------

            $_SESSION['success'] = "Order #{$order_id} status updated to " . ucfirst($new_status);
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Failed to update status: " . $e->getMessage();
            error_log("Order status update failed: " . $e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Invalid status or no change.";
    }
    header("Location: order_detail.php?id=" . $order_id);
    exit;
}

// =============================================
// 4. RETRIEVE SESSION MESSAGES
// =============================================
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .order-detail-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .order-header { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 30px; font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .order-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .info-card h3 { margin-top: 0; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .info-row { display: flex; margin-bottom: 12px; }
        .info-label { width: 120px; color: #666; font-weight: 500; }
        .info-value { flex: 1; font-weight: 500; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background: #f8f9fa; padding: 12px; text-align: left; }
        .items-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        .total-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: right; font-size: 18px; }
        .total-amount { font-size: 24px; font-weight: bold; color: #28a745; }
        .alert-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        @media (max-width: 768px) { .order-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="order-detail-container">
        <div class="order-header">
            <div>
                <h1 style="margin: 0 0 10px 0;">Order #<?= $order_id ?></h1>
                <p style="color: #666; margin: 0;">
                    Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                </p>
            </div>
            <div>
                <span class="status-badge status-<?= $order['status'] ?>">
                    <?= ucfirst($order['status']) ?>
                </span>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Customer Information & Order Summary -->
        <div class="order-grid">
            <div class="info-card">
                <h3>👤 Customer Information</h3>
                <div class="info-row">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_email']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_phone']) ?></span>
                </div>
            </div>
            <!-- Order Summary -->
            <div class="info-card">
                <h3>📦 Order Summary</h3>
                <div class="info-row">
                    <span class="info-label">Order ID:</span>
                    <span class="info-value">#<?= $order['id'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value"><?= date('F j, Y', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value">
                        <?php
                        // Get stored payment method, fallback to 'cash_on_delivery'
                        $payment_method = $order['payment_method'] ?? 'cash_on_delivery';
                        if (empty($payment_method)) {
                            $payment_method = 'cash_on_delivery';
                        }
                        // Convert snake_case to readable format
                        $readable = ucwords(str_replace('_', ' ', $payment_method));
                        echo htmlspecialchars($readable);
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Status:</span>
                    <span class="info-value">
                        <?php
                        $payment_status = $order['payment_status'] ?? 'pending';
                        $status_colors = [
                            'pending' => '#856404',
                            'paid'    => '#155724',
                            'failed'  => '#721c24',
                            'refunded' => '#6c757d'
                        ];
                        $color = $status_colors[$payment_status] ?? '#333';
                        ?>
                        <span style="color: <?= $color ?>; font-weight: 600;">
                            <?= htmlspecialchars(ucfirst($payment_status)) ?>
                        </span>
                    </span>
                </div>
                <?php if (!empty($order['tracking_number'])): ?>
                    <div class="info-row">
                        <span class="info-label">Tracking:</span>
                        <span class="info-value"><code><?= htmlspecialchars($order['tracking_number']) ?></code></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Shipping Address -->
        <div class="info-card" style="margin-bottom: 20px;">
            <h3>🚚 Shipping Address</h3>
            <div class="info-row">
                <span class="info-label">Address:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?></span>
            </div>
        </div>

        <!-- Order Items -->
        <div class="info-card" style="margin-bottom: 20px;">
            <h3>🛒 Order Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal = 0;
                    while ($item = $items_result->fetch_assoc()): 
                        $item_subtotal = $item['unit_price'] * $item['quantity'];
                        $subtotal += $item_subtotal;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['product_name'] ?? 'Deleted Product') ?></strong>
                                <?php if (!$item['product_name']): ?>
                                    <br><small style="color: #dc3545;">(Product no longer available)</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['product_sku'] ?? 'N/A') ?></td>
                            <td>$<?= number_format($item['unit_price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><strong>$<?= number_format($item_subtotal, 2) ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="total-section">
                <div style="display: flex; justify-content: flex-end; gap: 30px; margin-bottom: 10px;">
                    <span>Subtotal:</span>
                    <span style="width: 120px; text-align: right;">$<?= number_format($subtotal, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 30px; margin-bottom: 10px;">
                    <span>Shipping:</span>
                    <span style="width: 120px; text-align: right;">$<?= number_format($order['shipping_amount'] ?? 0, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 30px; font-size: 20px; border-top: 2px solid #f0f0f0; padding-top: 15px;">
                    <span><strong>Total:</strong></span>
                    <span style="width: 120px; text-align: right;" class="total-amount">
                        $<?= number_format($order['total_amount'], 2) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Status Update Form -->
        <div class="info-card">
            <h3>⚡ Update Order Status</h3>
            <form method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label for="status" style="display: block; margin-bottom: 5px; font-weight: bold;">Change Status:</label>
                    <select name="status" id="status" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn btn-primary" style="padding: 10px 30px;">
                    Update Status
                </button>
            </form>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <a href="orders.php" class="btn btn-secondary">← Back to Orders</a>
            <a href="orders.php" class="btn" style="background: #007bff; color: white;">View All Orders</a>
        </div>
    </div>
</body>
</html>