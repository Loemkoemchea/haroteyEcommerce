<?php
// file: user/order_detail.php
require_once 'auth_check.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order details
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit;
}

// Get order items
$items_stmt = $conn->prepare("
    SELECT oi.*, p.name, p.description 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

// Get status history
$history_stmt = $conn->prepare("
    SELECT * FROM order_status_history 
    WHERE order_id = ? 
    ORDER BY created_at DESC
");
$history_stmt->bind_param("i", $order_id);
$history_stmt->execute();
$history = $history_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .order-detail-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .order-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tracking-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .tracking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .tracking-number {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            letter-spacing: 2px;
        }
        
        .progress-tracker {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .progress-step:not(:last-child):before {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 3px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .progress-step.completed:not(:last-child):before {
            background: #28a745;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            background: white;
            border: 3px solid #e0e0e0;
            border-radius: 50%;
            margin: 0 auto 10px;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        
        .progress-step.completed .step-icon {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .progress-step.active .step-icon {
            border-color: #28a745;
            color: #28a745;
        }
        
        .step-label {
            font-size: 14px;
            color: #666;
        }
        
        .progress-step.completed .step-label {
            color: #28a745;
            font-weight: 600;
        }
        
        .order-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .info-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            color: #333;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
        }
        
        .info-label {
            width: 140px;
            color: #666;
        }
        
        .info-value {
            flex: 1;
            font-weight: 500;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
        }
        
        .items-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            position: sticky;
            top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .total-row {
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-outline {
            border: 2px solid #28a745;
            color: #28a745;
            background: white;
        }
        
        .status-timeline {
            margin-top: 30px;
        }
        
        .timeline-item {
            display: flex;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .timeline-time {
            width: 160px;
            color: #666;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-status {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="order-detail-container">
        <!-- Header -->
        <div class="order-header">
            <div>
                <h1 style="margin: 0 0 10px 0;">Order #<?= $order_id ?></h1>
                <p style="color: #666; margin: 0;">
                    Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                </p>
            </div>
            <div>
                <span class="status-badge status-<?= $order['status'] ?>" style="font-size: 16px; padding: 10px 20px;">
                    <?= ucfirst($order['status']) ?>
                </span>
            </div>
        </div>
        
        <!-- Tracking Section -->
        <?php if ($order['tracking_number']): ?>
            <div class="tracking-card">
                <div class="tracking-header">
                    <div>
                        <h3 style="margin: 0 0 5px 0;">📮 Tracking Information</h3>
                        <span style="color: #666;">Track your package in real-time</span>
                    </div>
                    <div class="tracking-number"><?= $order['tracking_number'] ?></div>
                </div>
                
                <!-- Progress Tracker -->
                <div class="progress-tracker">
                    <?php
                    $steps = [
                        'pending' => 'Order Placed',
                        'processing' => 'Processing',
                        'completed' => 'Shipped',
                        'delivered' => 'Delivered'
                    ];
                    
                    $current_step = $order['status'];
                    $completed = true;
                    
                    foreach ($steps as $status => $label):
                        $is_completed = $completed && in_array($status, ['pending', 'processing', 'completed', 'delivered']);
                        if ($status == $current_step) {
                            $completed = false;
                        }
                    ?>
                        <div class="progress-step <?= $is_completed ? 'completed' : '' ?> <?= $status == $current_step ? 'active' : '' ?>">
                            <div class="step-icon">✓</div>
                            <div class="step-label"><?= $label ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Order Details Grid -->
        <div class="order-grid">
            <!-- Left Column -->
            <div>
                <!-- Order Items -->
                <div class="info-section">
                    <h3>🛒 Order Items</h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            while ($item = $items->fetch_assoc()): 
                                $item_subtotal = $item['unit_price'] * $item['quantity'];
                                $subtotal += $item_subtotal;
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item['name'] ?? 'Product') ?></strong>
                                        <?php if (!$item['name']): ?>
                                            <br><small style="color: #dc3545;">(Product no longer available)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>$<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Customer Information -->
                <div class="info-section">
                    <h3>👤 Customer Information</h3>
                    <div class="info-row">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email Address:</span>
                        <span class="info-value"><?= htmlspecialchars($order['customer_email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone Number:</span>
                        <span class="info-value"><?= htmlspecialchars($order['customer_phone']) ?></span>
                    </div>
                    <?php if ($order['shipping_address']): ?>
                        <div class="info-row">
                            <span class="info-label">Shipping Address:</span>
                            <span class="info-value"><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Status History -->
                <?php if ($history->num_rows > 0): ?>
                    <div class="info-section">
                        <h3>📋 Order Timeline</h3>
                        <div class="status-timeline">
                            <?php while ($event = $history->fetch_assoc()): ?>
                                <div class="timeline-item">
                                    <div class="timeline-time">
                                        <?= date('M d, Y', strtotime($event['created_at'])) ?><br>
                                        <small style="color: #999;"><?= date('g:i A', strtotime($event['created_at'])) ?></small>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-status">
                                            <?= ucfirst($event['status']) ?>
                                        </div>
                                        <?php if ($event['comment']): ?>
                                            <p style="margin: 5px 0 0 0; color: #666;"><?= htmlspecialchars($event['comment']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column - Order Summary -->
            <div>
                <div class="summary-card">
                    <h3 style="margin-top: 0; margin-bottom: 25px;">📦 Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span><strong>$<?= number_format($subtotal, 2) ?></strong></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span><strong>$0.00</strong></span>
                    </div>
                    
                    <div class="summary-row" style="border-bottom: none;">
                        <span>Payment Method:</span>
                        <span>
                            <?= ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Cash on Delivery')) ?>
                        </span>
                    </div>
                    
                    <div class="summary-row" style="border-bottom: none;">
                        <span>Payment Status:</span>
                        <span style="color: <?= $order['payment_status'] == 'paid' ? '#28a745' : '#856404' ?>;">
                            <?= ucfirst($order['payment_status'] ?? 'pending') ?>
                        </span>
                    </div>
                    
                    <div class="total-row summary-row">
                        <span>Total:</span>
                        <span>$<?= number_format($order['total_amount'], 2) ?></span>
                    </div>
                    
                    <?php if (!empty($order['notes'])): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 6px;">
                            <strong style="color: #856404;">📝 Order Notes:</strong>
                            <p style="margin: 10px 0 0 0; color: #856404;"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="orders.php" class="btn btn-secondary" style="flex: 1;">← Back</a>
                        
                        <?php if ($order['status'] === 'pending'): ?>
                            <a href="cancel-order.php?id=<?= $order['id'] ?>" class="btn btn-danger" style="flex: 1;" onclick="return confirm('Are you sure you want to cancel this order?')">Cancel Order</a>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'completed'): ?>
                            <a href="review.php?order_id=<?= $order['id'] ?>" class="btn btn-primary" style="flex: 1;">Write Review</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Need Help -->
                    <div style="margin-top: 25px; padding-top: 25px; border-top: 1px solid #eee; text-align: center;">
                        <p style="color: #666; margin-bottom: 10px;">Need help with this order?</p>
                        <a href="../contact.php?order=<?= $order['id'] ?>" style="color: #28a745; text-decoration: none;">Contact Support →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>