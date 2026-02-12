<?php
// file: product.php
include 'db.php';

// --- GET: Display product ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: index.php");
    exit;
}

// --- POST: Add to cart ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    // Add to cart logic (session, database, etc.)
    // Redirect back to product page or cart
    header("Location: cart.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($product['name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <p><strong>Price:</strong> $<?= number_format($product['price'], 2) ?></p>
        <p><strong>Stock:</strong> <?= $product['stock_quantity'] ?></p>

        <!-- Add to Cart Form -->
        <form method="POST">
            <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>">
            <button type="submit" name="add_to_cart">Add to Cart</button>
        </form>

        <a href="index.php">← Back</a>
    </div>
</body>
</html>