<?php
// file: product.php
session_start();
include 'db.php';

// ---------- 1. GET PRODUCT ID ----------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// ---------- 2. FETCH PRODUCT DETAILS ----------
$stmt = $conn->prepare("
    SELECT p.*, 
           c.name AS category_name, 
           c.slug AS category_slug,
           b.name AS brand_name,
           b.slug AS brand_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: index.php");
    exit;
}

// ---------- 3. FETCH PRODUCT IMAGES ----------
$images = $conn->prepare("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, sort_order ASC
");
$images->bind_param("i", $id);
$images->execute();
$product_images = $images->get_result();
$images->close();

// ---------- 4. FETCH PRODUCT REVIEWS ----------
$reviews = $conn->prepare("
    SELECT pr.*, u.full_name, u.username
    FROM product_reviews pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.product_id = ? AND pr.status = 'approved'
    ORDER BY pr.created_at DESC
    LIMIT 10
");
$reviews->bind_param("i", $id);
$reviews->execute();
$product_reviews = $reviews->get_result();
$reviews->close();

// Get review summary
$review_summary = $conn->prepare("
    SELECT 
        COUNT(*) AS total_reviews,
        AVG(rating) AS avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS rating_5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS rating_4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS rating_3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS rating_2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS rating_1
    FROM product_reviews
    WHERE product_id = ? AND status = 'approved'
");
$review_summary->bind_param("i", $id);
$review_summary->execute();
$review_stats = $review_summary->get_result()->fetch_assoc();
$review_summary->close();

// ---------- 5. FETCH RELATED PRODUCTS (same category) ----------
$related = $conn->prepare("
    SELECT id, name, price, slug,
           (SELECT image_path FROM product_images 
            WHERE product_id = products.id AND is_primary = 1 LIMIT 1) AS image
    FROM products
    WHERE category_id = ? AND id != ? AND is_active = 1
    LIMIT 4
");
$related->bind_param("ii", $product['category_id'], $id);
$related->execute();
$related_products = $related->get_result();
$related->close();

// ---------- 6. DECODE SPECIFICATIONS (JSON) ----------
$specs = [];
if (!empty($product['specification'])) {
    $specs = json_decode($product['specification'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Harotey Shop</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Product Page Styles */
        * {
            box-sizing: border-box;
        }
        body {
            background: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .product-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            gap: 8px;
            margin-bottom: 25px;
            color: #666;
            font-size: 14px;
        }
        .breadcrumb a {
            color: #28a745;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        /* Product Main */
        .product-main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        /* Gallery */
        .gallery {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .main-image {
            width: 100%;
            height: 400px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 60px;
            overflow: hidden;
        }
        .main-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .thumbnail-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .thumbnail {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border: 2px solid transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
        }
        .thumbnail img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .thumbnail.active {
            border-color: #28a745;
        }
        /* Product Info */
        .product-info {
            display: flex;
            flex-direction: column;
        }
        .product-category {
            color: #28a745;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .product-category a {
            color: #28a745;
            text-decoration: none;
        }
        .product-title {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        .product-rating {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        .stars {
            display: flex;
            gap: 3px;
        }
        .star-filled {
            color: #ffc107;
        }
        .star-empty {
            color: #e4e5e9;
        }
        .rating-value {
            font-weight: 600;
            color: #333;
        }
        .review-count {
            color: #666;
            font-size: 14px;
        }
        .product-price {
            display: flex;
            align-items: baseline;
            gap: 15px;
            margin-bottom: 20px;
        }
        .current-price {
            font-size: 36px;
            font-weight: 700;
            color: #28a745;
        }
        .old-price {
            font-size: 20px;
            color: #999;
            text-decoration: line-through;
        }
        .discount-badge {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }
        .product-stock {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .stock-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }
        .in-stock {
            background: #d4edda;
            color: #155724;
        }
        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        .product-short-desc {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        /* Quantity & Add to Cart */
        .add-to-cart-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .quantity-selector {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .quantity-btn {
            width: 44px;
            height: 44px;
            background: #f8f9fa;
            border: none;
            font-size: 20px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            transition: background 0.2s;
        }
        .quantity-btn:hover {
            background: #e9ecef;
        }
        .quantity-input {
            width: 60px;
            height: 44px;
            border: none;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-add-to-cart {
            flex: 1;
            height: 44px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 25px;
        }
        .btn-add-to-cart:hover {
            background: #218838;
        }
        .btn-add-to-cart:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .btn-wishlist {
            width: 44px;
            height: 44px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 20px;
            color: #dc3545;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-wishlist:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        .btn-wishlist.active {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        /* Product Meta */
        .product-meta {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        .meta-item {
            display: flex;
            margin-bottom: 8px;
        }
        .meta-label {
            width: 100px;
            font-weight: 600;
            color: #333;
        }
        .meta-value a {
            color: #28a745;
            text-decoration: none;
        }
        .meta-value a:hover {
            text-decoration: underline;
        }
        /* Tabs */
        .product-tabs {
            margin-top: 50px;
            border-top: 1px solid #eee;
        }
        .tab-headers {
            display: flex;
            gap: 30px;
            border-bottom: 1px solid #eee;
        }
        .tab-header {
            padding: 15px 0;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        .tab-header.active {
            color: #28a745;
            border-bottom-color: #28a745;
        }
        .tab-content {
            padding: 30px 0;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        /* Description */
        .description-content {
            line-height: 1.8;
            color: #555;
        }
        /* Specifications */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
        }
        .specs-table tr {
            border-bottom: 1px solid #eee;
        }
        .specs-table td {
            padding: 12px 0;
        }
        .specs-table td:first-child {
            width: 200px;
            font-weight: 600;
            color: #333;
        }
        /* Reviews */
        .reviews-summary {
            display: flex;
            gap: 40px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        .average-rating {
            text-align: center;
        }
        .average-number {
            font-size: 48px;
            font-weight: 700;
            color: #333;
        }
        .rating-bars {
            flex: 1;
        }
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .rating-label {
            width: 60px;
            font-size: 14px;
            color: #666;
        }
        .bar-container {
            flex: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: #ffc107;
            border-radius: 4px;
        }
        .rating-count {
            width: 40px;
            font-size: 14px;
            color: #666;
        }
        .review-item {
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .review-author {
            font-weight: 600;
            color: #333;
        }
        .review-date {
            color: #999;
            font-size: 13px;
        }
        .review-title {
            font-weight: 600;
            margin-bottom: 8px;
        }
        .review-text {
            color: #666;
            line-height: 1.6;
        }
        .btn-write-review {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        /* Related Products */
        .related-products {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
        .related-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
        }
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
        }
        .related-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
        }
        .related-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transform: translateY(-3px);
        }
        .related-image {
            height: 150px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 30px;
        }
        .related-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .related-name {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            text-decoration: none;
            margin-bottom: 8px;
            display: block;
        }
        .related-price {
            font-size: 18px;
            font-weight: 700;
            color: #28a745;
        }
        @media (max-width: 768px) {
            .product-main {
                grid-template-columns: 1fr;
            }
            .main-image {
                height: 300px;
            }
            .reviews-summary {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header (same as index) -->
    <header style="background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 15px 0; margin-bottom: 30px;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" style="font-size: 24px; font-weight: 700; color: #28a745; text-decoration: none;">🛍️ Harotey Shop</a>
            <nav>
                <a href="index.php" style="color: #333; text-decoration: none; margin: 0 10px;">Home</a>
                <a href="cart.php" style="color: #333; text-decoration: none; margin: 0 10px;">Cart</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user/dashboard.php" style="color: #333; text-decoration: none; margin: 0 10px;">Account</a>
                <?php else: ?>
                    <a href="user/index.php" style="color: #333; text-decoration: none; margin: 0 10px;">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="product-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span>/</span>
            <?php if ($product['category_name']): ?>
                <a href="index.php?category=<?= $product['category_id'] ?>">
                    <?= htmlspecialchars($product['category_name']) ?>
                </a>
                <span>/</span>
            <?php endif; ?>
            <span><?= htmlspecialchars($product['name']) ?></span>
        </div>

        <!-- Product Main Section -->
        <div class="product-main">
            <!-- Left: Gallery -->
            <div class="gallery">
                <?php 
                $primary_image = null;
                $gallery_images = [];
                while ($img = $product_images->fetch_assoc()) {
                    if ($img['is_primary']) {
                        $primary_image = $img['image_path'];
                    }
                    $gallery_images[] = $img['image_path'];
                }
                // If no images at all, use placeholder
                if (empty($gallery_images)) {
                    $gallery_images[] = null;
                }
                ?>
                <div class="main-image" id="mainImage">
                    <?php if ($gallery_images[0]): ?>
                        <img src="<?= htmlspecialchars($gallery_images[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        🖼️ No Image
                    <?php endif; ?>
                </div>
                <?php if (count($gallery_images) > 1): ?>
                <div class="thumbnail-list">
                    <?php foreach ($gallery_images as $index => $img): ?>
                        <div class="thumbnail <?= $index == 0 ? 'active' : '' ?>" 
                             onclick="document.getElementById('mainImage').innerHTML = '<?= $img ? '<img src=\"'.htmlspecialchars($img).'\">' : '🖼️ No Image' ?>';
                                      document.querySelectorAll('.thumbnail').forEach(el => el.classList.remove('active'));
                                      this.classList.add('active');">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="">
                            <?php else: ?>
                                🖼️
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Product Info -->
            <div class="product-info">
                <?php if ($product['category_name']): ?>
                    <div class="product-category">
                        <a href="index.php?category=<?= $product['category_id'] ?>">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </a>
                    </div>
                <?php endif; ?>

                <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

                <!-- Rating -->
                <div class="product-rating">
                    <div class="stars">
                        <?php 
                        $avg = $review_stats['avg_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?= $i <= round($avg) ? 'star-filled' : 'star-empty' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-value"><?= number_format($avg, 1) ?></span>
                    <span class="review-count">(<?= (int)($review_stats['total_reviews'] ?? 0) ?> reviews)</span>
                </div>

                <!-- Price -->
                <div class="product-price">
                    <span class="current-price">৳<?= number_format($product['price'], 2) ?></span>
                    <?php if ($product['compare_price'] > $product['price']): ?>
                        <span class="old-price">৳<?= number_format($product['compare_price'], 2) ?></span>
                        <span class="discount-badge">
                            -<?= round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100) ?>%
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Stock Status -->
                <div class="product-stock">
                    <span class="stock-badge <?= $product['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            ✅ In Stock (<?= $product['stock_quantity'] ?> available)
                        <?php else: ?>
                            ❌ Out of Stock
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Short Description -->
                <?php if (!empty($product['short_description'])): ?>
                    <div class="product-short-desc">
                        <?= nl2br(htmlspecialchars($product['short_description'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Add to Cart & Wishlist -->
                <?php if ($product['stock_quantity'] > 0): ?>
                    <form action="cart.php" method="get" class="add-to-cart-section">
                        <input type="hidden" name="add" value="<?= $product['id'] ?>">
                        <div class="quantity-selector">
                            <button type="button" class="quantity-btn" onclick="decrementQuantity()">−</button>
                            <input type="number" id="quantity" name="quantity" class="quantity-input" value="1" min="1" max="<?= $product['stock_quantity'] ?>" readonly>
                            <button type="button" class="quantity-btn" onclick="incrementQuantity(<?= $product['stock_quantity'] ?>)">+</button>
                        </div>
                        <button type="submit" class="btn-add-to-cart">
                            🛒 Add to Cart
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn-add-to-cart" disabled>Out of Stock</button>
                <?php endif; ?>

                <!-- Wishlist Button (only if logged in) -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user/wishlist.php?add=<?= $product['id'] ?>" class="btn-wishlist" onclick="return confirm('Add to wishlist?')">
                        ❤️
                    </a>
                <?php endif; ?>

                <!-- Product Meta -->
                <div class="product-meta">
                    <?php if ($product['sku']): ?>
                        <div class="meta-item">
                            <span class="meta-label">SKU:</span>
                            <span class="meta-value"><?= htmlspecialchars($product['sku']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($product['brand_name']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Brand:</span>
                            <span class="meta-value">
                                <a href="index.php?brand=<?= $product['brand_id'] ?>">
                                    <?= htmlspecialchars($product['brand_name']) ?>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <span class="meta-label">Sold:</span>
                        <span class="meta-value"><?= (int)$product['sold_count'] ?> units</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs: Description, Specifications, Reviews -->
        <div class="product-tabs">
            <div class="tab-headers">
                <div class="tab-header active" onclick="switchTab('description')">📝 Description</div>
                <?php if (!empty($specs)): ?>
                    <div class="tab-header" onclick="switchTab('specs')">📊 Specifications</div>
                <?php endif; ?>
                <div class="tab-header" onclick="switchTab('reviews')">⭐ Reviews (<?= (int)($review_stats['total_reviews'] ?? 0) ?>)</div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Description Tab -->
                <div id="tab-description" class="tab-pane active">
                    <div class="description-content">
                        <?= !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : '<p>No description available.</p>' ?>
                    </div>
                </div>

                <!-- Specifications Tab -->
                <?php if (!empty($specs)): ?>
                    <div id="tab-specs" class="tab-pane">
                        <table class="specs-table">
                            <?php foreach ($specs as $key => $value): ?>
                                <tr>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></td>
                                    <td><?= htmlspecialchars($value) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Reviews Tab -->
                <div id="tab-reviews" class="tab-pane">
                    <?php if ($review_stats['total_reviews'] > 0): ?>
                        <div class="reviews-summary">
                            <div class="average-rating">
                                <div class="average-number"><?= number_format($avg, 1) ?></div>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="<?= $i <= round($avg) ? 'star-filled' : 'star-empty' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <div style="color: #666; margin-top: 5px;"><?= $review_stats['total_reviews'] ?> reviews</div>
                            </div>
                            <div class="rating-bars">
                                <?php 
                                $total = $review_stats['total_reviews'] ?: 1;
                                for ($i = 5; $i >= 1; $i--): 
                                    $count = $review_stats["rating_$i"] ?? 0;
                                    $percent = ($count / $total) * 100;
                                ?>
                                <div class="rating-bar-item">
                                    <span class="rating-label"><?= $i ?> ★</span>
                                    <div class="bar-container">
                                        <div class="bar-fill" style="width: <?= $percent ?>%;"></div>
                                    </div>
                                    <span class="rating-count"><?= $count ?></span>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($product_reviews->num_rows > 0): ?>
                        <div class="reviews-list">
                            <?php while ($rev = $product_reviews->fetch_assoc()): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <span class="review-author">
                                            <?= htmlspecialchars($rev['full_name'] ?: $rev['username']) ?>
                                        </span>
                                        <span class="review-date">
                                            <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="stars" style="margin-bottom: 8px;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="<?= $i <= $rev['rating'] ? 'star-filled' : 'star-empty' ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if (!empty($rev['title'])): ?>
                                        <div class="review-title"><?= htmlspecialchars($rev['title']) ?></div>
                                    <?php endif; ?>
                                    <div class="review-text"><?= nl2br(htmlspecialchars($rev['review'])) ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; text-align: center; padding: 30px;">No reviews yet. Be the first to review this product!</p>
                    <?php endif; ?>

                    <!-- Write a Review Button (only for logged-in users) -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="user/write_review.php?product_id=<?= $product['id'] ?>" class="btn-write-review">
                            ✍️ Write a Review
                        </a>
                    <?php else: ?>
                        <p style="text-align: center; margin-top: 20px;">
                            <a href="user/index.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" style="color: #28a745;">
                                Login</a> to write a review.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if ($related_products->num_rows > 0): ?>
        <div class="related-products">
            <h2 class="related-title">🔍 You May Also Like</h2>
            <div class="related-grid">
                <?php while ($rel = $related_products->fetch_assoc()): ?>
                    <div class="related-card">
                        <div class="related-image">
                            <?php if ($rel['image']): ?>
                                <img src="<?= htmlspecialchars($rel['image']) ?>" alt="<?= htmlspecialchars($rel['name']) ?>">
                            <?php else: ?>
                                🖼️
                            <?php endif; ?>
                        </div>
                        <a href="product.php?id=<?= $rel['id'] ?>" class="related-name">
                            <?= htmlspecialchars($rel['name']) ?>
                        </a>
                        <div class="related-price">৳<?= number_format($rel['price'], 2) ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div style="text-align: center; margin-top: 40px;">
            <a href="javascript:history.back()" style="color: #28a745; text-decoration: none; font-weight: 600;">
                ← Go Back
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: #333; color: white; padding: 30px 0; margin-top: 50px;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; text-align: center;">
            <p>© 2024 Harotey Shop. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.querySelectorAll('.tab-header').forEach(header => header.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Quantity increment/decrement
        function incrementQuantity(max) {
            let input = document.getElementById('quantity');
            let value = parseInt(input.value);
            if (value < max) {
                input.value = value + 1;
            }
        }
        function decrementQuantity() {
            let input = document.getElementById('quantity');
            let value = parseInt(input.value);
            if (value > 1) {
                input.value = value - 1;
            }
        }

        // Update cart link to include quantity
        document.querySelector('form[action="cart.php"]')?.addEventListener('submit', function(e) {
            let qty = document.getElementById('quantity')?.value || 1;
            let addId = this.querySelector('input[name="add"]').value;
            this.action = 'cart.php?add=' + addId + '&quantity=' + qty;
        });
    </script>
</body>
</html>