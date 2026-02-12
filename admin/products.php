<?php
// file: admin/products.php
require_once 'auth_check.php';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected = $_POST['selected'] ?? [];
    
    if (!empty($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        
        if ($action === 'delete') {
            $conn->query("DELETE FROM products WHERE id IN ($ids)");
            $_SESSION['message'] = "Selected products deleted successfully.";
        } elseif ($action === 'stock_in') {
            $_SESSION['bulk_ids'] = $selected;
            header("Location: edit_product.php?bulk=1");
            exit;
        }
    }
}

// Get filter and search
$search = $_GET['search'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';  // ✅ renamed to avoid confusion
$sort = $_GET['sort'] ?? 'id_desc';

// Build query
$query = "SELECT * FROM products WHERE 1=1";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
}

// ✅ Use stock_quantity for filtering
if ($stock_filter === 'low') {
    $query .= " AND stock_quantity < 5 AND stock_quantity > 0";
} elseif ($stock_filter === 'out') {
    $query .= " AND stock_quantity = 0";
} elseif ($stock_filter === 'in') {
    $query .= " AND stock_quantity > 0";
}

switch ($sort) {
    case 'id_asc': $query .= " ORDER BY id ASC"; break;
    case 'name_asc': $query .= " ORDER BY name ASC"; break;
    case 'name_desc': $query .= " ORDER BY name DESC"; break;
    case 'price_asc': $query .= " ORDER BY price ASC"; break;
    case 'price_desc': $query .= " ORDER BY price DESC"; break;
    case 'stock_asc': $query .= " ORDER BY stock_quantity ASC"; break;
    case 'stock_desc': $query .= " ORDER BY stock_quantity DESC"; break;
    default: $query .= " ORDER BY id DESC";
}

$products = $conn->query($query);
$product_count = $products ? $products->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .filters { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .search-box { flex: 1; min-width: 200px; }
        .stock-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .stock-high { background: #d4edda; color: #155724; }
        .stock-low { background: #fff3cd; color: #856404; }
        .stock-out { background: #f8d7da; color: #721c24; }
        .message { padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 20px; }
        .bulk-actions { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px; display: none; }
        .bulk-actions.show { display: block; }
        .product-table { width: 100%; border-collapse: collapse; }
        .product-table th { background: #f2f2f2; padding: 12px; text-align: left; }
        .product-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        .product-table tr:hover { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>📋 Manage Products</h1>
            <div>
                <a href="dashboard.php" class="btn" style="background: #6c757d;">← Dashboard</a>
                <a href="add_product.php" class="btn" style="background: #28a745;">+ Add New Product</a>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap;">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search products..." 
                           value="<?= htmlspecialchars($search) ?>" style="width: 100%;">
                </div>
                
                <select name="stock_filter">
                    <option value="">All Stock Status</option>
                    <option value="in" <?= $stock_filter === 'in' ? 'selected' : '' ?>>In Stock</option>
                    <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Low Stock (<5)</option>
                    <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
                
                <select name="sort">
                    <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price Low-High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price High-Low</option>
                    <option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>Stock Low-High</option>
                    <option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Stock High-Low</option>
                </select>
                
                <button type="submit" class="btn" style="background: #007bff;">Apply Filters</button>
                <a href="products.php" class="btn" style="background: #6c757d;">Reset</a>
            </form>
        </div>

        <!-- Bulk Actions -->
        <form method="POST" id="bulkForm">
            <div id="bulkActions" class="bulk-actions">
                <span style="margin-right: 15px;"><span id="selectedCount">0</span> product(s) selected</span>
                <select name="bulk_action">
                    <option value="delete">Delete Selected</option>
                    <option value="stock_in">Update Stock</option>
                </select>
                <button type="submit" class="btn" style="background: #28a745;" 
                        onclick="return confirm('Are you sure?')">Apply</button>
                <button type="button" class="btn" style="background: #6c757d;" onclick="clearSelection()">Clear</button>
            </div>

            <p style="margin-bottom: 15px;">Total Products: <strong><?= $product_count ?></strong></p>

            <?php if ($products && $products->num_rows > 0): ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                            </th>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected[]" value="<?= $product['id'] ?>" 
                                           class="product-checkbox" onclick="updateSelection()">
                                </td>
                                <td>#<?= $product['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars(substr($product['description'] ?? '', 0, 50)) ?>
                                    <?= isset($product['description']) && strlen($product['description']) > 50 ? '...' : '' ?>
                                </td>
                                <td><strong>$<?= number_format($product['price'], 2) ?></strong></td>
                                <td>
                                    <?php 
                                    $stock = $product['stock_quantity'] ?? 0; // ✅ use stock_quantity
                                    if ($stock >= 10): ?>
                                        <span class="stock-badge stock-high"><?= $stock ?> in stock</span>
                                    <?php elseif ($stock > 0): ?>
                                        <span class="stock-badge stock-low"><?= $stock ?> low stock</span>
                                    <?php else: ?>
                                        <span class="stock-badge stock-out">Out of stock</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($product['created_at'])) ?></td>
                                <td>
                                    <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn" 
                                       style="padding: 5px 10px; background: #007bff;">Edit</a>
                                    <a href="delete_product.php?id=<?= $product['id'] ?>" class="btn" 
                                       style="padding: 5px 10px; background: #dc3545;" 
                                       onclick="return confirm('Delete <?= htmlspecialchars(addslashes($product['name'])) ?>?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 8px;">
                    <h3 style="color: #666;">No products found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                    <a href="add_product.php" class="btn" style="background: #28a745; margin-top: 15px;">
                        + Add Your First Product
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        function toggleAll(source) {
            const checkboxes = document.getElementsByClassName('product-checkbox');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
            updateSelection();
        }
        function updateSelection() {
            const checkboxes = document.getElementsByClassName('product-checkbox');
            let count = 0;
            for (let i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) count++;
            }
            document.getElementById('selectedCount').textContent = count;
            const bulkActions = document.getElementById('bulkActions');
            if (count > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
                document.getElementById('selectAll').checked = false;
            }
        }
        function clearSelection() {
            const checkboxes = document.getElementsByClassName('product-checkbox');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = false;
            }
            updateSelection();
        }
        document.addEventListener('DOMContentLoaded', updateSelection);
    </script>
</body>
</html>