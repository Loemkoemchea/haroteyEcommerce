<?php
// file: user/edit_review.php
require_once 'auth_check.php';

$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$review_id) {
    $_SESSION['error'] = "Invalid review ID.";
    header("Location: my_reviews.php");
    exit;
}

// ✅ Fetch review – verify it belongs to the user (any status)
$stmt = $conn->prepare("
    SELECT pr.*, p.name AS product_name, p.price, 
           o.order_number, o.created_at AS order_date,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS product_image
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    LEFT JOIN orders o ON pr.order_id = o.id
    WHERE pr.id = ? AND pr.user_id = ?
");
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$review) {
    $_SESSION['error'] = "Review not found.";
    header("Location: my_reviews.php");
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_review'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $title = trim($_POST['title'] ?? '');
    $review_text = trim($_POST['review'] ?? '');
    $pros = trim($_POST['pros'] ?? '');
    $cons = trim($_POST['cons'] ?? '');

    $errors = [];
    if ($rating < 1 || $rating > 5) $errors[] = "Please select a rating.";
    if (empty($review_text)) $errors[] = "Review text cannot be empty.";

    if (empty($errors)) {
        // ✅ Always set status to 'pending' after edit – requires admin re-approval
        $new_status = 'Approved';
        
        $stmt = $conn->prepare("
            UPDATE product_reviews SET 
                rating = ?, 
                title = ?, 
                review = ?, 
                pros = ?, 
                cons = ?, 
                status = ?,
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("isssssii", $rating, $title, $review_text, $pros, $cons, $new_status, $review_id, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Your review has been updated and is pending re-approval.";
            header("Location: my_reviews.php");
            exit;
        } else {
            $error = "Failed to update review: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Review - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .edit-container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .product-summary { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .product-image { width: 80px; height: 80px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; }
        .rating-stars input { display: none; }
        .rating-stars label { font-size: 30px; color: #e4e5e9; cursor: pointer; transition: color 0.2s; }
        .rating-stars input:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ label { color: #ffc107; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input[type="text"], .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; }
        .form-group textarea:focus, .form-group input:focus { border-color: #28a745; outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-primary { background: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-primary:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #5a6268; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .note { background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="edit-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>✏️ Edit Your Review</h1>
            <a href="my_reviews.php" class="btn-secondary">← Back to My Reviews</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($review['status'] !== 'pending'): ?>
            <div class="note">
                📝 <strong>Note:</strong> This review is currently <strong><?= $review['status'] ?></strong>. 
                After editing, it will be set to <strong>pending</strong> and require admin approval again.
            </div>
        <?php endif; ?>

        <div class="product-summary">
            <div class="product-image">
                <?php if (!empty($review['product_image'])): ?>
                    <img src="../<?= htmlspecialchars($review['product_image']) ?>" alt="<?= htmlspecialchars($review['product_name']) ?>">
                <?php else: ?>
                    <span style="font-size: 30px;">🖼️</span>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="margin: 0 0 5px;"><?= htmlspecialchars($review['product_name']) ?></h3>
                <div style="color: #666;">Order: #<?= htmlspecialchars($review['order_number'] ?? 'N/A') ?></div>
                <div style="font-weight: 700; color: #28a745; margin-top: 5px;">৳<?= number_format($review['price'], 2) ?></div>
            </div>
        </div>

        <form method="POST" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <div class="form-group">
                <label>Your Rating *</label>
                <div class="rating-stars">
                    <input type="radio" name="rating" value="5" id="star5" <?= $review['rating'] == 5 ? 'checked' : '' ?>>
                    <label for="star5">★</label>
                    <input type="radio" name="rating" value="4" id="star4" <?= $review['rating'] == 4 ? 'checked' : '' ?>>
                    <label for="star4">★</label>
                    <input type="radio" name="rating" value="3" id="star3" <?= $review['rating'] == 3 ? 'checked' : '' ?>>
                    <label for="star3">★</label>
                    <input type="radio" name="rating" value="2" id="star2" <?= $review['rating'] == 2 ? 'checked' : '' ?>>
                    <label for="star2">★</label>
                    <input type="radio" name="rating" value="1" id="star1" <?= $review['rating'] == 1 ? 'checked' : '' ?>>
                    <label for="star1">★</label>
                </div>
            </div>

            <div class="form-group">
                <label for="title">Review Title (Optional)</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($review['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="review">Your Review *</label>
                <textarea id="review" name="review" rows="5" required><?= htmlspecialchars($review['review'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pros">Pros (Optional)</label>
                    <textarea id="pros" name="pros" rows="3"><?= htmlspecialchars($review['pros'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="cons">Cons (Optional)</label>
                    <textarea id="cons" name="cons" rows="3"><?= htmlspecialchars($review['cons'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" name="update_review" class="btn-primary" style="flex: 1;">💾 Update Review</button>
                <a href="my_reviews.php" class="btn-secondary" style="flex: 0.3; text-align: center;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>