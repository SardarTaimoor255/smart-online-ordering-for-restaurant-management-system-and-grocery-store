<?php
// Include database configuration and helper functions
require_once __DIR__ . '/../../bootstrap.php';

// Only admin can access this page
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header('Location: ' . BASE_URL . 'index.php?page=login');
    exit();
}

// ─── CSV Export handler for Reports ─────────────────────────────────
if (isset($_GET['export_csv']) && $_GET['tab'] == 'reports') {
    $reportMonth = isset($_GET['report_month']) ? $_GET['report_month'] : date('Y-m');
    $startDate = date('Y-m-01', strtotime($reportMonth));
    $endDate = date('Y-m-t', strtotime($reportMonth));

    // Fetch data
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

    // Top stores
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

    // Top items
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

    // Top riders
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
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Admin Report for ' . date('F Y', strtotime($reportMonth))]);
    fputcsv($output, []);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Orders (Period)', $totalOrdersPeriod ?? 0]);
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
    exit;
}

// FETCH BASIC STATISTICS
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders    = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue   = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status='delivered'")->fetchColumn();
$totalStores    = $pdo->query("SELECT COUNT(*) FROM stores WHERE is_active=1")->fetchColumn();
$pendingOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

// ─── NEW: Count pending store requests ───
$pendingStoreRequests = $pdo->query("SELECT COUNT(*) FROM stores WHERE is_active = 0")->fetchColumn();

// ─── NEW: Count pending rider requests ───
$pendingRiderRequests = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'rider' AND is_approved = 0")->fetchColumn();

// ─── NEW: Count pending rider assignments ───
$pendingAssignments = $pdo->query("SELECT COUNT(*) FROM rider_assignments WHERE status = 'pending'")->fetchColumn();

// EARNINGS BREAKDOWN (only from delivered orders)
$totalDeliveryFees   = $pdo->query("SELECT SUM(delivery_fee) FROM orders WHERE status='delivered'")->fetchColumn();
$totalRiderEarnings  = $pdo->query("SELECT SUM(rider_earnings) FROM orders WHERE status='delivered'")->fetchColumn();
$totalAdminEarnings  = $pdo->query("SELECT SUM(admin_earnings) FROM orders WHERE status='delivered'")->fetchColumn();
$totalRestaurantEarnings = $pdo->query("SELECT SUM(restaurant_earnings) FROM orders WHERE status='delivered'")->fetchColumn();
$totalCommission = $pdo->query("SELECT SUM(commission_amount) FROM orders WHERE status='delivered'")->fetchColumn();

