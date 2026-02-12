<?php
// file: admin/order_detail.php
require_once 'auth_check.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = "Order not found";
    header("Location: orders.php");
    exit;
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Order status updated to $new_status";
        header("Location: order_detail.php?id=$order_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .order-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            margin-bottom: 12px;
        }
        .info-label {
            width: 120px;
            color: #666;
            font-weight: 500;
        }
        .info-value {
            flex: 1;
            font-weight: 500;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .total-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: right;
            font-size: 18px;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .print-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .print-btn:hover {
            background: #5a6268;
        }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with Actions -->
        <div class="order-header no-print">
            <div>
                <h1 style="margin: 0;">Order #<?= $order_id ?></h1>
                <p style="color: #666; margin: 5px 0 0 0;">
                    Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                </p>
            </div>
            <div>
                <span class="status-badge status-<?= $order['status'] ?>">
                    <?= ucfirst($order['status']) ?>
                </span>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;" class="no-print">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
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
                    <span class="info-label">Order Time:</span>
                    <span class="info-value"><?= date('g:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= $order['status'] ?>" style="font-size: 12px;">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="info-card" style="margin-bottom: 20px;">
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
                        $item_subtotal = $item['price'] * $item['quantity'];
                        $subtotal += $item_subtotal;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['product_name'] ?? 'Deleted Product') ?></strong>
                                <?php if (!$item['product_name']): ?>
                                    <br><small style="color: #dc3545;">(Product no longer available)</small>
                                <?php endif; ?>
                            </td>
                            <td>$<?= number_format($item['price'], 2) ?></td>
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
                    <span style="width: 120px; text-align: right;">$0.00</span>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 30px; font-size: 20px; border-top: 2px solid #f0f0f0; padding-top: 15px;">
                    <span><strong>Total:</strong></span>
                    <span style="width: 120px; text-align: right;" class="total-amount">
                        $<?= number_format($order['total_amount'], 2) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Status Update Form (Admin only) -->
        <div class="info-card no-print">
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
                <button type="submit" name="update_status" class="btn" style="background: #28a745; padding: 10px 30px;">
                    Update Status
                </button>
            </form>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <a href="orders.php" class="btn" style="background: #6c757d;">← Back to Orders</a>
            <button onclick="window.print()" class="print-btn">🖨️ Print Invoice</button>
            <a href="orders.php" class="btn" style="background: #007bff;">View All Orders</a>
        </div>
    </div>
</body>
</html>