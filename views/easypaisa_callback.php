<?php
// views/easypaisa_callback.php
require_once __DIR__ . '/../bootstrap.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') {
    redirect('index.php?page=login');
}
if (!isset($_SESSION['easypaisa_order_data'])) {
    redirect('index.php?page=cart');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $mobile = trim($_POST['mobile_number']);
    $pin = $_POST['pin'];

    if (!preg_match('/^03[0-9]{9}$/', $mobile)) {
        $_SESSION['error'] = "Invalid mobile number format. Please use 03xxxxxxxxx.";
        redirect('index.php?page=easypaisa_simulator');
    }
    if (!preg_match('/^[0-9]{4}$/', $pin)) {
        $_SESSION['error'] = "PIN must be 4 digits.";
        redirect('index.php?page=easypaisa_simulator');
    }

    $orderData = $_SESSION['easypaisa_order_data'];
    $address = $orderData['address'];
    $payment_method = $orderData['payment_method'];
    $storeData = $orderData['store_data'];               // pre‑calculated per store
    $pointsToRedeem = $orderData['points_to_redeem'] ?? 0;
    $userPointsBefore = $orderData['user_points'] ?? 0;
    $savedCoupon = $orderData['coupon'] ?? null;

    $pdo->beginTransaction();
    try {
        $allOrderIds = [];
        $totalPointsEarned = 0;
        $totalPointsUsed = $pointsToRedeem * 100; // convert Rs to points

        $riderPercent = 80;
        try {
            $riderPercent = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='rider_percentage'")->fetchColumn();
            if ($riderPercent === false) $riderPercent = 80;
            $riderPercent = floatval($riderPercent);
        } catch (PDOException $e) {
            $riderPercent = 80;
        }

        foreach ($storeData as $storeId => $data) {
            // commission logic
            $stmt = $pdo->prepare("SELECT store_type, commission_rate FROM stores WHERE id = ?");
            $stmt->execute([$storeId]);
            $storeInfo = $stmt->fetch();
            $storeType = $storeInfo['store_type'];
            $commissionRate = $storeInfo['commission_rate'] ?? 0;

            $commissionAmount = 0;
            if ($storeType == 'grocery' && $commissionRate > 0) {
                $commissionAmount = ($data['subtotal'] - $data['coupon_share'] - $data['points_share']) * ($commissionRate / 100);
            }

            $restaurantEarnings = ($data['subtotal'] - $data['coupon_share'] - $data['points_share']) - $commissionAmount;
            $adminEarnings = ($data['delivery'] * (1 - $riderPercent / 100)) + $commissionAmount;
            $riderEarnings = $data['delivery'] * ($riderPercent / 100);
            $pointsEarnedStore = calculatePointsEarned($data['subtotal'] - $data['coupon_share'] - $data['points_share']);

            // Insert order – status = awaiting_rider (rider must accept)
            $stmt = $pdo->prepare("INSERT INTO orders (
                customer_id, store_id, total_amount, delivery_fee,
                restaurant_earnings, admin_earnings, rider_earnings, commission_amount,
                discount_amount, points_used, points_earned,
                status, payment_method, delivery_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'awaiting_rider', ?, ?)");

            $stmt->execute([
                $_SESSION['user_id'],
                $storeId,
                $data['final_total'],
                $data['delivery'],
                $restaurantEarnings,
                $adminEarnings,
                $riderEarnings,
                $commissionAmount,
                $data['coupon_share'] + $data['points_share'],
                $data['points_share'] * 100,
                $pointsEarnedStore,
                $payment_method,
                $address
            ]);

            $orderId = $pdo->lastInsertId();
            // Create pending request – rider must accept/reject
            createRiderAssignmentRequest($pdo, $orderId);
            $allOrderIds[] = $orderId;

            // Insert order items
            foreach ($data['items'] as $item_id => $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$orderId, $item_id, $item['quantity'], $item['price']]);
            }

            $totalPointsEarned += $pointsEarnedStore;
        }

        // Update user points
        $newPoints = $userPointsBefore - $totalPointsUsed + $totalPointsEarned;
        $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
        $stmt->execute([$newPoints, $_SESSION['user_id']]);

        // Cleanup session
        unset($_SESSION['cart']);
        unset($_SESSION['coupon']);
        unset($_SESSION['points_to_redeem']);
        unset($_SESSION['easypaisa_order_data']);

        // Update address if changed
        $userStmt = $pdo->prepare("SELECT address FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $savedAddress = $userStmt->fetchColumn();
        if ($address != $savedAddress) {
            $updateAddr = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
            $updateAddr->execute([$address, $_SESSION['user_id']]);
        }

        $orderIdsStr = implode(', ', $allOrderIds);
        addNotification($pdo, $_SESSION['user_id'], "Orders Placed", "Your orders #$orderIdsStr have been placed successfully! A rider will be assigned soon.");

        $pdo->commit();
        $_SESSION['success'] = "Payment successful! Orders placed. Waiting for rider acceptance.";
        redirect("index.php?page=customer_dashboard");

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Order failed: " . $e->getMessage();
        redirect('index.php?page=cart');
    }
} else {
    redirect('index.php?page=cart');
}
?>