// TOP RESTAURANTS (by number of orders)
$topRestaurants = $pdo->query("
    SELECT s.store_name, COUNT(o.id) as orders 
    FROM stores s 
    JOIN orders o ON s.id = o.store_id 
    GROUP BY s.id 
    ORDER BY orders DESC 
    LIMIT 5
")->fetchAll();

// REVENUE CHART DATA (last 6 months)
$sixMonthsAgo = date('Y-m-01', strtotime('-5 months'));
$stmt = $pdo->prepare("
    SELECT created_at, total_amount 
    FROM orders 
    WHERE status = 'delivered' AND created_at >= ?
    ORDER BY created_at ASC
");
$stmt->execute([$sixMonthsAgo]);
$orders = $stmt->fetchAll();

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $date = new DateTime("-$i months");
    $months[$date->format('Y-m')] = $date->format('M');
}
$revenueByMonth = array_fill_keys(array_keys($months), 0);
foreach ($orders as $order) {
    $ym = date('Y-m', strtotime($order['created_at']));
    if (isset($revenueByMonth[$ym])) {
        $revenueByMonth[$ym] += $order['total_amount'];
    }
}
$labels = array_values($months);
$values = array_values($revenueByMonth);

// RECENT ORDERS (with customer and rider names)
$recentOrders = $pdo->query("
    SELECT o.*, u.name as customer, r.name as rider_name
    FROM orders o 
    JOIN users u ON o.customer_id = u.id 
    LEFT JOIN users r ON o.rider_id = r.id AND r.role = 'rider'
    ORDER BY o.created_at DESC 
    LIMIT 20
")->fetchAll();

// ---------- NEW: Fetch rider assignment status for each order (used in orders tab) ----------
$orderAssignmentStatus = [];
if (!empty($recentOrders)) {
    $orderIds = array_column($recentOrders, 'id');
    $in = str_repeat('?,', count($orderIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT ra.order_id, ra.rider_id, ra.status, ra.created_at, u.name as rider_name
        FROM rider_assignments ra
        JOIN users u ON ra.rider_id = u.id
        WHERE ra.order_id IN ($in)
        ORDER BY ra.created_at DESC
    ");
    $stmt->execute($orderIds);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        if (!isset($orderAssignmentStatus[$row['order_id']])) {
            $orderAssignmentStatus[$row['order_id']] = $row;
        }
    }
}

// ---------- NEW: Fetch pending rider account approvals (riders with is_approved = 0) ----------
$pendingRiders = $pdo->query("
    SELECT id, name, email, created_at 
    FROM users 
    WHERE role = 'rider' AND is_approved = 0
    ORDER BY created_at DESC
")->fetchAll();

// ---------- NEW: Reports data ──────────────────────────────────────────────
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

// Daily order count and revenue for chart
$dailyStats = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$dailyStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailyStatsData = $dailyStats->fetchAll();
$chartLabelsDaily = [];
$chartOrdersDaily = [];
$chartRevenueDaily = [];
foreach ($dailyStatsData as $row) {
    $chartLabelsDaily[] = date('M j', strtotime($row['date']));
    $chartOrdersDaily[] = (int)$row['orders'];
    $chartRevenueDaily[] = (float)$row['revenue'];
}

// Payment method distribution
$paymentStats = $pdo->prepare("
    SELECT payment_method, COUNT(*) as count
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY payment_method
");
$paymentStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$paymentStatsData = $paymentStats->fetchAll();
$paymentLabels = [];
$paymentCounts = [];
foreach ($paymentStatsData as $row) {
    $paymentLabels[] = ucfirst($row['payment_method']);
    $paymentCounts[] = (int)$row['count'];
}

// Order status breakdown
$statusStats = $pdo->prepare("
    SELECT status, COUNT(*) as count
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");
$statusStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$statusStatsData = $statusStats->fetchAll();
$statusLabels = [];
$statusCounts = [];
foreach ($statusStatsData as $row) {
    $statusLabels[] = ucfirst(str_replace('_', ' ', $row['status']));
    $statusCounts[] = (int)$row['count'];
}

// ACTIVE TAB
$activeTab = $_GET['tab'] ?? 'overview';
$statusColors = [
    'pending'          => '#FFC107',
    'preparing'        => '#3ECFCF',
    'out_for_delivery' => '#818CF8',
    'delivered'        => '#10B981',
    'cancelled'        => '#EF4444'
];
?>

<style>
/* DARK THEME ADMIN STYLES (unchanged) */
.admin-shell{display:flex;min-height:calc(100vh - 70px);margin:-1rem -12px 0;gap:0;background:var(--bg-base);}
.admin-sidebar{width:240px;flex-shrink:0;background:rgba(255,255,255,0.025);border-right:1px solid var(--border);padding:1.5rem 0;display:flex;flex-direction:column;gap:0.25rem;position:sticky;top:70px;height:calc(100vh - 70px);overflow-y:auto;transition:width 0.3s ease;}
.admin-sidebar.collapsed{width:64px;}
.sidebar-header{padding:0 1.25rem 1.25rem;border-bottom:1px solid var(--border);margin-bottom:0.75rem;display:flex;align-items:center;justify-content:space-between;}
.sidebar-title{font-size:0.7rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);white-space:nowrap;overflow:hidden;}
.admin-sidebar.collapsed .sidebar-title,.admin-sidebar.collapsed .nav-label,.admin-sidebar.collapsed .sidebar-badge,.admin-sidebar.collapsed .sidebar-section-label{display:none;}
.sidebar-toggle{background:none;border:1px solid var(--border);color:var(--text-muted);border-radius:6px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.7rem;flex-shrink:0;transition:var(--transition);}
.sidebar-toggle:hover{border-color:var(--primary);color:var(--primary);}
.sidebar-nav-item{display:flex;align-items:center;gap:10px;padding:0.6rem 1.25rem;color:var(--text-secondary);text-decoration:none;font-size:0.875rem;font-weight:500;transition:var(--transition);position:relative;white-space:nowrap;margin:0 0.5rem;border-radius:var(--radius-sm);}
.sidebar-nav-item:hover{background:rgba(255,255,255,0.05);color:var(--text-primary);}
.sidebar-nav-item.active{background:rgba(255,87,34,0.12);color:var(--primary);border:1px solid rgba(255,87,34,0.2);}
.sidebar-nav-item.active::before{content:'';position:absolute;left:-0.5rem;top:50%;transform:translateY(-50%);width:3px;height:20px;background:var(--primary);border-radius:2px;}
.nav-icon{width:18px;text-align:center;font-size:0.9rem;flex-shrink:0;}
.sidebar-section-label{font-size:0.65rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);padding:1rem 1.75rem 0.4rem;}
.sidebar-badge{margin-left:auto;background:var(--primary);color:white;border-radius:50px;font-size:0.65rem;font-weight:700;padding:1px 7px;flex-shrink:0;}
.admin-main{flex:1;padding:2rem;min-width:0;overflow-x:hidden;}
.admin-page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
.admin-page-title{font-family:var(--font-display);font-size:1.75rem;font-weight:700;margin:0;line-height:1.2;}
.admin-page-subtitle{color:var(--text-muted);font-size:0.85rem;margin-top:0.25rem;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.25rem;margin-bottom:2rem;}
.stat-card{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.4rem;position:relative;overflow:hidden;transition:var(--transition);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--accent-color,var(--primary));opacity:.7;}
.stat-card:hover{background:rgba(255,255,255,0.05);border-color:var(--accent-color,var(--primary));transform:translateY(-3px);box-shadow:0 12px 40px rgba(0,0,0,.4);}
.stat-icon{width:42px;height:42px;border-radius:var(--radius-sm);background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:1rem;border:1px solid rgba(255,255,255,0.08);}
.stat-value{font-family:var(--font-display);font-size:1.9rem;font-weight:700;line-height:1;margin-bottom:0.3rem;}
.stat-label{color:var(--text-muted);font-size:0.8rem;font-weight:500;text-transform:uppercase;letter-spacing:0.06em;}
.stat-trend{margin-top:0.75rem;font-size:0.78rem;font-weight:600;display:flex;align-items:center;gap:4px;}
.admin-grid{display:grid;grid-template-columns:1fr 340px;gap:1.5rem;margin-bottom:1.5rem;}
@media(max-width:1100px){.admin-grid{grid-template-columns:1fr;}}
.admin-panel{background:rgba(255,255,255,0.025);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;}
.panel-header{padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:1rem;}
.panel-title{font-weight:700;font-size:0.9rem;display:flex;align-items:center;gap:8px;}
.panel-body{padding:1.4rem;}
.chart-wrap{position:relative;height:220px;margin-top:0.5rem;}
.atbl{width:100%;border-collapse:collapse;}
.atbl th{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);padding:0.75rem 1rem;border-bottom:1px solid var(--border);text-align:left;background:rgba(255,255,255,0.015);white-space:nowrap;}
.atbl td{padding:0.85rem 1rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.875rem;vertical-align:middle;}
.atbl tr:hover td{background:rgba(255,255,255,0.025);}
.atbl tr:last-child td{border-bottom:none;}
.sdot{display:inline-flex;align-items:center;gap:5px;padding:.25em .75em;border-radius:50px;font-size:.72rem;font-weight:600;text-transform:capitalize;}
.rank-item{display:flex;align-items:center;gap:12px;padding:.7rem 0;border-bottom:1px solid rgba(255,255,255,.04);}
.rank-item:last-child{border-bottom:none;}
.rank-num{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:var(--text-muted);flex-shrink:0;}
.rank-num.gold{background:rgba(255,193,7,.15);border-color:rgba(255,193,7,.3);color:var(--accent);}
.rank-bar-w{flex:1;max-width:80px;}
.rank-bar{height:4px;border-radius:2px;background:linear-gradient(90deg,var(--primary),var(--accent));transition:width 1s ease;}
.rf{display:flex;gap:6px;align-items:center;}
.rf select{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);font-size:.78rem;padding:.3rem .5rem;max-width:120px;}
.rf select option{background:#1A1820;}
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
.qa-btn{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--radius-md);padding:.9rem;display:flex;flex-direction:column;align-items:center;gap:.5rem;text-align:center;text-decoration:none;color:var(--text-secondary);font-size:.8rem;font-weight:600;transition:var(--transition);cursor:pointer;}
.qa-btn:hover{background:rgba(255,255,255,.06);border-color:var(--primary);color:var(--primary);transform:translateY(-2px);}
.qa-icon{font-size:1.3rem;}
.admin-tabs{display:flex;gap:.25rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border);overflow-x:auto;}
.admin-tab{padding:.6rem 1.1rem;font-size:.85rem;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:var(--transition);white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-1px;}
.admin-tab:hover{color:var(--text-primary);background:rgba(255,255,255,.03);}
.admin-tab.active{color:var(--primary);border-bottom-color:var(--primary);background:rgba(255,87,34,.06);}
@media(max-width:900px){.admin-sidebar{display:none;}.admin-main{padding:1rem;}.stat-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:500px){.stat-grid{grid-template-columns:1fr;}.qa-grid{grid-template-columns:1fr 1fr;}}
</style>

