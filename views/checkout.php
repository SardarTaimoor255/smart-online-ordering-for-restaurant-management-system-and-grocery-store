<?php
// ==============================================
// VIEWS/CHECKOUT.PHP — Multi-store order splitting + correct delivery fees
// Currency: Rs. (Rupees)
// ==============================================

if (!isLoggedIn()) {
    redirect('index.php?page=login');
}

if (empty($_SESSION['cart'])) {
    redirect('index.php?page=cart');
}

$errors = [];

// ──────────────────────────────────────────────
// Helper: get delivery fee for a store (consistent with cart.php)
// ──────────────────────────────────────────────
function getStoreDeliveryFee($storeId) {
    // In a real app, fetch from database or store settings
    // For now, same as cart.php – 120 Rs per store
    return 120;
}

// ──────────────────────────────────────────────
// 0. Fetch user's saved address for auto‑fill
// ──────────────────────────────────────────────
$userStmt = $pdo->prepare("SELECT address, points FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$userRow = $userStmt->fetch();
$savedAddress = $userRow['address'] ?? '';
$userPoints = $userRow['points'] ?? 0;
$_SESSION['user_points'] = $userPoints; // ensure session has latest

// ──────────────────────────────────────────────
// 1. Group cart items by store & calculate per-store subtotals
// ──────────────────────────────────────────────
$cartItems = $_SESSION['cart'];
$grouped = [];
foreach ($cartItems as $id => $item) {
    $storeId = $item['store_id'];
    if (!isset($grouped[$storeId])) {
        $grouped[$storeId] = [];
    }
    $grouped[$storeId][$id] = $item;
}

// ─── Fetch store names for all stores in the cart ───
$storeIds = array_keys($grouped);
$storeNames = [];
if (!empty($storeIds)) {
    $in = str_repeat('?,', count($storeIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, store_name FROM stores WHERE id IN ($in)");
    $stmt->execute($storeIds);
    while ($row = $stmt->fetch()) {
        $storeNames[$row['id']] = $row['store_name'];
    }
}

$storeSubtotals = [];
$overallSubtotal = 0;
foreach ($grouped as $storeId => $items) {
    $storeSub = 0;
    foreach ($items as $item) {
        $storeSub += $item['price'] * $item['quantity'];
    }
    $storeSubtotals[$storeId] = $storeSub;
    $overallSubtotal += $storeSub;
}

// ──────────────────────────────────────────────
// 2. Apply coupon discount (overall, then split proportionally)
// ──────────────────────────────────────────────
$couponDiscountTotal = 0;
$coupon = $_SESSION['coupon'] ?? null;
if ($coupon) {
    if ($coupon['discount_type'] == 'percentage') {
        $couponDiscountTotal = $overallSubtotal * ($coupon['discount_value'] / 100);
    } else {
        $couponDiscountTotal = $coupon['discount_value'];
    }
}

// ──────────────────────────────────────────────
// 3. Apply loyalty points discount (overall, split proportionally)
// ──────────────────────────────────────────────
$pointsDiscountTotal = 0;
if (isset($_POST['redeem_points']) && $userPoints >= 100) {
    // Simulate applying points – real logic in order processing
    $pointsDiscountTotal = applyLoyaltyDiscount($userPoints, $overallSubtotal - $couponDiscountTotal);
    $_SESSION['points_to_redeem'] = $pointsDiscountTotal;
}
if (isset($_SESSION['points_to_redeem'])) {
    $pointsDiscountTotal = $_SESSION['points_to_redeem'];
}

$finalOverallTotal = $overallSubtotal - $couponDiscountTotal - $pointsDiscountTotal;

// ──────────────────────────────────────────────
// 4. Calculate per‑store delivery fees & final totals
// ──────────────────────────────────────────────
$storeData = []; // will hold subtotal, delivery, discount share, points share, final total
$totalDelivery = 0;

foreach ($grouped as $storeId => $items) {
    $storeSub = $storeSubtotals[$storeId];
    
    // Proportion of this store's subtotal to overall
    $proportion = ($overallSubtotal > 0) ? $storeSub / $overallSubtotal : 0;
    
    $storeCouponShare = $couponDiscountTotal * $proportion;
    $storePointsShare = $pointsDiscountTotal * $proportion;
    
    $storeDelivery = getStoreDeliveryFee($storeId);
    $totalDelivery += $storeDelivery;
    
    $storeFinal = $storeSub - $storeCouponShare - $storePointsShare + $storeDelivery;
    
    $storeData[$storeId] = [
        'items' => $items,
        'subtotal' => $storeSub,
        'delivery' => $storeDelivery,
        'coupon_share' => $storeCouponShare,
        'points_share' => $storePointsShare,
        'final_total' => $storeFinal,
        'proportion' => $proportion,
        'store_name' => $storeNames[$storeId] ?? 'Store #' . $storeId
    ];
}

$grandTotal = $overallSubtotal - $couponDiscountTotal - $pointsDiscountTotal + $totalDelivery;

// ──────────────────────────────────────────────
// 5. Handle coupon application (POST)
// ──────────────────────────────────────────────
if (isset($_POST['apply_coupon'])) {
    $code = trim($_POST['coupon_code']);
    if (empty($code)) {
        $_SESSION['error'] = "Please enter a coupon code.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();
        if ($coupon && $overallSubtotal >= $coupon['min_order_amount']) {
            $_SESSION['coupon'] = $coupon;
            $_SESSION['success'] = "Coupon applied!";
        } else {
            $_SESSION['error'] = "Invalid coupon or does not meet minimum order amount.";
        }
    }
    redirect('index.php?page=checkout');
}

// ──────────────────────────────────────────────
// 6. Handle order submission with validation & multi-store splitting
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];

    // Address validation
    if (empty($address)) {
        $errors['address'] = "Delivery address is required.";
    } elseif (strlen($address) < 10) {
        $errors['address'] = "Please enter a complete address (min 10 characters).";
    } elseif (strlen($address) > 500) {
        $errors['address'] = "Address is too long (max 500 characters).";
    }

    // Payment method validation
    $allowed_payments = ['cod', 'jazzcash', 'easypaisa', 'card'];
    if (!in_array($payment_method, $allowed_payments)) {
        $errors['payment'] = "Invalid payment method selected.";
    }

    if (!empty($errors)) {
        // errors will be displayed in the form
    } else {
        // ──────────────────────────────────────────
        // EasyPaisa simulator redirect – store all necessary data
        // ──────────────────────────────────────────
        if ($payment_method == 'easypaisa') {
            $_SESSION['easypaisa_order_data'] = [
                'address' => $address,
                'payment_method' => $payment_method,
                'store_data' => $storeData,      // full per-store breakdown
                'coupon' => $_SESSION['coupon'] ?? null,
                'points_to_redeem' => $pointsDiscountTotal,
                'user_points' => $userPoints
            ];
            redirect('index.php?page=easypaisa_simulator');
        }
        // ──────────────────────────────────────────
        // JazzCash simulator redirect
        // ──────────────────────────────────────────
        elseif ($payment_method == 'jazzcash') {
            $_SESSION['jazzcash_order_data'] = [
                'address' => $address,
                'payment_method' => $payment_method,
                'store_data' => $storeData,
                'coupon' => $_SESSION['coupon'] ?? null,
                'points_to_redeem' => $pointsDiscountTotal,
                'user_points' => $userPoints
            ];
            redirect('index.php?page=jazzcash_simulator');
        }

        // ──────────────────────────────────────────
        // For COD and Card: process orders directly
        // ──────────────────────────────────────────
        $pdo->beginTransaction();
        try {
            $allOrderIds = [];
            $totalPointsEarned = 0;
            $totalPointsUsed = $pointsDiscountTotal * 100; // convert Rs to points (assuming 1 point = Rs.0.01? adjust as needed)
            $totalDiscountAmount = $couponDiscountTotal + $pointsDiscountTotal;

            // Get rider percentage from settings
            $riderPercent = 80;
            try {
                $riderPercent = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='rider_percentage'")->fetchColumn();
                if ($riderPercent === false) $riderPercent = 80;
                $riderPercent = floatval($riderPercent);
            } catch (PDOException $e) {
                $riderPercent = 80;
            }

            foreach ($storeData as $storeId => $data) {
                // Get store commission info
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

                // Insert order for this store (status = 'awaiting_rider')
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
                // Create rider assignment request instead of direct assignment
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
            $newPoints = $userPoints - $totalPointsUsed + $totalPointsEarned;
            $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
            $stmt->execute([$newPoints, $_SESSION['user_id']]);

            // Clear cart, coupon, points redemption
            unset($_SESSION['cart']);
            unset($_SESSION['coupon']);
            unset($_SESSION['points_to_redeem']);

            // Update saved address if changed
            if ($address != $savedAddress) {
                $updateAddr = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
                $updateAddr->execute([$address, $_SESSION['user_id']]);
            }

            // Add notification
            $orderIdsStr = implode(', ', $allOrderIds);
            addNotification($pdo, $_SESSION['user_id'], "Orders Placed", "Your orders #$orderIdsStr have been placed successfully!");

            $pdo->commit();
            $_SESSION['success'] = "Orders placed successfully!";
            redirect("index.php?page=customer_dashboard");
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Order failed: " . $e->getMessage();
        }
    }
}
?>

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h4>Checkout</h4></div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo $err; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label>Delivery Address</label>
                        <textarea name="address" class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" rows="3" required><?php 
                            echo htmlspecialchars($_POST['address'] ?? $savedAddress); 
                        ?></textarea>
                        <?php if (isset($errors['address'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['address']; ?></div>
                        <?php endif; ?>
                        <small class="text-muted">Please provide a complete street address, city, and postal code.</small>
                    </div>

                    <div class="mb-3">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-select <?php echo isset($errors['payment']) ? 'is-invalid' : ''; ?>" required>
                            <option value="cod" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cod') ? 'selected' : ''; ?>>Cash on Delivery</option>
                            <option value="jazzcash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'jazzcash') ? 'selected' : ''; ?>>JazzCash</option>
                            <option value="easypaisa" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'easypaisa') ? 'selected' : ''; ?>>EasyPaisa</option>
                            <option value="card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'card') ? 'selected' : ''; ?>>Card Payment</option>
                        </select>
                        <?php if (isset($errors['payment'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['payment']; ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="place_order" class="btn btn-success btn-lg w-100">Place Order</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h4>Order Summary</h4></div>
            <div class="card-body">
                <?php if (count($storeData) > 1): ?>
                    <!-- Multi-store breakdown -->
                    <div class="mb-3">
                        <strong>Items subtotal:</strong> Rs. <?php echo number_format($overallSubtotal, 2); ?>
                    </div>
                    <?php foreach ($storeData as $storeId => $data): ?>
                        <div class="small text-muted mb-1"><?php echo htmlspecialchars($data['store_name']); ?></div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Subtotal:</span>
                            <span>Rs. <?php echo number_format($data['subtotal'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Delivery:</span>
                            <span>Rs. <?php echo number_format($data['delivery'], 2); ?></span>
                        </div>
                        <?php if ($data['coupon_share'] > 0): ?>
                            <div class="d-flex justify-content-between text-success small">
                                <span>Coupon discount:</span>
                                <span>-Rs. <?php echo number_format($data['coupon_share'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($data['points_share'] > 0): ?>
                            <div class="d-flex justify-content-between text-success small">
                                <span>Points discount:</span>
                                <span>-Rs. <?php echo number_format($data['points_share'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between fw-bold mt-1 mb-3 pb-2 border-bottom">
                            <span>Store total:</span>
                            <span>Rs. <?php echo number_format($data['final_total'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between fw-bold mt-2">
                        <span>Grand Total:</span>
                        <span class="text-primary">Rs. <?php echo number_format($grandTotal, 2); ?></span>
                    </div>
                <?php else: 
                    // Single store – simple display
                    $single = reset($storeData);
                ?>
                    <p>Food Subtotal: Rs. <?php echo number_format($single['subtotal'], 2); ?></p>
                    <?php if ($couponDiscountTotal > 0): ?>
                        <p>Coupon Discount: -Rs. <?php echo number_format($couponDiscountTotal, 2); ?></p>
                    <?php endif; ?>
                    <?php if ($pointsDiscountTotal > 0): ?>
                        <p>Points Discount: -Rs. <?php echo number_format($pointsDiscountTotal, 2); ?></p>
                    <?php endif; ?>
                    <p>Delivery Fee: Rs. <?php echo number_format($single['delivery'], 2); ?></p>
                    <hr>
                    <h4>Total: Rs. <?php echo number_format($single['final_total'], 2); ?></h4>
                <?php endif; ?>

                <!-- Coupon & points forms -->
                <form method="POST" class="mt-3">
                    <div class="input-group">
                        <input type="text" name="coupon_code" class="form-control" placeholder="Coupon Code">
                        <button type="submit" name="apply_coupon" class="btn btn-outline-primary">Apply</button>
                    </div>
                </form>

                <form method="POST" class="mt-2">
                    <button type="submit" name="redeem_points" class="btn btn-warning btn-sm">Redeem Points (<?php echo $userPoints; ?> points available)</button>
                </form>

                <div class="mt-3 small text-muted">
                    <i class="fas fa-truck"></i> Delivery fees support our riders.
                </div>
            </div>
        </div>
    </div>
</div>