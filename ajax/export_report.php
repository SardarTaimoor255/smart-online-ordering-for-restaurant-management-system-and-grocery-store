<?php
// ajax/export_report.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'owner') {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

$reportMonth = isset($_GET['report_month']) ? $_GET['report_month'] : date('Y-m');
$startDate = date('Y-m-01', strtotime($reportMonth));
$endDate = date('Y-m-t', strtotime($reportMonth));

// Get the owner's store
$ownerId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM stores WHERE owner_id = ?");
$stmt->execute([$ownerId]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(404);
    echo 'No store found';
    exit();
}
$storeId = $store['id'];

// Fetch report data
$stmt = $pdo->prepare("
    SELECT 
        SUM(total_amount) as total_sales,
        SUM(restaurant_earnings) as total_earnings,
        SUM(delivery_fee) as total_delivery,
        SUM(commission_amount) as total_commission,
        SUM(discount_amount) as total_discount
    FROM orders
    WHERE store_id = ? AND created_at BETWEEN ? AND ?
");
$stmt->execute([$storeId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$salesData = $stmt->fetch();

$totalSales = $salesData['total_sales'] ?? 0;
$totalEarnings = $salesData['total_earnings'] ?? 0;
$totalDelivery = $salesData['total_delivery'] ?? 0;
$totalCommission = $salesData['total_commission'] ?? 0;
$totalDiscount = $salesData['total_discount'] ?? 0;

$topProducts = $pdo->prepare("
    SELECT si.name, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN store_items si ON oi.item_id = si.id
    WHERE o.store_id = ? AND o.created_at BETWEEN ? AND ?
    GROUP BY si.id
    ORDER BY total_qty DESC
");
$topProducts->execute([$storeId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topProductsList = $topProducts->fetchAll();

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . $reportMonth . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Write header
fputcsv($output, ['Report for ' . date('F Y', strtotime($reportMonth))]);
fputcsv($output, []);
fputcsv($output, ['Metric', 'Value']);
fputcsv($output, ['Total Sales (Customer)', 'Rs. ' . number_format($totalSales, 2)]);
fputcsv($output, ['Net Earnings (Your Share)', 'Rs. ' . number_format($totalEarnings, 2)]);
fputcsv($output, ['Delivery Fees Collected', 'Rs. ' . number_format($totalDelivery, 2)]);
fputcsv($output, ['Commission Paid', 'Rs. ' . number_format($totalCommission, 2)]);
fputcsv($output, ['Discounts Given', 'Rs. ' . number_format($totalDiscount, 2)]);
fputcsv($output, []);
fputcsv($output, ['Top Selling Products']);
fputcsv($output, ['Product Name', 'Quantity Sold', 'Revenue']);
foreach ($topProductsList as $prod) {
    fputcsv($output, [$prod['name'], $prod['total_qty'], 'Rs. ' . number_format($prod['revenue'], 2)]);
}

fclose($output);
exit();
?>