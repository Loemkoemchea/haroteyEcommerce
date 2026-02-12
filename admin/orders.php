<?php
// file: admin/orders.php
require_once 'auth_check.php';

// =============================================
// 1. UPDATE ORDER STATUS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($order_id && in_array($status, ['pending', 'processing', 'completed', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Order #{$order_id} status updated to " . ucfirst($status);
        } else {
            $_SESSION['error'] = "Failed to update order #{$order_id}.";
        }
        $stmt->close();
    }
    header("Location: orders.php");
    exit;
}

// =============================================
// 2. FILTERS & PAGINATION
// =============================================
$status_filter = $_GET['status'] ?? '';
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';
$search       = $_GET['search'] ?? '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = ["1=1"];
$params = [];
$types = "";

if (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if (!empty($search)) {
    $where[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR id = ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = (int)$search;
    $types .= "sssi";
}

$where_clause = implode(" AND ", $where);

// =============================================
// 3. GET TOTAL ORDERS COUNT
// =============================================
$count_sql = "SELECT COUNT(*) FROM orders WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_orders);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_orders / $limit);

// =============================================
// 4. FETCH ORDERS FOR CURRENT PAGE
// =============================================
$sql = "SELECT * FROM orders WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

// =============================================
// 5. ORDER STATISTICS (for summary cards)
// =============================================
$stats = [];
$statuses = ['pending', 'processing', 'completed', 'cancelled'];
foreach ($statuses as $status) {
    $result = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(total_amount),0) as total FROM orders WHERE status = '$status'");
    $stats[$status] = $result->fetch_assoc();
}
$total_revenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM orders WHERE status != 'cancelled'")->fetch_assoc()['total'];

// =============================================
// 6. RETRIEVE SESSION FLASH MESSAGES
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
    <title>Orders - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .filters { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filter-form { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; margin-bottom: 5px; font-size: 12px; color: #666; }
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .order-table { width: 100%; border-collapse: collapse; }
        .order-table th { background: #f2f2f2; padding: 12px; text-align: left; }
        .order-table td { padding: 15px 12px; border-bottom: 1px solid #dee2e6; }
        .order-table tr:hover { background: #f8f9fa; }
        .status-form { display: flex; gap: 5px; }
        .status-select { padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .summary-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #28a745; }
        .summary-card h4 { margin: 0 0 5px 0; color: #666; font-size: 13px; }
        .summary-number { font-size: 24px; font-weight: bold; color: #333; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; }
        .page-link { padding: 8px 14px; border: 1px solid #dee2e6; border-radius: 4px; color: #28a745; text-decoration: none; }
        .page-link.active { background: #28a745; color: white; border-color: #28a745; }
        .alert-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>📦 Order Management</h1>
            <a href="dashboard.php" class="btn" style="background: #6c757d;">← Dashboard</a>
        </div>

        <?php if ($success): ?>
            <div class="alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Order Statistics -->
        <div class="summary-cards">
            <div class="summary-card">
                <h4>Total Orders</h4>
                <div class="summary-number"><?= $total_orders ?></div>
            </div>
            <div class="summary-card">
                <h4>Total Revenue</h4>
                <div class="summary-number">$<?= number_format($total_revenue, 2) ?></div>
            </div>
            <div class="summary-card" style="border-left-color: #ffc107;">
                <h4>Pending</h4>
                <div class="summary-number"><?= $stats['pending']['count'] ?? 0 ?></div>
                <small>$<?= number_format($stats['pending']['total'] ?? 0, 2) ?></small>
            </div>
            <div class="summary-card" style="border-left-color: #28a745;">
                <h4>Completed</h4>
                <div class="summary-number"><?= $stats['completed']['count'] ?? 0 ?></div>
                <small>$<?= number_format($stats['completed']['total'] ?? 0, 2) ?></small>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Order Status</label>
                    <select name="status" class="status-select" style="width: 100%;">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="width: 100%;">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="width: 100%;">
                </div>
                <div class="filter-group" style="flex: 2;">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Order ID, Customer name, Email, Phone..." 
                           value="<?= htmlspecialchars($search) ?>" style="width: 100%;">
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn" style="background: #007bff;">Apply Filters</button>
                    <a href="orders.php" class="btn" style="background: #6c757d;">Reset</a>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <?php if ($orders && $orders->num_rows > 0): ?>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?= $order['id'] ?></strong></td>
                            <td>
                                <?= htmlspecialchars($order['customer_name']) ?><br>
                                <small style="color: #666;"><?= htmlspecialchars($order['customer_email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                            <td><strong>$<?= number_format($order['total_amount'], 2) ?></strong></td>
                            <td>
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?= date('M d, Y', strtotime($order['created_at'])) ?><br>
                                <small style="color: #666;"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                            </td>
                            <td>
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                <a href="order_detail.php?id=<?= $order['id'] ?>" style="color: #007bff; margin-top: 5px; display: inline-block;">View Details →</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">← Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 8px;">
                <h3 style="color: #666;">No orders found</h3>
                <p>Try adjusting your filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>