<?php
require_once '../config/database.php';

if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'owner'], true)) {
    exit('error');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $allowedStatuses = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

    if (!$orderId || !in_array($status, $allowedStatuses, true)) {
        $_SESSION['error'] = "Invalid order status update.";
        redirect($_SERVER['HTTP_REFERER'] ?? '../index.php');
    }

    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $orderId]);

    // Get rider ID (if any)
    $riderStmt = $pdo->prepare("SELECT rider_id FROM orders WHERE id = ?");
    $riderStmt->execute([$orderId]);
    $riderId = $riderStmt->fetchColumn();

    // Insert tracking record if rider exists and status is relevant
    if ($riderId) {
    if ($status == 'preparing') {
        addDeliveryTracking($pdo, $orderId, $riderId, 'assigned');
    } elseif ($status == 'out_for_delivery') {
        // Owner marks as ready – different status
        addDeliveryTracking($pdo, $orderId, $riderId, 'ready_for_pickup');
    } elseif ($status == 'delivered') {
        addDeliveryTracking($pdo, $orderId, $riderId, 'delivered');
    }
}

    // Auto-assign rider if status becomes out_for_delivery and no rider
    if ($status == 'out_for_delivery') {
        $riderCheck = $pdo->prepare("
            SELECT o.rider_id, u.role
            FROM orders o
            LEFT JOIN users u ON o.rider_id = u.id
            WHERE o.id = ?
        ");
        $riderCheck->execute([$orderId]);
        $orderRider = $riderCheck->fetch();

        if (empty($orderRider['rider_id']) || $orderRider['role'] != 'rider') {
            if (!assignRiderToOrder($pdo, $orderId)) {
                $clearInvalidRider = $pdo->prepare("UPDATE orders SET rider_id = NULL WHERE id = ?");
                $clearInvalidRider->execute([$orderId]);
                $_SESSION['error'] = "No active riders are available for automatic assignment.";
            } else {
                // After assignment, insert assigned tracking
                $newRiderStmt = $pdo->prepare("SELECT rider_id FROM orders WHERE id = ?");
                $newRiderStmt->execute([$orderId]);
                $newRiderId = $newRiderStmt->fetchColumn();
                if ($newRiderId) {
                    addDeliveryTracking($pdo, $orderId, $newRiderId, 'assigned');
                }
            }
        }
    }
    
    // Notify customer
    $order = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $cust = $order->fetch();
    addNotification($pdo, $cust['customer_id'], "Order Update", "Your order #$orderId is now $status");
    
    if (!isset($_SESSION['error'])) {
        $_SESSION['success'] = "Status updated!";
    }
    redirect($_SERVER['HTTP_REFERER'] ?? '../index.php');
}
?>