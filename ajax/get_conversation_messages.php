<?php
require_once '../config/database.php';
if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') exit;

$store_id = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
$customer_id = $_SESSION['user_id'];

if (!$store_id) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name 
    FROM store_chat_messages m
    JOIN users u ON (m.sender_type='customer' AND u.id=m.customer_id) OR (m.sender_type='owner' AND u.id=m.owner_id)
    WHERE m.store_id = ? AND m.customer_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$store_id, $customer_id]);
$messages = $stmt->fetchAll();

// Mark unread owner messages as read
$update = $pdo->prepare("UPDATE store_chat_messages SET is_read=1 WHERE store_id=? AND customer_id=? AND sender_type='owner' AND is_read=0");
$update->execute([$store_id, $customer_id]);

header('Content-Type: application/json');
echo json_encode($messages);
?>
