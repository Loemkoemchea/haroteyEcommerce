<?php
// file: admin/dashboard.php
require_once 'auth_check.php';  // ✅ this already includes db.php and checks admin role

// Get statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$total_customers = $conn->query("SELECT COUNT(DISTINCT customer_email) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'")->fetch_assoc()['total'] ?? 0;

// Get recent orders
$recent_orders = $conn->query("
    SELECT * FROM orders 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Get low stock products (stock_quantity < 5 AND > 0)
$low_stock = $conn->query("
    SELECT * FROM products 
    WHERE stock_quantity < 5 AND stock_quantity > 0 
    ORDER BY stock_quantity ASC 
    LIMIT 5
");

// Get out of stock products
$out_of_stock = $conn->query("
    SELECT * FROM products 
    WHERE stock_quantity = 0 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #28a745; }
        .stat-card h3 { margin: 0 0 10px 0; color: #666; font-size: 14px; text-transform: uppercase; }
        .stat-number { font-size: 32px; font-weight: bold; color: #333; }
        .stat-desc { color: #666; font-size: 12px; margin-top: 5px; }
        .warning { color: #dc3545; }
        .warning-border { border-left-color: #dc3545; }
        .warning-text { color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 4px; }
        .admin-nav { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; }
        .admin-nav a { padding: 8px 16px; text-decoration: none; color: #495057; border-radius: 4px; }
        .admin-nav a:hover { background: #e9ecef; }
        .admin-nav a.active { background: #28a745; color: white; }
        .section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { margin-top: 0; font-size: 18px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-pending { background: #ffc107; color: #856404; }
        .badge-processing { background: #17a2b8; color: white; }
        .badge-completed { background: #28a745; color: white; }
        .badge-cancelled { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>🛍️ Harotey Admin Panel</h1>
            <div>
                <span style="margin-right: 15px;">Welcome, <?= htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Admin') ?>!</span>
                <a href="logout.php" class="btn" style="background: #dc3545;">Logout</a>
            </div>
        </div>

        <div class="admin-nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="add_product.php">+ Add Product</a>
            <a href="settings.php">Settings</a>
            <a href="../index.php" target="_blank">View Shop</a>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <h3>Total Products</h3>
                <div class="stat-number"><?= $total_products ?></div>
                <div class="stat-desc">Active products in catalog</div>
            </div>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="stat-number"><?= $total_orders ?></div>
                <div class="stat-desc">All time orders</div>
            </div>
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="stat-number"><?= $total_customers ?></div>
                <div class="stat-desc">Unique customers</div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-number">$<?= number_format($total_revenue, 2) ?></div>
                <div class="stat-desc">Completed orders</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Recent Orders -->
            <div class="section">
                <h2>📦 Recent Orders</h2>
                <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $order['status'] ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order_detail.php?id=<?= $order['id'] ?>" style="color: #007bff;">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <p style="text-align: right; margin-top: 15px;">
                        <a href="orders.php">View All Orders →</a>
                    </p>
                <?php else: ?>
                    <p>No orders yet.</p>
                <?php endif; ?>
            </div>

            <!-- Stock Alerts -->
            <div class="section">
                <h2>⚠️ Stock Alerts</h2>
                
                <?php if ($low_stock && $low_stock->num_rows > 0): ?>
                    <h3 style="font-size: 16px; color: #856404;">Low Stock (Less than 5)</h3>
                    <table style="width: 100%; margin-bottom: 20px;">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $low_stock->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td style="color: #856404; font-weight: bold;"><?= $product['stock_quantity'] ?></td>
                                    <td>
                                        <a href="edit_product.php?id=<?= $product['id'] ?>" style="color: #007bff;">Restock</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($out_of_stock && $out_of_stock->num_rows > 0): ?>
                    <h3 style="font-size: 16px; color: #dc3545;">Out of Stock</h3>
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $out_of_stock->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td style="color: #dc3545; font-weight: bold;">0</td>
                                    <td>
                                        <a href="edit_product.php?id=<?= $product['id'] ?>" style="color: #007bff;">Restock</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ((!$low_stock || $low_stock->num_rows == 0) && (!$out_of_stock || $out_of_stock->num_rows == 0)): ?>
                    <p style="color: #28a745;">✓ All products have sufficient stock</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section" style="margin-top: 20px;">
            <h2>⚡ Quick Actions</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="add_product.php" class="btn" style="background: #28a745;">➕ Add New Product</a>
                <a href="products.php" class="btn" style="background: #007bff;">📋 Manage Products</a>
                <a href="orders.php" class="btn" style="background: #17a2b8;">📦 View All Orders</a>
                <a href="../index.php" class="btn" style="background: #6c757d;" target="_blank">🛒 Visit Shop</a>
            </div>
        </div>
    </div>
</body>
</html>