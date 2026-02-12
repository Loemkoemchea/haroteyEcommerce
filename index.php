<?php
// file: index.php – Product listing with sidebar
session_start();
include 'db.php';

// =============================================
// 1. BUILD FILTER CONDITIONS
// =============================================
$where = ["p.is_active = 1"];
$params = [];
$types = "";

// Category filter
if (!empty($_GET['category'])) {
    $cat_id = (int)$_GET['category'];
    $where[] = "p.category_id = ?";
    $params[] = $cat_id;
    $types .= "i";
}

// Brand filter
if (!empty($_GET['brand'])) {
    $brand_id = (int)$_GET['brand'];
    $where[] = "p.brand_id = ?";
    $params[] = $brand_id;
    $types .= "i";
}

// Price range filter
if (!empty($_GET['min_price']) && !empty($_GET['max_price'])) {
    $min = (float)$_GET['min_price'];
    $max = (float)$_GET['max_price'];
    $where[] = "p.price BETWEEN ? AND ?";
    $params[] = $min;
    $params[] = $max;
    $types .= "dd";
} elseif (!empty($_GET['price_range'])) {
    // Predefined ranges: 0-1000,1000-5000,5000-10000,10000+
    $range = $_GET['price_range'];
    switch ($range) {
        case '0-1000':
            $where[] = "p.price BETWEEN 0 AND 1000";
            break;
        case '1000-5000':
            $where[] = "p.price BETWEEN 1000 AND 5000";
            break;
        case '5000-10000':
            $where[] = "p.price BETWEEN 5000 AND 10000";
            break;
        case '10000+':
            $where[] = "p.price >= 10000";
            break;
    }
}

// Stock status filter
if (!empty($_GET['stock']) && $_GET['stock'] == 'in_stock') {
    $where[] = "p.stock_quantity > 0";
} elseif (!empty($_GET['stock']) && $_GET['stock'] == 'out_of_stock') {
    $where[] = "p.stock_quantity = 0";
}

// Search query
if (!empty($_GET['search'])) {
    $search = "%" . $conn->real_escape_string($_GET['search']) . "%";
    $where[] = "(p.name LIKE ? OR p.short_description LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

// =============================================
// 2. SORTING
// =============================================
$order_by = "p.created_at DESC"; // default
if (!empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'price_asc':
            $order_by = "p.price ASC";
            break;
        case 'price_desc':
            $order_by = "p.price DESC";
            break;
        case 'name_asc':
            $order_by = "p.name ASC";
            break;
        case 'newest':
            $order_by = "p.created_at DESC";
            break;
        case 'popular':
            $order_by = "p.sold_count DESC";
            break;
        case 'rating':
            $order_by = "p.rating_avg DESC";
            break;
    }
}

// =============================================
// 3. PAGINATION
// =============================================
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clause = implode(" AND ", $where);

// Get total products count
$count_sql = "SELECT COUNT(*) FROM products p WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_products);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_products / $limit);

