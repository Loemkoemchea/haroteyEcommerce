<?php
session_start();
include 'db.php';

// =============================================
// 1. ADD TO CART (via GET ?add=product_id)
// =============================================
if (isset($_GET['add'])) {
    $product_id = (int)$_GET['add'];

    // Verify product exists and is active
    $stmt = $conn->prepare("SELECT id, stock_quantity FROM products WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if ($product) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] = 0;
        }
        $_SESSION['cart'][$product_id] += 1;

        // Cap at available stock
        if ($_SESSION['cart'][$product_id] > $product['stock_quantity']) {
            $_SESSION['cart'][$product_id] = $product['stock_quantity'];
            $_SESSION['warning'] = "Quantity adjusted to available stock.";
        }
    }
    header("Location: cart.php");
    exit;
}

// =============================================
// 2. REMOVE ITEM (via GET ?remove=product_id)
// =============================================
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$product_id]);
    header("Location: cart.php");
    exit;
}

// =============================================
// 3. UPDATE CART (POST from quantity form)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $quantities = $_POST['quantity'] ?? [];

    foreach ($quantities as $product_id => $qty) {
        $product_id = (int)$product_id;
        $qty = (int)$qty;

        // Get real stock from DB
        $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            unset($_SESSION['cart'][$product_id]);
            continue;
        }

        $real_stock = $product['stock_quantity'];

        if ($qty <= 0) {
            unset($_SESSION['cart'][$product_id]);
        } elseif ($qty > $real_stock) {
            $_SESSION['cart'][$product_id] = $real_stock;
            $_SESSION['warning'] = "Quantity for product #{$product_id} adjusted to available stock ({$real_stock}).";
        } else {
            $_SESSION['cart'][$product_id] = $qty;
        }
    }
    header("Location: cart.php");
    exit;
}

// =============================================
// 4. FETCH CART ITEMS WITH PRODUCT DETAILS + IMAGES
// =============================================
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $sql = "SELECT 
                p.id, 
                p.name, 
                p.price, 
                p.stock_quantity,
                (SELECT image_path FROM product_images 
                 WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM products p
            WHERE p.id IN ($ids)";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $row['cart_quantity'] = $_SESSION['cart'][$row['id']];
        $cart_items[] = $row;
        $total += $row['price'] * $row['cart_quantity'];
    }
}

