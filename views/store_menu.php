<?php
// ==============================================
// VIEWS/STORE_MENU.PHP — Dynamic cover & logo + offline blocking
// ==============================================

$storeId = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if(!$store) { echo "<div class='alert alert-danger'>Store not found!</div>"; exit; }

// Check if store is offline
$isOffline = empty($store['is_online']); // is_online = 0 means offline

// Helper: fetch active coupons for this store
function getStoreActiveCoupons($pdo, $storeId) {
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE store_id = ? 
          AND is_active = 1 
          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY expiry_date ASC
    ");
    $stmt->execute([$storeId]);
    return $stmt->fetchAll();
}
$availableCoupons = getStoreActiveCoupons($pdo, $storeId);

if(isset($_POST['add_to_cart'])) {
    // Prevent adding to cart if store offline
    if ($isOffline) {
        $_SESSION['error'] = "This store is offline – cannot place orders.";
        redirect("index.php?page=store_menu&id=$storeId");
    }
    $item_id = $_POST['item_id'];
    $quantity = $_POST['quantity'];
    if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    if(isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id]['quantity'] += $quantity;
    } else {
        $stmt2 = $pdo->prepare("SELECT * FROM store_items WHERE id = ?");
        $stmt2->execute([$item_id]);
        $item = $stmt2->fetch();
        $_SESSION['cart'][$item_id] = [
            'name'=>$item['name'],
            'price'=>$item['price'],
            'quantity'=>$quantity,
            'store_id'=>$storeId,
            'image'=>$item['image']
        ];
    }
    $_SESSION['success'] = "Added to cart!";
    redirect("index.php?page=store_menu&id=$storeId");
}

$items = $pdo->prepare("SELECT * FROM store_items WHERE store_id = ? AND is_available = 1");
$items->execute([$storeId]);
$menuItems = $items->fetchAll();
$categories = [];
foreach($menuItems as $item) { $categories[$item['category']][] = $item; }

$cartTotal = 0;
if(isset($_SESSION['cart'])) { foreach($_SESSION['cart'] as $ci) { if($ci['store_id']==$storeId) $cartTotal += $ci['price']*$ci['quantity']; } }

