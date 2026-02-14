<?php
// file: admin/export_pdf.php
require_once 'auth_check.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Dompdf autoloader

use Dompdf\Dompdf;
use Dompdf\Options;

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');

$start_date = "$year-$month-01 00:00:00";
$end_date = date("Y-m-t 23:59:59", strtotime($start_date));

// ========== FETCH DATA ==========
// Summary
$summary = $conn->prepare("
    SELECT COUNT(*) as orders, COALESCE(SUM(total_amount),0) as revenue
    FROM orders WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
");
$summary->bind_param("ss", $start_date, $end_date);
$summary->execute();
$stats = $summary->get_result()->fetch_assoc();
$summary->close();

// Daily
$daily = $conn->prepare("
    SELECT DATE(created_at) as day, COUNT(*) as orders, SUM(total_amount) as revenue
    FROM orders WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY DATE(created_at) ORDER BY day ASC
");
$daily->bind_param("ss", $start_date, $end_date);
$daily->execute();
$daily_data = $daily->get_result();
$daily->close();

// Top products
$top = $conn->prepare("
    SELECT p.name, SUM(oi.quantity) as sold, SUM(oi.total_amount) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.created_at BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY p.id ORDER BY sold DESC LIMIT 10
");
$top->bind_param("ss", $start_date, $end_date);
$top->execute();
$top_list = $top->get_result();
$top->close();

// Payment methods
$payment = $conn->prepare("
    SELECT payment_method, COUNT(*) as count
    FROM orders WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY payment_method
");
$payment->bind_param("ss", $start_date, $end_date);
$payment->execute();
$payment_data = $payment->get_result();
$payment->close();

// ========== BUILD HTML ==========
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 5px; }
        h2 { color: #333; margin-top: 25px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #f2f2f2; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .summary { display: flex; gap: 20px; margin: 20px 0; }
        .card { background: #f9f9f9; padding: 15px; border-radius: 5px; flex: 1; text-align: center; }
        .card h3 { margin: 0 0 5px; color: #666; font-size: 14px; }
        .card .number { font-size: 24px; font-weight: bold; color: #28a745; }
    </style>
</head>
<body>
    <h1>Harotey Shop – Sales Report</h1>
    <p><strong>Period:</strong> ' . date('F Y', strtotime($start_date)) . '</p>

    <div class="summary">
        <div class="card"><h3>Total Orders</h3><div class="number">' . $stats['orders'] . '</div></div>
        <div class="card"><h3>Total Revenue</h3><div class="number">$' . number_format($stats['revenue'], 2) . '</div></div>
    </div>

    <h2>Daily Breakdown</h2>
    <table>
        <tr><th>Date</th><th>Orders</th><th>Revenue</th></tr>';
while ($row = $daily_data->fetch_assoc()) {
    $html .= '<tr><td>' . date('M d, Y', strtotime($row['day'])) . '</td><td>' . $row['orders'] . '</td><td>$' . number_format($row['revenue'], 2) . '</td></tr>';
}
$html .= '</table>

    <h2>Top 10 Products</h2>
    <table>
        <tr><th>Product</th><th>Sold</th><th>Revenue</th></tr>';
while ($row = $top_list->fetch_assoc()) {
    $html .= '<tr><td>' . htmlspecialchars($row['name']) . '</td><td>' . $row['sold'] . '</td><td>$' . number_format($row['revenue'], 2) . '</td></tr>';
}
$html .= '</table>

    <h2>Payment Methods</h2>
    <table>
        <tr><th>Method</th><th>Orders</th></tr>';
while ($row = $payment_data->fetch_assoc()) {
    $method = $row['payment_method'] ?? 'Unknown';
    $display = ucwords(str_replace('_', ' ', $method));
    $html .= '<tr><td>' . $display . '</td><td>' . $row['count'] . '</td></tr>';
}
$html .= '</table>
    <p style="margin-top: 30px; color: #999; font-size: 12px;">Generated on ' . date('Y-m-d H:i:s') . '</p>
</body>
</html>';

// ========== GENERATE PDF ==========
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("sales_report_{$year}_{$month}.pdf", ["Attachment" => true]);
exit;