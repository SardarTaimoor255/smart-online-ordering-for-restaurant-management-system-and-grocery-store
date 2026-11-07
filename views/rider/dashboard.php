<?php
// ==============================================
// VIEWS/RIDER/DASHBOARD.PHP — Added profile update + online/offline toggle
// ==============================================
require_once __DIR__ . '/../../bootstrap.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'rider') {
    header('Location: ' . BASE_URL . 'index.php?page=login');
    exit();
}

$riderId = $_SESSION['user_id'];

// ========== CHECK IF RIDER ACCOUNT IS APPROVED ==========
$stmt = $pdo->prepare("SELECT is_approved, is_active, is_online FROM users WHERE id = ?");
$stmt->execute([$riderId]);
$user = $stmt->fetch();

if (!$user || $user['is_approved'] != 1 || $user['is_active'] != 1) {
    // Show pending approval message (same as before)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Pending Approval</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { background: #0a0c10; display: flex; align-items: center; justify-content: center; height: 100vh; }
            .pending-card { max-width: 500px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; text-align: center; padding: 2rem; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="pending-card mx-auto">
                <div class="mb-4"><i class="fas fa-clock" style="font-size: 3rem; color: #FFC107;"></i></div>
                <h3>Account Pending Approval</h3>
                <p class="text-muted">Your rider account is waiting for admin approval. You will be notified once approved.</p>
                <a href="index.php?page=logout" class="btn btn-danger mt-3"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get current online status (default to 1 if column missing)
$riderOnline = $user['is_online'] ?? 1;

// ========== PROFILE UPDATE HANDLER (self-contained) ==========
$profileSuccess = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name)) {
        $profileError = "Name is required.";
    } elseif (empty($phone)) {
        $profileError = "Phone number is required.";
    } elseif (!preg_match('/^03[0-9]{9}$/', $phone)) {
        $profileError = "Phone must be a valid Pakistani number (03xxxxxxxxx).";
    } elseif (!empty($newPassword)) {
        if (empty($currentPassword)) {
            $profileError = "Current password is required to set a new password.";
        } elseif (strlen($newPassword) < 6) {
            $profileError = "New password must be at least 6 characters.";
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $profileError = "New password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $profileError = "New password must contain at least one number.";
        } elseif ($newPassword !== $confirmPassword) {
            $profileError = "New passwords do not match.";
        } else {
            // Verify current password (plain text – matches registration)
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$riderId]);
            $dbPassword = $stmt->fetchColumn();
            if ($dbPassword !== $currentPassword) {
                $profileError = "Current password is incorrect.";
            }
        }
    }

    if (empty($profileError)) {
        if (!empty($newPassword)) {
            $updateStmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ?, password = ? WHERE id = ?");
            $updateStmt->execute([$name, $phone, $address, $newPassword, $riderId]);
        } else {
            $updateStmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
            $updateStmt->execute([$name, $phone, $address, $riderId]);
        }
        $_SESSION['user_name'] = $name;
        $profileSuccess = "Profile updated successfully!";
    }
}

// Auto-expire pending requests
expireOldRiderRequests($pdo, 10);

