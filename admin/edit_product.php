<?php
// file: admin/edit_product.php
require_once 'auth_check.php';

// =============================================
// 1. DETERMINE MODE: SINGLE EDIT OR BULK UPDATE
// =============================================
$is_bulk = isset($_GET['bulk']) && $_GET['bulk'] == 1;
$product_ids = [];

if ($is_bulk) {
    // Bulk edit from session
    $product_ids = $_SESSION['bulk_ids'] ?? [];
    if (empty($product_ids)) {
        header("Location: products.php");
        exit;
    }
    $ids_string = implode(',', array_map('intval', $product_ids));
    $products = $conn->query("SELECT id, name, stock_quantity FROM products WHERE id IN ($ids_string) ORDER BY id");
} else {
    // Single product edit
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        header("Location: products.php");
        exit;
    }
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $_SESSION['error'] = "Product not found";
        header("Location: products.php");
        exit;
    }
}

$message = '';
$error = '';

// =============================================
// 2. HANDLE FORM SUBMISSION
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_bulk) {
        // ---------- BULK STOCK UPDATE ----------
        $stock_values = $_POST['stock_quantity'] ?? []; // ✅ use stock_quantity
        $success_count = 0;

        foreach ($stock_values as $pid => $stock_qty) {
            $pid = (int)$pid;
            $stock_qty = (int)$stock_qty;
            if ($stock_qty >= 0) {
                $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $stmt->bind_param("ii", $stock_qty, $pid);
                if ($stmt->execute()) {
                    $success_count++;
                }
                $stmt->close();
            }
        }

        $_SESSION['message'] = "$success_count products updated successfully!";
        unset($_SESSION['bulk_ids']);
        header("Location: products.php");
        exit;
    } else {
        // ---------- SINGLE PRODUCT UPDATE ----------
        $id = (int)$_POST['product_id'];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0); // ✅ use stock_quantity

        $errors = [];
        if (empty($name)) $errors[] = "Product name is required";
        if ($price <= 0) $errors[] = "Price must be greater than 0";
        if ($stock_quantity < 0) $errors[] = "Stock cannot be negative";

        if (empty($errors)) {
            $stmt = $conn->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, stock_quantity = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("ssdii", $name, $description, $price, $stock_quantity, $id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Product updated successfully!";
                header("Location: products.php");
                exit;
            } else {
                $error = "Error updating product: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = implode("<br>", $errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_bulk ? 'Bulk Stock Update' : 'Edit Product' ?> - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: <?= $is_bulk ? '800px' : '600px' ?>; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group textarea { height: 120px; resize: vertical; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .price-input { position: relative; }
        .price-input:before { content: "$"; position: absolute; left: 10px; top: 11px; color: #666; }
        .price-input input { padding-left: 25px !important; }
        .product-row { background: #f8f9fa; padding: 15px; margin-bottom: 15px; border-radius: 4px; border-left: 4px solid #28a745; }
        .product-name { font-weight: bold; margin-bottom: 10px; font-size: 16px; }
        .current-stock { color: #666; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1><?= $is_bulk ? '📦 Bulk Stock Update' : '✏️ Edit Product' ?></h1>
            <div>
                <a href="products.php" class="btn" style="background: #6c757d;">← Back to Products</a>
            </div>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="error-message"><?= $error ?></div>
            <?php endif; ?>

            <?php if ($is_bulk): ?>
                <!-- ---------- BULK STOCK UPDATE FORM ---------- -->
                <form method="POST">
                    <h3 style="margin-top: 0; margin-bottom: 20px;">Update Stock for <?= count($product_ids) ?> Products</h3>
                    
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <div class="product-row">
                            <div class="product-name">
                                <?= htmlspecialchars($product['name']) ?> 
                                <span style="color: #666; font-weight: normal;">(ID: #<?= $product['id'] ?>)</span>
                            </div>
                            <div class="current-stock">
                                Current Stock: <strong><?= $product['stock_quantity'] ?></strong>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="stock_<?= $product['id'] ?>">New Stock Quantity</label>
                                <input type="number" id="stock_<?= $product['id'] ?>" 
                                       name="stock_quantity[<?= $product['id'] ?>]" 
                                       min="0" value="<?= $product['stock_quantity'] ?>" required>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn" style="background: #28a745; flex: 1;">Update Stock</button>
                        <a href="products.php" class="btn" style="background: #6c757d; flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>

            <?php else: ?>
                <!-- ---------- SINGLE PRODUCT EDIT FORM ---------- -->
                <form method="POST">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?= htmlspecialchars($product['name']) ?>"
                               placeholder="Enter product name">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  placeholder="Enter product description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Price *</label>
                        <div class="price-input">
                            <input type="number" id="price" name="price" step="0.01" min="0.01" required
                                   value="<?= $product['price'] ?>"
                                   placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" required
                               value="<?= $product['stock_quantity'] ?>"
                               placeholder="Enter quantity">
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn" style="background: #28a745; flex: 1;">Update Product</button>
                        <a href="products.php" class="btn" style="background: #6c757d; flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>