// =============================================
// 5. SESSION MESSAGES
// =============================================
$warning = $_SESSION['warning'] ?? '';
unset($_SESSION['warning']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Harotey Shop</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Cart Page Styles */
        .cart-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .cart-header h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
        }
        .continue-shopping {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .continue-shopping:hover {
            background: #5a6268;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #ffc107;
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .cart-table th {
            background: #f8f9fa;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        .cart-table td {
            padding: 20px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }
        .cart-table tr:last-child td {
            border-bottom: none;
        }
        .product-image {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 30px;
        }
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 6px;
        }
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .product-details {
            display: flex;
            flex-direction: column;
        }
        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            text-decoration: none;
            margin-bottom: 5px;
        }
        .product-name:hover {
            color: #28a745;
        }
        .product-stock {
            font-size: 12px;
            color: #6c757d;
        }
        .stock-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: #e8f5e9;
            color: #28a745;
        }
        .out-of-stock {
            background: #f8d7da;
            color: #dc3545;
        }
        .quantity-input {
            width: 80px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-align: center;
            font-size: 15px;
        }
        .remove-link {
            color: #dc3545;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .remove-link:hover {
            background: #f8d7da;
            color: #bd2130;
        }
        .cart-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .cart-total {
            background: #f8f9fa;
            padding: 20px 25px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 20px;
        }
        .cart-total strong {
            font-size: 20px;
            color: #333;
        }
        .total-amount {
            font-size: 28px;
            font-weight: 700;
            color: #28a745;
        }
        .cart-actions {
            display: flex;
            gap: 15px;
        }
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-update {
            background: #ffc107;
            color: #333;
        }
        .btn-update:hover {
            background: #e0a800;
        }
        .btn-checkout {
            background: #28a745;
            color: white;
        }
        .btn-checkout:hover {
            background: #218838;
        }
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-cart i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
            display: block;
        }
        .empty-cart h2 {
            color: #666;
            margin-bottom: 15px;
        }
        .empty-cart p {
            color: #999;
            margin-bottom: 25px;
        }
        @media (max-width: 768px) {
            .cart-table {
                display: block;
                overflow-x: auto;
            }
            .product-info {
                flex-direction: column;
                align-items: flex-start;
            }
            .cart-footer {
                flex-direction: column;
                align-items: stretch;
            }
            .cart-total {
                justify-content: space-between;
            }
            .cart-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header (reuse from index.php or include) -->
    <header style="background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 15px 0;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" style="font-size: 24px; font-weight: 700; color: #28a745; text-decoration: none;">🛍️ Harotey Shop</a>
            <nav>
                <a href="index.php" style="color: #333; text-decoration: none; margin: 0 10px;">Home</a>
                <a href="cart.php" style="color: #28a745; text-decoration: none; margin: 0 10px; font-weight: 600;">Cart</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user/dashboard.php" style="color: #333; text-decoration: none; margin: 0 10px;">Account</a>
                <?php else: ?>
                    <a href="user/index.php" style="color: #333; text-decoration: none; margin: 0 10px;">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="cart-container">
        <div class="cart-header">
            <h1>🛒 Your Shopping Cart</h1>
            <a href="index.php" class="continue-shopping">
                ← Continue Shopping
            </a>
        </div>

        <?php if ($warning): ?>
            <div class="warning">⚠️ <?= htmlspecialchars($warning) ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i>🛒</i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added anything yet.</p>
                <a href="index.php" class="btn btn-checkout" style="display: inline-block; padding: 12px 30px;">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <form method="post">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-image">
                                            <?php if ($item['primary_image']): ?>
                                                <img src="<?= htmlspecialchars($item['primary_image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                            <?php else: ?>
                                                🖼️
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-details">
                                            <a href="product.php?id=<?= $item['id'] ?>" class="product-name">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                            <span class="product-stock">
                                                <span class="stock-badge <?= $item['stock_quantity'] > 0 ? '' : 'out-of-stock' ?>">
                                                    <?= $item['stock_quantity'] > 0 ? "✓ {$item['stock_quantity']} in stock" : '❌ Out of stock' ?>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td><strong>৳<?= number_format($item['price'], 2) ?></strong></td>
                                <td>
                                    <input type="number" name="quantity[<?= $item['id'] ?>]" 
                                           value="<?= $item['cart_quantity'] ?>" 
                                           min="0" max="<?= $item['stock_quantity'] ?>" 
                                           class="quantity-input"
                                           <?= $item['stock_quantity'] == 0 ? 'disabled' : '' ?>>
                                </td>
                                <td><strong>৳<?= number_format($item['price'] * $item['cart_quantity'], 2) ?></strong></td>
                                <td>
                                    <a href="?remove=<?= $item['id'] ?>" class="remove-link" onclick="return confirm('Remove this item from cart?')">
                                        🗑️ Remove
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-footer">
                    <div class="cart-total">
                        <span style="font-size: 18px; color: #333;">Total:</span>
                        <span class="total-amount">৳<?= number_format($total, 2) ?></span>
                    </div>
                    <div class="cart-actions">
                        <button type="submit" name="update" class="btn btn-update">
                            🔄 Update Cart
                        </button>
                        <a href="checkout.php" class="btn btn-checkout">
                            → Proceed to Checkout
                        </a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Footer
    <footer style="background: #333; color: white; padding: 30px 0; margin-top: 60px;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; text-align: center;">
            <p>© 2024 Harotey Shop. All rights reserved.</p>
        </div>
    </footer> -->
</body>
</html>