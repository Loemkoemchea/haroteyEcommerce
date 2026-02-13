<?php
// file: user/dashboard.php
require_once 'auth_check.php';

// =============================================
// FETCH USER STATISTICS (same as before)
// =============================================
$stats = [];

// Total orders
$result = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
$result->bind_param("i", $user_id);
$result->execute();
$stats['total_orders'] = $result->get_result()->fetch_assoc()['count'];

// Total spent
$result = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ? AND status != 'cancelled'");
$result->bind_param("i", $user_id);
$result->execute();
$stats['total_spent'] = $result->get_result()->fetch_assoc()['total'] ?? 0;

// Pending orders
$result = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'pending'");
$result->bind_param("i", $user_id);
$result->execute();
$stats['pending_orders'] = $result->get_result()->fetch_assoc()['count'];

// Wishlist count
$result = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM wishlist_items wi 
    JOIN wishlists w ON wi.wishlist_id = w.id 
    WHERE w.user_id = ?
");
$result->bind_param("i", $user_id);
$result->execute();
$stats['wishlist_count'] = $result->get_result()->fetch_assoc()['count'] ?? 0;

// Recent orders
$recent_orders = $conn->prepare("
    SELECT * FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_orders->bind_param("i", $user_id);
$recent_orders->execute();
$recent_orders = $recent_orders->get_result();

// Recommended products
$recommended_products = $conn->prepare("
    SELECT p.*, COUNT(oi.product_id) as purchase_count 
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY p.id
    ORDER BY purchase_count DESC
    LIMIT 4
");
$recommended_products->bind_param("i", $user_id);
$recommended_products->execute();
$recommended_products = $recommended_products->get_result();

// =============================================
// PROFILE IMAGE HANDLING
// =============================================
$profile_image = $user['profile_image'] ?? 'default-avatar.png';
$avatar_src = '../' . $profile_image; // ✅ path from /user/ subfolder
$avatar_initial = strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .dashboard-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            height: fit-content;
        }
        
        .user-info {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        /* ✅ Updated avatar styles */
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #28a745;
            color: white;
            font-size: 40px;
            font-weight: bold;
            border: 3px solid #28a745;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .user-email {
            color: #666;
            font-size: 14px;
            word-break: break-word;
        }
        
        .user-join {
            color: #999;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #555;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #e8f5e9;
            color: #28a745;
        }
        
        .sidebar-menu a i {
            margin-right: 12px;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .badge {
            margin-left: auto;
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        
        /* Main Content */
        .main-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }
        
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .welcome-title h2 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .welcome-title p {
            margin: 0;
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #555;
            font-size: 14px;
        }
        
        .orders-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #eee;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn-view {
            padding: 6px 12px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .btn-view:hover {
            background: #0069d9;
        }
        
        .btn-shop {
            display: inline-block;
            padding: 12px 25px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-shop:hover {
            background: #218838;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .empty-state h4 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .product-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .product-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .product-price {
            color: #28a745;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .btn-add {
            display: inline-block;
            padding: 6px 12px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        
        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- SIDEBAR with Profile Image -->
        <div class="sidebar">
            <div class="user-info">
                <?php if ($profile_image !== 'default-avatar.png' && file_exists($avatar_src)): ?>
                    <div class="user-avatar">
                        <img src="<?= htmlspecialchars($avatar_src) ?>" alt="<?= htmlspecialchars($user['username']) ?>">
                    </div>
                <?php else: ?>
                    <div class="user-avatar">
                        <?= $avatar_initial ?>
                    </div>
                <?php endif; ?>
                <div class="user-name"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
                <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                <div class="user-join">
                    Member since <?= date('M Y', strtotime($user['created_at'])) ?>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i>📊</i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="orders.php">
                        <i>📦</i> My Orders
                        <?php if ($stats['pending_orders'] > 0): ?>
                            <span class="badge"><?= $stats['pending_orders'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="wishlist.php">
                        <i>❤️</i> Wishlist
                        <?php if ($stats['wishlist_count'] > 0): ?>
                            <span class="badge"><?= $stats['wishlist_count'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="profile.php">
                        <i>👤</i> Profile Settings
                    </a>
                </li>
                <li>
                    <a href="addresses.php">
                        <i>🏠</i> Saved Addresses
                    </a>
                </li>
                <li>
                    <a href="my_reviews.php">
                        <i>⭐</i> My Reviews
                    </a>
                </li>
                <li style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">
                    <a href="../index.php">
                        <i>🛍️</i> Continue Shopping
                    </a>
                </li>
                <li>
                    <a href="logout.php" style="color: #dc3545;">
                        <i>🚪</i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="welcome-section">
                <div class="welcome-title">
                    <h2>Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'] ?: $user['username'])[0]) ?>! 👋</h2>
                    <p>Here's what's happening with your account today.</p>
                </div>
                <a href="../index.php" class="btn-shop">🛒 Start Shopping</a>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="stat-number"><?= $stats['total_orders'] ?></div>
                    <div class="stat-label">All time orders</div>
                </div>
                <div class="stat-card">
                    <h3>Total Spent</h3>
                    <div class="stat-number">$<?= number_format($stats['total_spent'], 2) ?></div>
                    <div class="stat-label">Lifetime purchase</div>
                </div>
                <div class="stat-card">
                    <h3>Pending Orders</h3>
                    <div class="stat-number"><?= $stats['pending_orders'] ?></div>
                    <div class="stat-label">Awaiting processing</div>
                </div>
                <div class="stat-card">
                    <h3>Wishlist</h3>
                    <div class="stat-number"><?= $stats['wishlist_count'] ?></div>
                    <div class="stat-label">Saved items</div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div style="margin-bottom: 40px;">
                <div class="section-header">
                    <h3>📋 Recent Orders</h3>
                    <?php if ($stats['total_orders'] > 0): ?>
                        <a href="orders.php" style="color: #28a745; text-decoration: none;">View All →</a>
                    <?php endif; ?>
                </div>
                
                <?php if ($recent_orders->num_rows > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                    <td><strong>$<?= number_format($order['total_amount'], 2) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= $order['status'] ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: <?= $order['payment_status'] == 'paid' ? '#28a745' : '#856404' ?>;">
                                            <?= ucfirst(str_replace('_', ' ', $order['payment_status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-view">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i>📦</i>
                        <h4>No orders yet</h4>
                        <p>Looks like you haven't placed any orders yet.</p>
                        <a href="../index.php" class="btn-shop" style="display: inline-block; padding: 10px 20px;">Start Shopping</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recommended Products -->
            <?php if ($recommended_products->num_rows > 0): ?>
                <div>
                    <div class="section-header">
                        <h3>✨ Recommended for You</h3>
                        <a href="../index.php" style="color: #28a745; text-decoration: none;">View All →</a>
                    </div>
                    
                    <div class="product-grid">
                        <?php while ($product = $recommended_products->fetch_assoc()): ?>
                            <div class="product-card">
                                <div class="product-title"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                                <a href="../cart.php?add=<?= $product['id'] ?>" class="btn-add">Add to Cart</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>