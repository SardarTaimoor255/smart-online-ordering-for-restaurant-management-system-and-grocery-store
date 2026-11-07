<?php

require_once '../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'owner') {
    exit('error');
}

$store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
$customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$message = trim($_POST['message'] ?? '');

$owner_id = $_SESSION['user_id'];

if (!$store_id || !$customer_id || $message === '') {
    exit('error');
}

try {

    /*
    |--------------------------------------------------------------------------
    | Verify Store Ownership + Get Store Name
    |--------------------------------------------------------------------------
    */

    $storeStmt = $pdo->prepare("
        SELECT id, store_name
        FROM stores
        WHERE id = ? AND owner_id = ?
        LIMIT 1
    ");

    $storeStmt->execute([
        $store_id,
        $owner_id
    ]);

    $store = $storeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        exit('error');
    }

    $store_name = $store['store_name'];


    /*
    |--------------------------------------------------------------------------
    | Start Database Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Insert Owner Chat Message
    |--------------------------------------------------------------------------
    */

    $insertMessage = $pdo->prepare("
        INSERT INTO store_chat_messages
        (
            store_id,
            customer_id,
            owner_id,
            message,
            sender_type
        )
        VALUES (?, ?, ?, ?, 'owner')
    ");

    $messageResult = $insertMessage->execute([
        $store_id,
        $customer_id,
        $owner_id,
        $message
    ]);

    if (!$messageResult) {

        $pdo->rollBack();

        exit('error');
    }


    /*
    |--------------------------------------------------------------------------
    | Create Notification For Customer
    |--------------------------------------------------------------------------
    */

    $notificationTitle = "New Message from " . $store_name;

    $notificationMessage =
        $store_name .
        " replied to your message: " .
        mb_substr($message, 0, 100);


    $insertNotification = $pdo->prepare("
        INSERT INTO notifications
        (
            user_id,
            title,
            message,
            is_read,
            created_at
        )
        VALUES (?, ?, ?, 0, NOW())
    ");

    $notificationResult = $insertNotification->execute([
        $customer_id,
        $notificationTitle,
        $notificationMessage
    ]);

    if (!$notificationResult) {

        $pdo->rollBack();

        exit('error');
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Everything
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    echo 'success';

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | For debugging only
    |--------------------------------------------------------------------------
    */

    error_log("Owner Message Error: " . $e->getMessage());

    echo 'error';
}

?>