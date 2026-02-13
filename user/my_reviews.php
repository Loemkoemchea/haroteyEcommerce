<?php
// file: user/my_reviews.php
require_once 'auth_check.php';

$message = '';
$error   = '';

// =============================================
// 1. HANDLE DELETE ACTION
// =============================================
if (isset($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];

    // Verify this review belongs to the user
    $check = $conn->prepare("SELECT id FROM product_reviews WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $review_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $delete = $conn->prepare("DELETE FROM product_reviews WHERE id = ?");
        $delete->bind_param("i", $review_id);
        $delete->execute();
        if ($delete->affected_rows > 0) {
            $_SESSION['success'] = "Review deleted successfully.";
        }
        $delete->close();
    }
    $check->close();
    header("Location: my_reviews.php");
    exit;
}

// =============================================
// 2. PAGINATION SETUP
// =============================================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM product_reviews WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_stmt->bind_result($total_reviews);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_reviews / $limit);

// =============================================
// 3. FETCH USER REVIEWS WITH PRODUCT DETAILS
// =============================================
$reviews = $conn->prepare("
    SELECT 
        pr.id,
        pr.rating,
        pr.title,
        pr.review,
        pr.pros,
        pr.cons,
        pr.status,
        pr.created_at,
        pr.updated_at,
        pr.order_id,
        p.id AS product_id,
        p.name AS product_name,
        p.slug,
        p.price,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS product_image,
        o.order_number
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    LEFT JOIN orders o ON pr.order_id = o.id
    WHERE pr.user_id = ?
    ORDER BY pr.created_at DESC
    LIMIT ? OFFSET ?
");
$reviews->bind_param("iii", $user_id, $limit, $offset);
$reviews->execute();
$user_reviews = $reviews->get_result();
$reviews->close();

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
    <title>My Reviews - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .reviews-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; }
        .header h1 { margin: 0; color: #333; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .empty-state { text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 12px; }
        .review-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); padding: 25px; margin-bottom: 25px; border: 1px solid #eee; transition: all 0.2s; }
        .review-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; }
        .product-info { display: flex; gap: 15px; flex: 1; }
        .product-image { width: 70px; height: 70px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-image img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .product-details h3 { margin: 0 0 5px; font-size: 18px; }
        .product-details h3 a { color: #333; text-decoration: none; }
        .product-details h3 a:hover { color: #28a745; }
        .product-price { font-weight: 600; color: #28a745; }
        .order-ref { font-size: 13px; color: #666; margin-top: 5px; }
        .review-status { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .rating { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .stars { display: flex; gap: 2px; }
        .star-filled { color: #ffc107; }
        .star-empty { color: #e4e5e9; }
        .rating-number { font-weight: 600; color: #333; }
        .review-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 10px; }
        .review-text { color: #555; line-height: 1.6; margin-bottom: 15px; white-space: pre-line; }
        .pros-cons { display: flex; gap: 30px; margin-bottom: 15px; flex-wrap: wrap; }
        .pros, .cons { flex: 1; min-width: 200px; }
        .pros h4, .cons h4 { margin: 0 0 8px; font-size: 15px; display: flex; align-items: center; gap: 5px; }
        .pros h4 { color: #28a745; }
        .cons h4 { color: #dc3545; }
        .review-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
        .review-date { color: #999; font-size: 13px; }
        .review-actions { display: flex; gap: 10px; }
        .btn-edit { background: #ffc107; color: #333; padding: 6px 14px; border-radius: 30px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete { background: #dc3545; color: white; padding: 6px 14px; border-radius: 30px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .btn-delete:hover { background: #c82333; }
        .btn-primary { background: #28a745; color: white; padding: 12px 25px; border-radius: 30px; text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-primary:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 25px; border-radius: 30px; text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-secondary:hover { background: #5a6268; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 40px; }
        .page-link { padding: 8px 14px; border: 1px solid #dee2e6; border-radius: 30px; color: #28a745; text-decoration: none; }
        .page-link.active { background: #28a745; color: white; border-color: #28a745; }
        .page-link:hover { background: #e9ecef; }
        @media (max-width: 768px) {
            .review-header { flex-direction: column; }
            .review-status { align-items: flex-start; margin-top: 10px; }
        }
    </style>
</head>
<body>
    <div class="reviews-container">
        <div class="header">
            <h1>⭐ My Product Reviews</h1>
            <div>
                <a href="dashboard.php" class="btn-secondary">← Dashboard</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($user_reviews->num_rows > 0): ?>
            <?php while ($review = $user_reviews->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="product-info">
                            <div class="product-image">
                                <?php if (!empty($review['product_image'])): ?>
                                    <img src="../<?= htmlspecialchars($review['product_image']) ?>" alt="<?= htmlspecialchars($review['product_name']) ?>">
                                <?php else: ?>
                                    <span style="font-size: 24px;">🖼️</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-details">
                                <h3>
                                    <a href="../product.php?id=<?= $review['product_id'] ?>">
                                        <?= htmlspecialchars($review['product_name']) ?>
                                    </a>
                                </h3>
                                <div class="product-price">৳<?= number_format($review['price'], 2) ?></div>
                                <?php if (!empty($review['order_number'])): ?>
                                    <div class="order-ref">
                                        Order: <a href="order_detail.php?id=<?= $review['order_id'] ?>" style="color: #28a745;">
                                            #<?= htmlspecialchars($review['order_number']) ?>
                                        </a>
                                        <span style="margin-left: 8px; background: #28a745; color: white; padding: 2px 8px; border-radius: 30px; font-size: 11px;">Verified Purchase</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="review-status">
                            <span class="status-badge status-<?= $review['status'] ?>">
                                <?= ucfirst($review['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="<?= $i <= $review['rating'] ? 'star-filled' : 'star-empty' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-number"><?= $review['rating'] ?>/5</span>
                    </div>

                    <?php if (!empty($review['title'])): ?>
                        <div class="review-title"><?= htmlspecialchars($review['title']) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($review['review'])): ?>
                        <div class="review-text"><?= nl2br(htmlspecialchars($review['review'])) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($review['pros']) || !empty($review['cons'])): ?>
                        <div class="pros-cons">
                            <?php if (!empty($review['pros'])): ?>
                                <div class="pros">
                                    <h4>✅ Pros</h4>
                                    <p style="color: #666;"><?= nl2br(htmlspecialchars($review['pros'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($review['cons'])): ?>
                                <div class="cons">
                                    <h4>❌ Cons</h4>
                                    <p style="color: #666;"><?= nl2br(htmlspecialchars($review['cons'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="review-footer">
                        <div class="review-date">
                            Reviewed on <?= date('M d, Y', strtotime($review['created_at'])) ?>
                            <?php if ($review['updated_at'] != $review['created_at']): ?>
                                <br><span style="font-size: 12px;">(edited <?= date('M d, Y', strtotime($review['updated_at'])) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="review-actions">
                            <?php if ($review['status'] === 'pending'): ?>
                                <a href="edit_review.php?id=<?= $review['id'] ?>" class="btn-edit">✏️ Edit</a>
                            <?php else: ?>
                                <a href="edit_review.php?id=<?= $review['id'] ?>" class="btn-edit">✏️ Edit</a>
                            <?php endif; ?>
                            <a href="?delete=<?= $review['id'] ?>" class="btn-delete" onclick="return confirm('Delete this review permanently?')">🗑️ Delete</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="page-link">← Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="page-link">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 60px; margin-bottom: 20px;">📝</div>
                <h2>You haven't written any reviews yet</h2>
                <p>Share your experience with products you've purchased!</p>
                <a href="orders.php?status=completed" class="btn-primary" style="display: inline-block; padding: 12px 30px;">
                    📦 Review Your Purchases
                </a>
                <br>
                <a href="../index.php" style="display: inline-block; margin-top: 20px; color: #28a745;">Continue Shopping →</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>