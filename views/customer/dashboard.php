<?php
// ==============================================
// VIEWS/CUSTOMER/DASHBOARD.PHP
// ==============================================

if (!defined('ACCESS_ROUTER')) {
    require_once __DIR__ . '/../../bootstrap.php';
}

if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') {
    header('Location: ' . BASE_URL . 'index.php?page=login');
    exit();
}

$userId = $_SESSION['user_id'];

// ==============================================
// Fetch user details
// ==============================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// ==============================================
// Fetch order history
// ==============================================
$orders = $pdo->prepare("
    SELECT o.*, s.store_name
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
");
$orders->execute([$userId]);
$orderHistory = $orders->fetchAll();

// ==============================================
// Loyalty points
// ==============================================
$points = $user['points'];

// ==============================================
// Notifications
// ==============================================
$notifications = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$notifications->execute([$userId]);
$userNotifications = $notifications->fetchAll();

// ==============================================
// Unread Notification Count
// ==============================================
$unreadCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
    AND is_read = 0
");
$unreadCountStmt->execute([$userId]);
$unreadNotificationCount = (int)$unreadCountStmt->fetchColumn();

// ==============================================
// Order Count
// ==============================================
$orderCount = count($orderHistory);

// ==============================================
// Handle Profile Update
// ==============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $errors = [];
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($phone)) {
        $errors[] = "Phone is required.";
    }
    if (!preg_match('/^03[0-9]{9}$/', $phone)) {
        $errors[] = "Phone must be a valid Pakistani number (03xxxxxxxxx).";
    }
    if (empty($errors)) {
        $update = $pdo->prepare("
            UPDATE users
            SET name = ?, phone = ?, address = ?
            WHERE id = ?
        ");
        $update->execute([$name, $phone, $address, $userId]);
        $_SESSION['user_name'] = $name;
        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    redirect('index.php?page=customer_dashboard');
}

// ==============================================
// Handle Password Change
// ==============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    $stmt = $pdo->prepare("
        SELECT password
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $dbPass = $stmt->fetchColumn();
    $errors = [];
    if ($current != $dbPass) {
        $errors[] = "Current password is incorrect.";
    } elseif (strlen($new) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    } elseif ($new != $confirm) {
        $errors[] = "New passwords do not match.";
    } else {
        $update = $pdo->prepare("
            UPDATE users
            SET password = ?
            WHERE id = ?
        ");
        $update->execute([$new, $userId]);
        $_SESSION['success'] = "Password changed successfully!";
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    redirect('index.php?page=customer_dashboard');
}

// ==============================================
// Mark Notifications as Read
// ==============================================
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 1) {
    $markReadStmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
        AND is_read = 0
    ");
    $markReadStmt->execute([$userId]);
    header('Location: ' . BASE_URL . 'index.php?page=customer_dashboard#notifications');
    exit();
}
?>

<style>
/* ==========================================
   CUSTOMER DASHBOARD - BRIGHT & COMPACT
========================================== */
.customer-dashboard {
    margin-top: 12px;
    color: #e8edf5;
}

/* ==========================================
   SIDEBAR
========================================== */
.dashboard-nav {
    background: rgba(255, 255, 255, 0.055);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    padding: 7px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.dashboard-nav .list-group {
    gap: 3px;
}
.dashboard-nav .list-group-item {
    background: transparent;
    color: #e4e8ee;
    border: none;
    border-radius: 9px !important;
    margin-bottom: 2px;
    padding: 11px 13px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}
.dashboard-nav .list-group-item:last-child {
    margin-bottom: 0;
}
.dashboard-nav .list-group-item:hover {
    background: rgba(255, 255, 255, 0.09);
    color: #ffffff;
    transform: translateX(2px);
}
.dashboard-nav .list-group-item.active {
    background: linear-gradient(135deg, var(--primary), #ff6b45);
    color: white;
    box-shadow: 0 5px 14px rgba(255, 90, 55, 0.22);
}

/* ==========================================
   NAVIGATION
========================================== */
.nav-item-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.nav-left {
    display: flex;
    align-items: center;
    gap: 9px;
}
.nav-icon {
    width: 24px;
    text-align: center;
    font-size: 0.95rem;
}

/* ==========================================
   BADGES
========================================== */
.order-count-badge {
    min-width: 23px;
    height: 23px;
    padding: 0 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50px;
    background: rgba(255,255,255,0.14);
    font-size: 0.7rem;
    font-weight: 700;
}
.dashboard-nav .active .order-count-badge {
    background: rgba(255,255,255,0.28);
}
.notification-badge {
    min-width: 23px;
    height: 23px;
    padding: 0 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50px;
    background: #ff4b4b;
    color: white;
    font-size: 0.7rem;
    font-weight: 800;
    animation: notificationPulse 2s infinite;
}
@keyframes notificationPulse {
    0% { box-shadow: 0 0 0 0 rgba(255,71,71,0.5); }
    70% { box-shadow: 0 0 0 7px rgba(255,71,71,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,71,71,0); }
}

/* ==========================================
   DASHBOARD CARDS
========================================== */
.dashboard-card {
    background: rgba(255, 255, 255, 0.055);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 6px 22px rgba(0,0,0,0.1);
}
.dashboard-card-header {
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(255,255,255,0.055);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.dashboard-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1rem;
    color: #ffffff;
}
.dashboard-card-body {
    padding: 17px 18px;
}

/* ==========================================
   BUTTONS
========================================== */
.dashboard-card .btn {
    border-radius: 8px;
    font-weight: 600;
}
.dashboard-card .btn-primary {
    box-shadow: 0 4px 10px rgba(255, 90, 55, 0.15);
}

/* ==========================================
   ORDER TABLE
========================================== */
.order-table {
    margin-bottom: 0;
    color: #e7ebf0;
}
.order-table thead th {
    color: #b9c0c9;
    border-color: rgba(255,255,255,0.1);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    padding: 10px;
}
.order-table td {
    border-color: rgba(255,255,255,0.08);
    vertical-align: middle;
    padding: 11px 10px;
}
.order-table tbody tr {
    transition: 0.2s ease;
}
.order-table tbody tr:hover {
    background: rgba(255,255,255,0.055);
}
.order-id {
    color: var(--primary);
    font-weight: 800;
}
.track-btn {
    border-radius: 7px;
    font-weight: 600;
    white-space: nowrap;
}

/* ==========================================
   STATUS BADGES
========================================== */
.order-table .badge {
    padding: 6px 9px;
    border-radius: 6px;
    font-size: 0.7rem;
}

/* ==========================================
   NOTIFICATIONS
========================================== */
.notification-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.notification-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px;
    border-radius: 11px;
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.09);
    transition: all 0.2s ease;
}
.notification-item:hover {
    background: rgba(255,255,255,0.075);
    transform: translateY(-1px);
}
.notification-item.unread {
    background: linear-gradient(135deg, rgba(255,90,55,0.15), rgba(255,255,255,0.045));
    border-color: rgba(255,90,55,0.35);
}
.notification-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,90,55,0.16);
    color: #ff7957;
    font-size: 0.9rem;
}
.notification-content {
    flex: 1;
    min-width: 0;
}
.notification-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #f4f6f8;
    margin-bottom: 3px;
}
.notification-message {
    color: #b6bec8;
    font-size: 0.82rem;
    line-height: 1.45;
}
.notification-time {
    margin-top: 5px;
    color: #8e98a4;
    font-size: 0.7rem;
}
.unread-dot {
    width: 8px;
    height: 8px;
    min-width: 8px;
    border-radius: 50%;
    background: #ff4b4b;
    margin-top: 5px;
    box-shadow: 0 0 8px rgba(255,71,71,0.55);
}

