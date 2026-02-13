<?php
// file: admin/reviews.php
require_once 'auth_check.php';

// Handle approve/reject/delete actions
if (isset($_GET['approve'])) {
    $review_id = (int)$_GET['approve'];
    $conn->query("UPDATE product_reviews SET status = 'approved' WHERE id = $review_id");
    
    // Update product rating
    $product_id = $conn->query("SELECT product_id FROM product_reviews WHERE id = $review_id")->fetch_assoc()['product_id'];
    $conn->query("
        UPDATE products SET 
            rating_avg = (SELECT AVG(rating) FROM product_reviews WHERE product_id = $product_id AND status = 'approved'),
            rating_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = $product_id AND status = 'approved')
        WHERE id = $product_id
    ");
    $_SESSION['success'] = "Review approved and product rating updated.";
    header("Location: reviews.php");
    exit;
}

if (isset($_GET['reject'])) {
    $review_id = (int)$_GET['reject'];
    $conn->query("UPDATE product_reviews SET status = 'rejected' WHERE id = $review_id");
    $_SESSION['success'] = "Review rejected.";
    header("Location: reviews.php");
    exit;
}

if (isset($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];
    // Get product_id before deletion to update rating
    $product_id = $conn->query("SELECT product_id FROM product_reviews WHERE id = $review_id")->fetch_assoc()['product_id'];
    $conn->query("DELETE FROM product_reviews WHERE id = $review_id");
    // Update product rating
    if ($product_id) {
        $conn->query("
            UPDATE products SET 
                rating_avg = COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = $product_id AND status = 'approved'), 0),
                rating_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = $product_id AND status = 'approved')
            WHERE id = $product_id
        ");
    }
    $_SESSION['success'] = "Review deleted.";
    header("Location: reviews.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter by status
$status_filter = $_GET['status'] ?? '';
$where = "1=1";
if ($status_filter) {
    $where .= " AND pr.status = '" . $conn->real_escape_string($status_filter) . "'";
}

// Total count
$total = $conn->query("SELECT COUNT(*) FROM product_reviews pr WHERE $where")->fetch_row()[0];
$total_pages = ceil($total / $limit);

// Fetch reviews
$reviews = $conn->query("
    SELECT 
        pr.*,
        u.username,
        u.email,
        p.name AS product_name,
        p.slug,
        o.order_number
    FROM product_reviews pr
    JOIN users u ON pr.user_id = u.id
    JOIN products p ON pr.product_id = p.id
    LEFT JOIN orders o ON pr.order_id = o.id
    WHERE $where
    ORDER BY pr.created_at DESC
    LIMIT $limit OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tab { padding: 8px 16px; border-radius: 30px; text-decoration: none; color: #666; background: #f8f9fa; }
        .filter-tab.active { background: #28a745; color: white; }
        .table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table th { background: #f8f9fa; padding: 15px; text-align: left; }
        .table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .btn { padding: 6px 14px; border-radius: 30px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-block; margin-right: 5px; }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; }
        .page-link { padding: 8px 14px; border: 1px solid #dee2e6; border-radius: 30px; color: #28a745; text-decoration: none; }
        .page-link.active { background: #28a745; color: white; border-color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⭐ Manage Product Reviews</h1>
            <a href="dashboard.php" class="btn" style="background: #6c757d; color: white;">← Dashboard</a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <a href="reviews.php" class="filter-tab <?= !$status_filter ? 'active' : '' ?>">All</a>
            <a href="reviews.php?status=pending" class="filter-tab <?= $status_filter == 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="reviews.php?status=approved" class="filter-tab <?= $status_filter == 'approved' ? 'active' : '' ?>">Approved</a>
            <a href="reviews.php?status=rejected" class="filter-tab <?= $status_filter == 'rejected' ? 'active' : '' ?>">Rejected</a>
        </div>

        <?php if ($reviews->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = $reviews->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td>
                                <a href="../product.php?id=<?= $r['product_id'] ?>">
                                    <?= htmlspecialchars($r['product_name']) ?>
                                </a>
                                <br><small>Order: <?= htmlspecialchars($r['order_number'] ?? 'N/A') ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($r['username']) ?><br>
                                <small><?= htmlspecialchars($r['email']) ?></small>
                            </td>
                            <td style="font-weight: 600;"><?= $r['rating'] ?>/5</td>
                            <td>
                                <strong><?= htmlspecialchars($r['title'] ?? '') ?></strong><br>
                                <?= htmlspecialchars(substr($r['review'] ?? '', 0, 50)) ?>...
                            </td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $r['status'] ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r['status'] !== 'approved'): ?>
                                    <a href="?approve=<?= $r['id'] ?>&status=<?= $status_filter ?>" class="btn btn-approve" onclick="return confirm('Approve this review?')">✓ Approve</a>
                                <?php endif; ?>
                                <a href="?delete=<?= $r['id'] ?>&status=<?= $status_filter ?>" class="btn btn-delete" onclick="return confirm('Permanently delete this review?')">🗑 Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= $status_filter ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p style="text-align: center; padding: 50px; background: white; border-radius: 12px;">No reviews found.</p>
        <?php endif; ?>
    </div>
</body>
</html>