<?php
// file: user/wishlist.php
require_once 'auth_check.php';

$message = '';
$error   = '';

// ------------------------------------------------------------
// 1. ENSURE USER HAS A DEFAULT WISHLIST
// ------------------------------------------------------------
$stmt = $conn->prepare("SELECT id FROM wishlists WHERE user_id = ? AND name = 'Default Wishlist'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    // Create default wishlist
    $share_token = bin2hex(random_bytes(16));
    $insert = $conn->prepare("INSERT INTO wishlists (user_id, name, share_token) VALUES (?, 'Default Wishlist', ?)");
    $insert->bind_param("is", $user_id, $share_token);
    $insert->execute();
    $wishlist_id = $insert->insert_id;
    $insert->close();
} else {
    $row = $result->fetch_assoc();
    $wishlist_id = $row['id'];
}
$stmt->close();

// ------------------------------------------------------------
// 2. HANDLE ACTIONS
// ------------------------------------------------------------

// --- ADD TO WISHLIST ---
if (isset($_GET['add'])) {
    $product_id = (int)$_GET['add'];

    // Verify product exists and is active
    $check = $conn->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
    $check->bind_param("i", $product_id);
    $check->execute();
    $prod_exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($prod_exists) {
        // Check if already in wishlist
        $exists = $conn->prepare("SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
        $exists->bind_param("ii", $wishlist_id, $product_id);
        $exists->execute();
        if ($exists->get_result()->num_rows == 0) {
            $insert = $conn->prepare("INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)");
            $insert->bind_param("ii", $wishlist_id, $product_id);
            $insert->execute();
            $insert->close();
            $_SESSION['success'] = "Product added to your wishlist.";
        } else {
            $_SESSION['info'] = "Product is already in your wishlist.";
        }
        $exists->close();
    } else {
        $_SESSION['error'] = "Product not found.";
    }
    header("Location: wishlist.php");
    exit;
}

// --- REMOVE FROM WISHLIST ---
if (isset($_GET['remove'])) {
    $item_id = (int)$_GET['remove'];
    $delete = $conn->prepare("DELETE FROM wishlist_items WHERE id = ? AND wishlist_id = ?");
    $delete->bind_param("ii", $item_id, $wishlist_id);
    $delete->execute();
    if ($delete->affected_rows > 0) {
        $_SESSION['success'] = "Item removed from wishlist.";
    }
    $delete->close();
    header("Location: wishlist.php");
    exit;
}

// --- MOVE TO CART (ADD TO CART AND REMOVE FROM WISHLIST) ---
if (isset($_GET['move_to_cart'])) {
    $item_id = (int)$_GET['move_to_cart'];
    // Get product_id from wishlist item
    $get = $conn->prepare("SELECT product_id FROM wishlist_items WHERE id = ? AND wishlist_id = ?");
    $get->bind_param("ii", $item_id, $wishlist_id);
    $get->execute();
    $item = $get->get_result()->fetch_assoc();
    $get->close();

    if ($item) {
        // Initialize cart session if needed
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $pid = $item['product_id'];
        if (!isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid] = 0;
        }
        $_SESSION['cart'][$pid] += 1; // default quantity 1

        // Remove from wishlist
        $delete = $conn->prepare("DELETE FROM wishlist_items WHERE id = ?");
        $delete->bind_param("i", $item_id);
        $delete->execute();
        $delete->close();

        $_SESSION['success'] = "Product moved to cart.";
    }
    header("Location: wishlist.php");
    exit;
}

// ------------------------------------------------------------
// 3. FETCH WISHLIST ITEMS WITH PRODUCT DETAILS
// ------------------------------------------------------------
$items = $conn->prepare("
    SELECT 
        wi.id AS item_id,
        wi.product_id,
        wi.added_at,
        p.name,
        p.price,
        p.stock_quantity,
        p.slug,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM wishlist_items wi
    JOIN products p ON wi.product_id = p.id
    WHERE wi.wishlist_id = ?
    ORDER BY wi.added_at DESC
");
$items->bind_param("i", $wishlist_id);
$items->execute();
$wishlist_items = $items->get_result();
$items->close();

// ------------------------------------------------------------
// 4. RETRIEVE SESSION MESSAGES
// ------------------------------------------------------------
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['info'])) {
    $info = $_SESSION['info'];
    unset($_SESSION['info']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - Harotey Shop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .wishlist-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        .empty-state i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
        }
        .empty-state h2 {
            color: #666;
            margin-bottom: 15px;
        }
        .empty-state p {
            color: #999;
            margin-bottom: 25px;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .product-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transform: translateY(-3px);
        }
        .product-image {
            width: 100%;
            height: 180px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 40px;
        }
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            text-decoration: none;
        }
        .product-name:hover {
            color: #28a745;
        }
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 10px;
        }
        .product-stock {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .added-date {
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
        }
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            flex: 1;
        }
        .btn-primary {
            background: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background: #218838;
        }
        .btn-outline {
            background: white;
            border: 1px solid #28a745;
            color: #28a745;
        }
        .btn-outline:hover {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .continue-shopping {
            display: inline-block;
            margin-top: 30px;
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
        }
        .continue-shopping:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="wishlist-container">
        <div class="header">
            <h1>❤️ My Wishlist</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($info)): ?>
            <div class="message info"><?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <?php if ($wishlist_items->num_rows > 0): ?>
            <div class="product-grid">
                <?php while ($item = $wishlist_items->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($item['image']): ?>
                                <img src="../<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <?php else: ?>
                                🖼️
                            <?php endif; ?>
                        </div>
                        <a href="../product.php?id=<?= $item['product_id'] ?>" class="product-name">
                            <?= htmlspecialchars($item['name']) ?>
                        </a>
                        <div class="product-price">৳<?= number_format($item['price'], 2) ?></div>
                        <div class="product-stock">
                            <?php if ($item['stock_quantity'] > 0): ?>
                                ✅ In Stock (<?= $item['stock_quantity'] ?>)
                            <?php else: ?>
                                ❌ Out of Stock
                            <?php endif; ?>
                        </div>
                        <div class="added-date">
                            Added on <?= date('M d, Y', strtotime($item['added_at'])) ?>
                        </div>
                        <div class="product-actions">
                            <?php if ($item['stock_quantity'] > 0): ?>
                                <a href="?move_to_cart=<?= $item['item_id'] ?>" class="btn btn-primary" onclick="return confirm('Move this item to cart?')">
                                    🛒 Move to Cart
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary" disabled style="opacity:0.5;">Out of Stock</button>
                            <?php endif; ?>
                            <a href="?remove=<?= $item['item_id'] ?>" class="btn btn-danger" onclick="return confirm('Remove from wishlist?')">
                                ✕ Remove
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 60px; margin-bottom: 20px;">❤️</div>
                <h2>Your wishlist is empty</h2>
                <p>Save your favorite items here and shop them later!</p>
                <a href="../index.php" class="btn btn-primary" style="display: inline-block; padding: 12px 30px;">
                    🛍️ Continue Shopping
                </a>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="../index.php" class="continue-shopping">← Continue Shopping</a>
        </div>
    </div>
</body>
</html>