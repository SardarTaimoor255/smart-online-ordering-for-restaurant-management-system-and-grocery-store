<?php
// ajax/toggle_rider_online.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'rider') {
    header('Location: ../index.php?page=login');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_online'])) {
    $rider_id = $_POST['rider_id'];
    
    // Verify the rider owns this session
    if ($rider_id == $_SESSION['user_id']) {
        $toggle = $pdo->prepare("UPDATE users SET is_online = NOT is_online WHERE id = ? AND role = 'rider'");
        $toggle->execute([$rider_id]);
    }
    
    // Redirect back to rider dashboard using relative path
    header('Location: ../index.php?page=rider_dashboard');
    exit();
} else {
    header('Location: ../index.php?page=rider_dashboard');
    exit();
}
?>