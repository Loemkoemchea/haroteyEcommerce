<?php
// file: admin/reports.php
require_once 'auth_check.php';

$current_year = date('Y');
$current_month = date('m');

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;

// Build date range for the selected month
$start_date = "$selected_year-$selected_month-01 00:00:00";
$end_date = date("Y-m-t 23:59:59", strtotime($start_date));

// =============================================
// 1. Summary stats for the selected month
// =============================================
$summary = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_order_value
    FROM orders
    WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
");
$summary->bind_param("ss", $start_date, $end_date);
$summary->execute();
$stats = $summary->get_result()->fetch_assoc();
$summary->close();

// =============================================
// 2. Daily breakdown for the selected month
// =============================================
$daily = $conn->prepare("
    SELECT 
        DATE(created_at) as day,
        COUNT(*) as orders,
        SUM(total_amount) as revenue
    FROM orders
    WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$daily->bind_param("ss", $start_date, $end_date);
$daily->execute();
$daily_data = $daily->get_result();
$daily->close();

// Prepare arrays for Chart.js
$daily_labels = [];
$daily_revenue = [];
$daily_orders = [];
while ($row = $daily_data->fetch_assoc()) {
    $daily_labels[] = date('M d', strtotime($row['day']));
    $daily_revenue[] = $row['revenue'];
    $daily_orders[] = $row['orders'];
}
// Reset pointer for table display later
$daily_data->data_seek(0);

// =============================================
// 3. Top selling products for the selected month
// =============================================
$top_products = $conn->prepare("
    SELECT 
        p.id,
        p.name,
        SUM(oi.quantity) as total_sold,
        SUM(oi.total_amount) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.created_at BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 10
");
$top_products->bind_param("ss", $start_date, $end_date);
$top_products->execute();
$top_list = $top_products->get_result();
$top_products->close();

$product_labels = [];
$product_sold = [];
while ($row = $top_list->fetch_assoc()) {
    $product_labels[] = $row['name'];
    $product_sold[] = $row['total_sold'];
}
// Reset pointer
$top_list->data_seek(0);

// =============================================
// 4. Export CSV
// =============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . $selected_year . '_' . $selected_month . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Orders', 'Revenue']);

    // Use stored daily data
    $daily_for_export = $conn->prepare("
        SELECT DATE(created_at) as day, COUNT(*) as orders, SUM(total_amount) as revenue
        FROM orders
        WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");
    $daily_for_export->bind_param("ss", $start_date, $end_date);
    $daily_for_export->execute();
    $export_result = $daily_for_export->get_result();
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [$row['day'], $row['orders'], $row['revenue']]);
    }
    fclose($output);
    exit;
    // =============================================
    // 5. Payment method breakdown for donut chart
    // =============================================
    $payment = $conn->prepare("
        SELECT 
            payment_method,
            COUNT(*) as order_count,
            SUM(total_amount) as total_revenue
        FROM orders
        WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
        GROUP BY payment_method
        ORDER BY order_count DESC
    ");
    $payment->bind_param("ss", $start_date, $end_date);
    $payment->execute();
    $payment_result = $payment->get_result();
    $payment->close();

    $payment_labels = [];
    $payment_counts = [];
    $payment_revenues = [];
    while ($row = $payment_result->fetch_assoc()) {
        $method = $row['payment_method'] ?? 'Unknown';
        $display = ucwords(str_replace('_', ' ', $method));
        $payment_labels[] = $display;
        $payment_counts[] = $row['order_count'];
        $payment_revenues[] = $row['total_revenue'];
    }
    // Reset pointer if needed later
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Harotey Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .filters { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #666; }
        .filter-group select, .filter-group button { padding: 8px 16px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 4px solid #28a745; }
        .stat-card h3 { margin: 0 0 10px; color: #666; font-size: 14px; }
        .stat-number { font-size: 28px; font-weight: bold; color: #333; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px; }
        .table-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .table-container h2 { margin-top: 0; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .export-buttons { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        canvas { max-height: 300px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Sales Reports</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
        </div>

        <!-- Filter form -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year">
                        <?php for ($y = $current_year - 2; $y <= $current_year; $y++): ?>
                            <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Apply Filter</button>
                <a href="?year=<?= $current_year ?>&month=<?= $current_month ?>" class="btn btn-secondary">Current Month</a>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-number">$<?= number_format($stats['total_revenue'], 2) ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="stat-number"><?= $stats['total_orders'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Average Order Value</h3>
                <div class="stat-number">$<?= number_format($stats['avg_order_value'], 2) ?></div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="chart-row">
            <!-- Daily Revenue Chart -->
            <div class="chart-container">
                <h2>Daily Revenue – <?= date('F Y', strtotime($start_date)) ?></h2>
                <canvas id="dailyChart"></canvas>
            </div>
            <!-- Top Products Chart -->
            <div class="chart-container">
                <h2>Top Products by Quantity</h2>
                <canvas id="productChart"></canvas>
            </div>
            <!-- New row for donut chart -->
            <!-- <div class="chart-row" style="margin-top: 30px;">
                <div class="chart-container" style="grid-column: span 2; max-width: 500px; margin: 0 auto;">
                    <h2>Payment Method Distribution</h2>
                    <canvas id="paymentChart"></canvas>
                </div>
            </div> -->
        </div>

        <!-- Daily Breakdown & Top Products Tables -->
        <div class="two-column">
            <!-- Daily Sales Table -->
            <div class="table-container">
                <h2>Daily Sales – <?= date('F Y', strtotime($start_date)) ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daily_data->num_rows > 0): ?>
                            <?php while ($row = $daily_data->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($row['day'])) ?></td>
                                    <td><?= $row['orders'] ?></td>
                                    <td>$<?= number_format($row['revenue'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align: center;">No sales this month</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="export-buttons">
                    <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&export=csv" class="btn">📥 Export CSV</a>
                    <a href="export_pdf.php?year=<?= $selected_year ?>&month=<?= $selected_month ?>" class="btn" style="background: #dc3545;">📄 Export PDF</a>
                </div>
            </div>

            <!-- Top Products Table -->
            <div class="table-container">
                <h2>Top Selling Products – <?= date('F Y', strtotime($start_date)) ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($top_list->num_rows > 0): ?>
                            <?php while ($row = $top_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= $row['total_sold'] ?></td>
                                    <td>$<?= number_format($row['total_revenue'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align: center;">No products sold</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Database Backup Section -->
        <div style="margin-top: 40px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
            <h2 style="margin-top: 0;">🗄️ Database Backup</h2>
            <p style="color: #666;">Download a complete SQL dump of your database (via phpMyAdmin recommended).</p>
            <a href="backup.php" class="btn" onclick="return confirm('Generate and download database backup?')">📥 Download Backup (SQL)</a>
            <p style="margin-top: 10px; color: #999; font-size: 13px;">For large databases, use phpMyAdmin's export for better performance.</p>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const dailyLabels = <?= json_encode($daily_labels) ?>;
        const dailyRevenue = <?= json_encode($daily_revenue) ?>;
        const productLabels = <?= json_encode($product_labels) ?>;
        const productSold = <?= json_encode($product_sold) ?>;

        // Daily Revenue Chart
        const ctxDaily = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctxDaily, {
            type: 'bar',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Revenue ($)',
                    data: dailyRevenue,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '$' + value; }
                        }
                    }
                }
            }
        });

        // Top Products Chart
        const ctxProduct = document.getElementById('productChart').getContext('2d');
        new Chart(ctxProduct, {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Quantity Sold',
                    data: productSold,
                    backgroundColor: 'rgba(255, 193, 7, 0.5)',
                    borderColor: '#ffc107',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y', // horizontal bar for better label readability
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>