<div class="admin-shell">
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <span class="sidebar-title">Admin Panel</span>
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-chevron-left" id="sidebarChevron"></i></button>
        </div>
        <div class="sidebar-section-label">Overview</div>
        <a href="?page=admin_dashboard&tab=overview" class="sidebar-nav-item <?php echo $activeTab==='overview'?'active':''; ?>"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span><span class="nav-label">Dashboard</span></a>
        <div class="sidebar-section-label">Manage</div>
        <a href="?page=admin_dashboard&tab=orders" class="sidebar-nav-item <?php echo $activeTab==='orders'?'active':''; ?>">
            <span class="nav-icon"><i class="fas fa-receipt"></i></span>
            <span class="nav-label">Orders</span>
            <?php if($pendingOrders>0): ?><span class="sidebar-badge"><?php echo $pendingOrders; ?></span><?php endif; ?>
        </a>
        <a href="?page=admin_dashboard&tab=reports" class="sidebar-nav-item <?php echo $activeTab==='reports'?'active':''; ?>"><span class="nav-icon"><i class="fas fa-chart-pie"></i></span><span class="nav-label">Reports</span></a>
        <a href="?page=admin_dashboard&tab=restaurants" class="sidebar-nav-item <?php echo $activeTab==='restaurants'?'active':''; ?>"><span class="nav-icon"><i class="fas fa-store"></i></span><span class="nav-label">Restaurants</span></a>
        <a href="?page=admin_dashboard&tab=users" class="sidebar-nav-item <?php echo $activeTab==='users'?'active':''; ?>"><span class="nav-icon"><i class="fas fa-users"></i></span><span class="nav-label">Users</span></a>
        <a href="?page=admin_dashboard&tab=requests" class="sidebar-nav-item <?php echo $activeTab==='requests'?'active':''; ?>">
            <span class="nav-icon"><i class="fas fa-clock"></i></span>
            <span class="nav-label">Store Requests</span>
            <?php if($pendingStoreRequests>0): ?><span class="sidebar-badge"><?php echo $pendingStoreRequests; ?></span><?php endif; ?>
        </a>
        <a href="?page=admin_dashboard&tab=rider_requests" class="sidebar-nav-item <?php echo $activeTab==='rider_requests'?'active':''; ?>">
            <span class="nav-icon"><i class="fas fa-user-check"></i></span>
            <span class="nav-label">Rider Requests</span>
            <?php if($pendingRiderRequests>0): ?><span class="sidebar-badge"><?php echo $pendingRiderRequests; ?></span><?php endif; ?>
        </a>
        <a href="?page=admin_dashboard&tab=rider_assignments" class="sidebar-nav-item <?php echo $activeTab==='rider_assignments'?'active':''; ?>">
            <span class="nav-icon"><i class="fas fa-motorcycle"></i></span>
            <span class="nav-label">Rider Assignments</span>
            <?php if($pendingAssignments>0): ?><span class="sidebar-badge"><?php echo $pendingAssignments; ?></span><?php endif; ?>
        </a>
        <div class="sidebar-section-label">System</div>
        <a href="?page=home" class="sidebar-nav-item"><span class="nav-icon"><i class="fas fa-globe"></i></span><span class="nav-label">View Site</span></a>
        <a href="?page=logout" class="sidebar-nav-item" style="color:#FCA5A5;"><span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span><span class="nav-label">Logout</span></a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">
                    <?php 
                        $ts = [
                            'overview'          => 'Dashboard',
                            'orders'            => 'Orders',
                            'restaurants'       => 'Restaurants',
                            'users'             => 'Users',
                            'requests'          => 'Store Requests',
                            'rider_requests'    => 'Rider Requests',
                            'rider_assignments' => 'Rider Assignments',
                            'reports'           => 'Reports'
                        ];
                        echo $ts[$activeTab] ?? 'Dashboard';
                    ?>
                </h1>
                <div class="admin-page-subtitle"><?php echo date('l, F j, Y'); ?> &nbsp;·&nbsp; <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <?php if($pendingOrders>0): ?>
                    <span style="background:rgba(255,87,34,.12);border:1px solid rgba(255,87,34,.25);color:var(--primary);border-radius:50px;padding:.35em 1em;font-size:.8rem;font-weight:700;">
                        <i class="fas fa-bell me-1"></i><?php echo $pendingOrders; ?> Pending
                    </span>
                <?php endif; ?>
                <a href="?page=admin_dashboard&tab=<?php echo $activeTab; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-right me-1"></i>Refresh</a>
            </div>
        </div>

        <!-- Mobile tabs -->
        <div class="admin-tabs d-lg-none">
            <a href="?page=admin_dashboard&tab=overview" class="admin-tab <?php echo $activeTab==='overview'?'active':''; ?>"><i class="fas fa-gauge-high me-1"></i>Overview</a>
            <a href="?page=admin_dashboard&tab=orders" class="admin-tab <?php echo $activeTab==='orders'?'active':''; ?>">
                <i class="fas fa-receipt me-1"></i>Orders
                <?php if($pendingOrders>0): ?><span class="badge bg-danger ms-1"><?php echo $pendingOrders; ?></span><?php endif; ?>
            </a>
            <a href="?page=admin_dashboard&tab=restaurants" class="admin-tab <?php echo $activeTab==='restaurants'?'active':''; ?>"><i class="fas fa-store me-1"></i>Stores</a>
            <a href="?page=admin_dashboard&tab=users" class="admin-tab <?php echo $activeTab==='users'?'active':''; ?>"><i class="fas fa-users me-1"></i>Users</a>
            <a href="?page=admin_dashboard&tab=requests" class="admin-tab <?php echo $activeTab==='requests'?'active':''; ?>">
                <i class="fas fa-clock me-1"></i>Store Req.
                <?php if($pendingStoreRequests>0): ?><span class="badge bg-danger ms-1"><?php echo $pendingStoreRequests; ?></span><?php endif; ?>
            </a>
            <a href="?page=admin_dashboard&tab=rider_requests" class="admin-tab <?php echo $activeTab==='rider_requests'?'active':''; ?>">
                <i class="fas fa-user-check me-1"></i>Rider Req.
                <?php if($pendingRiderRequests>0): ?><span class="badge bg-danger ms-1"><?php echo $pendingRiderRequests; ?></span><?php endif; ?>
            </a>
            <a href="?page=admin_dashboard&tab=rider_assignments" class="admin-tab <?php echo $activeTab==='rider_assignments'?'active':''; ?>">
                <i class="fas fa-motorcycle me-1"></i>Rider Assign.
                <?php if($pendingAssignments>0): ?><span class="badge bg-danger ms-1"><?php echo $pendingAssignments; ?></span><?php endif; ?>
            </a>
            <a href="?page=admin_dashboard&tab=reports" class="admin-tab <?php echo $activeTab==='reports'?'active':''; ?>"><i class="fas fa-chart-pie me-1"></i>Reports</a>
        </div>

        <?php if($activeTab === 'overview'): ?>
           
            <!--  OVERVIEW TAB: Core stats, earnings, chart, top restaurants, recent orders -->
            <div class="stat-grid">
                <div class="stat-card" style="--accent-color:#818CF8;">
                    <div class="stat-icon" style="color:#818CF8;"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-trend" style="color:#818CF8;"><i class="fas fa-arrow-trend-up"></i> All accounts</div>
                </div>
                <div class="stat-card" style="--accent-color:var(--primary);">
                    <div class="stat-icon" style="color:var(--primary);"><i class="fas fa-receipt"></i></div>
                    <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-trend" style="color:var(--primary);"><i class="fas fa-clock"></i> <?php echo $pendingOrders; ?> pending</div>
                </div>
                <div class="stat-card" style="--accent-color:var(--success);">
                    <div class="stat-icon" style="color:var(--success);"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalRevenue??0,0); ?></div>
                    <div class="stat-label">Revenue</div>
                    <div class="stat-trend" style="color:var(--success);"><i class="fas fa-circle-check"></i> Delivered only</div>
                </div>
                <div class="stat-card" style="--accent-color:#3ECFCF;">
                    <div class="stat-icon" style="color:#3ECFCF;"><i class="fas fa-store"></i></div>
                    <div class="stat-value"><?php echo number_format($totalStores); ?></div>
                    <div class="stat-label">Active Stores</div>
                    <div class="stat-trend" style="color:#3ECFCF;"><i class="fas fa-wifi"></i> Currently live</div>
                </div>
            </div>

            <div class="stat-grid" style="margin-bottom:1.5rem;">
                <div class="stat-card" style="--accent-color:#F59E0B;">
                    <div class="stat-icon" style="color:#F59E0B;"><i class="fas fa-truck"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalDeliveryFees??0,0); ?></div>
                    <div class="stat-label">Delivery Fees</div>
                    <div class="stat-trend" style="color:#F59E0B;"><i class="fas fa-receipt"></i> Collected</div>
                </div>
                <div class="stat-card" style="--accent-color:#3ECFCF;">
                    <div class="stat-icon" style="color:#3ECFCF;"><i class="fas fa-motorcycle"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalRiderEarnings??0,0); ?></div>
                    <div class="stat-label">Rider Payouts</div>
                    <div class="stat-trend" style="color:#3ECFCF;"><i class="fas fa-percent"></i> 80% of fees</div>
                </div>
                <div class="stat-card" style="--accent-color:#F97316;">
                    <div class="stat-icon" style="color:#F97316;"><i class="fas fa-percent"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalCommission??0,0); ?></div>
                    <div class="stat-label">Grocery Commission</div>
                    <div class="stat-trend" style="color:#F97316;"><i class="fas fa-chart-line"></i> 3% on grocery</div>
                </div>
                <div class="stat-card" style="--accent-color:var(--success);">
                    <div class="stat-icon" style="color:var(--success);"><i class="fas fa-building"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalAdminEarnings??0,0); ?></div>
                    <div class="stat-label">Admin Net</div>
                    <div class="stat-trend" style="color:var(--success);"><i class="fas fa-chart-line"></i> Platform earnings</div>
                </div>
                <div class="stat-card" style="--accent-color:var(--primary);">
                    <div class="stat-icon" style="color:var(--primary);"><i class="fas fa-utensils"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalRestaurantEarnings??0,0); ?></div>
                    <div class="stat-label">Restaurant Earnings</div>
                    <div class="stat-trend" style="color:var(--primary);"><i class="fas fa-store"></i> Food subtotal</div>
                </div>
            </div>

            <div class="admin-grid">
                <div class="admin-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fas fa-chart-line" style="color:var(--primary);"></i> Revenue Overview</div>
                        <span style="font-size:.75rem;color:var(--text-muted);">Last 6 months</span>
                    </div>
                    <div class="panel-body">
                        <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-trophy" style="color:var(--accent);"></i> Top Restaurants</div></div>
                    <div class="panel-body" style="padding:1rem 1.4rem;">
                        <?php $maxO = max(array_column($topRestaurants,'orders')?:[1]); foreach($topRestaurants as $i=>$r): $pct = $maxO>0 ? round(($r['orders']/$maxO)*100) : 0; ?>
                        <div class="rank-item">
                            <div class="rank-num <?php echo $i===0?'gold':''; ?>"><?php echo $i+1; ?></div>
                            <div style="flex:1;font-size:.875rem;font-weight:600;"><?php echo htmlspecialchars($r['store_name']); ?></div>
                            <div class="rank-bar-w"><div class="rank-bar" style="width:<?php echo $pct; ?>%;"></div></div>
                            <div style="font-size:.8rem;font-weight:700;color:var(--primary);min-width:30px;text-align:right;"><?php echo $r['orders']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="admin-grid">
                <div class="admin-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fas fa-list-check" style="color:#818CF8;"></i> Recent Orders</div>
                        <a href="?page=admin_dashboard&tab=orders" style="font-size:.8rem;color:var(--primary);text-decoration:none;">View all →</a>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="atbl">
                            <thead>
                                <tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Rider / Assignment Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach(array_slice($recentOrders,0,6) as $order): 
                                    $sc = $statusColors[$order['status']] ?? '#888';
                                    $isDelivered = ($order['status'] == 'delivered');
                                    $hasRider = !empty($order['rider_id']) && !empty($order['rider_name']);
                                    $assignStatus = $orderAssignmentStatus[$order['id']] ?? null;
                                ?>
                                <tr>
                                    <td><span style="font-weight:700;color:var(--primary);">#<?php echo $order['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($order['customer']); ?></td>
                                    <td><strong>Rs. <?php echo number_format($order['total_amount'],2); ?></strong></td>
                                    <td><span class="sdot" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;border:1px solid <?php echo $sc; ?>40;"><span style="width:5px;height:5px;border-radius:50%;background:<?php echo $sc; ?>;display:inline-block;"></span><?php echo str_replace('_',' ',$order['status']); ?></span></td>
                                    <td>
                                        <?php if($isDelivered): ?>
                                            <span class="text-muted" style="font-size:.75rem;"><i class="fas fa-check-circle me-1" style="color:var(--success);"></i> Completed</span>
                                        <?php elseif($hasRider): ?>
                                            <span class="sdot" style="background:rgba(16,185,129,0.15);color:var(--success);border:1px solid rgba(16,185,129,0.25);"><i class="fas fa-motorcycle me-1"></i> <?php echo htmlspecialchars($order['rider_name']); ?></span>
                                        <?php elseif($assignStatus && $assignStatus['status'] === 'pending'): ?>
                                            <span class="sdot" style="background:rgba(255,193,7,0.15);color:#FFC107;"><i class="fas fa-hourglass-half me-1"></i> Request pending with <?php echo htmlspecialchars($assignStatus['rider_name']); ?></span>
                                        <?php elseif($assignStatus && $assignStatus['status'] === 'rejected'): ?>
                                            <span class="sdot" style="background:rgba(239,68,68,0.15);color:#EF4444;"><i class="fas fa-times-circle me-1"></i> Rejected by <?php echo htmlspecialchars($assignStatus['rider_name']); ?></span>
                                        <?php elseif($assignStatus && $assignStatus['status'] === 'expired'): ?>
                                            <span class="sdot" style="background:rgba(239,68,68,0.15);color:#EF4444;"><i class="fas fa-clock me-1"></i> Expired (<?php echo htmlspecialchars($assignStatus['rider_name']); ?>)</span>
                                        <?php else: ?>
                                            <?php $riders = $pdo->query("SELECT id, name FROM users WHERE role='rider' AND is_active=1 ORDER BY name")->fetchAll(); ?>
                                            <form method="POST" action="ajax/assign_rider.php" class="rf">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="rider_id">
                                                    <option value="">Select rider</option>
                                                    <?php foreach($riders as $rd): ?>
                                                        <option value="<?php echo $rd['id']; ?>"><?php echo htmlspecialchars($rd['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary" style="font-size:.75rem;padding:.3rem .7rem;">Assign</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-bolt" style="color:var(--accent);"></i> Quick Actions</div></div>
                    <div class="panel-body">
                        <div class="qa-grid">
                            <a href="?page=admin_dashboard&tab=orders" class="qa-btn"><span class="qa-icon">📦</span>Orders</a>
                            <a href="?page=admin_dashboard&tab=users" class="qa-btn"><span class="qa-icon">👥</span>Users</a>
                            <a href="?page=admin_dashboard&tab=restaurants" class="qa-btn"><span class="qa-icon">🏪</span>Stores</a>
                            <a href="?page=store_list" target="_blank" class="qa-btn"><span class="qa-icon">🌐</span>View Site</a>
                        </div>
                        <div style="margin-top:1.5rem;display:flex;flex-direction:column;gap:.75rem;">
                            <?php
                            $pills = [
                                ['Pending Orders', $pendingOrders, 'var(--primary)', 'fas fa-circle'],
                                ['Active Stores', $totalStores, 'var(--success)', 'fas fa-circle'],
                                ['Registered Users', $totalUsers, '#818CF8', 'fas fa-circle'],
                                ['Revenue (Delivered)', 'Rs. '.number_format($totalRevenue??0,0), 'var(--success)', 'fas fa-dollar-sign'],
                                ['Delivery Fees', 'Rs. '.number_format($totalDeliveryFees??0,0), '#F59E0B', 'fas fa-truck'],
                                ['Rider Payouts', 'Rs. '.number_format($totalRiderEarnings??0,0), '#3ECFCF', 'fas fa-motorcycle'],
                            ];
                            foreach($pills as $p): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.7rem .9rem;background:rgba(255,255,255,.025);border-radius:var(--radius-sm);border:1px solid var(--border);">
                                <span style="font-size:.82rem;color:var(--text-secondary);"><i class="<?php echo $p[3]; ?> me-2" style="color:<?php echo $p[2]; ?>;font-size:.5rem;"></i><?php echo $p[0]; ?></span>
                                <span style="font-weight:700;color:<?php echo $p[2]; ?>;"><?php echo $p[1]; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif($activeTab === 'orders'): ?>
            
            <!--  ORDERS TAB: Full list of recent orders -->
            <div class="admin-panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-receipt" style="color:var(--primary);"></i> All Recent Orders</div><span style="font-size:.78rem;color:var(--text-muted);"><?php echo count($recentOrders); ?> records</span></div>
                <div style="overflow-x:auto;">
                    <table class="atbl">
                        <thead><tr><th>Order#</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th><th>Rider / Assignment Status</th></tr></thead>
                        <tbody>
                        <?php foreach($recentOrders as $order): 
                            $sc = $statusColors[$order['status']] ?? '#888';
                            $isDelivered = ($order['status'] == 'delivered');
                            $hasRider = !empty($order['rider_id']) && !empty($order['rider_name']);
                            $assignStatus = $orderAssignmentStatus[$order['id']] ?? null;
                        ?>
                        <tr>
                            <td><span style="font-weight:700;color:var(--primary);">#<?php echo $order['id']; ?></span></td>
                            <td><?php echo htmlspecialchars($order['customer']); ?></td>
                            <td><strong>Rs. <?php echo number_format($order['total_amount'],2); ?></strong></td>
                            <td><span class="sdot" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;border:1px solid <?php echo $sc; ?>40;"><span style="width:5px;height:5px;border-radius:50%;background:<?php echo $sc; ?>;display:inline-block;"></span><?php echo str_replace('_',' ',$order['status']); ?></span></td>
                            <td style="color:var(--text-muted);font-size:.8rem;"><?php echo date('M j, Y',strtotime($order['created_at'])); ?></td>
                            <td>
                                <?php if($isDelivered): ?>
                                    <span class="text-muted" style="font-size:.75rem;"><i class="fas fa-check-circle me-1" style="color:var(--success);"></i> Completed</span>
                                <?php elseif($hasRider): ?>
                                    <span class="sdot" style="background:rgba(16,185,129,0.15);color:var(--success);border:1px solid rgba(16,185,129,0.25);"><i class="fas fa-motorcycle me-1"></i> <?php echo htmlspecialchars($order['rider_name']); ?></span>
                                <?php elseif($assignStatus && $assignStatus['status'] === 'pending'): ?>
                                    <span class="sdot" style="background:rgba(255,193,7,0.15);color:#FFC107;"><i class="fas fa-hourglass-half me-1"></i> Request pending with <?php echo htmlspecialchars($assignStatus['rider_name']); ?></span>
                                <?php elseif($assignStatus && $assignStatus['status'] === 'rejected'): ?>
                                    <span class="sdot" style="background:rgba(239,68,68,0.15);color:#EF4444;"><i class="fas fa-times-circle me-1"></i> Rejected by <?php echo htmlspecialchars($assignStatus['rider_name']); ?></span>
                                <?php elseif($assignStatus && $assignStatus['status'] === 'expired'): ?>
                                    <span class="sdot" style="background:rgba(239,68,68,0.15);color:#EF4444;"><i class="fas fa-clock me-1"></i> Expired (<?php echo htmlspecialchars($assignStatus['rider_name']); ?>)</span>
                                <?php else: ?>
                                    <?php $riders = $pdo->query("SELECT id, name FROM users WHERE role='rider' AND is_active=1 ORDER BY name")->fetchAll(); ?>
                                    <form method="POST" action="ajax/assign_rider.php" class="rf">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="rider_id">
                                            <option value="">Select rider</option>
                                            <?php foreach($riders as $rd): ?>
                                                <option value="<?php echo $rd['id']; ?>"><?php echo htmlspecialchars($rd['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary" style="font-size:.75rem;padding:.3rem .7rem;">Assign</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif($activeTab === 'restaurants'): ?>

            <!-- RESTAURANTS TAB -->
            <?php $allStores = $pdo->query("SELECT s.*, u.name as owner_name, COUNT(o.id) as order_count FROM stores s LEFT JOIN users u ON s.owner_id=u.id LEFT JOIN orders o ON s.id=o.store_id GROUP BY s.id ORDER BY s.created_at DESC")->fetchAll(); ?>
            <div class="admin-panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-store" style="color:#3ECFCF;"></i> All Stores</div><span style="font-size:.78rem;color:var(--text-muted);"><?php echo count($allStores); ?> total</span></div>
                <div style="overflow-x:auto;">
                    <table class="atbl">
                        <thead><tr><th>#</th><th>Store</th><th>Type</th><th>Owner</th><th>Orders</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach($allStores as $i=>$s): ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($s['store_name']); ?></strong><div style="font-size:.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($s['location']??''); ?></div></td>
                            <td><span class="sdot" style="background:rgba(62,207,207,.15);color:#3ECFCF;border:1px solid rgba(62,207,207,.25);"><?php echo ucfirst($s['store_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($s['owner_name']??'—'); ?></td>
                            <td><strong><?php echo $s['order_count']; ?></strong></td>
                            <td>
                                <?php if($s['is_active']): ?>
                                    <span class="sdot" style="background:rgba(16,185,129,.15);color:var(--success);border:1px solid rgba(16,185,129,.3);"><span style="width:5px;height:5px;border-radius:50%;background:var(--success);display:inline-block;"></span> Active</span>
                                <?php else: ?>
                                    <span class="sdot" style="background:rgba(239,68,68,.15);color:#EF4444;border:1px solid rgba(239,68,68,.3);"><span style="width:5px;height:5px;border-radius:50%;background:#EF4444;display:inline-block;"></span> Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif($activeTab === 'users'): ?>
            <!-- USERS TAB -->
            <?php $allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50")->fetchAll(); $rclr=['admin'=>'#EF4444','owner'=>'#818CF8','customer'=>'#3ECFCF','rider'=>'#FFC107']; ?>
            <div class="admin-panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-users" style="color:#818CF8;"></i> All Users</div><span style="font-size:.78rem;color:var(--text-muted);"><?php echo $totalUsers; ?> total</span></div>
                <div style="overflow-x:auto;">
                    <table class="atbl">
                        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Points</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php foreach($allUsers as $i=>$u): $rc = $rclr[$u['role']]??'#888'; ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?php echo $i+1; ?></td>
                            <td><div style="display:flex;align-items:center;gap:10px;"><span style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:white;flex-shrink:0;"><?php echo strtoupper(substr($u['name'],0,1)); ?></span><strong><?php echo htmlspecialchars($u['name']); ?></strong></div></td>
                            <td style="color:var(--text-secondary);"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="sdot" style="background:<?php echo $rc; ?>20;color:<?php echo $rc; ?>;border:1px solid <?php echo $rc; ?>40;"><?php echo ucfirst($u['role']); ?></span></td>
                            <td><strong><?php echo $u['points']??0; ?></strong> <span style="color:var(--text-muted);font-size:.75rem;">pts</span></td>
                            <td style="color:var(--text-muted);font-size:.8rem;"><?php echo date('M j, Y',strtotime($u['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif($activeTab === 'requests'): ?>
            <!-- STORE REQUESTS TAB -->
            <?php
            $pendingStores = $pdo->query("
                SELECT s.*, u.name as owner_name, u.email as owner_email
                FROM stores s
                JOIN users u ON s.owner_id = u.id
                WHERE s.is_active = 0
                ORDER BY s.created_at DESC
            ")->fetchAll();
            ?>
            <div class="admin-panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-clock" style="color:var(--accent);"></i> Pending Store Approvals</div><span style="font-size:.78rem;color:var(--text-muted);"><?php echo count($pendingStores); ?> requests</span></div>
                <?php if(empty($pendingStores)): ?>
                <div class="panel-body text-center py-5">
                    <div style="font-size:3rem;">✅</div>
                    <h5>No pending requests</h5>
                    <p class="text-muted">All store requests have been processed.</p>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="atbl">
                        <thead>
                            <tr><th>Store Name</th><th>Owner</th><th>Type</th><th>Location</th><th>Requested</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach($pendingStores as $storeReq): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($storeReq['store_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($storeReq['category']??'Uncategorized'); ?></small></td>
                            <td><?php echo htmlspecialchars($storeReq['owner_name']); ?><br><small><?php echo htmlspecialchars($storeReq['owner_email']); ?></small></td>
                            <td><span class="sdot" style="background:rgba(255,193,7,0.15);color:#FFC107;"><?php echo ucfirst($storeReq['store_type']); ?></span></td>
                            <td style="max-width:200px;"><?php echo htmlspecialchars($storeReq['location']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($storeReq['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-success" onclick="approveStore(<?php echo $storeReq['id']; ?>, '<?php echo htmlspecialchars($storeReq['store_name']); ?>', <?php echo $storeReq['owner_id']; ?>)"><i class="fas fa-check"></i> Approve</button>
                                    <button class="btn btn-sm btn-danger" onclick="rejectStore(<?php echo $storeReq['id']; ?>, '<?php echo htmlspecialchars($storeReq['store_name']); ?>', <?php echo $storeReq['owner_id']; ?>)"><i class="fas fa-times"></i> Reject</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif($activeTab === 'rider_requests'): ?>
            <!-- RIDER REQUESTS TAB -->
            <div class="admin-panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-user-check" style="color:var(--primary);"></i> Pending Rider Approvals</div>
                    <span style="font-size:.78rem;color:var(--text-muted);"><?php echo count($pendingRiders); ?> requests</span>
                </div>
                <?php if(empty($pendingRiders)): ?>
                <div class="panel-body text-center py-5">
                    <div style="font-size:3rem;">🏍️</div>
                    <h5>No pending rider requests</h5>
                    <p class="text-muted">All riders have been approved.</p>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="atbl">
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Registered</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach($pendingRiders as $rider): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($rider['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rider['email']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($rider['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-success" onclick="approveRider(<?php echo $rider['id']; ?>, '<?php echo htmlspecialchars($rider['name']); ?>')"><i class="fas fa-check"></i> Approve</button>
                                    <button class="btn btn-sm btn-danger" onclick="rejectRider(<?php echo $rider['id']; ?>, '<?php echo htmlspecialchars($rider['name']); ?>')"><i class="fas fa-times"></i> Reject</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif($activeTab === 'rider_assignments'): ?>
            <?php
                // ─── Expire old pending requests (5 minutes timeout) ───
                expireOldRiderRequests($pdo, 5);
                // Re-fetch assignments after expiration
                $allAssignments = $pdo->query("
                    SELECT ra.*, 
                           o.id as order_id, o.total_amount, o.status as order_status,
                           u_customer.name as customer_name,
                           u_rider.name as rider_name
                    FROM rider_assignments ra
                    JOIN orders o ON ra.order_id = o.id
                    JOIN users u_customer ON o.customer_id = u_customer.id
                    JOIN users u_rider ON ra.rider_id = u_rider.id
                    ORDER BY ra.created_at DESC
                    LIMIT 100
                ")->fetchAll();
            ?>
            <!-- RIDER ASSIGNMENTS TAB with auto-refresh -->
            <div class="admin-panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-motorcycle" style="color:var(--primary);"></i> Rider Assignment Requests</div>
                    <span style="font-size:.78rem;color:var(--text-muted);">Last 100 requests</span>
                    <span style="font-size:.7rem;color:#FFC107;background:rgba(255,193,7,0.12);padding:0.2rem 0.8rem;border-radius:50px;border:1px solid rgba(255,193,7,0.2);">
                        <i class="fas fa-sync-alt fa-spin"></i> Auto-refreshes every 30s &bull; Expires after 5 min
                    </span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="atbl">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Order Total</th>
                                <th>Rider</th>
                                <th>Status</th>
                                <th>Requested At</th>
                                <th>Responded At</th>
                                <th>Current Order Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($allAssignments as $assign): 
                            $statusClass = '';
                            $statusIcon = '';
                            switch($assign['status']) {
                                case 'pending':
                                    $statusClass = '#FFC107';
                                    $statusIcon = 'fa-hourglass-half';
                                    break;
                                case 'accepted':
                                    $statusClass = '#10B981';
                                    $statusIcon = 'fa-check-circle';
                                    break;
                                case 'rejected':
                                    $statusClass = '#EF4444';
                                    $statusIcon = 'fa-times-circle';
                                    break;
                                case 'expired':
                                    $statusClass = '#6B7280';
                                    $statusIcon = 'fa-clock';
                                    break;
                            }
                        ?>
                        <tr>
                            <td><strong><a href="?page=order_details&id=<?php echo $assign['order_id']; ?>" style="color:var(--primary);">#<?php echo $assign['order_id']; ?></a></strong></td>
                            <td><?php echo htmlspecialchars($assign['customer_name']); ?></td>
                            <td>Rs. <?php echo number_format($assign['total_amount'],2); ?></td>
                            <td><?php echo htmlspecialchars($assign['rider_name']); ?></td>
                            <td><span class="sdot" style="background:<?php echo $statusClass; ?>20;color:<?php echo $statusClass; ?>;border:1px solid <?php echo $statusClass; ?>40;"><i class="fas <?php echo $statusIcon; ?> me-1"></i><?php echo ucfirst($assign['status']); ?></span></td>
                            <td><?php echo date('M j, Y h:i A', strtotime($assign['created_at'])); ?></td>
                            <td><?php echo $assign['responded_at'] ? date('M j, Y h:i A', strtotime($assign['responded_at'])) : '-'; ?></td>
                            <td>
                                <span class="sdot" style="background:<?php echo $statusColors[$assign['order_status']] ?? '#888'; ?>20;color:<?php echo $statusColors[$assign['order_status']] ?? '#888'; ?>;"><?php echo ucfirst(str_replace('_',' ',$assign['order_status'])); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($allAssignments)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No rider assignment records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif($activeTab === 'reports'): ?>
            <!-- REPORTS TAB (unchanged) -->
            <?php
            $monthOptions = '';
            for ($m = 1; $m <= 12; $m++) {
                $val = date('Y-m', mktime(0,0,0,$m,1,date('Y')));
                $sel = ($reportMonth == $val) ? 'selected' : '';
                $monthOptions .= "<option value=\"$val\" $sel>" . date('F Y', mktime(0,0,0,$m,1,date('Y'))) . "</option>";
            }
            ?>
            <div class="admin-panel" style="margin-bottom:1.5rem;">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-filter"></i> Filter Reports</div></div>
                <div class="panel-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="page" value="admin_dashboard">
                        <input type="hidden" name="tab" value="reports">
                        <div class="col-md-4">
                            <label class="form-label">Month</label>
                            <select name="report_month" class="form-select">
                                <?php echo $monthOptions; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>
                        <div class="col-md-2">
                            <a href="ajax/export_admin_report.php?report_month=<?php echo $reportMonth; ?>" class="btn btn-info">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Metrics Cards -->
            <div class="stat-grid" style="margin-bottom:1.5rem;">
                <div class="stat-card" style="--accent-color:#818CF8;">
                    <div class="stat-icon" style="color:#818CF8;"><i class="fas fa-receipt"></i></div>
                    <div class="stat-value"><?php echo $totalOrdersPeriod; ?></div>
                    <div class="stat-label">Total Orders (Period)</div>
                </div>
                <div class="stat-card" style="--accent-color:#10B981;">
                    <div class="stat-icon" style="color:#10B981;"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalSales, 2); ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
                <div class="stat-card" style="--accent-color:#FF5722;">
                    <div class="stat-icon" style="color:#FF5722;"><i class="fas fa-building"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($platformEarnings, 2); ?></div>
                    <div class="stat-label">Platform Earnings</div>
                </div>
                <div class="stat-card" style="--accent-color:#F59E0B;">
                    <div class="stat-icon" style="color:#F59E0B;"><i class="fas fa-motorcycle"></i></div>
                    <div class="stat-value">Rs. <?php echo number_format($totalRiderPayout, 2); ?></div>
                    <div class="stat-label">Rider Payouts</div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card" style="--accent-color:#3ECFCF;">
                        <div class="stat-value">Rs. <?php echo number_format($totalDelivery, 2); ?></div>
                        <div class="stat-label">Delivery Fees</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="--accent-color:#F97316;">
                        <div class="stat-value">Rs. <?php echo number_format($totalCommission, 2); ?></div>
                        <div class="stat-label">Commission</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="--accent-color:#EF4444;">
                        <div class="stat-value">Rs. <?php echo number_format($totalDiscount, 2); ?></div>
                        <div class="stat-label">Discounts</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="--accent-color:#8B5CF6;">
                        <div class="stat-value"><?php echo count($topStoresList); ?></div>
                        <div class="stat-label">Top Stores</div>
                    </div>
                </div>
            </div>

            <div class="admin-grid">
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-chart-line"></i> Daily Orders (<?php echo date('F Y', strtotime($reportMonth)); ?>)</div></div>
                    <div class="panel-body">
                        <?php if(empty($chartLabelsDaily)): ?>
                            <div class="alert alert-info text-center">No data for this period.</div>
                        <?php else: ?>
                            <div style="height:200px;"><canvas id="dailyOrdersChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-chart-line"></i> Daily Revenue (<?php echo date('F Y', strtotime($reportMonth)); ?>)</div></div>
                    <div class="panel-body">
                        <?php if(empty($chartLabelsDaily)): ?>
                            <div class="alert alert-info text-center">No data for this period.</div>
                        <?php else: ?>
                            <div style="height:200px;"><canvas id="dailyRevenueChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="admin-panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-trophy"></i> Top Stores</div></div>
                <div style="overflow-x:auto;">
                    <?php if(empty($topStoresList)): ?>
                        <div class="panel-body text-center"><p class="text-muted">No store data.</p></div>
                    <?php else: ?>
                        <table class="atbl">
                            <thead><tr><th>#</th><th>Store Name</th><th>Orders</th><th>Revenue</th></tr></thead>
                            <tbody>
                                <?php $rank=1; foreach($topStoresList as $store): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($store['store_name']); ?></strong></td>
                                    <td><?php echo $store['order_count']; ?></td>
                                    <td>Rs. <?php echo number_format($store['revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-grid">
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-utensils"></i> Top Items</div></div>
                    <div style="overflow-x:auto;">
                        <?php if(empty($topItemsList)): ?>
                            <div class="panel-body text-center"><p class="text-muted">No item data.</p></div>
                        <?php else: ?>
                            <table class="atbl">
                                <thead><tr><th>#</th><th>Item</th><th>Store</th><th>Qty</th><th>Revenue</th></tr></thead>
                                <tbody>
                                    <?php $rank=1; foreach($topItemsList as $item): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['store_name']); ?></td>
                                        <td><?php echo $item['total_qty']; ?></td>
                                        <td>Rs. <?php echo number_format($item['revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-motorcycle"></i> Top Riders</div></div>
                    <div style="overflow-x:auto;">
                        <?php if(empty($topRidersList)): ?>
                            <div class="panel-body text-center"><p class="text-muted">No rider data.</p></div>
                        <?php else: ?>
                            <table class="atbl">
                                <thead><tr><th>#</th><th>Rider</th><th>Deliveries</th><th>Earnings</th></tr></thead>
                                <tbody>
                                    <?php $rank=1; foreach($topRidersList as $rider): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($rider['name']); ?></strong></td>
                                        <td><?php echo $rider['deliveries']; ?></td>
                                        <td>Rs. <?php echo number_format($rider['earnings'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="admin-grid">
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-credit-card"></i> Payment Methods</div></div>
                    <div class="panel-body">
                        <?php if(empty($paymentLabels)): ?>
                            <div class="alert alert-info text-center">No payment data.</div>
                        <?php else: ?>
                            <div style="height:180px;"><canvas id="paymentChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="admin-panel">
                    <div class="panel-header"><div class="panel-title"><i class="fas fa-circle"></i> Order Status</div></div>
                    <div class="panel-body">
                        <?php if(empty($statusLabels)): ?>
                            <div class="alert alert-info text-center">No status data.</div>
                        <?php else: ?>
                            <div style="height:180px;"><canvas id="statusChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                // Daily Orders Chart
                <?php if(!empty($chartLabelsDaily)): ?>
                new Chart(document.getElementById('dailyOrdersChart'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chartLabelsDaily); ?>,
                        datasets: [{
                            label: 'Orders',
                            data: <?php echo json_encode($chartOrdersDaily); ?>,
                            backgroundColor: 'rgba(255,87,34,0.6)',
                            borderColor: '#FF5722',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B6572' } },
                            y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B6572', stepSize: 1 } }
                        }
                    }
                });

                // Daily Revenue Chart
                new Chart(document.getElementById('dailyRevenueChart'), {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chartLabelsDaily); ?>,
                        datasets: [{
                            label: 'Revenue (Rs.)',
                            data: <?php echo json_encode($chartRevenueDaily); ?>,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16,185,129,0.2)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B6572' } },
                            y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B6572', callback: v => 'Rs. ' + v } }
                        }
                    }
                });
                <?php endif; ?>

                // Payment Chart (Pie)
                <?php if(!empty($paymentLabels)): ?>
                new Chart(document.getElementById('paymentChart'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($paymentLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($paymentCounts); ?>,
                            backgroundColor: ['#FF5722', '#10B981', '#3ECFCF', '#818CF8']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#A09898' } }
                        }
                    }
                });
                <?php endif; ?>

                // Status Chart (Pie)
                <?php if(!empty($statusLabels)): ?>
                new Chart(document.getElementById('statusChart'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($statusLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($statusCounts); ?>,
                            backgroundColor: ['#FFC107', '#3ECFCF', '#818CF8', '#10B981', '#EF4444']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#A09898' } }
                        }
                    }
                });
                <?php endif; ?>
            </script>

        <?php endif; ?>
    </main>
</div>

<!-- Chart.js for the revenue chart (overview) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    function toggleSidebar() {
        const s = document.getElementById('adminSidebar');
        const c = document.getElementById('sidebarChevron');
        s.classList.toggle('collapsed');
        c.className = s.classList.contains('collapsed') ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
    }

    <?php if($activeTab === 'overview'): ?>
        const labels = <?php echo json_encode($labels); ?>;
        const values = <?php echo json_encode($values); ?>;
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 220);
        grad.addColorStop(0, 'rgba(255,87,34,0.3)');
        grad.addColorStop(1, 'rgba(255,87,34,0.02)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (Rs.)',
                    data: values,
                    borderColor: '#FF5722',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#FF5722',
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    fill: true,
                    backgroundColor: grad,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1A1820',
                        borderColor: 'rgba(255,87,34,0.3)',
                        borderWidth: 1,
                        titleColor: '#F5F0EB',
                        bodyColor: '#A09898',
                        callbacks: { label: c => ' Rs. ' + c.parsed.y.toLocaleString() }
                    }
                },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B6572', font: { size: 12 } }, border: { color: 'rgba(255,255,255,0.06)' } },
                    y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B6572', font: { size: 12 }, callback: v => 'Rs. ' + v.toLocaleString() }, border: { color: 'rgba(255,255,255,0.06)' } }
                }
            }
        });
    <?php endif; ?>

    // Auto-refresh on Rider Assignments tab every 30 seconds
    if (window.location.search.includes('tab=rider_assignments')) {
        console.log('Auto-refresh enabled for Rider Assignments (30s)');
        setTimeout(function() {
            location.reload();
        }, 30000);
    }

    // Existing functions
    function approveStore(storeId, storeName, ownerId) {
        if (confirm(`Approve "${storeName}"? The store will become visible to customers.`)) {
            $.post('ajax/approve_store.php', { store_id: storeId, owner_id: ownerId }, function(res) {
                if (res === 'success') location.reload();
                else alert('Error approving store');
            });
        }
    }

    function rejectStore(storeId, storeName, ownerId) {
        let reason = prompt("Optional rejection reason (will be sent to the owner):");
        $.post('ajax/reject_store.php', { store_id: storeId, owner_id: ownerId, reason: reason }, function(res) {
            if (res === 'success') location.reload();
            else alert('Error rejecting store');
        });
    }

    function approveRider(riderId, riderName) {
        if (confirm(`Approve rider "${riderName}"? They will be able to accept delivery requests.`)) {
            $.post('ajax/approve_rider.php', { rider_id: riderId }, function(res) {
                if (res === 'success') location.reload();
                else alert('Error approving rider');
            });
        }
    }

    function rejectRider(riderId, riderName) {
        if (confirm(`Reject rider "${riderName}"? The account will be deactivated.`)) {
            $.post('ajax/reject_rider.php', { rider_id: riderId }, function(res) {
                if (res === 'success') location.reload();
                else alert('Error rejecting rider');
            });
        }
    }
</script>