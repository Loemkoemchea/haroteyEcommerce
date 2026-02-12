<?php
// file: admin/add_product.php
require_once 'auth_check.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Product name is required";
    if ($price <= 0) $errors[] = "Price must be greater than 0";
    if ($stock < 0) $errors[] = "Stock cannot be negative";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $name, $description, $price, $stock);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Product added successfully!";
            header("Location: products.php");
            exit;
        } else {
            $error = "Error adding product: " . $conn->error;
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
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #28a745;
            outline: none;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.1);
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .price-input {
            position: relative;
        }
        .price-input:before {
            content: "$";
            position: absolute;
            left: 10px;
            top: 11px;
            color: #666;
        }
        .price-input input {
            padding-left: 25px !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>➕ Add New Product</h1>
            <div>
                <a href="products.php" class="btn" style="background: #6c757d;">← Back to Products</a>
            </div>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="error-message"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           placeholder="Enter product name">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Enter product description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <small style="color: #666;">Optional but recommended</small>
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
                    <label for="stock">Initial Stock *</label>
                    <input type="number" id="stock" name="stock" min="0" required
                           value="<?= htmlspecialchars($_POST['stock'] ?? '0') ?>"
                           placeholder="Enter quantity">
                </div>

                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn" style="background: #28a745; flex: 1;">Add Product</button>
                    <a href="products.php" class="btn" style="background: #6c757d; flex: 1; text-align: center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>