<?php
// file: user/orders.php
require_once 'auth_check.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total orders count
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_orders / $limit);

// Get orders
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM orders WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .orders-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .orders-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .orders-header h1 {
            margin: 0;
            color: #333;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #dee2e6;
            border-radius: 30px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-tab:hover,
        .filter-tab.active {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .order-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 25px;
            transition: all 0.3s;
        }
        
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-id {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .order-date {
            color: #666;
        }
        
        .order-body {
            display: grid;
            grid-template-columns: 1fr 200px;
            gap: 30px;
        }
        
        .order-items {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-total {
            text-align: right;
        }
        
        .total-amount {
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
        }
        
        .status-timeline {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .timeline-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .timeline-step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        
        .timeline-step:not(:last-child):before {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .timeline-icon {
            width: 30px;
            height: 30px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 50%;
            margin: 0 auto 5px;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .timeline-step.completed .timeline-icon {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .timeline-step.active .timeline-icon {
            border-color: #28a745;
            color: #28a745;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #666;
            text-decoration: none;
        }
        
        .page-link.active {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="orders-container">
        <div class="orders-header">
            <div>
                <h1>📦 My Orders</h1>
                <p style="color: #666; margin: 10px 0 0 0;">Track and manage your orders</p>
            </div>
            <a href="dashboard.php" class="btn" style="background: #6c757d;">← Back to Dashboard</a>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="orders.php" class="filter-tab <?= empty($status_filter) ? 'active' : '' ?>">All Orders</a>
            <a href="orders.php?status=pending" class="filter-tab <?= $status_filter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="orders.php?status=processing" class="filter-tab <?= $status_filter === 'processing' ? 'active' : '' ?>">Processing</a>
            <a href="orders.php?status=completed" class="filter-tab <?= $status_filter === 'completed' ? 'active' : '' ?>">Completed</a>
            <a href="orders.php?status=cancelled" class="filter-tab <?= $status_filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
        </div>
        
        <!-- Orders List -->
        <?php if ($orders->num_rows > 0): ?>
            <?php while ($order = $orders->fetch_assoc()): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <span class="order-id">Order #<?= $order['id'] ?></span>
                            <span class="order-date"> | <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <span class="status-badge status-<?= $order['status'] ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </div>
                    
                    <div class="order-body">
                        <div>
                            <h4 style="margin-top: 0; margin-bottom: 15px;">Items</h4>
                            <?php
                            $items_stmt = $conn->prepare("
                                SELECT oi.*, p.name 
                                FROM order_items oi 
                                LEFT JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?
                            ");
                            $items_stmt->bind_param("i", $order['id']);
                            $items_stmt->execute();
                            $items = $items_stmt->get_result();
                            ?>
                            <ul class="order-items">
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <li class="order-item">
                                        <span>
                                            <?= htmlspecialchars($item['name'] ?? 'Product') ?> 
                                            × <?= $item['quantity'] ?>
                                        </span>
                                        <span>$<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></span>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                        
                        <div class="order-total">
                            <h4 style="margin-top: 0; margin-bottom: 15px;">Order Summary</h4>
                            <div style="margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span>Subtotal:</span>
                                    <span>$<?= number_format($order['total_amount'], 2) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span>Shipping:</span>
                                    <span>$0.00</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span>Payment:</span>
                                    <span style="color: <?= $order['payment_status'] == 'paid' ? '#28a745' : '#856404' ?>;">
                                        <?= ucfirst(str_replace('_', ' ', $order['payment_status'])) ?>
                                    </span>
                                </div>
                                <hr style="margin: 15px 0;">
                                <div style="display: flex; justify-content: space-between; font-size: 18px;">
                                    <span><strong>Total:</strong></span>
                                    <span class="total-amount">$<?= number_format($order['total_amount'], 2) ?></span>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn" style="background: #007bff; display: block; text-align: center;">View Details</a>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <a href="cancel-order.php?id=<?= $order['id'] ?>" class="btn" style="background: #dc3545; display: block; text-align: center; margin-top: 10px;" onclick="return confirm('Cancel this order?')">Cancel Order</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tracking Number -->
                    <?php if ($order['tracking_number']): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                                <span style="color: #666;">📮 Tracking Number:</span>
                                <code style="background: #f8f9fa; padding: 8px 15px; border-radius: 4px; font-size: 16px;"><?= $order['tracking_number'] ?></code>
                                <span style="color: #28a745;">📱 Track your order</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . $status_filter : '' ?>" class="page-link">← Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?><?= $status_filter ? '&status=' . $status_filter : '' ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $status_filter ? '&status=' . $status_filter : '' ?>" class="page-link">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 12px;">
                <div style="font-size: 80px; margin-bottom: 20px;">📭</div>
                <h2 style="color: #333; margin-bottom: 10px;">No orders found</h2>
                <p style="color: #666; margin-bottom: 30px;">
                    <?= $status_filter ? "You don't have any {$status_filter} orders." : "You haven't placed any orders yet." ?>
                </p>
                <a href="../index.php" class="btn-shop" style="display: inline-block; padding: 12px 30px;">Start Shopping</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>