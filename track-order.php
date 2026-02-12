<?php
// file: track-order.php
include 'db.php';

$order = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = trim($_POST['order_id']);
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_email = ?");
    $stmt->bind_param("is", $order_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if (!$order) {
        $error = "No order found with this ID and email combination";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Order - Harotey Shop</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .track-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .track-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }
        
        .track-card h1 {
            color: #28a745;
            margin-bottom: 30px;
        }
        
        .track-form {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #28a745;
            outline: none;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.1);
        }
        
        .btn-track {
            width: 100%;
            padding: 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-track:hover {
            background: #218838;
        }
        
        .order-details {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
            text-align: left;
        }
        
        .tracking-number {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 18px;
            color: #28a745;
            border: 1px dashed #28a745;
        }
    </style>
</head>
<body>
    <div class="track-container">
        <div class="track-card">
            <h1>📦 Track Your Order</h1>
            <p style="color: #666; margin-bottom: 30px;">Enter your order ID and email address to track your package</p>
            
            <form method="POST" class="track-form">
                <div class="form-group">
                    <label for="order_id">Order ID</label>
                    <input type="text" id="order_id" name="order_id" required 
                           placeholder="e.g., 12345">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="your@email.com">
                </div>
                
                <button type="submit" class="btn-track">Track Order</button>
                
                <p style="margin-top: 20px;">
                    <a href="index.php" style="color: #666;">← Back to Shop</a>
                </p>
            </form>
            
            <?php if ($error): ?>
                <div style="margin-top: 30px; padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($order): ?>
                <div class="order-details">
                    <h2 style="color: #333; margin-bottom: 20px;">Order #<?= $order['id'] ?></h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <strong>Status:</strong><br>
                            <span class="status-badge status-<?= $order['status'] ?>" style="display: inline-block; margin-top: 8px;">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </div>
                        <div>
                            <strong>Order Date:</strong><br>
                            <?= date('F j, Y', strtotime($order['created_at'])) ?>
                        </div>
                        <div>
                            <strong>Total Amount:</strong><br>
                            ৳<?= number_format($order['total_amount'], 2) ?>
                        </div>
                        <div>
                            <strong>Payment Status:</strong><br>
                            <?= ucfirst($order['payment_status'] ?? 'pending') ?>
                        </div>
                    </div>
                    
                    <?php if ($order['tracking_number']): ?>
                        <div style="margin-top: 30px;">
                            <h3 style="margin-bottom: 15px;">Tracking Information</h3>
                            <div class="tracking-number">
                                <?= $order['tracking_number'] ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 30px;">
                        <a href="user/order_detail.php?id=<?= $order['id'] ?>" class="btn" style="background: #007bff;">View Full Details</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>