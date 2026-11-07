<?php
require_once '../config/database.php';
if (!isLoggedIn() || $_SESSION['user_role'] != 'owner') exit;

$store_id = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
$customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
$owner_id = $_SESSION['user_id'];

if (!$store_id || !$customer_id) {
    exit;
}

$storeStmt = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND owner_id = ? LIMIT 1");
$storeStmt->execute([$store_id, $owner_id]);
if (!$storeStmt->fetch()) {
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name
    FROM store_chat_messages m
    JOIN users u ON (m.sender_type='customer' AND u.id=m.customer_id) OR (m.sender_type='owner' AND u.id=m.owner_id)
    WHERE m.store_id = ? AND m.customer_id = ? AND m.owner_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$store_id, $customer_id, $owner_id]);
$messages = $stmt->fetchAll();

$update = $pdo->prepare("UPDATE store_chat_messages SET is_read=1 WHERE store_id=? AND customer_id=? AND owner_id=? AND sender_type='customer' AND is_read=0");
$update->execute([$store_id, $customer_id, $owner_id]);

foreach ($messages as $message) {
    $class = $message['sender_type'] === 'owner' ? 'owner' : 'customer';
    $name = $message['sender_type'] === 'owner' ? 'You' : $message['sender_name'];
    echo '<div class="chat-msg-item ' . $class . '">';
    echo '<strong>' . htmlspecialchars($name) . '</strong>';
    echo '<div>' . nl2br(htmlspecialchars($message['message'])) . '</div>';
    echo '<small class="text-muted">' . htmlspecialchars(date('M j, g:i A', strtotime($message['created_at']))) . '</small>';
    echo '</div>';
}
?>
