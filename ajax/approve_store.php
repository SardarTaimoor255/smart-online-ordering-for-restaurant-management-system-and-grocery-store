<?php
require_once '../config/database.php';
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') exit('error');

$store_id = $_POST['store_id'];
$owner_id = $_POST['owner_id'];

$stmt = $pdo->prepare("UPDATE stores SET is_active = 1 WHERE id = ?");
if ($stmt->execute([$store_id])) {
    // Notify owner
    addNotification($pdo, $owner_id, "Store Approved", "Your store has been approved and is now live on SmartOrder!");
    echo 'success';
} else {
    echo 'error';
}
?>