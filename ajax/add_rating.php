<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("INSERT INTO ratings (user_id, store_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['store_id'], $_POST['rating'], $_POST['comment']]);
    $_SESSION['success'] = "Review added!";
    redirect($_SERVER['HTTP_REFERER']);
}
?>