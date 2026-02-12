<?php
// file: admin/delete_product.php
require_once 'auth_check.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id) {
    // Check if product exists in orders before deleting
    $check = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();
    $order_count = $result->fetch_assoc()['count'];
    
    if ($order_count > 0) {
        // Product has orders - soft delete or just mark as inactive
        // For now, we'll just set stock to 0 and rename it
        $stmt = $conn->prepare("UPDATE products SET name = CONCAT(name, ' (Archived)'), stock = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $message = "Product has orders and has been archived instead of deleted.";
    } else {
        // Safe to delete
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $message = "Product deleted successfully.";
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = $message;
    } else {
        $_SESSION['error'] = "Error deleting product: " . $conn->error;
    }
}

header("Location: products.php");
exit;
?>