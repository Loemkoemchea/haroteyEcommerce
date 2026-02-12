<?php
session_start();
include 'db.php';

// ----------------------------------------------
// 1. VALIDATE CART
// ----------------------------------------------
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// ----------------------------------------------
// 2. GET AND SANITIZE CUSTOMER INPUT
// ----------------------------------------------
$customer_name  = trim($_POST['customer_name'] ?? '');
$customer_email = trim($_POST['customer_email'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$shipping_address = trim($_POST['address'] ?? '');
$city           = trim($_POST['city'] ?? '');
$postal_code    = trim($_POST['postal_code'] ?? '');
$country        = trim($_POST['country'] ?? 'Bangladesh');
$payment_method = $_POST['payment_method'] ?? 'cash_on_delivery';
$notes          = trim($_POST['notes'] ?? '');

// Build full address
$full_address = $shipping_address;
if ($city)       $full_address .= ", " . $city;
if ($postal_code) $full_address .= " - " . $postal_code;
if ($country)    $full_address .= ", " . $country;

// Logged in user?
$user_id = $_SESSION['user_id'] ?? null;

// ----------------------------------------------
// 3. VERIFY STOCK AND PREPARE ORDER DATA
// ----------------------------------------------
$total      = 0;
$cart_items = [];

// Begin transaction
$conn->begin_transaction();

try {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $product_id = (int)$product_id;
        $quantity   = (int)$quantity;

        // Fetch current stock and price
        $stmt = $conn->prepare("
            SELECT id, name, price, stock_quantity 
            FROM products 
            WHERE id = ? AND is_active = 1
            FOR UPDATE
        ");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new Exception("Product ID {$product_id} no longer exists.");
        }

        if ($product['stock_quantity'] < $quantity) {
            throw new Exception(
                "Insufficient stock for {$product['name']}. " .
                "Available: {$product['stock_quantity']}, requested: {$quantity}."
            );
        }

        // Store item data for later use
        $cart_items[] = [
            'product_id' => $product_id,
            'name'       => $product['name'],
            'price'      => $product['price'],
            'quantity'   => $quantity,
            'subtotal'   => $product['price'] * $quantity
        ];

        $total += $product['price'] * $quantity;
    }

    // ----------------------------------------------
    // 4. INSERT ORDER (with correct column names)
    // ----------------------------------------------
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, 
            customer_name, 
            customer_email, 
            customer_phone,
            billing_address,      -- ⬅️ required, set same as shipping
            shipping_address, 
            subtotal, 
            total_amount, 
            payment_method, 
            customer_notes        -- ⬅️ was 'notes', now correct
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Use the same address for billing (simplified)
    $billing_address = $full_address;

    $stmt->bind_param(
        "isssssddss",            // 10 parameters
        $user_id, 
        $customer_name, 
        $customer_email, 
        $customer_phone,
        $billing_address,        // billing_address
        $full_address,           // shipping_address
        $total,                  // subtotal
        $total,                  // total_amount
        $payment_method,
        $notes                   // customer_notes
    );
    $stmt->execute();
    $order_id = $stmt->insert_id;

    // Generate tracking number
    $tracking_number = "HAR" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "BD";
    $conn->query("UPDATE orders SET tracking_number = '{$tracking_number}' WHERE id = {$order_id}");

    // ----------------------------------------------
    // 5. INSERT ORDER ITEMS & UPDATE STOCK
    // ----------------------------------------------
    $item_stmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, product_id, product_name, unit_price, quantity, subtotal, total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stock_stmt = $conn->prepare("
        UPDATE products 
        SET stock_quantity = stock_quantity - ?,
            sold_count = sold_count + ?
        WHERE id = ?
    ");

    foreach ($cart_items as $item) {
        // Insert order item
        $item_stmt->bind_param(
            "iisiddi",
            $order_id,
            $item['product_id'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['subtotal'],
            $item['subtotal']
        );
        $item_stmt->execute();

        // Decrease stock, increase sold count
        $stock_stmt->bind_param("iii", $item['quantity'], $item['quantity'], $item['product_id']);
        $stock_stmt->execute();
    }

    // ----------------------------------------------
    // 6. ADD STATUS HISTORY
    // ----------------------------------------------
    $history_stmt = $conn->prepare("
        INSERT INTO order_status_history (order_id, status, comment, created_by)
        VALUES (?, 'pending', 'Order placed successfully', ?)
    ");
    $created_by = $user_id ? "User ID: $user_id" : "Guest: $customer_name";
    $history_stmt->bind_param("is", $order_id, $created_by);
    $history_stmt->execute();

    // ----------------------------------------------
    // 7. COMMIT TRANSACTION
    // ----------------------------------------------
    $conn->commit();

    // ----------------------------------------------
    // 8. CLEAR CART
    // ----------------------------------------------
    unset($_SESSION['cart']);

    // ----------------------------------------------
    // 9. SEND TELEGRAM NOTIFICATION (optional)
    // ----------------------------------------------
    $botToken = "8581941364:AAGS9iL46AWJ3Bfa0TVPnn9RrLNihJ8eubY"; // your bot token
    $chatID   = "1299806559"; // your chat ID

    $message  = "🛒 *New Order #{$order_id}*\n";
    $message .= "Customer: $customer_name\n";
    $message .= "Email: $customer_email\n";
    $message .= "Phone: $customer_phone\n";
    $message .= "Total: ৳" . number_format($total, 2) . "\n";
    $message .= "Payment: " . ucfirst(str_replace('_', ' ', $payment_method)) . "\n";
    $message .= "Tracking: $tracking_number\n";
    $message .= "Items:\n";
    foreach ($cart_items as $item) {
        $message .= "- {$item['name']} x{$item['quantity']} = ৳" . number_format($item['subtotal'], 2) . "\n";
    }

    // Send using cURL
    $ch = curl_init("https://api.telegram.org/bot$botToken/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatID,
        'text'    => $message,
        'parse_mode' => 'Markdown'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    // ----------------------------------------------
    // 10. REDIRECT TO ORDER CONFIRMATION
    // ----------------------------------------------
    header("Location: order-confirmation.php?id=" . $order_id);
    exit;

} catch (Exception $e) {
    // ----------------------------------------------
    // 11. ROLLBACK ON ERROR
    // ----------------------------------------------
    $conn->rollback();
    
    // Log the error (optional)
    error_log("Order failed: " . $e->getMessage());

    // Show user-friendly error message
    echo "<div style='max-width:600px; margin:50px auto; padding:30px; background:#f8d7da; color:#721c24; border-radius:8px;'>";
    echo "<h2>❌ Order Failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='cart.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:4px;'>← Back to Cart</a>";
    echo "</div>";
    exit;
}
?>