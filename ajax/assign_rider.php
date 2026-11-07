<?php
require_once '../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    exit('error');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $riderId = filter_input(INPUT_POST, 'rider_id', FILTER_VALIDATE_INT);

    if (!$orderId || !$riderId) {
        $_SESSION['error'] = "Please select a valid rider.";
        redirect($_SERVER['HTTP_REFERER'] ?? '../index.php?page=admin_dashboard');
    }

    if (assignRiderToOrder($pdo, $orderId, $riderId)) {
        $_SESSION['success'] = "Rider assigned!";
    } else {
        $_SESSION['error'] = "Selected user is not an active rider.";
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '../index.php?page=admin_dashboard');
}
?>