/* ==========================================
   EMPTY STATE
========================================== */
.empty-state {
    text-align: center;
    padding: 28px 18px;
}
.empty-state i {
    font-size: 2.2rem;
    color: var(--primary);
    opacity: 0.8;
    margin-bottom: 9px;
}
.empty-state h5 {
    font-size: 1rem;
    margin-bottom: 5px;
}
.empty-state p {
    font-size: 0.85rem;
}

/* ==========================================
   LOYALTY POINTS
========================================== */
.points-card {
    text-align: center;
    padding: 10px;
}
.points-display {
    font-size: 3.2rem;
    font-weight: 850;
    color: var(--primary);
    line-height: 1;
    text-shadow: 0 4px 12px rgba(255,90,55,0.18);
}

/* ==========================================
   PROFILE FORM
========================================== */
.dashboard-card label {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 6px;
    color: #dfe5eb;
}
.dashboard-card .form-control {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.13);
    color: #ffffff;
    border-radius: 8px;
    padding: 9px 11px;
    font-size: 0.9rem;
}
.dashboard-card .form-control:focus {
    background: rgba(255,255,255,0.09);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 0 0 3px rgba(255,90,55,0.12);
}
.dashboard-card .form-control:disabled {
    background: rgba(255,255,255,0.04);
    color: #9ca6b0;
}

