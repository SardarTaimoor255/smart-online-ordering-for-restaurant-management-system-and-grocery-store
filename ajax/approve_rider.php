<?php
// ajax/approve_rider.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    echo 'error';
    exit();
}

$rider_id = $_POST['rider_id'] ?? 0;

if ($rider_id) {
    $stmt = $pdo->prepare("UPDATE users SET is_approved = 1, is_active = 1 WHERE id = ? AND role = 'rider'");
    $stmt->execute([$rider_id]);
    
    if ($stmt->rowCount() > 0) {
        // Notify the rider that their account is approved
        addNotification($pdo, $rider_id, "Account Approved", "Your rider account has been approved. You can now accept delivery requests.");
        echo 'success';
    } else {
        echo 'error';
    }
} else {
    echo 'error';
}
?>