<?php
// file: checkout.php – ONLY FOR LOGGED-IN USERS
session_start();
include 'db.php';

// ---------- 1. AUTHENTICATION CHECK ----------
if (!isset($_SESSION['user_id'])) {
    // Remember where the user wanted to go
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header("Location: user/index.php?redirect=$redirect");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---------- 2. FETCH USER DATA ----------
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---------- 3. FETCH DEFAULT SHIPPING ADDRESS (if any) ----------
$addr = null;
$addr_stmt = $conn->prepare("
    SELECT * FROM user_addresses 
    WHERE user_id = ? AND is_default = 1 
    LIMIT 1
");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addr = $addr_stmt->get_result()->fetch_assoc();
$addr_stmt->close();

// If no default address, try to get any address
if (!$addr) {
    $addr_stmt = $conn->prepare("
        SELECT * FROM user_addresses 
        WHERE user_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $addr_stmt->bind_param("i", $user_id);
    $addr_stmt->execute();
    $addr = $addr_stmt->get_result()->fetch_assoc();
    $addr_stmt->close();
}

// ---------- 4. VERIFY CART IS NOT EMPTY ----------
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// ---------- 5. CALCULATE CART TOTAL ----------
$total = 0;
$cart_items = [];
foreach ($_SESSION['cart'] as $pid => $qty) {
    $pid = (int)$pid;
    $qty = (int)$qty;
    $prod = $conn->prepare("SELECT name, price, stock_quantity FROM products WHERE id = ? AND is_active = 1");
    $prod->bind_param("i", $pid);
    $prod->execute();
    $p = $prod->get_result()->fetch_assoc();
    $prod->close();
    if ($p) {
        $cart_items[] = [
            'id' => $pid,
            'name' => $p['name'],
            'price' => $p['price'],
            'quantity' => $qty,
            'subtotal' => $p['price'] * $qty
        ];
        $total += $p['price'] * $qty;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Harotey Shop</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        * { box-sizing: border-box; }
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
        }
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }
        .checkout-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            padding: 30px;
        }
        .order-summary {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        h1, h2, h3 { margin-top: 0; }
        .form-section {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child { border-bottom: none; }
        .form-section h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border 0.2s;
            background: white;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #28a745;
            outline: none;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.1);
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        .address-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .address-option:hover {
            background: #e8f5e9;
        }
        .address-option.selected {
            border-color: #28a745;
            background: #e8f5e9;
        }
        .address-option input[type="radio"] {
            width: 20px;
            height: 20px;
            accent-color: #28a745;
            margin: 0;
        }
        .address-details {
            flex: 1;
        }
        .address-name {
            font-weight: 600;
            color: #333;
        }
        .address-line {
            color: #666;
            font-size: 14px;
            margin-top: 3px;
        }
        .btn-add-address {
            display: inline-block;
            margin-top: 10px;
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .payment-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .payment-option.selected {
            border-color: #28a745;
            background: #e8f5e9;
        }
        .payment-option input[type="radio"] {
            width: 20px;
            height: 20px;
            accent-color: #28a745;
            margin: 0;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed #eee;
        }
        .order-item:last-child { border-bottom: none; }
        .item-name {
            font-weight: 600;
            color: #333;
        }
        .item-quantity {
            color: #666;
            font-size: 14px;
        }
        .item-price {
            font-weight: 600;
            color: #28a745;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            font-size: 20px;
            font-weight: 700;
        }
        .total-amount {
            color: #28a745;
            font-size: 24px;
        }
        .btn-place-order {
            width: 100%;
            padding: 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 25px;
        }
        .btn-place-order:hover {
            background: #218838;
        }
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .login-required {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            .order-summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <!-- Header (reuse from index) -->
    <header style="background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 15px 0; margin-bottom: 30px;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" style="font-size: 24px; font-weight: 700; color: #28a745; text-decoration: none;">🛍️ Harotey Shop</a>
            <nav>
                <a href="index.php" style="color: #333; text-decoration: none; margin: 0 10px;">Home</a>
                <a href="cart.php" style="color: #333; text-decoration: none; margin: 0 10px;">Cart</a>
                <a href="user/dashboard.php" style="color: #333; text-decoration: none; margin: 0 10px;"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></a>
            </nav>
        </div>
    </header>

    <div class="checkout-container">
        <!-- LEFT: CHECKOUT FORM -->
        <div class="checkout-form">
            <h1 style="margin-bottom: 25px;">📦 Checkout</h1>

                <form action="order.php" method="POST" id="checkoutForm" onsubmit="return confirm('Are you sure you want to place this order?');">
                <!-- ---------- CONTACT INFORMATION ---------- -->
                <div class="form-section">
                    <h2>📞 Contact Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Full Name *</label>
                            <input type="text" id="customer_name" name="customer_name" required
                                   value="<?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="customer_phone">Phone Number *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="01XXXXXXXXX">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="customer_email">Email Address *</label>
                        <input type="email" id="customer_email" name="customer_email" required
                               value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        <small style="color: #666;">Your email cannot be changed here. <a href="user/profile.php" style="color: #28a745;">Edit profile</a></small>
                    </div>
                </div>

                <!-- ---------- SHIPPING ADDRESS ---------- -->
                <div class="form-section">
                    <h2>🏠 Shipping Address</h2>
                    
                    <?php if ($addr): ?>
                        <div style="margin-bottom: 20px;">
                            <div class="address-option selected" onclick="document.getElementById('address_<?= $addr['id'] ?>').click();">
                                <input type="radio" name="address_id" id="address_<?= $addr['id'] ?>" value="<?= $addr['id'] ?>" checked hidden>
                                <div>
                                    <span class="address-name"><?= htmlspecialchars($addr['full_name'] ?: $user['full_name']) ?></span>
                                    <div class="address-line">
                                        <?= htmlspecialchars($addr['address_line1']) ?>,
                                        <?= $addr['address_line2'] ? htmlspecialchars($addr['address_line2']) . ', ' : '' ?>
                                        <?= htmlspecialchars($addr['city']) ?>,
                                        <?= $addr['postal_code'] ? htmlspecialchars($addr['postal_code']) . ', ' : '' ?>
                                        <?= htmlspecialchars($addr['country']) ?>
                                        <br>📞 <?= htmlspecialchars($addr['phone']) ?>
                                    </div>
                                </div>
                                <span style="margin-left: auto; background: #28a745; color: white; padding: 3px 10px; border-radius: 20px; font-size: 12px;">Default</span>
                            </div>
                        </div>
                        <a href="user/addresses.php" class="btn-add-address">+ Add another address</a>
                        
                        <!-- hidden fields for new address if user chooses to enter manually -->
                        <div style="margin-top: 25px; display: none;" id="manualAddress">
                            <p style="color: #28a745; font-weight: 600;">Or enter a new address:</p>
                            <?php else: ?>
                                <p style="color: #666; margin-bottom: 15px;">You haven't saved any addresses yet.</p>
                                <a href="user/addresses.php" class="btn-add-address">+ Add a new address</a>
                                <div style="margin-top: 25px;">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="address_line1">Street Address *</label>
                                <input type="text" id="address_line1" name="address_line1" 
                                       value="<?= htmlspecialchars($addr['address_line1'] ?? '') ?>" 
                                       placeholder="House number, street name">
                            </div>
                            <div class="form-group">
                                <label for="address_line2">Address Line 2 (Optional)</label>
                                <input type="text" id="address_line2" name="address_line2" 
                                       value="<?= htmlspecialchars($addr['address_line2'] ?? '') ?>" 
                                       placeholder="Apartment, suite, unit">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City *</label>
                                    <input type="text" id="city" name="city" 
                                           value="<?= htmlspecialchars($addr['city'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" 
                                           value="<?= htmlspecialchars($addr['postal_code'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="country">Country *</label>
                                <input type="text" id="country" name="country" 
                                       value="<?= htmlspecialchars($addr['country'] ?? 'Bangladesh') ?>">
                            </div>
                        </div>
                </div>

                <!-- ---------- PAYMENT METHOD ---------- -->
                <div class="form-section">
                    <h2>💳 Payment Method</h2>
                    <div class="payment-options">
                        <label class="payment-option <?= (!isset($_POST['payment_method']) || $_POST['payment_method'] == 'cash_on_delivery') ? 'selected' : '' ?>">
                            <input type="radio" name="payment_method" value="cash_on_delivery" checked>
                            <span style="font-weight: 600;">Cash on Delivery</span>
                            <span style="margin-left: auto; color: #666; font-size: 14px;">Pay when you receive</span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="bkash">
                            <span style="font-weight: 600;">bKash</span>
                            <span style="margin-left: auto; color: #666; font-size: 14px;">Send money</span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="nagad">
                            <span style="font-weight: 600;">Nagad</span>
                            <span style="margin-left: auto; color: #666; font-size: 14px;">Send money</span>
                        </label>
                    </div>
                </div>

                <!-- ---------- ORDER NOTES ---------- -->
                <div class="form-section">
                    <h2>📝 Order Notes (Optional)</h2>
                    <div class="form-group">
                        <textarea name="notes" rows="3" placeholder="Special instructions for delivery, e.g. gate code, delivery time, etc." style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                    </div>
                </div>

                <!-- Hidden field to indicate address method -->
                <input type="hidden" name="use_saved_address" id="use_saved_address" value="1">
                
                <button type="submit" class="btn-place-order" onclick="return validateCheckout()">✅ Place Order</button>
            </form>
        </div>

        <!-- RIGHT: ORDER SUMMARY -->
        <div class="order-summary">
            <h2 style="margin-bottom: 20px;">🛒 Your Order</h2>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                <?php foreach ($cart_items as $item): ?>
                    <div class="order-item">
                        <div>
                            <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="item-quantity"> × <?= $item['quantity'] ?></span>
                        </div>
                        <span class="item-price">৳<?= number_format($item['subtotal'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="total-row">
                <span>Total</span>
                <span class="total-amount">৳<?= number_format($total, 2) ?></span>
            </div>
            <div class="secure-badge">
                <span>🔒</span> Secure checkout · SSL encrypted
            </div>
        </div>
    </div>

    <script>
        // Auto-highlight payment option when selected
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Handle address selection – hide/show manual fields if saved address exists
        <?php if ($addr): ?>
        // If we have a saved address, manual fields are hidden by default.
        // Add a link to enter a new address.
        const manualDiv = document.getElementById('manualAddress');
        const addNewLink = document.createElement('a');
        addNewLink.href = '#';
        addNewLink.className = 'btn-add-address';
        addNewLink.style.marginTop = '15px';
        addNewLink.innerHTML = '+ Enter a different address';
        addNewLink.onclick = function(e) {
            e.preventDefault();
            manualDiv.style.display = 'block';
            this.style.display = 'none';
            document.getElementById('use_saved_address').value = '0';
            // Uncheck the saved address radio if exists
            const savedRadio = document.querySelector('input[name="address_id"]');
            if (savedRadio) savedRadio.checked = false;
        };
        document.querySelector('.form-section:nth-child(2) .btn-add-address').after(addNewLink);
        <?php endif; ?>

        // Basic form validation
        function validateCheckout() {
            const name = document.getElementById('customer_name').value.trim();
            const phone = document.getElementById('customer_phone').value.trim();
            if (!name || !phone) {
                alert('Please fill in all required fields.');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>