<?php
// ajax/mark_chat_read.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'owner') {
    echo 'error';
    exit();
}

$store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;

if (!$store_id || !$customer_id) {
    echo 'error';
    exit();
}

// Verify that this store belongs to the logged‑in owner
$stmt = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND owner_id = ?");
$stmt->execute([$store_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo 'error';
    exit();
}

// Mark all messages for this customer and store as read
$update = $pdo->prepare("UPDATE store_chat_messages SET is_read = 1 WHERE store_id = ? AND customer_id = ?");
$update->execute([$store_id, $customer_id]);

echo 'success';
?>