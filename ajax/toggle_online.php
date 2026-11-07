<?php
// ajax/toggle_online.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'owner') {
    header('Location: ../index.php?page=login');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_online'])) {
    $store_id = $_POST['store_id'];
    
    // Verify this store belongs to the owner
    $stmt = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND owner_id = ?");
    $stmt->execute([$store_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $toggle = $pdo->prepare("UPDATE stores SET is_online = NOT is_online WHERE id = ?");
        $toggle->execute([$store_id]);
    }
    
    // ✅ Redirect back to owner dashboard (Store Info tab)
    header('Location: ../index.php?page=owner_dashboard&tab=store_info');
    exit();
} else {
    header('Location: ../index.php?page=owner_dashboard&tab=store_info');
    exit();
}
?>