// Dynamic cover image (uploaded > Unsplash)
$heroImage = !empty($store['cover_image']) 
    ? $store['cover_image'] 
    : ($store['store_type'] === 'grocery'
        ? 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=1200&q=80'
        : 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=80');

// Dynamic logo (uploaded > emoji)
$logoHtml = '';
if (!empty($store['logo'])) {
    $logoHtml = '<img src="' . htmlspecialchars($store['logo']) . '" alt="Logo" style="width:70px;height:70px;border-radius:var(--radius-md);object-fit:cover;border:2px solid rgba(255,255,255,0.15);">';
} else {
    $logoHtml = '<div style="width:70px;height:70px;border-radius:var(--radius-md);background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:2.2rem;border:2px solid rgba(255,255,255,0.15);flex-shrink:0;backdrop-filter:blur(8px);">' . ($store['store_type']==='grocery' ? '🛒' : '🍽️') . '</div>';
}

$placeholderImg = 'assets/images/placeholder.PNG';
?>

<!-- Store Hero Banner (height: 375px, cover with top focus) -->
<div style="position:relative;border-radius:var(--radius-xl);overflow:hidden;margin-bottom:2rem;height:375px;">
    <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="<?php echo htmlspecialchars($store['store_name']); ?>"
         style="width:100%;height:100%;object-fit:cover;object-position:center top;">
    <div style="position:absolute;inset:0;background:linear-gradient(105deg,rgba(13,12,18,0.95) 0%,rgba(13,12,18,0.7) 55%,rgba(13,12,18,0.15) 100%);display:flex;align-items:center;padding:2rem 2.5rem;gap:1.5rem;">
        <?php echo $logoHtml; ?>
        <div>
            <div class="section-label">Menu</div>
            <h1 style="font-family:var(--font-display);font-size:clamp(1.5rem,3.5vw,2.2rem);margin-bottom:0.25rem;"><?php echo htmlspecialchars($store['store_name']); ?></h1>
            <p style="color:rgba(245,240,235,0.65);margin:0;font-size:0.9rem;">
                <i class="fas fa-map-marker-alt me-1" style="color:var(--primary);"></i>
                <?php echo htmlspecialchars($store['location']); ?>
                <?php if($store['description']): ?>
                &nbsp;·&nbsp; <?php echo htmlspecialchars($store['description']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<!-- Offline warning banner -->
<?php if ($isOffline): ?>
<div class="alert alert-warning text-center mb-3">
    <i class="fas fa-store-slash me-2"></i> <strong>This store is currently offline</strong> – you can browse the menu but cannot place orders.
</div>
<?php endif; ?>

<?php if(isLoggedIn() && $_SESSION['user_role'] == 'customer'): ?>
<div class="mb-3">
    <button class="btn btn-outline-info" id="startChatBtn" data-store-id="<?php echo $store['id']; ?>" data-store-name="<?php echo htmlspecialchars($store['store_name']); ?>">
        <i class="fas fa-comment-dots"></i> Chat with this Store
    </button>
</div>
<?php endif; ?>

<!-- Coupon Section -->
<?php if (!empty($availableCoupons)): ?>
<div class="card mb-4" style="border-left: 4px solid var(--primary); background: rgba(255,87,34,0.05); border-radius: var(--radius-lg);">
    <div class="card-body">
        <h5><i class="fas fa-tag me-2 text-primary"></i> Today’s Offers</h5>
        <div class="row">
            <?php foreach ($availableCoupons as $coupon): ?>
            <div class="col-md-6 mb-2">
                <div class="d-flex justify-content-between align-items-center p-3 border rounded bg-white bg-opacity-10" style="border-color: rgba(255,255,255,0.1) !important;">
                    <div>
                        <strong class="text-primary"><?php echo htmlspecialchars($coupon['code']); ?></strong><br>
                        <small>
                            <?php if ($coupon['discount_type'] == 'percentage'): ?>
                                <?php echo $coupon['discount_value']; ?>% OFF
                            <?php else: ?>
                                Rs. <?php echo number_format($coupon['discount_value'], 2); ?> OFF
                            <?php endif; ?>
                            <?php if ($coupon['min_order_amount'] > 0): ?>
                                <br>Min order: Rs. <?php echo number_format($coupon['min_order_amount'], 2); ?>
                            <?php endif; ?>
                            <?php if ($coupon['expiry_date']): ?>
                                <br><span class="text-muted">Valid until <?php echo date('M j, Y', strtotime($coupon['expiry_date'])); ?></span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary copy-coupon" data-code="<?php echo $coupon['code']; ?>">
                        Copy Code
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <small class="text-muted"><i class="fas fa-info-circle"></i> Use code at checkout to save.</small>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- MENU -->
    <div class="col-lg-8">
        <?php if(empty($categories)): ?>
        <div style="text-align:center;padding:4rem 2rem;">
            <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400&q=80" alt="Empty menu"
                 style="width:180px;height:130px;object-fit:cover;border-radius:var(--radius-lg);margin-bottom:1.25rem;opacity:0.5;">
            <h5 style="font-family:var(--font-display);">Menu Coming Soon</h5>
            <p style="color:var(--text-secondary);">This store hasn't added menu items yet.</p>
        </div>
        <?php endif; ?>

        <?php foreach($categories as $cat => $items): ?>
        <div class="mb-5">
            <div class="d-flex align-items-center gap-3 mb-3">
                <h4 style="font-family:var(--font-display);margin:0;"><?php echo ucfirst($cat); ?></h4>
                <div style="flex:1;height:1px;background:var(--border);"></div>
                <span class="badge" style="background:rgba(255,255,255,0.06);color:var(--text-secondary);border:1px solid var(--border);"><?php echo count($items); ?> items</span>
            </div>
            <div class="row g-3">
                <?php foreach($items as $item):
                    $imgUrl = !empty($item['image']) ? $item['image'] : $placeholderImg;
                ?>
                <div class="col-md-6">
                    <div class="card" style="border-radius:var(--radius-md);transition:var(--transition);overflow:hidden;" onmouseenter="this.style.borderColor='rgba(255,87,34,0.35)'" onmouseleave="this.style.borderColor='var(--border)'">
                        <div style="display:flex;gap:0;">
                            <div style="width:100px;flex-shrink:0;overflow:hidden;position:relative;">
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     style="width:100%;height:100%;object-fit:cover;min-height:110px;transition:transform 0.4s ease;"
                                     onmouseenter="this.style.transform='scale(1.07)'"
                                     onmouseleave="this.style.transform='scale(1)'">
                            </div>
                            <div style="flex:1;padding:0.9rem 0.9rem 0.9rem 1rem;display:flex;flex-direction:column;justify-content:space-between;">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
                                        <h6 style="font-weight:700;margin:0;font-size:0.9rem;"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <div style="color:var(--primary);font-weight:700;font-size:0.95rem;">Rs. <?php echo number_format($item['price'],2); ?></div>
                                    </div>
                                    <?php if($item['description']): ?>
                                    <p style="color:var(--text-muted);font-size:0.76rem;margin:0 0 0.5rem;"><?php echo htmlspecialchars(substr($item['description'],0,70)).(strlen($item['description'])>70?'…':''); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if(isLoggedIn() && $_SESSION['user_role'] == 'customer'): ?>
                                    <?php if ($isOffline): ?>
                                        <div class="alert alert-warning text-center mt-2 mb-0" style="padding:0.2rem;font-size:0.75rem;">
                                            <i class="fas fa-store-slash"></i> Offline – can't order
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <input type="number" name="quantity" value="1" min="1" max="20" class="form-control form-control-sm" style="width:52px;text-align:center;">
                                            <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-plus me-1"></i> Add</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif(!isLoggedIn()): ?>
                                    <a href="index.php?page=login" class="btn btn-outline-secondary btn-sm">Login to Order</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- SIDEBAR (Reviews unchanged) -->
    <div class="col-lg-4">
        <div class="card" style="border-radius:var(--radius-lg);">
            <div style="height:70px;position:relative;overflow:hidden;">
                <img src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=600&q=80" alt="Reviews"
                     style="width:100%;height:100%;object-fit:cover;object-position:center 40%;">
                <div style="position:absolute;inset:0;background:rgba(13,12,18,0.8);display:flex;align-items:center;padding:0 1.25rem;">
                    <h5 style="margin:0;font-family:var(--font-display);">⭐ Reviews</h5>
                </div>
            </div>
            <div class="card-body">
                <?php
                $ratings = $pdo->prepare("SELECT r.*, u.name FROM ratings r JOIN users u ON r.user_id = u.id WHERE r.store_id = ? ORDER BY r.created_at DESC LIMIT 5");
                $ratings->execute([$storeId]);
                $reviews = $ratings->fetchAll();
                if(count($reviews) === 0): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;text-align:center;padding:0.75rem 0;">No reviews yet. Be the first!</p>
                <?php endif; ?>
                <?php foreach($reviews as $review): ?>
                <div style="border-bottom:1px solid var(--border);padding-bottom:0.85rem;margin-bottom:0.85rem;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span style="width:28px;height:28px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:white;"><?php echo strtoupper(substr($review['name'],0,1)); ?></span>
                        <strong><?php echo htmlspecialchars($review['name']); ?></strong>
                        <small class="ms-auto"><?php echo date('M j', strtotime($review['created_at'])); ?></small>
                    </div>
                    <div class="rating-stars"><?php echo str_repeat("★",$review['rating']).str_repeat("☆",5-$review['rating']); ?></div>
                    <?php if($review['comment']): ?>
                    <p style="font-size:0.82rem;margin-top:0.3rem;"><?php echo htmlspecialchars($review['comment']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if(isLoggedIn() && $_SESSION['user_role'] == 'customer'): ?>
                <div style="border-top:1px solid var(--border);padding-top:1rem;">
                    <h6>Leave a Review</h6>
                    <form method="POST" action="ajax/add_rating.php">
                        <input type="hidden" name="store_id" value="<?php echo $storeId; ?>">
                        <select name="rating" class="form-select form-select-sm mb-2">
                            <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                            <option value="4">⭐⭐⭐⭐ Good</option>
                            <option value="3">⭐⭐⭐ Average</option>
                            <option value="2">⭐⭐ Poor</option>
                            <option value="1">⭐ Terrible</option>
                        </select>
                        <textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="Your experience…"></textarea>
                        <button type="submit" class="btn btn-primary btn-sm w-100 mt-2">Submit Review</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Coupon copy functionality
document.querySelectorAll('.copy-coupon').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const code = this.getAttribute('data-code');
        navigator.clipboard.writeText(code).then(() => {
            const original = this.innerHTML;
            this.innerHTML = 'Copied!';
            setTimeout(() => this.innerHTML = original, 1500);
        }).catch(() => {
            alert('Press Ctrl+C to copy: ' + code);
        });
    });
});

// Existing chat script (unchanged)
$('#startChatBtn').click(function() {
    let storeId = $(this).data('store-id');
    let storeName = $(this).data('store-name');
    $('#chatPanel').show();
    currentStoreId = storeId;
    $('#chatStoreName').text(storeName);
    loadConversation(storeId);
    $('#chatStoreList').hide();
    $('#chatConversation').show();
    if (chatRefreshInterval) clearInterval(chatRefreshInterval);
    chatRefreshInterval = setInterval(() => {
        if ($('#chatConversation').is(':visible')) loadConversation(currentStoreId);
    }, 5000);
});
</script>