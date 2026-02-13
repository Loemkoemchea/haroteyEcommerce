<?php
// file: user/write_review.php
require_once 'auth_check.php';

// =============================================
// 1. VALIDATE REQUIRED PARAMETERS
// =============================================
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$order_id || !$product_id) {
    $_SESSION['error'] = "Invalid request. Missing product or order information.";
    header("Location: orders.php");
    exit;
}

// =============================================
// 2. VERIFY ORDER BELONGS TO USER AND IS COMPLETED
// =============================================
$order_check = $conn->prepare("
    SELECT id, status, created_at FROM orders 
    WHERE id = ? AND user_id = ? AND status = 'completed'
");
$order_check->bind_param("ii", $order_id, $user_id);
$order_check->execute();
$order = $order_check->get_result()->fetch_assoc();
$order_check->close();

if (!$order) {
    $_SESSION['error'] = "You can only review products from completed orders.";
    header("Location: orders.php");
    exit;
}

// =============================================
// 3. VERIFY PRODUCT IS IN THIS ORDER
// =============================================
$item_check = $conn->prepare("
    SELECT id, product_name FROM order_items 
    WHERE order_id = ? AND product_id = ?
");
$item_check->bind_param("ii", $order_id, $product_id);
$item_check->execute();
$item = $item_check->get_result()->fetch_assoc();
$item_check->close();

if (!$item) {
    $_SESSION['error'] = "Product not found in this order.";
    header("Location: order_detail.php?id=" . $order_id);
    exit;
}

// =============================================
// 4. CHECK IF ALREADY REVIEWED
// =============================================
$review_check = $conn->prepare("
    SELECT id FROM product_reviews 
    WHERE user_id = ? AND product_id = ? AND order_id = ?
");
$review_check->bind_param("iii", $user_id, $product_id, $order_id);
$review_check->execute();
$existing_review = $review_check->get_result()->fetch_assoc();
$review_check->close();

if ($existing_review) {
    $_SESSION['info'] = "You have already reviewed this product.";
    header("Location: my_reviews.php");
    exit;
}

// =============================================
// 5. FETCH PRODUCT DETAILS FOR DISPLAY
// =============================================
$product_stmt = $conn->prepare("
    SELECT name, price, 
           (SELECT image_path FROM product_images WHERE product_id = products.id AND is_primary = 1 LIMIT 1) AS image
    FROM products WHERE id = ?
");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product = $product_stmt->get_result()->fetch_assoc();
$product_stmt->close();

// =============================================
// 6. HANDLE FORM SUBMISSION
// =============================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $title = trim($_POST['title'] ?? '');
    $review_text = trim($_POST['review'] ?? '');
    $pros = trim($_POST['pros'] ?? '');
    $cons = trim($_POST['cons'] ?? '');

    $errors = [];
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating.";
    }
    if (empty($review_text)) {
        $errors[] = "Review text cannot be empty.";
    }

    if (empty($errors)) {
        // Set verified_purchase to TRUE (they ordered it)
        $verified = 1;
        $status = 'approved'; // ✅ auto‑approve for testing
        $created_at = date('Y-m-d H:i:s');

        // DEBUG: Uncomment to see if query fails
        // echo "Inserting: product=$product_id, user=$user_id, order=$order_id, rating=$rating, title=$title, review=$review_text, pros=$pros, cons=$cons, status=$status, verified=$verified<br>";

        $stmt = $conn->prepare("
            INSERT INTO product_reviews (
                product_id, user_id, order_id, rating, title, review, 
                pros, cons, status, verified_purchase, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiiisssssis",
            $product_id,
            $user_id,
            $order_id,
            $rating,
            $title,
            $review_text,
            $pros,
            $cons,
            $status,
            $verified,
            $created_at
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Your review has been submitted and is pending approval. Thank you!";
            header("Location: my_reviews.php");
            exit;
        } else {
            // Capture database error
            $error = "Database error: " . $conn->error;
            error_log("Review insert failed: " . $conn->error);
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }

    // Update product rating_avg and rating_count
    $update_rating = $conn->prepare("
        UPDATE products SET 
            rating_avg = (SELECT AVG(rating) FROM product_reviews WHERE product_id = ? AND status = 'approved'),
            rating_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = ? AND status = 'approved')
        WHERE id = ?
    ");
    $update_rating->bind_param("iii", $product_id, $product_id, $product_id);
    $update_rating->execute();
    $update_rating->close();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write a Review - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .review-container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .product-summary { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .product-image { width: 80px; height: 80px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-info { flex: 1; }
        .product-name { font-size: 18px; font-weight: 600; color: #333; }
        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; }
        .rating-stars input { display: none; }
        .rating-stars label { font-size: 30px; color: #e4e5e9; cursor: pointer; transition: color 0.2s; }
        .rating-stars input:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ label { color: #ffc107; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: #333; }
        .form-group input[type="text"], .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-primary { background: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .alert-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="review-container">
        <div class="review-header">
            <h1>✍️ Write a Review</h1>
            <a href="order_detail.php?id=<?= $order_id ?>" class="btn-secondary">← Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Product Summary -->
        <div class="product-summary">
            <div class="product-image">
                <?php if (!empty($product['image'])): ?>
                    <img src="../<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <?php else: ?>
                    <span style="font-size: 30px;">🖼️</span>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                <div style="color: #666; margin-top: 5px;">
                    Order #<?= $order_id ?> • Purchased on <?= date('M d, Y', strtotime($order['created_at'])) ?>
                </div>
                <div style="font-weight: 700; color: #28a745; margin-top: 8px;">
                    ৳<?= number_format($product['price'], 2) ?>
                </div>
            </div>
        </div>

        <!-- Review Form -->
        <form method="POST" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="form-group">
                <label>Your Rating *</label>
                <div class="rating-stars">
                    <input type="radio" name="rating" value="5" id="star5" <?= (isset($_POST['rating']) && $_POST['rating'] == 5) ? 'checked' : '' ?>>
                    <label for="star5">★</label>
                    <input type="radio" name="rating" value="4" id="star4" <?= (isset($_POST['rating']) && $_POST['rating'] == 4) ? 'checked' : '' ?>>
                    <label for="star4">★</label>
                    <input type="radio" name="rating" value="3" id="star3" <?= (isset($_POST['rating']) && $_POST['rating'] == 3) ? 'checked' : '' ?>>
                    <label for="star3">★</label>
                    <input type="radio" name="rating" value="2" id="star2" <?= (isset($_POST['rating']) && $_POST['rating'] == 2) ? 'checked' : '' ?>>
                    <label for="star2">★</label>
                    <input type="radio" name="rating" value="1" id="star1" <?= (isset($_POST['rating']) && $_POST['rating'] == 1) ? 'checked' : '' ?>>
                    <label for="star1">★</label>
                </div>
            </div>

            <div class="form-group">
                <label for="title">Review Title (Optional)</label>
                <input type="text" id="title" name="title" placeholder="Summarize your experience" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="review">Your Review *</label>
                <textarea id="review" name="review" rows="5" placeholder="Tell others what you liked or disliked..." required><?= htmlspecialchars($_POST['review'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pros">Pros (Optional)</label>
                    <textarea id="pros" name="pros" rows="3" placeholder="What did you like?"><?= htmlspecialchars($_POST['pros'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="cons">Cons (Optional)</label>
                    <textarea id="cons" name="cons" rows="3" placeholder="What could be improved?"><?= htmlspecialchars($_POST['cons'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" name="submit_review" class="btn-primary" style="flex: 1;">✅ Submit Review</button>
                <a href="order_detail.php?id=<?= $order_id ?>" class="btn-secondary" style="flex: 0.3; text-align: center;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>