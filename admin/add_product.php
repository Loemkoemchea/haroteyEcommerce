<?php
// file: admin/add_product.php
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
// ------------------------------------------------------------

// ---------- HELPER FUNCTION: Upload Image ----------
function uploadProductImage($file, $product_id = null) {
    $target_dir = "../uploads/products/";
    
    // Create directory if not exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $allowed_types)) {
        return ["error" => "Only JPG, JPEG, PNG, GIF, WEBP files are allowed."];
    }

    // Max file size: 2MB
    if ($file["size"] > 2 * 1024 * 1024) {
        return ["error" => "File size must be less than 2MB."];
    }

    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $extension;
    $target_file = $target_dir . $new_filename;
    $relative_path = "uploads/products/" . $new_filename;

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "path" => $relative_path];
    } else {
        return ["error" => "Failed to upload file."];
    }
}
// ------------------------------------------------------------

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Product name is required";
    if ($price <= 0) $errors[] = "Price must be greater than 0";
    if ($stock_quantity < 0) $errors[] = "Stock cannot be negative";

    // Handle image upload
    $image_upload_result = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $image_upload_result = uploadProductImage($_FILES['product_image']);
        if (isset($image_upload_result['error'])) {
            $errors[] = $image_upload_result['error'];
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Generate unique slug
            $slug = createSlug($name, $conn);

            // Insert product
            $stmt = $conn->prepare("
                INSERT INTO products (
                    name, slug, description, price, stock_quantity, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sssdi", $name, $slug, $description, $price, $stock_quantity);
            $stmt->execute();
            $product_id = $stmt->insert_id;
            $stmt->close();

            // Insert product image if uploaded
            if ($image_upload_result && isset($image_upload_result['path'])) {
                $img_stmt = $conn->prepare("
                    INSERT INTO product_images (
                        product_id, image_path, is_primary, sort_order, created_at
                    ) VALUES (?, ?, 1, 0, NOW())
                ");
                $img_stmt->bind_param("is", $product_id, $image_upload_result['path']);
                $img_stmt->execute();
                $img_stmt->close();
            }

            $conn->commit();
            $_SESSION['message'] = "Product added successfully!";
            header("Location: products.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error adding product: " . $e->getMessage();
        }
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
    <title>Add Product - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea, .form-group input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group textarea { height: 120px; resize: vertical; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .price-input { position: relative; }
        .price-input:before { content: "$"; position: absolute; left: 10px; top: 11px; color: #666; }
        .price-input input { padding-left: 25px !important; }
        .btn-submit { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .btn-submit:hover { background: #218838; }
        .btn-cancel { background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; text-decoration: none; display: inline-block; text-align: center; }
        .btn-cancel:hover { background: #5a6268; }
        .image-preview { margin-top: 10px; max-width: 200px; display: none; }
        .image-preview img { width: 100%; border-radius: 4px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>➕ Add New Product</h1>
            <div>
                <a href="products.php" class="btn-cancel">← Back to Products</a>
            </div>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="error-message"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           placeholder="Enter product name">
                    <small style="color: #666;">Slug will be automatically generated from the name.</small>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Enter product description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="price">Price *</label>
                    <div class="price-input">
                        <input type="number" id="price" name="price" step="0.01" min="0.01" required
                               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                               placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label for="stock_quantity">Initial Stock *</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" required
                           value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '0') ?>"
                           placeholder="Enter quantity">
                </div>

                <div class="form-group">
                    <label for="product_image">Product Image (Optional)</label>
                    <input type="file" id="product_image" name="product_image" accept="image/*" onchange="previewImage(event)">
                    <small style="color: #666;">Allowed: JPG, PNG, GIF, WEBP. Max size: 2MB.</small>
                    <div class="image-preview" id="imagePreview">
                        <img src="" alt="Preview">
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn-submit" style="flex: 1;">Add Product</button>
                    <a href="products.php" class="btn-cancel" style="flex: 1; text-align: center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const preview = document.getElementById('imagePreview');
            const img = preview.querySelector('img');
            img.src = URL.createObjectURL(event.target.files[0]);
            preview.style.display = 'block';
        }
    </script>
</body>
</html>