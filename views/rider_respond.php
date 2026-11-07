<?php
// views/rider_respond.php
// Go up one level from 'views' to project root, then include bootstrap.php
require_once dirname(__DIR__) . '/bootstrap.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'rider') {
    redirect('index.php?page=login');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id']) && isset($_POST['action'])) {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    
    if ($action === 'accept') {
        if (acceptRiderRequest($pdo, $requestId, $_SESSION['user_id'])) {
            $_SESSION['success'] = "Order accepted. Proceed to delivery.";
        } else {
            $_SESSION['error'] = "Unable to accept. Request may have expired or already been processed.";
        }
    } elseif ($action === 'reject') {
        if (rejectRiderRequest($pdo, $requestId, $_SESSION['user_id'])) {
            $_SESSION['success'] = "Request rejected. Another rider will be notified.";
        } else {
            $_SESSION['error'] = "Unable to reject. Request may have expired or already been processed.";
        }
    } else {
        $_SESSION['error'] = "Invalid action.";
    }
    
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php?page=rider_dashboard');
} else {
    redirect('index.php?page=rider_dashboard');
}
?>