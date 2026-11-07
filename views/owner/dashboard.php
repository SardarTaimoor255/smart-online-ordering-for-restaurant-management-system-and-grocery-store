<?php

require_once __DIR__ . '/../../bootstrap.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'owner') {
    header('Location: ' . BASE_URL . 'index.php?page=login');
    exit();
}

$ownerId = $_SESSION['user_id'];
$stores  = $pdo->prepare("SELECT * FROM stores WHERE owner_id = ?");
$stores->execute([$ownerId]);
$store   = $stores->fetch();

// ──────────────────────────────────────────────
// 1. No store → show request form
// 2. Store exists but pending/rejected → show message
// 3. Store approved → show full dashboard
// ──────────────────────────────────────────────
if(!$store) {
    // Handle store request submission (unchanged)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_store'])) {
        $store_name = trim($_POST['store_name']);
        $store_type = $_POST['store_type'];
        $location = trim($_POST['location']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        
        $errors = [];
        if (empty($store_name)) $errors[] = "Store name is required.";
        if (empty($location)) $errors[] = "Location is required.";
        if (!in_array($store_type, ['restaurant', 'grocery'])) $errors[] = "Invalid store type.";
        if (strlen($description) > 500) $errors[] = "Description too long.";
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO stores (owner_id, store_name, store_type, location, description, category, is_active) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$ownerId, $store_name, $store_type, $location, $description, $category]);
            $_SESSION['success'] = "Store request submitted! Admin will review and approve soon.";
            
            // Notify admin
            $admin = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
            if ($admin) {
                addNotification($pdo, $admin['id'], "New Store Request", "$store_name has requested to open a store.");
            }
            redirect('?page=owner_dashboard');
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
    }
    ?>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4>Request a Store</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label>Store Name *</label>
                                <input type="text" name="store_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Store Type *</label>
                                <select name="store_type" class="form-select" required>
                                    <option value="restaurant">Restaurant</option>
                                    <option value="grocery">Grocery Store</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Location *</label>
                                <input type="text" name="location" class="form-control" placeholder="Full address" required>
                            </div>
                            <div class="mb-3">
                                <label>Category</label>
                                <input type="text" name="category" class="form-control" placeholder="e.g., Fast Food, Vegetables">
                            </div>
                            <div class="mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" name="request_store" class="btn btn-primary w-100">Submit Request</button>
                        </form>
                        <p class="text-muted mt-3">Your request will be reviewed by the admin. You will be notified once approved.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    exit;
} else {
    // Store exists – check status
    if ($store['is_active'] == 0) {
        echo "<div class='alert alert-warning'><i class='fas fa-clock me-2'></i>Your store request is pending admin approval. You will be notified once approved.</div>";
        echo "</div></div>"; // close container
        exit;
    } elseif ($store['is_active'] == 2) {
        $reason = $store['rejection_reason'] ?? 'No reason provided';
        echo "<div class='alert alert-danger'><i class='fas fa-times-circle me-2'></i>Your store request was rejected. Reason: $reason</div>";
        echo "</div></div>";
        exit;
    }
    
    // ─── Approved store – full dashboard ───
    $tab = $_GET['tab'] ?? 'items';

    // ──────────────────────────────────────────────
    // Item Addition with Image Upload
    // ──────────────────────────────────────────────
    if($tab == 'items' && isset($_POST['add_item'])) {
        $errors = [];
        $name = trim($_POST['name']);
        $price = $_POST['price'];
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        
        if (empty($name)) $errors[] = "Item name is required.";
        elseif (strlen($name) < 2 || strlen($name) > 100) $errors[] = "Item name must be 2-100 characters.";
        if (empty($price) || !is_numeric($price) || $price <= 0) $errors[] = "Price must be positive.";
        elseif ($price > 9999) $errors[] = "Price cannot exceed 9999.";
        if (strlen($description) > 500) $errors[] = "Description too long (max 500).";
        if (strlen($category) > 50) $errors[] = "Category name too long.";
        
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
            $maxSize = 2 * 1024 * 1024;
            if (!in_array($file['type'], $allowed)) $errors[] = "Only JPG, PNG, WebP allowed.";
            if ($file['size'] > $maxSize) $errors[] = "Image max 2MB.";
            if (empty($errors)) {
                if (!is_dir('uploads/items')) mkdir('uploads/items', 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $unique = uniqid() . '_' . time() . '.' . $ext;
                $dest = 'uploads/items/' . $unique;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $imagePath = $dest;
                } else {
                    $errors[] = "Failed to upload image.";
                }
            }
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO store_items (store_id, name, description, price, category, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$store['id'], $name, $description, $price, $category, $imagePath]);
            $_SESSION['success'] = "Item added!";
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        redirect('?page=owner_dashboard&tab=items');
    }

    // Coupon creation
    if($tab == 'coupons' && isset($_POST['add_coupon'])) {
        $errors = [];
        $code = strtoupper(trim($_POST['code']));
        $discount_type = $_POST['discount_type'];
        $discount_value = $_POST['discount_value'];
        $min_order = $_POST['min_order'] ?: 0;
        $expiry = $_POST['expiry'] ?: null;
        if (empty($code)) $errors[] = "Coupon code required.";
        elseif (!preg_match('/^[A-Z0-9]{3,20}$/', $code)) $errors[] = "Code must be 3-20 uppercase letters/numbers.";
        if (!in_array($discount_type, ['percentage', 'fixed'])) $errors[] = "Invalid discount type.";
        if (empty($discount_value) || !is_numeric($discount_value) || $discount_value <= 0) $errors[] = "Discount value must be positive.";
        elseif ($discount_type == 'percentage' && $discount_value > 100) $errors[] = "Percentage cannot exceed 100.";
        elseif ($discount_type == 'fixed' && $discount_value > 999) $errors[] = "Fixed discount max 999.";
        if (!is_numeric($min_order) || $min_order < 0) $errors[] = "Min order must be >= 0.";
        if ($expiry && strtotime($expiry) < strtotime(date('Y-m-d'))) $errors[] = "Expiry date cannot be past.";
        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND store_id = ?");
            $check->execute([$code, $store['id']]);
            if ($check->fetch()) $errors[] = "Coupon code already exists.";
        }
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO coupons (store_id, code, discount_type, discount_value, min_order_amount, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$store['id'], $code, $discount_type, $discount_value, $min_order, $expiry]);
            $_SESSION['success'] = "Coupon created!";
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        redirect('?page=owner_dashboard&tab=coupons');
    }

    // Delete item
    if(isset($_GET['delete_item'])) {
        $pdo->prepare("DELETE FROM store_items WHERE id=? AND store_id=?")->execute([$_GET['delete_item'],$store['id']]);
        redirect('?page=owner_dashboard&tab=items');
    }

    // Stats
    $orderCount   = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE store_id=?"); $orderCount->execute([$store['id']]); $totalOrd = $orderCount->fetchColumn();
    $revCount = $pdo->prepare("SELECT SUM(restaurant_earnings) FROM orders WHERE store_id=? AND status='delivered'"); $revCount->execute([$store['id']]); $totalRestaurantEarnings = $revCount->fetchColumn();
    $itemCount    = $pdo->prepare("SELECT COUNT(*) FROM store_items WHERE store_id=?"); $itemCount->execute([$store['id']]); $totalItems = $itemCount->fetchColumn();

    // ─── ACTIVE ORDERS (for badge) ───
    $activeCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE store_id=? AND status NOT IN ('delivered','cancelled')");
    $activeCount->execute([$store['id']]);
    $activeOrd = $activeCount->fetchColumn();

    // ─── UNREAD CHAT MESSAGES ───
    $chatCountStmt = $pdo->prepare("SELECT COUNT(*) FROM store_chat_messages WHERE store_id = ? AND is_read = 0");
    $chatCountStmt->execute([$store['id']]);
    $unreadChats = $chatCountStmt->fetchColumn();

    // ─── REPORTS DATA ───
    $reportMonth = isset($_GET['report_month']) ? $_GET['report_month'] : date('Y-m');
    $startDate = date('Y-m-01', strtotime($reportMonth));
    $endDate = date('Y-m-t', strtotime($reportMonth));
    $reportStoreId = $store['id'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ? AND created_at BETWEEN ? AND ?");
    $stmt->execute([$reportStoreId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $totalOrders = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales, SUM(restaurant_earnings) as total_earnings, SUM(delivery_fee) as total_delivery, SUM(commission_amount) as total_commission, SUM(discount_amount) as total_discount FROM orders WHERE store_id = ? AND created_at BETWEEN ? AND ?");
    $stmt->execute([$reportStoreId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $salesData = $stmt->fetch();
    $totalSales = $salesData['total_sales'] ?? 0;
    $totalEarnings = $salesData['total_earnings'] ?? 0;
    $totalDelivery = $salesData['total_delivery'] ?? 0;
    $totalCommission = $salesData['total_commission'] ?? 0;
    $totalDiscount = $salesData['total_discount'] ?? 0;

    $stmt = $pdo->prepare("SELECT SUM(oi.quantity) as total_items FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.store_id = ? AND o.created_at BETWEEN ? AND ?");
    $stmt->execute([$reportStoreId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $totalItemsSold = $stmt->fetchColumn() ?: 0;

    $topProducts = $pdo->prepare("
        SELECT si.id, si.name, si.price, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN store_items si ON oi.item_id = si.id
        WHERE o.store_id = ? AND o.created_at BETWEEN ? AND ?
        GROUP BY si.id
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $topProducts->execute([$reportStoreId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $topProductsList = $topProducts->fetchAll();

    $dailySales = $pdo->prepare("
        SELECT DATE(created_at) as date, SUM(total_amount) as daily_sales
        FROM orders
        WHERE store_id = ? AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $dailySales->execute([$reportStoreId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $dailySalesData = $dailySales->fetchAll();
    $chartLabels = [];
    $chartValues = [];
    foreach ($dailySalesData as $row) {
        $chartLabels[] = date('M j', strtotime($row['date']));
        $chartValues[] = (float)$row['daily_sales'];
    }
?>

<style>
/* All existing CSS (unchanged) */
.owner-shell{display:flex;min-height:calc(100vh - 70px);margin:-1rem -12px 0;gap:0;background:var(--bg-base);}
.owner-sidebar{width:220px;flex-shrink:0;background:rgba(255,255,255,.025);border-right:1px solid var(--border);padding:1.5rem 0;display:flex;flex-direction:column;gap:0.25rem;position:sticky;top:70px;height:calc(100vh - 70px);overflow-y:auto;}
.owner-main{flex:1;padding:2rem;min-width:0;}
.o-nav-item{display:flex;align-items:center;gap:10px;padding:.6rem 1.25rem;color:var(--text-secondary);text-decoration:none;font-size:.875rem;font-weight:500;transition:var(--transition);margin:0 .5rem;border-radius:var(--radius-sm);white-space:nowrap;}
.o-nav-item:hover{background:rgba(255,255,255,.05);color:var(--text-primary);}
.o-nav-item.active{background:rgba(255,87,34,.12);color:var(--primary);border:1px solid rgba(255,87,34,.2);}
.o-nav-item.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:20px;background:var(--primary);border-radius:2px;}
.o-nav-item{position:relative;}
.o-section-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);padding:1rem 1.75rem .4rem;}
.owner-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:2rem;}
.o-stat{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.2rem;position:relative;overflow:hidden;transition:var(--transition);}
.o-stat::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--acolor,var(--primary));}
.o-stat:hover{background:rgba(255,255,255,.05);transform:translateY(-2px);}
.o-stat-val{font-family:var(--font-display);font-size:1.7rem;font-weight:700;margin-bottom:.2rem;}
.o-stat-lbl{color:var(--text-muted);font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em;}
.o-panel{background:rgba(255,255,255,.025);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:1.5rem;}
.o-panel-hdr{padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:1rem;}
.o-panel-title{font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:8px;}
.o-panel-body{padding:1.4rem;}
.otbl{width:100%;border-collapse:collapse;}
.otbl th{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);padding:.75rem 1rem;border-bottom:1px solid var(--border);text-align:left;background:rgba(255,255,255,.015);}
.otbl td{padding:.8rem 1rem;border-bottom:1px solid rgba(255,255,255,.04);font-size:.875rem;vertical-align:middle;}
.otbl tr:hover td{background:rgba(255,255,255,.025);}
.otbl tr:last-child td{border-bottom:none;}
.sdot{display:inline-flex;align-items:center;gap:5px;padding:.25em .75em;border-radius:50px;font-size:.72rem;font-weight:600;text-transform:capitalize;}
.owner-page-hdr{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;}
.rf{display:flex;gap:6px;align-items:center;}
.rf select{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);font-size:.78rem;padding:.3rem .5rem;max-width:130px;}
.rf select option{background:#1A1820;}
.store-info-bar{background:rgba(255,87,34,.07);border:1px solid rgba(255,87,34,.2);border-radius:var(--radius-lg);padding:1rem 1.4rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
@media(max-width:900px){.owner-sidebar{display:none;}.owner-main{padding:1rem;}}
.chat-msg-item{padding:.8rem;border-radius:var(--radius-md);margin-bottom:.5rem;}
.chat-msg-item.customer{background:rgba(255,87,34,.1);border:1px solid rgba(255,87,34,.2);}
.chat-msg-item.owner{background:rgba(255,255,255,.04);border:1px solid var(--border);}

/* Add small badge for unread count in chat list */
.chat-unread-badge {
    background: #EF4444;
    color: white;
    border-radius: 50px;
    padding: 0.1rem 0.6rem;
    font-size: 0.7rem;
    font-weight: 700;
    margin-left: 6px;
}
</style>

<div class="owner-shell">
<aside class="owner-sidebar">
    <div style="padding:0 1.25rem 1.25rem;border-bottom:1px solid var(--border);margin-bottom:.75rem;">
        <div style="font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);">My Store</div>
        <div style="font-weight:700;font-size:.95rem;margin-top:.4rem;"><?php echo htmlspecialchars($store['store_name']); ?></div>
        <div style="font-size:.75rem;color:var(--text-muted);"><?php echo ucfirst($store['store_type']); ?></div>
    </div>
    <div class="o-section-label">Manage</div>
    <a href="?page=owner_dashboard&tab=items" class="o-nav-item <?php echo $tab==='items'?'active':''; ?>"><i class="fas fa-utensils"></i> Menu Items</a>
    <a href="?page=owner_dashboard&tab=orders" class="o-nav-item <?php echo $tab==='orders'?'active':''; ?>"><i class="fas fa-receipt"></i> Orders
        <?php if($activeOrd > 0): ?>
            <span class="sidebar-badge"><?php echo $activeOrd; ?></span>
        <?php endif; ?>
    </a>
    <a href="?page=owner_dashboard&tab=reservations" class="o-nav-item <?php echo $tab==='reservations'?'active':''; ?>"><i class="fas fa-calendar-check"></i> Reservations</a>
    <a href="?page=owner_dashboard&tab=coupons" class="o-nav-item <?php echo $tab==='coupons'?'active':''; ?>"><i class="fas fa-tag"></i> Coupons</a>
    <a href="?page=owner_dashboard&tab=reports" class="o-nav-item <?php echo $tab==='reports'?'active':''; ?>"><i class="fas fa-chart-bar"></i> Reports</a>
    <a href="?page=owner_dashboard&tab=subscription" class="o-nav-item <?php echo $tab==='subscription'?'active':''; ?>"><i class="fas fa-crown"></i> Subscription</a>
    <a href="?page=owner_dashboard&tab=qr" class="o-nav-item <?php echo $tab==='qr'?'active':''; ?>"><i class="fas fa-qrcode"></i> QR Code</a>
    <a href="?page=owner_dashboard&tab=storeChats" class="o-nav-item <?php echo $tab==='storeChats'?'active':''; ?>"><i class="fas fa-comments"></i> Chats
        <?php if($unreadChats > 0): ?>
            <span class="sidebar-badge"><?php echo $unreadChats; ?></span>
        <?php endif; ?>
    </a>
    <a href="?page=owner_dashboard&tab=store_info" class="o-nav-item <?php echo $tab==='store_info'?'active':''; ?>"><i class="fas fa-store"></i> Store Info</a>
    <div class="o-section-label">Site</div>
    <a href="?page=home" class="o-nav-item"><i class="fas fa-globe"></i> View Site</a>
    <a href="?page=logout" class="o-nav-item" style="color:#FCA5A5;"><i class="fas fa-right-from-bracket"></i> Logout</a>
</aside>

<main class="owner-main">
    <div class="owner-page-hdr">
        <div>
            <h1><?php $ts=['items'=>'Menu Items','orders'=>'Orders','coupons'=>'Coupons','qr'=>'QR Code','storeChats'=>'Customer Chats','reservations'=>'Reservations','subscription'=>'Subscription','store_info'=>'Store Settings','reports'=>'Sales Reports']; echo $ts[$tab]??'Dashboard'; ?></h1>
            <div><?php echo htmlspecialchars($store['store_name']); ?> · <?php echo date('M j, Y'); ?></div>
        </div>
        <?php if($activeOrd>0): ?><span class="badge bg-primary"><?php echo $activeOrd; ?> Active</span><?php endif; ?>
    </div>

    <div class="owner-stat-grid">
        <div class="o-stat"><i class="fas fa-receipt"></i><div class="o-stat-val"><?php echo $totalOrd; ?></div><div class="o-stat-lbl">Total Orders</div></div>
        <div class="o-stat"><i class="fas fa-dollar-sign"></i><div class="o-stat-val">Rs. <?php echo number_format($totalRestaurantEarnings??0,0); ?></div><div class="o-stat-lbl">Net Earnings</div></div>
        <div class="o-stat"><i class="fas fa-utensils"></i><div class="o-stat-val"><?php echo $totalItems; ?></div><div class="o-stat-lbl">Menu Items</div></div>
        <div class="o-stat"><i class="fas fa-clock"></i><div class="o-stat-val"><?php echo $activeOrd; ?></div><div class="o-stat-lbl">Active Orders</div></div>
    </div>

<?php
// ==================== TAB: MENU ITEMS ====================
if($tab == 'items') {
    $items = $pdo->prepare("SELECT * FROM store_items WHERE store_id = ?");
    $items->execute([$store['id']]);
    $itemList = $items->fetchAll();
?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-plus-circle"></i> Add New Menu Item</div></div>
        <div class="o-panel-body">
            <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Item Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Price (Rs.) *</label><input type="number" step="0.01" name="price" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Category</label><input type="text" name="category" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="col-12"><label class="form-label">Item Image</label><input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp"><small class="text-muted">Max 2MB, JPG/PNG/WebP</small></div>
                    <div class="col-12"><button type="submit" name="add_item" class="btn btn-success">Add Item</button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-list"></i> Menu Items</div><span><?php echo count($itemList); ?> items</span></div>
        <?php if(empty($itemList)): ?>
        <div class="o-panel-body text-center"><div style="font-size:2.5rem;">🍽️</div><h5>No Items Yet</h5></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="otbl">
                <thead><tr><th>Item Name</th><th>Category</th><th>Price</th><th>Description</th><th>Image</th><th>Edit</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($itemList as $item): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                    <td><?php if($item['category']): ?><span class="sdot"><?php echo htmlspecialchars($item['category']); ?></span><?php else: echo '—'; endif; ?></td>
                    <td><strong>Rs. <?php echo number_format($item['price'],2); ?></strong></td>
                    <td style="max-width:200px;color:var(--text-secondary);font-size:.83rem;"><?php echo htmlspecialchars($item['description']??'—'); ?></td>
                    <td><?php if($item['image']): ?><img src="<?php echo htmlspecialchars($item['image']); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:8px;"><?php else: ?>—<?php endif; ?></td>
                    <td>
                        <button class="btn btn-sm btn-info edit-item-btn"
                            data-id="<?php echo $item['id']; ?>"
                            data-name="<?php echo htmlspecialchars($item['name']); ?>"
                            data-price="<?php echo $item['price']; ?>"
                            data-category="<?php echo htmlspecialchars($item['category']); ?>"
                            data-description="<?php echo htmlspecialchars($item['description']); ?>"
                            data-image="<?php echo $item['image']; ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                    <td><a href="?delete_item=<?php echo $item['id']; ?>&page=owner_dashboard&tab=items" onclick="return confirm('Delete?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" action="ajax/update_item.php">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit Menu Item</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        <div class="mb-3"><label class="form-label">Item Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Price (Rupees)</label><input type="number" step="0.01" name="price" id="edit_price" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Category</label><input type="text" name="category" id="edit_category" class="form-control"></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit_description" class="form-control" rows="3"></textarea></div>
                        <div class="mb-3"><label class="form-label">Current Image</label><br><img id="edit_current_image" src="" style="max-width: 100px; max-height: 100px; border-radius: 8px;"></div>
                        <div class="mb-3"><label class="form-label">Upload New Image (optional)</label><input type="file" name="new_image" class="form-control" accept="image/jpeg,image/png,image/webp"><small class="text-muted">Leave empty to keep current image.</small></div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update_item" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php
// ==================== TAB: ORDERS ====================
} elseif($tab == 'orders') {
    $orders = $pdo->prepare("SELECT o.*, u.name as customer FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.store_id = ? ORDER BY o.id DESC");
    $orders->execute([$store['id']]);
    $orderList = $orders->fetchAll();
    $statusColors = ['pending'=>'#FFC107','preparing'=>'#3ECFCF','out_for_delivery'=>'#818CF8','delivered'=>'#10B981','cancelled'=>'#EF4444'];
?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-receipt"></i> Store Orders</div><span><?php echo count($orderList); ?> total</span></div>
        <?php if(empty($orderList)): ?>
        <div class="o-panel-body text-center"><div style="font-size:2.5rem;">📦</div><h5>No Orders Yet</h5></div>
        <?php else: ?>
        <div style="overflow-x:auto;"><table class="otbl"><thead><tr><th>Order#</th><th>Customer</th><th>Items</th><th>Your Earnings</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
        <?php foreach($orderList as $order): 
            $sc = $statusColors[$order['status']]??'#888';
            $itemStmt = $pdo->prepare("SELECT si.name, oi.quantity, oi.price FROM order_items oi JOIN store_items si ON oi.item_id = si.id WHERE oi.order_id = ?");
            $itemStmt->execute([$order['id']]);
            $items = $itemStmt->fetchAll();
            $restaurantEarnings = $order['restaurant_earnings'] ?? ($order['total_amount'] - ($order['delivery_fee'] ?? 0));
        ?>
            <tr>
                <td><span style="font-weight:700;color:var(--primary);">#<?php echo $order['id']; ?></span></td>
                <td><?php echo htmlspecialchars($order['customer']); ?></td>
                <td style="max-width:250px;"><?php foreach($items as $item): ?><div><strong><?php echo htmlspecialchars($item['name']); ?></strong> x<?php echo $item['quantity']; ?> <span>Rs. <?php echo number_format($item['price']*$item['quantity'],2); ?></span></div><?php endforeach; ?></td>
                <td><strong>Rs. <?php echo number_format($restaurantEarnings,2); ?></strong></td>
                <td><span class="sdot" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo str_replace('_',' ',$order['status']); ?></span></td>
                <td><?php echo date('M j, Y',strtotime($order['created_at'])); ?></td>
                <td>
                    <?php if($order['status'] == 'delivered'): ?>
                        <span class="sdot" style="background:rgba(16,185,129,0.15);color:var(--success);"><i class="fas fa-check-circle"></i> Completed</span>
                    <?php else: ?>
                        <form method="POST" action="ajax/update_order_status.php" class="rf"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><select name="status"><option value="pending">Pending</option><option value="preparing">Preparing</option><option value="out_for_delivery">Out for Delivery</option><option value="delivered">Delivered</option></select><button type="submit" class="btn btn-sm btn-primary">Update</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </div>

<?php
// ==================== TAB: COUPONS ====================
} elseif($tab == 'coupons') {
    $coupons = $pdo->prepare("SELECT * FROM coupons WHERE store_id=? ORDER BY id DESC");
    $coupons->execute([$store['id']]);
    $couponList = $coupons->fetchAll();
?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-tag"></i> Create Coupon</div></div>
        <div class="o-panel-body">
            <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4"><label>Coupon Code *</label><input type="text" name="code" class="form-control" placeholder="SAVE20" required></div>
                    <div class="col-md-3"><label>Discount Type</label><select name="discount_type" class="form-select"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed (Rs.)</option></select></div>
                    <div class="col-md-2"><label>Value *</label><input type="number" step="0.01" name="discount_value" class="form-control" placeholder="e.g. 20" required></div>
                    <div class="col-md-2"><label>Min Order (Rs.)</label><input type="number" step="0.01" name="min_order" class="form-control" placeholder="0.00"></div>
                    <div class="col-md-3"><label>Expiry Date</label><input type="date" name="expiry" class="form-control"></div>
                    <div class="col-12"><button type="submit" name="add_coupon" class="btn btn-warning">Create Coupon</button></div>
                </div>
            </form>
        </div>
    </div>
    <?php if(!empty($couponList)): ?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-list"></i> Active Coupons</div></div>
        <div style="overflow-x:auto;"><table class="otbl"><thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Expiry</th></tr></thead><tbody>
        <?php foreach($couponList as $c): ?>
        <tr>
            <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
            <td><?php echo ucfirst($c['discount_type']); ?></td>
            <td><strong><?php echo $c['discount_type']==='percentage'?$c['discount_value'].'%':'Rs. '.number_format($c['discount_value'],2); ?></strong></td>
            <td><?php echo $c['min_order_amount']?'Rs. '.number_format($c['min_order_amount'],2):'—'; ?></td>
            <td><?php echo $c['expiry_date']?date('M j, Y',strtotime($c['expiry_date'])):'No expiry'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
    <?php endif; ?>

<?php
// ==================== TAB: REPORTS ====================
} elseif($tab == 'reports') {
    $monthOptions = '';
    for ($m = 1; $m <= 12; $m++) {
        $val = date('Y-m', mktime(0,0,0,$m,1,date('Y')));
        $sel = ($reportMonth == $val) ? 'selected' : '';
        $monthOptions .= "<option value=\"$val\" $sel>" . date('F Y', mktime(0,0,0,$m,1,date('Y'))) . "</option>";
    }
?>
    <!-- Report Filter -->
    <div class="o-panel" style="margin-bottom:1.5rem;">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-filter"></i> Filter Reports</div></div>
        <div class="o-panel-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="owner_dashboard">
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
                    <a href="ajax/export_report.php?report_month=<?php echo $reportMonth; ?>" class="btn btn-info">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="owner-stat-grid" style="margin-bottom:1.5rem;">
        <div class="o-stat" style="--acolor:#818CF8;">
            <div class="o-stat-val"><?php echo $totalOrders; ?></div>
            <div class="o-stat-lbl">Total Orders</div>
        </div>
        <div class="o-stat" style="--acolor:#10B981;">
            <div class="o-stat-val">Rs. <?php echo number_format($totalSales, 2); ?></div>
            <div class="o-stat-lbl">Total Sales (Customer)</div>
        </div>
        <div class="o-stat" style="--acolor:#FF5722;">
            <div class="o-stat-val">Rs. <?php echo number_format($totalEarnings, 2); ?></div>
            <div class="o-stat-lbl">Net Earnings (Your Share)</div>
        </div>
        <div class="o-stat" style="--acolor:#F59E0B;">
            <div class="o-stat-val"><?php echo $totalItemsSold; ?></div>
            <div class="o-stat-lbl">Items Sold</div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="o-stat" style="--acolor:#3ECFCF;">
                <div class="o-stat-val">Rs. <?php echo number_format($totalDelivery, 2); ?></div>
                <div class="o-stat-lbl">Delivery Fees Collected</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="o-stat" style="--acolor:#F97316;">
                <div class="o-stat-val">Rs. <?php echo number_format($totalCommission, 2); ?></div>
                <div class="o-stat-lbl">Commission Paid</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="o-stat" style="--acolor:#EF4444;">
                <div class="o-stat-val">Rs. <?php echo number_format($totalDiscount, 2); ?></div>
                <div class="o-stat-lbl">Discounts Given</div>
            </div>
        </div>
    </div>

    <!-- Sales Trend Chart -->
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-chart-line"></i> Daily Sales Trend (<?php echo date('F Y', strtotime($reportMonth)); ?>)</div></div>
        <div class="o-panel-body">
            <?php if(empty($chartLabels)): ?>
                <div class="alert alert-info text-center">No sales data for this month.</div>
            <?php else: ?>
                <div style="height:250px;">
                    <canvas id="salesChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-trophy"></i> Top Selling Products</div></div>
        <div style="overflow-x:auto;">
            <?php if(empty($topProductsList)): ?>
                <div class="o-panel-body text-center"><p class="text-muted">No product sales data for this period.</p></div>
            <?php else: ?>
                <table class="otbl">
                    <thead><tr><th>#</th><th>Product Name</th><th>Quantity Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php $rank=1; foreach($topProductsList as $prod): ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><strong><?php echo htmlspecialchars($prod['name']); ?></strong></td>
                            <td><?php echo $prod['total_qty']; ?></td>
                            <td>Rs. <?php echo number_format($prod['revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const ctx = document.getElementById('salesChart')?.getContext('2d');
            if (!ctx) return;
            const labels = <?php echo json_encode($chartLabels); ?>;
            const values = <?php echo json_encode($chartValues); ?>;
            if (labels.length === 0) return;
            const grad = ctx.createLinearGradient(0, 0, 0, 250);
            grad.addColorStop(0, 'rgba(255,87,34,0.3)');
            grad.addColorStop(1, 'rgba(255,87,34,0.02)');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales (Rs.)',
                        data: values,
                        borderColor: '#FF5722',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#FF5722',
                        pointRadius: 3,
                        fill: true,
                        backgroundColor: grad,
                        tension: 0.3
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
                            callbacks: {
                                label: function(context) {
                                    return 'Rs. ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255,255,255,0.04)' },
                            ticks: { color: '#6B6572', font: { size: 10 } },
                            border: { color: 'rgba(255,255,255,0.06)' }
                        },
                        y: {
                            grid: { color: 'rgba(255,255,255,0.04)' },
                            ticks: { color: '#6B6572', font: { size: 10 }, callback: function(value) { return 'Rs. ' + value.toLocaleString(); } },
                            border: { color: 'rgba(255,255,255,0.06)' }
                        }
                    }
                }
            });
        })();
    </script>

<?php
// ==================== TAB: QR CODE ====================
} elseif($tab == 'qr') {
    // Use BASE_URL to generate absolute URL (defined in bootstrap.php)
    $qrUrl = urlencode(BASE_URL . 'index.php?page=qr_menu&id=' . $store['id']);
?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-qrcode"></i> QR Menu Code</div></div>
        <div class="o-panel-body text-center">
            <div style="background:white;display:inline-block;padding:1rem;border-radius:16px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo $qrUrl; ?>" 
                     alt="QR Code for <?php echo htmlspecialchars($store['store_name']); ?>"
                     style="width:200px;height:200px;">
            </div>
            <div class="mt-3">
                <a href="<?php echo urldecode($qrUrl); ?>" target="_blank" class="btn btn-info">
                    <i class="fas fa-external-link-alt me-1"></i> Open Menu Link
                </a>
                <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?php echo $qrUrl; ?>" 
                   download="qr-menu-<?php echo $store['id']; ?>.png" 
                   class="btn btn-outline-secondary">
                    <i class="fas fa-download me-1"></i> Download QR
                </a>
            </div>
            <p class="text-muted mt-3">
                <i class="fas fa-info-circle"></i> Customers scan this QR to view your menu instantly.
            </p>
        </div>
    </div>

<?php
// ==================== TAB: STORE CHATS (with per‑customer unread indicator) ====================
} elseif($tab == 'storeChats') {
    // Fetch distinct customers with unread count per customer
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.customer_id, u.name as customer_name, m.store_id, s.store_name,
               (SELECT COUNT(*) FROM store_chat_messages m2 
                WHERE m2.customer_id = m.customer_id AND m2.store_id = m.store_id AND m2.is_read = 0) as unread_count
        FROM store_chat_messages m
        JOIN users u ON m.customer_id = u.id
        JOIN stores s ON m.store_id = s.id
        WHERE m.owner_id = ?
        ORDER BY unread_count DESC, m.id DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $conversations = $stmt->fetchAll();

    // Also fetch the global unread count again for sidebar (already have $unreadChats)
?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-comments"></i> Customer Chats</div></div>
        <div class="o-panel-body">
            <?php if(count($conversations) === 0): ?>
            <div class="text-center"><div style="font-size:2.5rem;">💬</div><h5>No Messages Yet</h5></div>
            <?php else: ?>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <?php foreach($conversations as $conv): ?>
                    <div class="o-panel" style="margin-bottom:.6rem;cursor:pointer;border-left: 3px solid <?php echo ($conv['unread_count'] > 0) ? '#EF4444' : 'transparent'; ?>;" 
                         onclick="loadOwnerChat('<?php echo $conv['store_id']; ?>','<?php echo $conv['customer_id']; ?>','<?php echo htmlspecialchars($conv['customer_name']); ?>')">
                        <div style="padding:.8rem 1rem;display:flex;align-items:center;gap:.75rem;position:relative;">
                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;">
                                <?php echo strtoupper(substr($conv['customer_name'],0,1)); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($conv['customer_name']); ?></strong>
                                <?php if($conv['unread_count'] > 0): ?>
                                    <span class="chat-unread-badge"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if($conv['unread_count'] > 0): ?>
                                <span style="position:absolute;top:8px;right:10px;width:10px;height:10px;background:#EF4444;border-radius:50%;"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="flex:1;min-width:280px;" id="ownerChatArea" style="display:none;">
                    <div class="o-panel">
                        <div class="o-panel-hdr" id="ownerChatHeader" style="display:none;">
                            <span id="ownerChatCustomerName"></span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="markChatRead()">Mark as Read</button>
                        </div>
                        <div id="ownerChatMessages" style="height:300px;overflow-y:auto;padding:1rem;"></div>
                        <div style="padding:1rem;display:flex;gap:.75rem;">
                            <input type="text" id="ownerChatInput" class="form-control" placeholder="Type your reply...">
                            <button class="btn btn-primary" onclick="sendOwnerMessage()">Send</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function markChatRead() {
        if (!currentCustomerId || !currentStoreId) return;
        $.post('ajax/mark_chat_read.php', { store_id: currentStoreId, customer_id: currentCustomerId }, function() {
            location.reload(); // refresh to update badge and list
        });
    }

    let originalLoad = loadOwnerChat;
    loadOwnerChat = function(storeId, customerId, customerName) {
        originalLoad(storeId, customerId, customerName);
        setTimeout(function() {
            $.post('ajax/mark_chat_read.php', { store_id: storeId, customer_id: customerId });
        }, 500);
    };
    </script>
<?php
// ==================== TAB: RESERVATIONS ====================
} elseif($tab == 'reservations') {
    $resStmt = $pdo->prepare("
        SELECT r.*, u.name as customer_name, u.email, s.store_name
        FROM reservations r 
        JOIN stores s ON r.store_id = s.id
        JOIN users u ON r.user_id = u.id 
        WHERE s.owner_id = ? 
        ORDER BY r.reservation_date DESC, r.reservation_time DESC
    ");
    $resStmt->execute([$_SESSION['user_id']]);
    $reservations = $resStmt->fetchAll();
?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-calendar-check"></i> Table Reservations</div></div>
        <?php if(empty($reservations)): ?>
            <div class="o-panel-body text-center"><div style="font-size:2.5rem;">🍽️</div><h5>No Reservations Yet</h5></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="otbl">
                    <thead><tr><th>Restaurant</th><th>Customer</th><th>Date</th><th>Time</th><th>People</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach($reservations as $res): 
                        $statusClass = $res['status']=='pending'?'#FFC107':($res['status']=='confirmed'?'#10B981':'#EF4444');
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($res['store_name']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($res['customer_name']); ?></strong><br><small><?php echo htmlspecialchars($res['email']); ?></small></td>
                            <td><?php echo date('M j, Y', strtotime($res['reservation_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($res['reservation_time'])); ?></td>
                            <td><?php echo $res['number_of_people']; ?></td>
                            <td><span class="sdot" style="background:<?php echo $statusClass; ?>20;color:<?php echo $statusClass; ?>;"><?php echo ucfirst($res['status']); ?></span></td>
                            <td>
                                <?php if($res['status'] == 'pending'): ?>
                                    <form method="POST" action="ajax/update_reservation.php" style="display:inline;">
                                        <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button class="btn btn-sm btn-success">Confirm</button>
                                    </form>
                                    <form method="POST" action="ajax/update_reservation.php" style="display:inline;">
                                        <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button class="btn btn-sm btn-danger">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted"><?php echo $res['status']=='confirmed'?'Confirmed':'Cancelled'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php
// ==================== TAB: SUBSCRIPTION ====================
} elseif($tab == 'subscription') {
    $tier = $store['subscription_tier'] ?? 'basic';
    $expires = $store['subscription_expires'] ?? null;
    $daysLeft = $expires ? max(0, (strtotime($expires) - time()) / 86400) : 0;
?>
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-crown"></i> Your Subscription Plan</div></div>
        <div class="o-panel-body text-center py-4">
            <div style="font-size:3rem;"><?php if($tier=='basic') echo '🆓'; elseif($tier=='standard') echo '⭐'; elseif($tier=='premium') echo '👑'; else echo '💎'; ?></div>
            <h3><?php echo ucfirst($tier); ?> Plan</h3>
            <?php if($tier == 'basic'): ?>
                <p class="text-muted">You are on the free plan. Upgrade to unlock more features.</p>
            <?php else: ?>
                <?php if($expires): ?><p class="text-muted">Valid until <?php echo date('M j, Y', strtotime($expires)); ?> (<?php echo round($daysLeft); ?> days left)</p><?php endif; ?>
            <?php endif; ?>
            <div class="alert alert-info mt-3">Subscription management is coming soon.</div>
            <button class="btn btn-secondary mt-2" disabled>Upgrade (Coming Soon)</button>
        </div>
    </div>

<?php
// ==================== TAB: STORE INFO ====================
} elseif($tab == 'store_info') {
    // Handle store update POST (logo, cover, etc.) – existing logic
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_store'])) {
        $store_name = trim($_POST['store_name']);
        $location = trim($_POST['location']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $errors = [];

        if (empty($store_name)) $errors[] = "Store name is required.";
        if (empty($location)) $errors[] = "Location is required.";
        if (strlen($description) > 500) $errors[] = "Description too long (max 500).";
        if (strlen($category) > 50) $errors[] = "Category too long.";

        // Logo upload
        $logoPath = $store['logo'];
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['logo'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
            $maxSize = 2 * 1024 * 1024;
            if (!in_array($file['type'], $allowed)) $errors[] = "Logo only JPG, PNG, WebP.";
            if ($file['size'] > $maxSize) $errors[] = "Logo max 2MB.";
            if (empty($errors)) {
                if (!is_dir('uploads/stores')) mkdir('uploads/stores', 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $unique = uniqid() . '_' . time() . '.' . $ext;
                $dest = 'uploads/stores/' . $unique;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $logoPath = $dest;
                } else {
                    $errors[] = "Failed to upload logo.";
                }
            }
        }

        // Cover image upload
        $coverPath = $store['cover_image'];
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['cover_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
            $maxSize = 2 * 1024 * 1024;
            if (!in_array($file['type'], $allowed)) $errors[] = "Cover image only JPG, PNG, WebP.";
            if ($file['size'] > $maxSize) $errors[] = "Cover image max 2MB.";
            if (empty($errors)) {
                if (!is_dir('uploads/stores')) mkdir('uploads/stores', 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $unique = uniqid() . '_' . time() . '.' . $ext;
                $dest = 'uploads/stores/' . $unique;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $coverPath = $dest;
                } else {
                    $errors[] = "Failed to upload cover image.";
                }
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE stores SET store_name = ?, location = ?, description = ?, category = ?, logo = ?, cover_image = ? WHERE id = ?");
            $stmt->execute([$store_name, $location, $description, $category, $logoPath, $coverPath, $store['id']]);
            $_SESSION['success'] = "Store information updated!";
            // Refresh store data
            $stores = $pdo->prepare("SELECT * FROM stores WHERE owner_id = ?");
            $stores->execute([$ownerId]);
            $store = $stores->fetch();
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        redirect('?page=owner_dashboard&tab=store_info');
    }

    // Fallback for is_online (ensure column exists)
    $isOnline = isset($store['is_online']) ? $store['is_online'] : 1;
?>

    <!-- Online/Offline Toggle -->
    <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
        <div>
            <strong><i class="fas fa-power-off me-2"></i>Store Status</strong><br>
            <small>When offline, customers cannot place new orders.</small>
        </div>
        <form method="POST" action="ajax/toggle_online.php">
            <input type="hidden" name="store_id" value="<?php echo $store['id']; ?>">
            <button type="submit" name="toggle_online" class="btn <?php echo $isOnline ? 'btn-success' : 'btn-secondary'; ?>">
                <i class="fas fa-<?php echo $isOnline ? 'check-circle' : 'clock'; ?>"></i>
                <?php echo $isOnline ? 'Online' : 'Offline'; ?>
            </button>
        </form>
    </div>

    <!-- Main Store Edit Form -->
    <div class="o-panel">
        <div class="o-panel-hdr"><div class="o-panel-title"><i class="fas fa-edit"></i> Edit Store Details</div></div>
        <div class="o-panel-body">
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php elseif(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Store Name *</label>
                    <input type="text" name="store_name" class="form-control" value="<?php echo htmlspecialchars($store['store_name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Location *</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($store['location']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($store['category'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($store['description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Store Logo</label>
                    <?php if($store['logo']): ?>
                        <div class="mb-2"><img src="<?php echo htmlspecialchars($store['logo']); ?>" style="max-width: 100px; max-height: 100px; border-radius: 8px;"></div>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">Leave empty to keep current logo.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cover Image</label>
                    <?php if($store['cover_image']): ?>
                        <div class="mb-2"><img src="<?php echo htmlspecialchars($store['cover_image']); ?>" style="max-width: 150px; max-height: 100px; border-radius: 8px;"></div>
                    <?php endif; ?>
                    <input type="file" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">Leave empty to keep current cover image.</small>
                </div>
                <button type="submit" name="update_store" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
<?php
}
?>
</main>
</div>

<script>
let currentStoreId = null, currentCustomerId = null;
function loadOwnerChat(storeId, customerId, customerName) {
    currentStoreId = storeId; currentCustomerId = customerId;
    document.getElementById('ownerChatArea').style.display = 'block';
    document.getElementById('ownerChatHeader').style.display = 'flex';
    document.getElementById('ownerChatCustomerName').textContent = customerName;
    fetchMessages();
    // Mark as read when opening
    $.post('ajax/mark_chat_read.php', { store_id: storeId, customer_id: customerId });
}
function fetchMessages() {
    if(!currentStoreId) return;
    $.get('ajax/get_owner_chat.php', { store_id: currentStoreId, customer_id: currentCustomerId }, function(data) {
        $('#ownerChatMessages').html(data);
        $('#ownerChatMessages').scrollTop($('#ownerChatMessages')[0].scrollHeight);
    });
}
function sendOwnerMessage() {
    let msg = $('#ownerChatInput').val().trim();
    if (!msg) return;
    $.post('ajax/send_owner_reply.php', { store_id: currentStoreId, customer_id: currentCustomerId, message: msg }, function(r) {
        if(r === 'success') { $('#ownerChatInput').val(''); fetchMessages(); }
    });
}
setInterval(function(){ if(currentStoreId) fetchMessages(); }, 5000);

$('.edit-item-btn').click(function() {
    $('#edit_item_id').val($(this).data('id'));
    $('#edit_name').val($(this).data('name'));
    $('#edit_price').val($(this).data('price'));
    $('#edit_category').val($(this).data('category'));
    $('#edit_description').val($(this).data('description'));
    let currentImg = $(this).data('image');
    if (currentImg) {
        $('#edit_current_image').attr('src', currentImg).show();
    } else {
        $('#edit_current_image').hide();
    }
    $('#editItemModal').modal('show');
});

// ─── Live polling for chat alerts ───
let previousUnread = <?php echo $unreadChats; ?>;

function checkNewMessages() {
    $.get('ajax/get_owner_unread_count.php', function(count) {
        count = parseInt(count);
        if (count > previousUnread) {
            showToast('📩 New message from a customer!');
        }
        previousUnread = count;
        // Update sidebar badge dynamically
        let badge = $('.o-nav-item:contains("Chats") .sidebar-badge');
        if (count > 0) {
            if (badge.length) {
                badge.text(count).show();
            } else {
                $('.o-nav-item:contains("Chats")').append('<span class="sidebar-badge">' + count + '</span>');
            }
        } else {
            badge.hide();
        }
    });
}

function showToast(message) {
    if (!$('#toastContainer').length) {
        $('body').append('<div id="toastContainer" style="position:fixed;top:70px;right:20px;z-index:9999;"></div>');
    }
    let toastHtml = `
        <div class="toast align-items-center text-white bg-primary border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-comment me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    $('#toastContainer').append(toastHtml);
    setTimeout(() => {
        $('#toastContainer .toast:first').remove();
    }, 5000);
}

setInterval(checkNewMessages, 10000);
</script>
<?php } ?>