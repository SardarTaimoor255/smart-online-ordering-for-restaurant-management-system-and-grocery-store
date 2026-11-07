<?php
require_once '../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'owner') {
    header('Location: ' . BASE_URL . 'index.php?page=login');
    exit();
}

$reservation_id = $_POST['reservation_id'];
$action = $_POST['action']; // 'confirm' or 'cancel'

$new_status = ($action == 'confirm') ? 'confirmed' : 'cancelled';

$stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
$stmt->execute([$new_status, $reservation_id]);

$res = $pdo->prepare("SELECT user_id, store_id FROM reservations WHERE id = ?");
$res->execute([$reservation_id]);
$data = $res->fetch();

$title = ($action == 'confirm') ? "Reservation Confirmed" : "Reservation Cancelled";
$message = ($action == 'confirm') 
    ? "Your table reservation has been confirmed. We look forward to serving you!"
    : "Your table reservation has been cancelled. Please contact the restaurant for details.";

addNotification($pdo, $data['user_id'], $title, $message);

$_SESSION['success'] = "Reservation " . ($action == 'confirm' ? 'confirmed' : 'cancelled') . " successfully!";
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?>