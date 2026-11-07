<?php
require_once '../config/database.php';
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') exit('error');

$store_id = $_POST['store_id'];
$owner_id = $_POST['owner_id'];
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'No reason provided';

// Delete or mark as rejected (is_active = 2)
$stmt = $pdo->prepare("UPDATE stores SET is_active = 2, rejection_reason = ? WHERE id = ?");
if ($stmt->execute([$reason, $store_id])) {
    addNotification($pdo, $owner_id, "Store Rejected", "Your store request was rejected. Reason: $reason");
    echo 'success';
} else {
    echo 'error';
}
?>