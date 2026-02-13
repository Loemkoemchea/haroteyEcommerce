<?php
// file: admin/edit_product.php
require_once 'auth_check.php';

// ---------- HELPER FUNCTION: Generate Unique Slug ----------
function createSlug($string, $conn, $table = 'products', $id = 0) {
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($string)));
    $slug = trim($slug, '-');
    if (empty($slug)) $slug = 'product';

    $original_slug = $slug;
    $counter = 1;

    $stmt = $conn->prepare("SELECT id FROM $table WHERE slug = ? AND id != ?");
    $stmt->bind_param("si", $slug, $id);
    $stmt->execute();
    $stmt->store_result();

    while ($stmt->num_rows > 0) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
        $stmt->bind_param("si", $slug, $id);
        $stmt->execute();
        $stmt->store_result();
    }
    $stmt->close();
    return $slug;
}

// ---------- FETCH CATEGORIES & BRANDS FOR DROPDOWNS ----------
$categories = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
$brands = $conn->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC");

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
        $stock_values = $_POST['stock_quantity'] ?? [];
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
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;

        $errors = [];
        if (empty($name)) $errors[] = "Product name is required";
        if ($price <= 0) $errors[] = "Price must be greater than 0";
        if ($stock_quantity < 0) $errors[] = "Stock cannot be negative";

        // Handle image upload (replace primary image)
        $new_image_path = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['product_image'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = "Only JPG, JPEG, PNG, GIF, WEBP files are allowed.";
            } else {
                $upload_dir = '../uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = uniqid() . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $new_filename;
                $relative_path = 'uploads/products/' . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $new_image_path = $relative_path;
                } else {
                    $errors[] = "Failed to upload image.";
                }
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Regenerate slug only if name changed
                $slug = $product['slug'];
                if ($name !== $product['name']) {
                    $slug = createSlug($name, $conn, 'products', $id);
                }

                // Update product
                $stmt = $conn->prepare("
                    UPDATE products SET
                        name = ?,
                        slug = ?,
                        description = ?,
                        price = ?,
                        stock_quantity = ?,
                        category_id = ?,
                        brand_id = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "sssdiiii",
                    $name, $slug, $description, $price,
                    $stock_quantity, $category_id, $brand_id, $id
                );
                $stmt->execute();
                $stmt->close();

                // Handle image replacement
                if ($new_image_path) {
                    // Get old primary image
                    $img_stmt = $conn->prepare("
                        SELECT id, image_path FROM product_images
                        WHERE product_id = ? AND is_primary = 1
                        LIMIT 1
                    ");
                    $img_stmt->bind_param("i", $id);
                    $img_stmt->execute();
                    $old_image = $img_stmt->get_result()->fetch_assoc();
                    $img_stmt->close();

                    if ($old_image) {
                        // Update existing primary image
                        $update = $conn->prepare("
                            UPDATE product_images
                            SET image_path = ?
                            WHERE id = ?
                        ");
                        $update->bind_param("si", $new_image_path, $old_image['id']);
                        $update->execute();
                        $update->close();

                        // Delete old file
                        if (file_exists('../' . $old_image['image_path'])) {
                            unlink('../' . $old_image['image_path']);
                        }
                    } else {
                        // Insert new primary image
                        $insert = $conn->prepare("
                            INSERT INTO product_images (product_id, image_path, is_primary, sort_order, created_at)
                            VALUES (?, ?, 1, 0, NOW())
                        ");
                        $insert->bind_param("is", $id, $new_image_path);
                        $insert->execute();
                        $insert->close();
                    }
                }

                $conn->commit();
                $_SESSION['message'] = "Product updated successfully!";
                header("Location: products.php");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error updating product: " . $e->getMessage();
            }
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
        .form-container { max-width: <?= $is_bulk ? '800px' : '700px' ?>; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea,
        .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group textarea { height: 120px; resize: vertical; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .price-input { position: relative; }
        .price-input:before { content: "$"; position: absolute; left: 10px; top: 11px; color: #666; }
        .price-input input { padding-left: 25px !important; }
        .btn-submit { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .btn-submit:hover { background: #218838; }
        .btn-cancel { background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; text-decoration: none; display: inline-block; text-align: center; }
        .btn-cancel:hover { background: #5a6268; }
        .product-row { background: #f8f9fa; padding: 15px; margin-bottom: 15px; border-radius: 4px; border-left: 4px solid #28a745; }
        .product-name { font-weight: bold; margin-bottom: 10px; font-size: 16px; }
        .current-stock { color: #666; margin-bottom: 10px; }
        .image-preview { margin-top: 10px; max-width: 200px; display: none; }
        .image-preview img { width: 100%; border-radius: 4px; border: 1px solid #ddd; }
        .current-image { max-width: 100px; margin-top: 5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        /* ===== Custom File Upload ===== */
        .file-upload-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn-upload {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: background 0.2s;
        }
        .btn-upload:hover {
            background: #218838;
        }
        .btn-upload i {
            margin-right: 6px;
        }
        .file-name {
            color: #666;
            font-size: 14px;
            font-style: italic;
        }
        #product_image {
            display: none; /* hide the actual file input */
        }
        .image-preview {
            margin-top: 15px;
            max-width: 200px;
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 5px;
            background: #f8f9fa;
            display: none;
        }
        .image-preview img {
            width: 100%;
            border-radius: 4px;
        }
        .current-image {
            display: inline-block;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
        }
        .current-image img {
            max-width: 100px;
            border-radius: 4px;
        }
        .current-image p {
            margin: 5px 0 0;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1><?= $is_bulk ? '📦 Bulk Stock Update' : '✏️ Edit Product' ?></h1>
            <div>
                <a href="products.php" class="btn-cancel">← Back to Products</a>
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
                        <button type="submit" class="btn-submit" style="flex: 1;">Update Stock</button>
                        <a href="products.php" class="btn-cancel" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <!-- ---------- SINGLE PRODUCT EDIT FORM ---------- -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" required
                               value="<?= htmlspecialchars($product['name']) ?>"
                               placeholder="Enter product name">
                        <small style="color: #666;">Slug will be regenerated if name changes.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id">
                                <option value="">-- No Category --</option>
                                <?php if ($categories && $categories->num_rows > 0): ?>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="brand_id">Brand</label>
                            <select id="brand_id" name="brand_id">
                                <option value="">-- No Brand --</option>
                                <?php if ($brands && $brands->num_rows > 0): ?>
                                    <?php while ($brand = $brands->fetch_assoc()): ?>
                                        <option value="<?= $brand['id'] ?>" <?= ($product['brand_id'] == $brand['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($brand['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"
                                  placeholder="Enter product description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
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
                    </div>

                    <!-- Product Image Section -->
                    <div class="form-group">
                        <label>Current Primary Image</label>
                        <?php
                        $img_stmt = $conn->prepare("
                            SELECT image_path FROM product_images
                            WHERE product_id = ? AND is_primary = 1
                            LIMIT 1
                        ");
                        $img_stmt->bind_param("i", $product['id']);
                        $img_stmt->execute();
                        $current_image = $img_stmt->get_result()->fetch_assoc();
                        $img_stmt->close();
                        ?>
                        <?php if ($current_image): ?>
                            <div class="current-image">
                                <img src="../<?= htmlspecialchars($current_image['image_path']) ?>" alt="Current Image">
                                <p>Current primary image</p>
                            </div>
                        <?php else: ?>
                            <p><em>No primary image</em></p>
                        <?php endif; ?>

                        <label for="product_image" style="display: block; margin-top: 15px; font-weight: 600;">Change Image (Optional)</label>
                        <div class="file-upload-wrapper">
                            <label for="product_image" class="btn-upload">
                                📸 Choose New Image
                            </label>
                            <input type="file" id="product_image" name="product_image" accept="image/*" onchange="previewImage(event); updateFileName(this);">
                            <span class="file-name" id="file-name">No file chosen</span>
                        </div>
                        <small style="color: #666; display: block; margin-top: 5px;">Allowed: JPG, PNG, GIF, WEBP. Max 2MB.</small>
                        <div class="image-preview" id="imagePreview">
                            <img src="" alt="Preview">
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn-submit" style="flex: 1;">💾 Update Product</button>
                        <a href="products.php" class="btn-cancel" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const preview = document.getElementById('imagePreview');
            const img = preview.querySelector('img');
            if (event.target.files && event.target.files[0]) {
                img.src = URL.createObjectURL(event.target.files[0]);
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
        }
    </script>
</body>
</html>