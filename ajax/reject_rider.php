<?php
// ajax/reject_rider.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    echo 'error';
    exit();
}

$rider_id = $_POST['rider_id'] ?? 0;

if ($rider_id) {
    // Option 1: Soft delete / deactivate
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, is_approved = 0 WHERE id = ? AND role = 'rider'");
    $stmt->execute([$rider_id]);
    
    // Option 2: Delete the user (uncomment if you prefer)
    // $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'rider'");
    
    if ($stmt->rowCount() > 0) {
        // Notify the rider that their account was rejected
        addNotification($pdo, $rider_id, "Account Rejected", "Your rider registration was rejected. Please contact support for more information.");
        echo 'success';
    } else {
        echo 'error';
    }
} else {
    echo 'error';
}
?>