<?php
// ==============================================
// VIEWS/CART.PHP — with multi-store warning & correct delivery fees
// Currency: Rs. (Rupees)
// ==============================================

// Remove item
if (isset($_GET['remove'])) {
    $item_id = $_GET['remove'];
    if (isset($_SESSION['cart'][$item_id])) {
        unset($_SESSION['cart'][$item_id]);
        $_SESSION['success'] = "Item removed from cart.";
    } else {
        $_SESSION['error'] = "Item not found in cart.";
    }
    redirect('index.php?page=cart');
}

// Update quantities
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $item_id => $qty) {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$item_id]);
        } else {
            $_SESSION['cart'][$item_id]['quantity'] = $qty;
        }
    }
    $_SESSION['success'] = "Cart updated.";
    redirect('index.php?page=cart');
}

// Redirect if empty
if (empty($_SESSION['cart'])) {
    $_SESSION['error'] = "Your cart is empty. Please add items before checkout.";
    redirect('index.php?page=store_list');
}

// ──────────────────────────────────────────────
// Group cart items by store and calculate totals
// ──────────────────────────────────────────────
$cartItems = $_SESSION['cart'];

// Helper: get delivery fee for a store (replace with your own logic)
function getStoreDeliveryFee($storeId) {
    // Example: fetch from database or use a mapping
    // For now, assume all stores have Rs. 120 delivery fee
    return 120;
}

// Group items by store_id
$grouped = [];
foreach ($cartItems as $id => $item) {
    $storeId = $item['store_id'];
    if (!isset($grouped[$storeId])) {
        $grouped[$storeId] = [];
    }
    $grouped[$storeId][$id] = $item;
}

// Calculate per-store subtotal, delivery, and overall totals
$subtotal = 0;
$totalDelivery = 0;
$storeBreakdown = []; // for optional display

foreach ($grouped as $storeId => $items) {
    $storeSubtotal = 0;
    foreach ($items as $item) {
        $storeSubtotal += $item['price'] * $item['quantity'];
    }
    $subtotal += $storeSubtotal;
    
    $storeDelivery = getStoreDeliveryFee($storeId);
    $totalDelivery += $storeDelivery;
    
    // ─── Fetch store name ───
    $storeName = 'Store #' . $storeId; // fallback
    $stmt = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
    $stmt->execute([$storeId]);
    $row = $stmt->fetch();
    if ($row) {
        $storeName = $row['store_name'];
    }
    
    $storeBreakdown[] = [
        'store_id' => $storeId,
        'store_name' => $storeName,
        'subtotal' => $storeSubtotal,
        'delivery' => $storeDelivery
    ];
}

$grandTotal = $subtotal + $totalDelivery;

// Check for multiple stores and show warning
$storeIds = array_unique(array_column($cartItems, 'store_id'));
$multiStoreWarning = (count($storeIds) > 1);
?>

