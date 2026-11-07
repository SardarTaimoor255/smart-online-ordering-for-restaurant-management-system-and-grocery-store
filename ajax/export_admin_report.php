<?php
// ajax/export_admin_report.php
require_once __DIR__ . '/../config/database.php';

// Only admin can access
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

$reportMonth = isset($_GET['report_month']) ? $_GET['report_month'] : date('Y-m');
$startDate = date('Y-m-01', strtotime($reportMonth));
$endDate = date('Y-m-t', strtotime($reportMonth));

// Total orders in period
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$totalOrdersPeriod = $stmt->fetchColumn();

// Total sales and platform earnings for the period
$stmt = $pdo->prepare("
    SELECT 
        SUM(total_amount) as total_sales,
        SUM(admin_earnings) as platform_earnings,
        SUM(delivery_fee) as total_delivery,
        SUM(rider_earnings) as total_rider_payout,
        SUM(commission_amount) as total_commission,
        SUM(discount_amount) as total_discount
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$reportData = $stmt->fetch();
$totalSales = $reportData['total_sales'] ?? 0;
$platformEarnings = $reportData['platform_earnings'] ?? 0;
$totalDelivery = $reportData['total_delivery'] ?? 0;
$totalRiderPayout = $reportData['total_rider_payout'] ?? 0;
$totalCommission = $reportData['total_commission'] ?? 0;
$totalDiscount = $reportData['total_discount'] ?? 0;

// Top stores by revenue
$topStores = $pdo->prepare("
    SELECT s.store_name, COUNT(o.id) as order_count, SUM(o.total_amount) as revenue
    FROM stores s
    JOIN orders o ON s.id = o.store_id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY revenue DESC
    LIMIT 10
");
$topStores->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topStoresList = $topStores->fetchAll();

// Top items by quantity
$topItems = $pdo->prepare("
    SELECT si.name, s.store_name, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN store_items si ON oi.item_id = si.id
    JOIN stores s ON si.store_id = s.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY si.id
    ORDER BY total_qty DESC
    LIMIT 10
");
$topItems->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topItemsList = $topItems->fetchAll();

// Top riders (by deliveries)
$topRiders = $pdo->prepare("
    SELECT u.name, COUNT(o.id) as deliveries, SUM(o.rider_earnings) as earnings
    FROM orders o
    JOIN users u ON o.rider_id = u.id
    WHERE o.rider_id IS NOT NULL AND o.created_at BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY deliveries DESC
    LIMIT 5
");
$topRiders->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topRidersList = $topRiders->fetchAll();

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="admin_report_' . $reportMonth . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Write header
fputcsv($output, ['Admin Report for ' . date('F Y', strtotime($reportMonth))]);
fputcsv($output, []);
fputcsv($output, ['Metric', 'Value']);
fputcsv($output, ['Total Orders (Period)', $totalOrdersPeriod]);
fputcsv($output, ['Total Sales (Customer)', 'Rs. ' . number_format($totalSales, 2)]);
fputcsv($output, ['Platform Earnings', 'Rs. ' . number_format($platformEarnings, 2)]);
fputcsv($output, ['Delivery Fees', 'Rs. ' . number_format($totalDelivery, 2)]);
fputcsv($output, ['Rider Payouts', 'Rs. ' . number_format($totalRiderPayout, 2)]);
fputcsv($output, ['Commission', 'Rs. ' . number_format($totalCommission, 2)]);
fputcsv($output, ['Discounts Given', 'Rs. ' . number_format($totalDiscount, 2)]);
fputcsv($output, []);
fputcsv($output, ['Top Stores']);
fputcsv($output, ['Store Name', 'Orders', 'Revenue']);
foreach ($topStoresList as $store) {
    fputcsv($output, [$store['store_name'], $store['order_count'], 'Rs. ' . number_format($store['revenue'], 2)]);
}
fputcsv($output, []);
fputcsv($output, ['Top Items']);
fputcsv($output, ['Item Name', 'Store', 'Quantity', 'Revenue']);
foreach ($topItemsList as $item) {
    fputcsv($output, [$item['name'], $item['store_name'], $item['total_qty'], 'Rs. ' . number_format($item['revenue'], 2)]);
}
fputcsv($output, []);
fputcsv($output, ['Top Riders']);
fputcsv($output, ['Rider Name', 'Deliveries', 'Earnings']);
foreach ($topRidersList as $rider) {
    fputcsv($output, [$rider['name'], $rider['deliveries'], 'Rs. ' . number_format($rider['earnings'], 2)]);
}

fclose($output);
exit();
?>