<?php
// file: checkout.php (UPDATED)
session_start();
include 'db.php';

if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// If user is logged in, pre-fill form
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout - Harotey Shop</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        
        .checkout-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .order-summary {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: fit-content;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        
        .login-prompt {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-login {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-form">
            <h1>Checkout</h1>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="login-prompt">
                    <span>Already have an account?</span>
                    <a href="user/index.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn-login">Login for faster checkout</a>
                </div>
            <?php endif; ?>
            
            <form action="order.php" method="POST">
                <div class="form-section">
                    <h3>📋 Contact Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="customer_name">Full Name *</label>
                            <input type="text" id="customer_name" name="customer_name" required 
                                   value="<?= htmlspecialchars($user['full_name'] ?? ($user['username'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="customer_email">Email Address *</label>
                            <input type="email" id="customer_email" name="customer_email" required 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="customer_phone">Phone Number *</label>
                            <input type="text" id="customer_phone" name="customer_phone" required 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>🏠 Shipping Address</h3>
                    <div class="form-group">
                        <label for="address">Street Address *</label>
                        <textarea id="address" name="address" rows="3" required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" required 
                                   value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" 
                                   value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" 
                                   value="<?= htmlspecialchars($user['country'] ?? 'Bangladesh') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>💳 Payment Method</h3>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="payment_method" value="cash_on_delivery" checked>
                            Cash on Delivery
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="payment_method" value="bkash">
                            bKash
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="payment_method" value="nagad">
                            Nagad
                        </label>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>📝 Order Notes (Optional)</h3>
                    <div class="form-group">
                        <textarea name="notes" rows="3" placeholder="Special instructions for delivery"></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; padding: 15px; background: #28a745; font-size: 16px;">
                    Place Order
                </button>
            </form>
        </div>
        
        <div class="order-summary">
            <h3 style="margin-top: 0;">Your Order</h3>
            <?php
            $total = 0;
            $cart_items = [];
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                if ($product) {
                    $subtotal = $product['price'] * $quantity;
                    $total += $subtotal;
                    ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                        <div>
                            <span style="font-weight: 600;"><?= htmlspecialchars($product['name']) ?></span>
                            <span style="color: #666;"> × <?= $quantity ?></span>
                        </div>
                        <span>৳<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <?php
                }
            }
            ?>
            
            <div style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Subtotal:</span>
                    <span>৳<?= number_format($total, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Shipping:</span>
                    <span>৳0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #f0f0f0; font-size: 18px; font-weight: bold;">
                    <span>Total:</span>
                    <span style="color: #28a745;">৳<?= number_format($total, 2) ?></span>
                </div>
            </div>
            
            <div style="margin-top: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">🛡️ Secure Checkout</h4>
                <p style="margin: 0; color: #666; font-size: 13px;">Your personal data will be used to process your order and for other purposes described in our privacy policy.</p>
            </div>
        </div>
    </div>
</body>
</html>