<div class="row g-4">
    <div class="col-lg-8">
        <?php if($multiStoreWarning): ?>
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                Your cart contains items from multiple stores. They will be processed as separate orders with individual delivery fees.
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-3 mb-4">
            <div>
                <div class="section-label">Review your items</div>
                <h2 style="font-family:var(--font-display);margin:0;">Shopping Cart</h2>
            </div>
            <span class="badge ms-auto" style="background:rgba(255,87,34,0.15);color:var(--primary);border:1px solid rgba(255,87,34,0.3);font-size:0.85rem;padding:0.5em 1em;">
                <?php echo count($cartItems); ?> item<?php echo count($cartItems)!==1?'s':''; ?>
            </span>
        </div>

        <?php if(empty($cartItems)): ?>
        <div class="card" style="border-radius:var(--radius-xl);overflow:hidden;">
            <div style="height:200px;position:relative;background: linear-gradient(135deg, #2c3e50, #1a1a2e);">
                <div style="position:absolute;inset:0;background:rgba(13,12,18,0.6);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:2rem;">
                    <div style="font-size:3rem;margin-bottom:0.75rem;">🛒</div>
                    <h4 style="font-family:var(--font-display);margin-bottom:0.4rem;">Your cart is empty</h4>
                    <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:1.5rem;">Browse our restaurants and add something delicious.</p>
                    <a href="index.php?page=store_list" class="btn btn-primary px-5">Browse Restaurants</a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <form method="POST">
            <div class="d-flex flex-column gap-3">
                <?php foreach($cartItems as $id => $item):
                    $imgUrl = !empty($item['image']) ? $item['image'] : 'assets/images/placeholder.PNG';
                ?>
                <div class="card" style="border-radius:var(--radius-md);transition:var(--transition);overflow:hidden;" onmouseenter="this.style.borderColor='rgba(255,87,34,0.25)'" onmouseleave="this.style.borderColor='var(--border)'">
                    <div class="card-body" style="padding:0;display:flex;align-items:stretch;">
                        <div style="width:90px;flex-shrink:0;overflow:hidden;">
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 style="width:100%;height:100%;object-fit:cover;min-height:80px;">
                        </div>
                        <div style="flex:1;padding:0.9rem 1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                            <div style="flex:1;min-width:120px;">
                                <h6 style="font-weight:700;margin-bottom:0.2rem;"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <div style="color:var(--text-muted);font-size:0.8rem;">Rs. <?php echo number_format($item['price'],2); ?> each</div>
                            </div>
                            <div>
                                <input type="number" name="quantity[<?php echo $id; ?>]" value="<?php echo $item['quantity']; ?>" min="0" max="20"
                                       class="form-control form-control-sm" style="width:58px;text-align:center;">
                            </div>
                            <div style="font-weight:700;">Rs. <?php echo number_format($item['price']*$item['quantity'],2); ?></div>
                            <a href="index.php?page=cart&remove=<?php echo $id; ?>" onclick="return confirm('Remove this item?')"
                               class="btn btn-danger btn-sm" style="width:30px;height:30px;padding:0;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" name="update_cart" class="btn btn-secondary"><i class="fas fa-rotate me-2"></i>Update Cart</button>
                <a href="index.php?page=store_list" class="btn btn-outline-secondary"><i class="fas fa-plus me-2"></i>Add More</a>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Order Summary -->
    <div class="col-lg-4">
        <div class="card" style="border-radius:var(--radius-xl);position:sticky;top:90px;border-color:rgba(255,87,34,0.2);overflow:hidden;">
            <div style="height:100px;position:relative;background: linear-gradient(135deg, #1a1a2e, #16213e);">
                <div style="position:absolute;inset:0;background:rgba(13,12,18,0.5);display:flex;align-items:center;padding:0 1.25rem;">
                    <h5 style="margin:0;font-family:var(--font-display);">Order Summary</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <span>Rs. <?php echo number_format($subtotal,2); ?></span>
                </div>

                <?php if($multiStoreWarning): ?>
                    <!-- Show delivery fees per store -->
                    <div class="mb-2">
                        <small class="text-muted">Delivery (per store):</small>
                        <?php foreach($storeBreakdown as $store): ?>
                            <div class="d-flex justify-content-between small">
                                <span><?php echo htmlspecialchars($store['store_name']); ?></span>
                                <span>Rs. <?php echo number_format($store['delivery'],2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                        <span>Total Delivery</span>
                        <span>Rs. <?php echo number_format($totalDelivery,2); ?></span>
                    </div>
                <?php else: ?>
                    <!-- Single store: simple line -->
                    <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                        <span>Delivery</span>
                        <span>Rs. <?php echo number_format($totalDelivery,2); ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-4">
                    <strong>Total</strong>
                    <strong style="color:var(--primary);">Rs. <?php echo number_format($grandTotal,2); ?></strong>
                </div>
                <a href="index.php?page=checkout" class="btn btn-success w-100 py-3">Proceed to Checkout</a>
                <div class="d-flex justify-content-center gap-4 mt-3">
                    <small><i class="fas fa-lock text-success me-1"></i>Secure</small>
                    <small><i class="fas fa-shield-alt text-primary me-1"></i>Protected</small>
                    <small><i class="fas fa-undo text-warning me-1"></i>Returns</small>
                </div>
            </div>
        </div>
    </div>
</div>