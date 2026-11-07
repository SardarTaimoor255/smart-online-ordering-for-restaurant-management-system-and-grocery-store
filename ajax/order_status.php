<?php
// ==============================================
// AJAX HANDLERS (ajax/ folder)
// ==============================================
// ajax/order_status.php
if(isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetch());
}
?>