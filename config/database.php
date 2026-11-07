<?php
// ==============================================
// CONFIGURATION FILE: config/database.php
// ==============================================
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_ordering_system');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper functions (existing)
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function displayNotification($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function markNotificationsRead($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

function addNotification($pdo, $userId, $title, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $title, $message]);
}

// ========== NEW: Insert delivery tracking record (ignores lat/lng) ==========
function addDeliveryTracking($pdo, $orderId, $riderId, $status) {
    $stmt = $pdo->prepare("
        INSERT INTO delivery_tracking (order_id, rider_id, status, latitude, longitude, updated_at)
        VALUES (?, ?, ?, NULL, NULL, NOW())
    ");
    $stmt->execute([$orderId, $riderId, $status]);
}

// ========== MODIFIED: getAvailableRiderId with exclusion ==========
function getAvailableRiderId($pdo, $excludeRiderIds = []) {
    $excludeSql = '';
    $params = [];
    if (!empty($excludeRiderIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeRiderIds), '?'));
        $excludeSql = "AND u.id NOT IN ($placeholders)";
        $params = $excludeRiderIds;
    }
    
    $sql = "
        SELECT u.id
        FROM users u
        LEFT JOIN orders o
            ON o.rider_id = u.id
            AND o.status IN ('pending', 'preparing', 'out_for_delivery')
        WHERE u.role = 'rider'
          AND u.is_active = 1
          AND u.is_online = 1   -- <-- ADD THIS LINE
          $excludeSql
        GROUP BY u.id
        ORDER BY COUNT(o.id) ASC, u.id ASC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rider = $stmt->fetch();
    return $rider ? (int)$rider['id'] : null;
}

// ========== NEW: Request-based assignment functions ==========
function createRiderAssignmentRequest($pdo, $orderId, $riderId = null) {
    if (!$riderId) {
        $riderId = getAvailableRiderId($pdo);
    }
    if (!$riderId) {
        addNotification($pdo, 1, "No Riders Available", "No active riders for order #$orderId.");
        $stmt = $pdo->prepare("UPDATE orders SET status = 'rider_unavailable' WHERE id = ?");
        $stmt->execute([$orderId]);
        return false;
    }
    
    // Check for existing pending request for this order
    $stmt = $pdo->prepare("SELECT id FROM rider_assignments WHERE order_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$orderId]);
    if ($stmt->fetch()) {
        return false;
    }
    
    $stmt = $pdo->prepare("INSERT INTO rider_assignments (order_id, rider_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$orderId, $riderId]);
    $requestId = $pdo->lastInsertId();
    
    // Notify rider
    addNotification($pdo, $riderId, "New Delivery Request", "You have a new delivery request for order #$orderId. Please accept or reject.");
    
    return $requestId;
}

function acceptRiderRequest($pdo, $requestId, $riderId) {
    $stmt = $pdo->prepare("SELECT * FROM rider_assignments WHERE id = ? AND rider_id = ? AND status = 'pending'");
    $stmt->execute([$requestId, $riderId]);
    $request = $stmt->fetch();
    if (!$request) return false;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE rider_assignments SET status = 'accepted', responded_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        
        $stmt = $pdo->prepare("UPDATE orders SET rider_id = ?, status = 'preparing' WHERE id = ?");
        $stmt->execute([$riderId, $request['order_id']]);
        
        // Insert delivery tracking record for 'assigned'
        addDeliveryTracking($pdo, $request['order_id'], $riderId, 'assigned');
        
        // Notify customer
        $stmt = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $stmt->execute([$request['order_id']]);
        $customerId = $stmt->fetchColumn();
        addNotification($pdo, $customerId, "Rider Assigned", "A rider has been assigned to your order #".$request['order_id']." and is preparing for delivery.");
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function rejectRiderRequest($pdo, $requestId, $riderId) {
    $stmt = $pdo->prepare("SELECT * FROM rider_assignments WHERE id = ? AND rider_id = ? AND status = 'pending'");
    $stmt->execute([$requestId, $riderId]);
    $request = $stmt->fetch();
    if (!$request) return false;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE rider_assignments SET status = 'rejected', responded_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        
        // Get all riders who have rejected this order
        $stmt = $pdo->prepare("SELECT rider_id FROM rider_assignments WHERE order_id = ? AND status = 'rejected'");
        $stmt->execute([$request['order_id']]);
        $rejectedRiders = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $excludeRiderIds = array_merge([$riderId], $rejectedRiders);
        
        $nextRiderId = getAvailableRiderId($pdo, $excludeRiderIds);
        if ($nextRiderId) {
            createRiderAssignmentRequest($pdo, $request['order_id'], $nextRiderId);
        } else {
            addNotification($pdo, 1, "No Riders Available", "All riders rejected order #".$request['order_id']);
            $stmt = $pdo->prepare("UPDATE orders SET status = 'rider_unavailable' WHERE id = ?");
            $stmt->execute([$request['order_id']]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function expireOldRiderRequests($pdo, $timeoutMinutes = 10) {
    $stmt = $pdo->prepare("
        SELECT ra.id, ra.order_id, ra.rider_id
        FROM rider_assignments ra
        WHERE ra.status = 'pending' 
          AND ra.created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$timeoutMinutes]);
    $expired = $stmt->fetchAll();
    foreach ($expired as $request) {
        $pdo->beginTransaction();
        try {
            $stmt2 = $pdo->prepare("UPDATE rider_assignments SET status = 'expired' WHERE id = ?");
            $stmt2->execute([$request['id']]);
            
            $stmt2 = $pdo->prepare("SELECT rider_id FROM rider_assignments WHERE order_id = ? AND status IN ('rejected','expired')");
            $stmt2->execute([$request['order_id']]);
            $exclude = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $nextRider = getAvailableRiderId($pdo, $exclude);
            if ($nextRider) {
                createRiderAssignmentRequest($pdo, $request['order_id'], $nextRider);
            } else {
                $stmt2 = $pdo->prepare("UPDATE orders SET status = 'rider_unavailable' WHERE id = ?");
                $stmt2->execute([$request['order_id']]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// ========== Legacy function kept for fallback (optional) ==========
function assignRiderToOrder($pdo, $orderId, $riderId = null) {
    // We now use createRiderAssignmentRequest. 
    // This function is kept for backward compatibility but should not be used directly.
    if (!$riderId) {
        $riderId = getAvailableRiderId($pdo);
    }
    if (!$riderId) return false;
    $riderStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'rider' AND is_active = 1 LIMIT 1");
    $riderStmt->execute([$riderId]);
    if (!$riderStmt->fetch()) return false;
    $orderStmt = $pdo->prepare("UPDATE orders SET rider_id = ? WHERE id = ?");
    $orderStmt->execute([$riderId, $orderId]);
    addNotification($pdo, $riderId, "New Delivery", "You have been assigned order #$orderId");
    return true;
}

function calculatePoints($amount) {
    return floor($amount);
}

// ========== UPDATED: Earn 1 point per Rs. 1 spent ==========
function calculatePointsEarned($amount) {
    return floor($amount); // 1 point per Rs. 1 spent
}

function applyLoyaltyDiscount($points, $total) {
    $discount = min(floor($points / 100), $total * 0.5); // 100 points = Rs. 1 discount, max 50% of total
    return $discount;
}

// Eco mode handling
if (!isset($_SESSION['eco_mode'])) {
    $_SESSION['eco_mode'] = false;
}
if (isset($_GET['toggle_eco'])) {
    $_SESSION['eco_mode'] = !$_SESSION['eco_mode'];
    redirect($_SERVER['HTTP_REFERER']);
}
?>