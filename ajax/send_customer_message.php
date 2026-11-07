<?php
require_once '../config/database.php';
if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') exit;

$store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
$message = trim($_POST['message'] ?? '');
$customer_id = $_SESSION['user_id'];

if (!$store_id || $message === '') {
    echo 'error';
    exit;
}

// Get owner_id
$stmt = $pdo->prepare("SELECT owner_id FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$owner = $stmt->fetch();
if (!$owner) { echo 'error'; exit; }

$insert = $pdo->prepare("INSERT INTO store_chat_messages (store_id, customer_id, owner_id, message, sender_type) VALUES (?, ?, ?, ?, 'customer')");
if ($insert->execute([$store_id, $customer_id, $owner['owner_id'], $message])) {
    echo 'success';
} else {
    echo 'error';
}
?>