/* ==========================================
   PASSWORD SECTION
========================================== */
.password-section {
    margin-top: 25px;
    padding-top: 22px;
    border-top: 1px solid rgba(255,255,255,0.1);
}
.password-section h5 {
    font-size: 1rem;
}

/* ==========================================
   ALERTS
========================================== */
.dashboard-card .alert {
    padding: 10px 14px;
    font-size: 0.85rem;
    border-radius: 8px;
}

/* ==========================================
   RESPONSIVE
========================================== */
@media (max-width: 768px) {
    .customer-dashboard {
        margin-top: 8px;
    }
    .dashboard-nav {
        margin-bottom: 10px;
    }
    .dashboard-nav .list-group-item {
        padding: 10px 12px;
    }
    .dashboard-card-header {
        padding: 13px 14px;
    }
    .dashboard-card-body {
        padding: 14px;
    }
    .notification-item {
        padding: 11px;
    }
    .points-display {
        font-size: 2.8rem;
    }
}
</style>

<div class="customer-dashboard">
    <div class="row g-4">
        <!-- ======================================
             SIDEBAR NAVIGATION
        ======================================= -->
        <div class="col-md-3">
            <div class="dashboard-nav">
                <div class="list-group">
                    <!-- MY ORDERS -->
                    <a href="#orders" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                        <div class="nav-item-content">
                            <div class="nav-left">
                                <span class="nav-icon"><i class="fas fa-box"></i></span>
                                <span>My Orders</span>
                            </div>
                            <?php if ($orderCount > 0): ?>
                                <span class="order-count-badge"><?php echo $orderCount; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <!-- PROFILE -->
                    <a href="#profile" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <div class="nav-left">
                            <span class="nav-icon"><i class="fas fa-user"></i></span>
                            <span>Profile</span>
                        </div>
                    </a>
                    <!-- LOYALTY POINTS -->
                    <a href="#points" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <div class="nav-left">
                            <span class="nav-icon"><i class="fas fa-star"></i></span>
                            <span>Loyalty Points</span>
                        </div>
                    </a>
                    <!-- NOTIFICATIONS -->
                    <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <div class="nav-item-content">
                            <div class="nav-left">
                                <span class="nav-icon"><i class="fas fa-bell"></i></span>
                                <span>Notifications</span>
                            </div>
                            <?php if ($unreadNotificationCount > 0): ?>
                                <span class="notification-badge"><?php echo $unreadNotificationCount; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- ======================================
             DASHBOARD CONTENT
        ======================================= -->
        <div class="col-md-9">
            <div class="tab-content">
                <!-- ======================================
                     MY ORDERS TAB
                ======================================= -->
                <div class="tab-pane fade show active" id="orders">
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h5><i class="fas fa-box me-2"></i> My Orders</h5>
                            <?php if ($orderCount > 0): ?>
                                <span class="badge bg-primary"><?php echo $orderCount; ?> Orders</span>
                            <?php endif; ?>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (count($orderHistory) == 0): ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-bag"></i>
                                    <h5>No Orders Yet</h5>
                                    <p class="text-muted">You haven't placed any orders yet.</p>
                                    <a href="index.php?page=store_list" class="btn btn-primary">Browse Stores</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table order-table">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Store</th>
                                                <th>Date</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Track</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orderHistory as $order): ?>
                                                <tr>
                                                    <td><span class="order-id">#<?php echo $order['id']; ?></span></td>
                                                    <td><?php echo htmlspecialchars($order['store_name']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                    <td><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php
                                                            echo $order['status'] == 'delivered' ? 'success' : ($order['status'] == 'cancelled' ? 'danger' : 'warning');
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="index.php?page=order_tracking&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary track-btn">
                                                            <i class="fas fa-location-arrow"></i> Track
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ======================================
                     PROFILE TAB
                ======================================= -->
                <div class="tab-pane fade" id="profile">
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h5><i class="fas fa-user-edit me-2"></i> Edit Profile</h5>
                        </div>
                        <div class="dashboard-card-body">
                            <!-- Error Message -->
                            <?php if(isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                            <?php endif; ?>
                            <!-- Success Message -->
                            <?php if(isset($_SESSION['success'])): ?>
                                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                            <?php endif; ?>
                            <!-- PROFILE UPDATE FORM -->
                            <form method="POST">
                                <div class="mb-3">
                                    <label>Full Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label>Email (Cannot change)</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label>Delivery Address</label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                                    <small class="text-muted">This address will be auto-filled during checkout.</small>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </form>
                            <!-- PASSWORD SECTION -->
                            <div class="password-section">
                                <h5 class="mb-3"><i class="fas fa-lock me-2"></i> Change Password</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label>Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================================
                     LOYALTY POINTS TAB
                ======================================= -->
                <div class="tab-pane fade" id="points">
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h5><i class="fas fa-star me-2"></i> Loyalty Points</h5>
                        </div>
                        <div class="dashboard-card-body">
                            <div class="points-card">
                                <div class="points-display"><?php echo $points; ?></div>
                                <p class="text-muted mt-2">Points Available</p>
                                <p class="mt-3 mb-0">
                                    <strong>How it works:</strong>
                                    Earn 1 point for every Rs. 1 spent.
                                    Redeem 100 points for Rs. 1 discount
                                    (maximum 50% of order total).
                                </p>
                                <a href="index.php?page=store_list" class="btn btn-success mt-4">Order Now & Earn Points</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================================
                     NOTIFICATIONS TAB
                ======================================= -->
                <div class="tab-pane fade" id="notifications">
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h5>
                                <i class="fas fa-bell me-2"></i> Notifications
                                <?php if ($unreadNotificationCount > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $unreadNotificationCount; ?> New</span>
                                <?php endif; ?>
                            </h5>
                            <?php if (count($userNotifications) > 0): ?>
                                <a href="index.php?page=customer_dashboard&mark_read=1#notifications" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-check-double"></i> Mark All Read
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (count($userNotifications) == 0): ?>
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <h5>No Notifications Yet</h5>
                                    <p class="text-muted mb-0">You don't have any notifications yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="notification-list">
                                    <?php foreach ($userNotifications as $notif): ?>
                                        <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                            <div class="notification-icon"><i class="fas fa-bell"></i></div>
                                            <div class="notification-content">
                                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                <div class="notification-time"><i class="far fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></div>
                                            </div>
                                            <?php if (!$notif['is_read']): ?>
                                                <span class="unread-dot"></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ==============================================
// Activate Bootstrap Tab from URL Hash
// ==============================================
if (window.location.hash) {
    var hash = window.location.hash.substring(1);
    var tabElement = document.querySelector('.list-group-item[href="#' + hash + '"]');
    if (tabElement) {
        var tab = new bootstrap.Tab(tabElement);
        tab.show();
    }
}
</script>