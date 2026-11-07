<?php
require_once '../config/database.php';
if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') exit;

$customer_id = $_SESSION['user_id'];

// Show every active restaurant and grocery store, plus the latest customer chat preview when one exists.
$stmt = $pdo->prepare("
    SELECT s.id, s.store_name, s.store_type,
           (SELECT message FROM store_chat_messages 
            WHERE store_id = s.id AND customer_id = ? 
            ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM store_chat_messages 
            WHERE store_id = s.id AND customer_id = ? 
            ORDER BY created_at DESC LIMIT 1) as last_time
    FROM stores s
    WHERE s.store_type IN ('restaurant', 'grocery')
      AND s.is_active = 1
    ORDER BY CASE WHEN last_time IS NULL THEN 1 ELSE 0 END, last_time DESC, s.store_type ASC, s.store_name ASC
");
$stmt->execute([$customer_id, $customer_id]);
$stores = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($stores);
?>
