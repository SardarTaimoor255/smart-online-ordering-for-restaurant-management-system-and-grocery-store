<?php
// views/jazzcash_simulator.php
if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') {
    redirect('index.php?page=login');
}
if (!isset($_SESSION['jazzcash_order_data'])) {
    redirect('index.php?page=cart');
}

$orderData = $_SESSION['jazzcash_order_data'];
$storeData = $orderData['store_data'];               // pre‑calculated per store
$pointsToRedeem = $orderData['points_to_redeem'] ?? 0;

// Calculate grand total from storeData
$grandTotal = 0;
foreach ($storeData as $store) {
    $grandTotal += $store['final_total'];
}
?>

<div class="container py-5">
    <div class="card shadow-lg mx-auto" style="max-width: 500px;">
        <div class="card-header bg-primary text-white text-center">
            <h3><i class="fas fa-mobile-alt me-2"></i>JazzCash Checkout</h3>
        </div>
        <div class="card-body">
            <p class="text-center mb-3">Amount to Pay: <strong style="font-size: 1.3rem;">Rs. <?php echo number_format($grandTotal, 2); ?></strong></p>
            <?php if ($pointsToRedeem > 0): ?>
                <div class="alert alert-info small">Points discount applied: -Rs. <?php echo number_format($pointsToRedeem, 2); ?></div>
            <?php endif; ?>
            <hr>
            <form method="POST" action="index.php?page=jazzcash_callback">
                <div class="mb-3">
                    <label>JazzCash Mobile Number</label>
                    <input type="tel" name="mobile_number" class="form-control" placeholder="03xxxxxxxxx" required pattern="^03[0-9]{9}$">
                    <small class="text-muted">Registered with JazzCash</small>
                </div>
                <div class="mb-3">
                    <label>JazzCash PIN</label>
                    <input type="password" name="pin" class="form-control" placeholder="Enter 4‑digit PIN" required pattern="[0-9]{4}" maxlength="4">
                </div>
                <button type="submit" name="confirm_payment" class="btn btn-success w-100 py-2">Confirm Payment</button>
            </form>
            <div class="text-center mt-3">
                <a href="index.php?page=cart" class="text-muted">Cancel</a>
            </div>
        </div>
    </div>
</div>