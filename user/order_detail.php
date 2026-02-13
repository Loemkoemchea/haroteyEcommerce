<?php
// file: user/order_detail.php
require_once 'auth_check.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// =============================================
// 1. FETCH ORDER DETAILS
// =============================================
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['error'] = "Order not found.";
    header("Location: orders.php");
    exit;
}

// =============================================
// 2. FETCH ORDER ITEMS – WITH PRODUCT ID & IMAGE
// =============================================
$items_stmt = $conn->prepare("
    SELECT 
        oi.id,
        oi.product_id,
        oi.product_name,
        oi.unit_price,
        oi.quantity,
        oi.subtotal,
        oi.total_amount,
        p.name AS current_product_name,
        p.slug,
        (SELECT image_path FROM product_images 
         WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
$items_stmt->close();

// =============================================
// 3. FETCH STATUS HISTORY
// =============================================
$history_stmt = $conn->prepare("
    SELECT * FROM order_status_history 
    WHERE order_id = ? 
    ORDER BY created_at DESC
");
$history_stmt->bind_param("i", $order_id);
$history_stmt->execute();
$history = $history_stmt->get_result();
$history_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .order-detail-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .order-header { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 30px; font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .order-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .info-card h3 { margin-top: 0; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .info-row { display: flex; margin-bottom: 12px; }
        .info-label { width: 140px; color: #666; font-weight: 500; }
        .info-value { flex: 1; font-weight: 500; }
        .items-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .items-table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #333; }
        .items-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .product-info { display: flex; align-items: center; gap: 12px; }
        .product-image { width: 60px; height: 60px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-image img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .product-details { display: flex; flex-direction: column; }
        .product-name { font-weight: 600; color: #333; text-decoration: none; }
        .product-name:hover { color: #28a745; }
        .btn-review { display: inline-block; padding: 6px 14px; background: #28a745; color: white; border-radius: 30px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .btn-review:hover { background: #218838; }
        .btn-cancel { background: #dc3545; color: white; padding: 8px 16px; border-radius: 30px; text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-cancel:hover { background: #c82333; }
        .btn-back { background: #6c757d; color: white; padding: 10px 20px; border-radius: 30px; text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-back:hover { background: #5a6268; }
        .total-section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: right; margin-top: 20px; }
        .total-row { display: flex; justify-content: flex-end; gap: 30px; align-items: center; font-size: 18px; }
        .total-amount { font-size: 24px; font-weight: 700; color: #28a745; }
        .tracking-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .tracking-number { font-size: 20px; font-weight: 700; color: #28a745; font-family: monospace; letter-spacing: 1px; }
        .progress-tracker { display: flex; justify-content: space-between; margin-top: 20px; position: relative; }
        .progress-step { flex: 1; text-align: center; position: relative; }
        .progress-step:not(:last-child)::before { content: ''; position: absolute; top: 15px; right: -50%; width: 100%; height: 2px; background: #e0e0e0; z-index: 1; }
        .progress-step.completed:not(:last-child)::before { background: #28a745; }
        .step-icon { width: 32px; height: 32px; background: white; border: 2px solid #e0e0e0; border-radius: 50%; margin: 0 auto 8px; position: relative; z-index: 2; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .progress-step.completed .step-icon { background: #28a745; border-color: #28a745; color: white; }
        .progress-step.active .step-icon { border-color: #28a745; color: #28a745; }
        .step-label { font-size: 12px; color: #666; }
        .timeline-item { display: flex; gap: 20px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .timeline-time { min-width: 160px; color: #666; }
        .timeline-status { font-weight: 600; color: #28a745; }
        @media (max-width: 768px) {
            .order-grid { grid-template-columns: 1fr; }
            .items-table { display: block; overflow-x: auto; }
            .product-info { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="order-detail-container">
        <!-- Header -->
        <div class="order-header">
            <div>
                <h1 style="margin: 0 0 10px 0;">Order #<?= $order['id'] ?></h1>
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

        <!-- Tracking Section -->
        <?php if (!empty($order['tracking_number'])): ?>
        <div class="tracking-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h3 style="margin: 0 0 5px 0;">📮 Tracking Information</h3>
                    <span style="color: #666;">Track your package in real-time</span>
                </div>
                <div class="tracking-number"><?= htmlspecialchars($order['tracking_number']) ?></div>
            </div>
            <div class="progress-tracker">
                <?php
                $steps = [
                    'pending'    => 'Order Placed',
                    'processing' => 'Processing',
                    'completed'  => 'Shipped',
                    'delivered'  => 'Delivered'
                ];
                $current_step = $order['status'];
                $completed = true;
                foreach ($steps as $status => $label):
                    $is_completed = $completed && ($current_step == $status || array_search($current_step, array_keys($steps)) > array_search($status, array_keys($steps)));
                    if ($status == $current_step) $completed = false;
                ?>
                    <div class="progress-step <?= $is_completed ? 'completed' : '' ?> <?= $status == $current_step ? 'active' : '' ?>">
                        <div class="step-icon"><?= $is_completed ? '✓' : ($status == $current_step ? '●' : '○') ?></div>
                        <div class="step-label"><?= $label ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer & Order Summary Grid -->
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
                <?php if (!empty($order['shipping_address'])): ?>
                <div class="info-row">
                    <span class="info-label">Shipping Address:</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
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
                    <span class="info-value"><?= ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Cash on Delivery')) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Status:</span>
                    <span class="info-value">
                        <span style="color: <?= ($order['payment_status'] ?? 'pending') == 'paid' ? '#28a745' : '#856404' ?>;">
                            <?= ucfirst($order['payment_status'] ?? 'pending') ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Order Items Table -->
        <h2 style="margin: 30px 0 15px;">🛒 Items in Your Order</h2>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotal = 0;
                while ($item = $items->fetch_assoc()): 
                    $item_subtotal = $item['unit_price'] * $item['quantity'];
                    $subtotal += $item_subtotal;
                    $product_name = $item['product_name'] ?: $item['current_product_name'] ?: 'Deleted Product';
                ?>
                <tr>
                    <td>
                        <div class="product-info">
                            <div class="product-image">
                                <?php if (!empty($item['product_image'])): ?>
                                    <img src="../<?= htmlspecialchars($item['product_image']) ?>" alt="<?= htmlspecialchars($product_name) ?>">
                                <?php else: ?>
                                    <span style="font-size: 24px;">🖼️</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-details">
                                <a href="../product.php?id=<?= $item['product_id'] ?>" class="product-name">
                                    <?= htmlspecialchars($product_name) ?>
                                </a>
                                <?php if (empty($item['current_product_name'])): ?>
                                    <small style="color: #999;">(no longer available)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>$<?= number_format($item['unit_price'], 2) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>$<?= number_format($item_subtotal, 2) ?></td>
                    <td>
                        <?php if ($order['status'] === 'completed'): ?>
                            <?php
                            // Check if already reviewed
                            $review_check = $conn->prepare("
                                SELECT id FROM product_reviews 
                                WHERE user_id = ? AND product_id = ? AND order_id = ?
                            ");
                            $review_check->bind_param("iii", $user_id, $item['product_id'], $order['id']);
                            $review_check->execute();
                            $already_reviewed = $review_check->get_result()->num_rows > 0;
                            $review_check->close();
                            ?>
                            <?php if ($already_reviewed): ?>
                                <span style="color: #28a745; font-weight: 600;">✓ Reviewed</span>
                            <?php else: ?>
                                <a href="write_review.php?order_id=<?= $order['id'] ?>&product_id=<?= $item['product_id'] ?>" class="btn-review">
                                    ✍️ Write Review
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Order Totals -->
        <div class="total-section">
            <div class="total-row" style="margin-bottom: 10px;">
                <span>Subtotal:</span>
                <span style="width: 120px; text-align: right;">$<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="total-row" style="margin-bottom: 10px;">
                <span>Shipping:</span>
                <span style="width: 120px; text-align: right;">$<?= number_format($order['shipping_amount'] ?? 0, 2) ?></span>
            </div>
            <div class="total-row" style="font-size: 20px; border-top: 2px solid #f0f0f0; padding-top: 15px;">
                <span><strong>Total:</strong></span>
                <span class="total-amount">$<?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>

        <!-- Order Notes (if any) -->
        <?php if (!empty($order['customer_notes'])): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 12px; margin-top: 30px;">
            <strong style="color: #856404;">📝 Order Notes:</strong>
            <p style="margin: 10px 0 0; color: #856404;"><?= nl2br(htmlspecialchars($order['customer_notes'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Status History -->
        <?php if ($history->num_rows > 0): ?>
        <div class="info-card" style="margin-top: 30px;">
            <h3>📋 Order Timeline</h3>
            <?php while ($event = $history->fetch_assoc()): ?>
                <div class="timeline-item">
                    <div class="timeline-time">
                        <?= date('M d, Y', strtotime($event['created_at'])) ?><br>
                        <small style="color: #999;"><?= date('g:i A', strtotime($event['created_at'])) ?></small>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-status"><?= ucfirst($event['status']) ?></div>
                        <?php if (!empty($event['comment'])): ?>
                            <p style="margin: 5px 0 0; color: #666;"><?= htmlspecialchars($event['comment']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 15px; margin-top: 30px; flex-wrap: wrap;">
            <a href="orders.php" class="btn-back">← Back to Orders</a>
            <?php if ($order['status'] === 'pending'): ?>
                <a href="cancel-order.php?id=<?= $order['id'] ?>" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel this order?')">
                    Cancel Order
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>