// Fetch pending assignment requests
$stmt = $pdo->prepare("
    SELECT ra.*, o.total_amount, o.delivery_address 
    FROM rider_assignments ra
    JOIN orders o ON ra.order_id = o.id
    WHERE ra.rider_id = ? AND ra.status = 'pending'
    ORDER BY ra.created_at ASC
");
$stmt->execute([$riderId]);
$pendingRequests = $stmt->fetchAll();

// Total earnings
$totalEarnings = $pdo->prepare("SELECT SUM(rider_earnings) FROM orders WHERE rider_id = ? AND status = 'delivered'");
$totalEarnings->execute([$riderId]);
$totalEarned = $totalEarnings->fetchColumn();

// Active deliveries
$activeStmt = $pdo->prepare("
    SELECT o.*, s.store_name, s.location as store_location, u.name as customer, u.address
    FROM orders o 
    JOIN stores s ON o.store_id = s.id 
    JOIN users u ON o.customer_id = u.id
    WHERE o.rider_id = ? AND o.status != 'delivered'
    ORDER BY o.created_at DESC
");
$activeStmt->execute([$riderId]);
$activeDeliveries = $activeStmt->fetchAll();

// Completed deliveries
$completedStmt = $pdo->prepare("
    SELECT o.*, s.store_name, s.location as store_location, u.name as customer
    FROM orders o 
    JOIN stores s ON o.store_id = s.id 
    JOIN users u ON o.customer_id = u.id
    WHERE o.rider_id = ? AND o.status = 'delivered'
    ORDER BY o.created_at DESC
    LIMIT 20
");
$completedStmt->execute([$riderId]);
$completedDeliveries = $completedStmt->fetchAll();

// Fetch current profile data to pre‑fill the form
$profileStmt = $pdo->prepare("SELECT name, phone, address FROM users WHERE id = ?");
$profileStmt->execute([$riderId]);
$currentProfile = $profileStmt->fetch();
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Rider Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>
    </div>

    <!-- Online/Offline Toggle (NEW) -->
    <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
        <div>
            <strong><i class="fas fa-power-off me-2"></i>My Status</strong><br>
            <small>When offline, you will not receive new delivery requests.</small>
        </div>
        <form method="POST" action="ajax/toggle_rider_online.php">
            <input type="hidden" name="rider_id" value="<?php echo $riderId; ?>">
            <button type="submit" name="toggle_online" class="btn <?php echo $riderOnline ? 'btn-success' : 'btn-secondary'; ?>">
                <i class="fas fa-<?php echo $riderOnline ? 'check-circle' : 'clock'; ?>"></i>
                <?php echo $riderOnline ? 'Online' : 'Offline'; ?>
            </button>
        </form>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Earnings</h5>
                    <h2 class="display-6">Rs. <?php echo number_format($totalEarned ?? 0, 2); ?></h2>
                    <p class="card-text">From delivered orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Deliveries</h5>
                    <h2 class="display-6"><?php echo count($activeDeliveries); ?></h2>
                    <p class="card-text">Pending or in transit</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Completed Orders</h5>
                    <h2 class="display-6"><?php echo count($completedDeliveries); ?></h2>
                    <p class="card-text">Total deliveries done</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PENDING DELIVERY REQUESTS SECTION -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Pending Delivery Requests</h5>
        </div>
        <div class="card-body">
            <?php if (empty($pendingRequests)): ?>
                <p class="text-muted">No pending requests at this time.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Total Amount</th>
                                <th>Delivery Address</th>
                                <th>Requested At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $req): ?>
                            <tr>
                                <td>#<?php echo $req['order_id']; ?></td>
                                <td>Rs. <?php echo number_format($req['total_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars(substr($req['delivery_address'], 0, 60)); ?>...</td>
                                <td><?php echo date('M j, Y h:i A', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=rider_respond" style="display:inline-block;">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="action" value="accept" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">Requests expire automatically after 5 minutes if no response.</small>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Deliveries Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-truck me-2"></i> Active Deliveries</h5>
        </div>
        <div class="card-body">
            <?php if(count($activeDeliveries) == 0): ?>
                <p class="text-muted">No active deliveries at the moment.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach($activeDeliveries as $order): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 border-warning">
                            <div class="card-body">
                                <h5 class="card-title">Order #<?php echo $order['id']; ?></h5>
                                <p><strong>Store:</strong> <?php echo htmlspecialchars($order['store_name']); ?></p>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer']); ?></p>
                                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                                <p><strong>Pickup from:</strong> <?php echo htmlspecialchars($order['store_location']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $order['status'] == 'out_for_delivery' ? 'info' : 'warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </p>
                                <p><strong>Your Earnings (this order):</strong> <span class="text-success">Rs. <?php echo number_format($order['rider_earnings'] ?? 0, 2); ?></span></p>
                                <form method="POST" action="ajax/update_delivery_status.php" class="mt-3">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="delivery_status" class="form-select mb-2" required>
                                        <option value="out_for_delivery" <?php echo $order['status'] == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                        <option value="delivered">Delivered</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary w-100">Update Status</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Completed Deliveries History -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Delivery History</h5>
        </div>
        <div class="card-body">
            <?php if(count($completedDeliveries) == 0): ?>
                <p class="text-muted">No completed deliveries yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Store</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Your Earnings</th>
                                <th>Delivered On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($completedDeliveries as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer']); ?></td>
                                <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td class="text-success">Rs. <?php echo number_format($order['rider_earnings'] ?? 0, 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== NEW: UPDATE PROFILE CARD ========== -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i> Update Profile</h5>
        </div>
        <div class="card-body">
            <?php if ($profileSuccess): ?>
                <div class="alert alert-success"><?php echo $profileSuccess; ?></div>
            <?php elseif ($profileError): ?>
                <div class="alert alert-danger"><?php echo $profileError; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($currentProfile['name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($currentProfile['phone']); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address (optional)</label>
                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($currentProfile['address'] ?? ''); ?></textarea>
                </div>
                <hr>
                <h6>Change Password (leave blank to keep current)</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
            </form>
        </div>
    </div>
</div>