// =============================================
// 4. FETCH PRODUCTS
// =============================================
$sql = "SELECT 
            p.id, p.name, p.slug, p.price, p.stock_quantity, 
            p.short_description, p.rating_avg, p.sold_count,
            (SELECT image_path FROM product_images 
             WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM products p
        WHERE $where_clause
        ORDER BY $order_by
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// =============================================
// 5. FETCH CATEGORIES FOR SIDEBAR (with counts)
// =============================================
$cats = $conn->query("
    SELECT c.id, c.name, c.slug, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.name ASC
");

// =============================================
// 6. FETCH BRANDS FOR SIDEBAR (with counts)
// =============================================
$brands = $conn->query("
    SELECT b.id, b.name, b.slug, COUNT(p.id) as product_count
    FROM brands b
    LEFT JOIN products p ON b.id = p.brand_id AND p.is_active = 1
    WHERE b.is_active = 1
    GROUP BY b.id
    ORDER BY b.name ASC
");

// =============================================
// 7. PRESERVE FILTERS IN URL
// =============================================
function buildUrl($params) {
    $existing = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($existing[$key]);
        } else {
            $existing[$key] = $value;
        }
    }
    return '?' . http_build_query($existing);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harotey Shop – Your Online Store</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Modern Shop Layout with Sidebar */
        * {
            box-sizing: border-box;
        }
        body {
            background: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
        }
        .shop-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .sidebar-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }
        .sidebar-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-title i {
            color: #28a745;
        }
        .category-list, .brand-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .category-item, .brand-item {
            margin-bottom: 10px;
        }
        .category-link, .brand-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            color: #555;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .category-link:hover, .brand-link:hover,
        .category-link.active, .brand-link.active {
            background: #e8f5e9;
            color: #28a745;
        }
        .category-name, .brand-name {
            font-size: 15px;
        }
        .category-count, .brand-count {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
            color: #666;
        }
        .price-range-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .price-range-item {
            margin-bottom: 10px;
        }
        .price-range-link {
            display: block;
            padding: 8px 12px;
            color: #555;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .price-range-link:hover,
        .price-range-link.active {
            background: #e8f5e9;
            color: #28a745;
        }
        .stock-filter {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .stock-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .stock-option:hover {
            background: #e8f5e9;
        }
        .stock-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #28a745;
            margin: 0;
        }
        .clear-filters {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 16px;
            background: #f8f9fa;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            border: 1px solid #ddd;
        }
        .clear-filters:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            padding: 25px;
        }
        .shop-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .shop-header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .results-count {
            color: #666;
            font-size: 15px;
        }
        .sort-bar {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .sort-label {
            color: #666;
            font-size: 14px;
        }
        .sort-select {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .product-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            transform: translateY(-3px);
            border-color: #28a745;
        }
        .product-image {
            height: 160px;
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
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            text-decoration: none;
            line-height: 1.4;
        }
        .product-name:hover {
            color: #28a745;
        }
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 8px;
        }
        .product-stock {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        .btn {
            padding: 8px 16px;
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
        .btn-view {
            background: #6c757d;
            color: white;
        }
        .btn-view:hover {
            background: #5a6268;
        }
        .btn-cart {
            background: #28a745;
            color: white;
        }
        .btn-cart:hover {
            background: #218838;
        }
        .btn-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        .page-link {
            padding: 8px 14px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            color: #28a745;
            text-decoration: none;
            transition: all 0.2s;
        }
        .page-link.active,
        .page-link:hover {
            background: #28a745;
            color: white;
            border-color: #28a745;
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
        @media (max-width: 992px) {
            .shop-container {
                grid-template-columns: 1fr;
            }
            .sidebar {
                position: static;
            }
        }
    </style>
</head>
<body>
    <!-- Header / Navigation (can be moved to separate file) -->
    <header style="background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 15px 0;">
        <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" style="font-size: 24px; font-weight: 700; color: #28a745; text-decoration: none;">🛍️ Harotey Shop</a>
            <div style="display: flex; gap: 20px; align-items: center;">
                <form action="index.php" method="GET" style="display: flex; gap: 5px;">
                    <input type="text" name="search" placeholder="Search products..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                           style="padding: 8px 15px; border: 1px solid #ddd; border-radius: 30px; width: 250px;">
                    <button type="submit" style="background: #28a745; color: white; border: none; border-radius: 30px; padding: 8px 20px; cursor: pointer;">🔍</button>
                </form>
                <a href="cart.php" style="color: #333; text-decoration: none; font-weight: 600;">🛒 Cart</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user/dashboard.php" style="color: #333; text-decoration: none;">👤 My Account</a>
                <?php else: ?>
                    <a href="user/index.php" style="color: #333; text-decoration: none;">🔐 Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="shop-container">
        <!-- ========== SIDEBAR ========== -->
        <aside class="sidebar">
            <!-- Categories -->
            <div class="sidebar-section">
                <h3 class="sidebar-title"><i>📂</i> Categories</h3>
                <ul class="category-list">
                    <li class="category-item">
                        <a href="<?= buildUrl(['category' => null, 'page' => null]) ?>" 
                           class="category-link <?= empty($_GET['category']) ? 'active' : '' ?>">
                            <span class="category-name">All Categories</span>
                            <span class="category-count"><?= $total_products ?></span>
                        </a>
                    </li>
                    <?php while ($cat = $cats->fetch_assoc()): ?>
                        <?php if ($cat['product_count'] > 0): ?>
                        <li class="category-item">
                            <a href="<?= buildUrl(['category' => $cat['id'], 'page' => null]) ?>" 
                               class="category-link <?= ($_GET['category'] ?? '') == $cat['id'] ? 'active' : '' ?>">
                                <span class="category-name"><?= htmlspecialchars($cat['name']) ?></span>
                                <span class="category-count"><?= $cat['product_count'] ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </ul>
            </div>

            <!-- Brands -->
            <div class="sidebar-section">
                <h3 class="sidebar-title"><i>🏷️</i> Brands</h3>
                <ul class="brand-list">
                    <li class="brand-item">
                        <a href="<?= buildUrl(['brand' => null, 'page' => null]) ?>" 
                           class="brand-link <?= empty($_GET['brand']) ? 'active' : '' ?>">
                            <span class="brand-name">All Brands</span>
                        </a>
                    </li>
                    <?php while ($brand = $brands->fetch_assoc()): ?>
                        <?php if ($brand['product_count'] > 0): ?>
                        <li class="brand-item">
                            <a href="<?= buildUrl(['brand' => $brand['id'], 'page' => null]) ?>" 
                               class="brand-link <?= ($_GET['brand'] ?? '') == $brand['id'] ? 'active' : '' ?>">
                                <span class="brand-name"><?= htmlspecialchars($brand['name']) ?></span>
                                <span class="brand-count"><?= $brand['product_count'] ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </ul>
            </div>

            <!-- Price Range -->
            <div class="sidebar-section">
                <h3 class="sidebar-title"><i>💰</i> Price Range</h3>
                <ul class="price-range-list">
                    <li><a href="<?= buildUrl(['price_range' => '0-1000', 'min_price' => null, 'max_price' => null, 'page' => null]) ?>" 
                           class="price-range-link <?= ($_GET['price_range'] ?? '') == '0-1000' ? 'active' : '' ?>">Under $1,000</a></li>
                    <li><a href="<?= buildUrl(['price_range' => '1000-5000', 'min_price' => null, 'max_price' => null, 'page' => null]) ?>" 
                           class="price-range-link <?= ($_GET['price_range'] ?? '') == '1000-5000' ? 'active' : '' ?>">$1,000 – $5,000</a></li>
                    <li><a href="<?= buildUrl(['price_range' => '5000-10000', 'min_price' => null, 'max_price' => null, 'page' => null]) ?>" 
                           class="price-range-link <?= ($_GET['price_range'] ?? '') == '5000-10000' ? 'active' : '' ?>">$5,000 – $10,000</a></li>
                    <li><a href="<?= buildUrl(['price_range' => '10000+', 'min_price' => null, 'max_price' => null, 'page' => null]) ?>" 
                           class="price-range-link <?= ($_GET['price_range'] ?? '') == '10000+' ? 'active' : '' ?>">Over $10,000</a></li>
                </ul>
                <!-- Custom price range (optional) -->
                <form method="GET" style="margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <input type="number" name="min_price" placeholder="Min" 
                           value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>"
                           style="width: 80px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                    <span style="color: #666;">–</span>
                    <input type="number" name="max_price" placeholder="Max" 
                           value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"
                           style="width: 80px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="submit" style="background: #28a745; color: white; border: none; border-radius: 4px; padding: 6px 12px; cursor: pointer;">Go</button>
                </form>
            </div>

            <!-- Stock Status -->
            <div class="sidebar-section">
                <h3 class="sidebar-title"><i>📦</i> Availability</h3>
                <div class="stock-filter">
                    <label class="stock-option">
                        <input type="radio" name="stock" value="all" 
                               onchange="window.location.href='<?= buildUrl(['stock' => null, 'page' => null]) ?>'"
                               <?= empty($_GET['stock']) ? 'checked' : '' ?>>
                        <span>All Products</span>
                    </label>
                    <label class="stock-option">
                        <input type="radio" name="stock" value="in_stock" 
                               onchange="window.location.href='<?= buildUrl(['stock' => 'in_stock', 'page' => null]) ?>'"
                               <?= ($_GET['stock'] ?? '') == 'in_stock' ? 'checked' : '' ?>>
                        <span>✅ In Stock Only</span>
                    </label>
                    <label class="stock-option">
                        <input type="radio" name="stock" value="out_of_stock" 
                               onchange="window.location.href='<?= buildUrl(['stock' => 'out_of_stock', 'page' => null]) ?>'"
                               <?= ($_GET['stock'] ?? '') == 'out_of_stock' ? 'checked' : '' ?>>
                        <span>❌ Out of Stock</span>
                    </label>
                </div>
            </div>

            <!-- Clear Filters -->
            <a href="index.php" class="clear-filters">✕ Clear All Filters</a>
        </aside>

        <!-- ========== MAIN CONTENT ========== -->
        <main class="main-content">
            <div class="shop-header">
                <div>
                    <h1>
                        <?php if (!empty($_GET['category'])): ?>
                            <?php
                            $cat_name = $conn->query("SELECT name FROM categories WHERE id = " . (int)$_GET['category'])->fetch_assoc()['name'] ?? 'Category';
                            echo htmlspecialchars($cat_name);
                            ?>
                        <?php elseif (!empty($_GET['brand'])): ?>
                            <?php
                            $brand_name = $conn->query("SELECT name FROM brands WHERE id = " . (int)$_GET['brand'])->fetch_assoc()['name'] ?? 'Brand';
                            echo htmlspecialchars($brand_name);
                            ?>
                        <?php elseif (!empty($_GET['search'])): ?>
                            Search: "<?= htmlspecialchars($_GET['search']) ?>"
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                    </h1>
                    <p class="results-count"><?= $total_products ?> product<?= $total_products != 1 ? 's' : '' ?> found</p>
                </div>
                <div class="sort-bar">
                    <span class="sort-label">Sort by:</span>
                    <select class="sort-select" onchange="window.location.href=this.value;">
                        <option value="<?= buildUrl(['sort' => 'newest', 'page' => null]) ?>" 
                                <?= (($_GET['sort'] ?? 'newest') == 'newest') ? 'selected' : '' ?>>Newest</option>
                        <option value="<?= buildUrl(['sort' => 'price_asc', 'page' => null]) ?>" 
                                <?= ($_GET['sort'] ?? '') == 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="<?= buildUrl(['sort' => 'price_desc', 'page' => null]) ?>" 
                                <?= ($_GET['sort'] ?? '') == 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="<?= buildUrl(['sort' => 'name_asc', 'page' => null]) ?>" 
                                <?= ($_GET['sort'] ?? '') == 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                        <option value="<?= buildUrl(['sort' => 'popular', 'page' => null]) ?>" 
                                <?= ($_GET['sort'] ?? '') == 'popular' ? 'selected' : '' ?>>Best Selling</option>
                        <option value="<?= buildUrl(['sort' => 'rating', 'page' => null]) ?>" 
                                <?= ($_GET['sort'] ?? '') == 'rating' ? 'selected' : '' ?>>Top Rated</option>
                    </select>
                </div>
            </div>

            <!-- Product Grid -->
            <?php if ($products->num_rows > 0): ?>
                <div class="product-grid">
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($product['primary_image']): ?>
                                    <img src="<?= htmlspecialchars($product['primary_image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <?php else: ?>
                                    🖼️
                                <?php endif; ?>
                            </div>
                            <a href="product.php?id=<?= $product['id'] ?>" class="product-name">
                                <?= htmlspecialchars($product['name']) ?>
                            </a>
                            <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                            <div class="product-stock">
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    ✅ Stock: <?= $product['stock_quantity'] ?>
                                <?php else: ?>
                                    ❌ Out of Stock
                                <?php endif; ?>
                            </div>
                            <div class="product-actions">
                                <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-view">View</a>
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <a href="cart.php?add=<?= $product['id'] ?>" class="btn btn-cart">Add to Cart</a>
                                <?php else: ?>
                                    <button class="btn btn-cart" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="page-link">← Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): ?>
                            <a href="<?= buildUrl(['page' => $i]) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="page-link">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i>🔍</i>
                    <h2>No products found</h2>
                    <p>Try adjusting your filters or search term.</p>
                    <a href="index.php" class="btn btn-cart" style="display: inline-block; padding: 12px 30px;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Footer -->
    <footer style="background: #333; color: white; padding: 30px 0; margin-top: 50px;">
        <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px; text-align: center;">
            <p>© 2024 Harotey Shop. All rights reserved.</p>
            <p style="color: #aaa; font-size: 14px; margin-top: 10px;">
                <a href="index.php" style="color: #28a745; text-decoration: none;">Home</a> | 
                <a href="shop.php" style="color: #28a745; text-decoration: none;">Shop</a> | 
                <a href="contact.php" style="color: #28a745; text-decoration: none;">Contact</a> | 
                <a href="about.php" style="color: #28a745; text-decoration: none;">About</a>
            </p>
        </div>
    </footer>
</body>
</html>