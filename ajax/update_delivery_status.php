<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['delivery_status'];
    $riderId = $_SESSION['user_id'];

    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);

    // Insert delivery tracking record
    if ($newStatus == 'out_for_delivery') {
        addDeliveryTracking($pdo, $orderId, $riderId, 'picked_up');
    } elseif ($newStatus == 'delivered') {
        addDeliveryTracking($pdo, $orderId, $riderId, 'delivered');
    }

    // Notify customer
    $order = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
    $order->execute([$orderId]);
    $cust = $order->fetch();
    addNotification($pdo, $cust['customer_id'], "Delivery Update", "Your order #$orderId is now $newStatus");

    $_SESSION['success'] = "Delivery status updated!";
    redirect($_SERVER['HTTP_REFERER']);
}
?>