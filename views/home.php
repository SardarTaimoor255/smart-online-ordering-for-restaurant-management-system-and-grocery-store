<?php
// Hero section background images
$heroImages = [
    'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=1200&q=80',
    'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=1200&q=80',
    'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1200&q=80',
];
$heroImg = $heroImages[array_rand($heroImages)];
?>

<!-- Hero Section -->
<div style="position:relative;border-radius:28px;overflow:hidden;margin-bottom:3rem;min-height:480px;display:flex;align-items:center;">
    <div style="position:absolute;inset:0;background-image:url('<?php echo $heroImg; ?>');background-size:cover;background-position:center;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(105deg,rgba(13,12,18,0.95) 0%,rgba(13,12,18,0.8) 50%,rgba(13,12,18,0.3) 100%);"></div>
    <div style="position:absolute;top:-80px;right:0;width:400px;height:400px;background:radial-gradient(circle,rgba(255,87,34,0.1),transparent 70%);pointer-events:none;"></div>

    <div style="position:relative;z-index:1;padding:3.5rem;max-width:640px;">
        <div class="section-label mb-3">🔥 Proudly Serving Mirpur AJK</div>
        <span class="urdu-tag fade-up fade-up-2" style="margin-bottom:1.25rem;">میرپور کا ذائقہ، آپ کے دروازے تک</span>
        <h1 class="display-heading" style="margin-bottom:1.25rem;">
            Taste the <span class="text-gradient">Best</span><br>of Your City
        </h1>
        <p style="font-size:1.1rem;color:rgba(245,240,235,0.75);max-width:460px;line-height:1.75;margin-bottom:2rem;">
            Order from local restaurants &amp; grocery stores. Every rupee goes directly to your neighbourhood businesses.
        </p>
        <div class="d-flex gap-3 flex-wrap">
            <a href="index.php?page=store_list" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-utensils me-2"></i>Browse Restaurants
            </a>
            <a href="index.php?page=store_list&type=grocery" class="btn btn-lg px-4" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.25);color:white;backdrop-filter:blur(8px);">
                <i class="fas fa-shopping-basket me-2"></i>Groceries
            </a>
        </div>
        <div class="d-flex gap-3 mt-4 flex-wrap">
            <div class="stat-pill"><i class="fas fa-store" style="color:var(--primary);"></i> 200+ Stores</div>
            <div class="stat-pill"><i class="fas fa-star" style="color:var(--accent);"></i> 4.8 Rating</div>
            <div class="stat-pill"><i class="fas fa-bolt" style="color:var(--success);"></i> Fast Delivery</div>
        </div>
    </div>
</div>

<!-- Food Categories -->
<div style="margin-bottom:3.5rem;">
    <div class="text-center mb-4">
        <div class="section-label">Explore by Category</div>
        <h2 style="font-family:var(--font-display);">What Are You Craving?</h2>
    </div>
    <div class="row g-3">
        <?php
        $categories = [
            ['Pizza','https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=400&q=80','?page=store_list&category=fast+food','#FF5722'],
            ['Burgers','https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=80','?page=store_list&category=fast+food','#FF8C42'],
            ['Desi Food','https://images.unsplash.com/photo-1567188040759-fb8a883dc6d8?w=400&q=80','?page=store_list&category=desi','#E64A19'],
            ['Chinese','https://images.unsplash.com/photo-1552611052-33e04de081de?w=400&q=80','?page=store_list&category=chinese','#c0392b'],
            ['Groceries','https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&q=80','?page=store_list&type=grocery','#10B981'],
            ['Fresh Salads','https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400&q=80','?page=store_list&category=vegetables','#059669'],
        ];
        foreach($categories as $i=>$cat): ?>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="index.php<?php echo $cat[2]; ?>" style="text-decoration:none;display:block;">
                <div class="card card-hover" style="border-radius:var(--radius-lg);overflow:hidden;animation-delay:<?php echo $i*0.07; ?>s;animation:fadeInUp 0.5s ease both;">
                    <div style="height:100px;position:relative;overflow:hidden;">
                        <img src="<?php echo $cat[1]; ?>" alt="<?php echo $cat[0]; ?>"
                             style="width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease;"
                             onmouseenter="this.style.transform='scale(1.08)'"
                             onmouseleave="this.style.transform='scale(1)'">
                        <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(13,12,18,0.7),transparent);"></div>
                    </div>
                    <div style="padding:0.6rem 0.75rem;text-align:center;">
                        <div style="font-weight:700;font-size:0.85rem;"><?php echo $cat[0]; ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========== TODAY'S OFFERS ========== -->
<?php
$offersStmt = $pdo->prepare("
    SELECT c.*, s.store_name, s.id as store_id
    FROM coupons c
    JOIN stores s ON c.store_id = s.id
    WHERE c.is_active = 1
      AND (c.expiry_date IS NULL OR c.expiry_date >= CURDATE())
      AND s.is_active = 1
    ORDER BY c.discount_value DESC
    LIMIT 4
");
$offersStmt->execute();
$offers = $offersStmt->fetchAll();

if (count($offers) > 0):
?>
<div class="mb-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <div class="section-label">🎉 Today's Offers</div>
            <h2 style="font-family:var(--font-display);margin:0;">Exclusive Deals</h2>
        </div>
        <a href="index.php?page=store_list" class="btn btn-outline-primary btn-sm">View All</a>
    </div>
    <div class="row g-3">
        <?php foreach ($offers as $offer): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card" style="border-radius:var(--radius-lg);overflow:hidden;background:rgba(255,87,34,0.05);border:1px solid rgba(255,87,34,0.12);">
                <div class="card-body text-center" style="padding:1.2rem;">
                    <div style="font-size:2rem;font-weight:800;color:var(--primary);">
                        <?php if ($offer['discount_type'] == 'percentage'): ?>
                            <?php echo $offer['discount_value']; ?>%
                        <?php else: ?>
                            Rs. <?php echo number_format($offer['discount_value'], 2); ?>
                        <?php endif; ?>
                        <span style="font-size:0.8rem;color:var(--text-muted);font-weight:400;">OFF</span>
                    </div>
                    <div style="font-weight:600;margin:0.2rem 0 0.1rem;"><?php echo htmlspecialchars($offer['store_name']); ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">
                        Code: <strong><?php echo htmlspecialchars($offer['code']); ?></strong>
                        <?php if ($offer['min_order_amount'] > 0): ?>
                            <br>Min order: Rs. <?php echo number_format($offer['min_order_amount'], 2); ?>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-sm btn-outline-primary mt-2 copy-code-btn" data-code="<?php echo $offer['code']; ?>">
                        <i class="fas fa-copy"></i> Copy Code
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Key Features Row -->
<div class="row g-4 mb-5">
    <?php
    $features = [
        ['🍽️','Local Restaurants','https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&q=80','Authentic flavors from your neighbourhood, delivered fresh.','var(--primary)'],
        ['🛒','Grocery Stores','https://images.unsplash.com/photo-1542838132-92c53300491e?w=600&q=80','Fresh groceries at your door — produce, dairy, essentials.','var(--success)'],
        ['📱','QR Smart Menus','https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&q=80','Scan a QR code at any table. Order contactless, instantly.','#3ECFCF'],
        ['🏆','Loyalty Rewards','https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&q=80','Earn points on every order and redeem for discounts.','var(--accent)'],
    ];
    foreach($features as $i=>$f): ?>
    <div class="col-md-6 col-lg-3">
        <div class="card card-hover h-100" style="border-radius:var(--radius-lg);overflow:hidden;animation-delay:<?php echo $i*0.1; ?>s;animation:fadeInUp 0.5s ease both;">
            <div style="height:120px;overflow:hidden;position:relative;">
                <img src="<?php echo $f[2]; ?>" alt="<?php echo $f[1]; ?>"
                     style="width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease;"
                     onmouseenter="this.style.transform='scale(1.06)'"
                     onmouseleave="this.style.transform='scale(1)'">
                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(13,12,18,0.6),transparent);"></div>
                <div style="position:absolute;top:0.75rem;left:0.75rem;font-size:1.5rem;"><?php echo $f[0]; ?></div>
            </div>
            <div class="card-body text-center py-3">
                <h5 style="font-family:var(--font-display);margin-bottom:0.5rem;font-size:1rem;"><?php echo $f[1]; ?></h5>
                <p style="color:var(--text-secondary);font-size:0.83rem;line-height:1.6;margin:0;"><?php echo $f[3]; ?></p>
                <div style="width:28px;height:3px;background:<?php echo $f[4]; ?>;border-radius:2px;margin:0.9rem auto 0;"></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ========== TOP RATED RESTAURANTS (LIMIT 4) – both restaurants & grocery ========== -->
<?php
// Fetch top rated stores (both restaurants and grocery)
$topRatedStmt = $pdo->prepare("
    SELECT s.id, s.store_name, s.cover_image, s.location, s.store_type,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM stores s
    JOIN ratings r ON s.id = r.store_id
    WHERE s.is_active = 1 AND s.is_online = 1
    GROUP BY s.id
    HAVING avg_rating >= 4.0
    ORDER BY avg_rating DESC, review_count DESC
    LIMIT 4
");
$topRatedStmt->execute();
$topRated = $topRatedStmt->fetchAll();

if (count($topRated) > 0):
?>
<div class="mb-5">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="section-label">⭐ Top Rated</div>
            <h2 style="font-family:var(--font-display);margin:0;">Top Rated Stores</h2>
        </div>
        <a href="index.php?page=store_list" class="btn btn-outline-primary btn-sm">View All</a>
    </div>
    <div class="row g-4">
        <?php foreach ($topRated as $store): 
            $cover = !empty($store['cover_image']) ? $store['cover_image'] : 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=400&q=80';
            $rating = round($store['avg_rating'], 1);
            $typeLabel = ($store['store_type'] == 'grocery') ? '🛒 Grocery' : '🍽️ Restaurant';
            $typeColor = ($store['store_type'] == 'grocery') ? 'var(--success)' : 'var(--primary)';
        ?>
        <div class="col-md-6 col-lg-3">
            <div class="card card-hover h-100" style="border-radius:var(--radius-lg);overflow:hidden;transition:all 0.3s ease;">
                <div style="height:140px;overflow:hidden;position:relative;">
                    <img src="<?php echo htmlspecialchars($cover); ?>" alt="<?php echo htmlspecialchars($store['store_name']); ?>"
                         style="width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease;"
                         onmouseenter="this.style.transform='scale(1.05)'"
                         onmouseleave="this.style.transform='scale(1)'">
                    <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,rgba(0,0,0,0.7),transparent);padding:1rem 0.8rem 0.5rem;">
                        <div style="display:flex;align-items:center;gap:6px;color:white;">
                            <i class="fas fa-star" style="color:var(--accent);"></i>
                            <strong><?php echo $rating; ?></strong>
                            <span style="font-size:0.7rem;opacity:0.7;">(<?php echo $store['review_count']; ?> reviews)</span>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="padding:0.8rem 1rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <h6 style="font-weight:700;margin-bottom:0.2rem;font-size:0.95rem;"><?php echo htmlspecialchars($store['store_name']); ?></h6>
                        <span style="font-size:0.65rem;color:<?php echo $typeColor; ?>;background:<?php echo $typeColor; ?>20;padding:0.1rem 0.5rem;border-radius:50px;"><?php echo $typeLabel; ?></span>
                    </div>
                    <p style="color:var(--text-muted);font-size:0.75rem;margin-bottom:0.5rem;">
                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($store['location']); ?>
                    </p>
                    <a href="index.php?page=store_menu&id=<?php echo $store['id']; ?>" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-utensils me-1"></i>View Menu
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ========== TRENDING ITEMS – fixed: only count delivered orders ========== -->
<?php
$trendingStmt = $pdo->prepare("
    SELECT si.id, si.name, si.price, si.image, si.store_id,
           s.store_name, s.store_type, COUNT(oi.item_id) as order_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN store_items si ON oi.item_id = si.id
    JOIN stores s ON si.store_id = s.id
    WHERE s.is_active = 1 AND s.is_online = 1 AND si.is_available = 1
      AND o.status = 'delivered'   -- Only count completed orders
    GROUP BY si.id
    ORDER BY order_count DESC
    LIMIT 8
");
$trendingStmt->execute();
$trendingItems = $trendingStmt->fetchAll();

if (count($trendingItems) > 0):
?>
<div class="mb-5">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="section-label">🔥 Trending Now</div>
            <h2 style="font-family:var(--font-display);margin:0;">Most Ordered Items</h2>
        </div>
    </div>
    <div class="row g-3">
        <?php foreach ($trendingItems as $item):
            $img = !empty($item['image']) ? $item['image'] : 'assets/images/placeholder.jpg';
            $typeIcon = ($item['store_type'] == 'grocery') ? '🛒' : '🍽️';
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card card-hover" style="border-radius:var(--radius-lg);overflow:hidden;transition:all 0.3s ease;">
                <div style="height:120px;overflow:hidden;position:relative;">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"
                         style="width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease;"
                         onmouseenter="this.style.transform='scale(1.06)'"
                         onmouseleave="this.style.transform='scale(1)'">
                    <div style="position:absolute;top:8px;right:8px;background:rgba(255,87,34,0.9);color:white;border-radius:50px;padding:0.2rem 0.7rem;font-size:0.65rem;font-weight:700;">
                        <?php echo $item['order_count']; ?> orders
                    </div>
                </div>
                <div class="card-body" style="padding:0.8rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <h6 style="font-weight:700;margin-bottom:0.2rem;font-size:0.9rem;"><?php echo htmlspecialchars($item['name']); ?></h6>
                        <span style="font-size:0.6rem;"><?php echo $typeIcon; ?></span>
                    </div>
                    <p style="color:var(--text-muted);font-size:0.7rem;margin-bottom:0.3rem;"><?php echo htmlspecialchars($item['store_name']); ?></p>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="color:var(--primary);font-weight:700;font-size:0.9rem;">Rs. <?php echo number_format($item['price'], 2); ?></span>
                        <a href="index.php?page=store_menu&id=<?php echo $item['store_id']; ?>" class="btn btn-primary btn-sm" style="font-size:0.7rem;padding:0.2rem 0.6rem;">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Popular Dishes Banner -->
<div style="position:relative;border-radius:var(--radius-xl);overflow:hidden;margin-bottom:3.5rem;height:220px;">
    <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1400&q=80"
         alt="Popular dishes" style="width:100%;height:100%;object-fit:cover;object-position:center 60%;">
    <div style="position:absolute;inset:0;background:linear-gradient(90deg,rgba(13,12,18,0.92) 0%,rgba(13,12,18,0.6) 60%,rgba(13,12,18,0.1) 100%);display:flex;align-items:center;padding:2.5rem 3rem;">
        <div>
            <div class="section-label mb-2">Fresh & Local</div>
            <h3 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:0.5rem;">Order from<br>your favourites</h3>
            <a href="index.php?page=store_list" class="btn btn-primary mt-2">
                <i class="fas fa-arrow-right me-2"></i>See All Stores
            </a>
        </div>
    </div>
</div>

<!-- ========== RECOMMENDATIONS – fixed: count delivered orders + fallback ========== -->
<!-- ========== RECOMMENDATIONS – with drag-to-scroll ========== -->
<?php
if(isLoggedIn() && $_SESSION['user_role'] == 'customer') {
    $userId = $_SESSION['user_id'];

    // Get items the customer has ordered before (only from delivered orders)
    $stmt = $pdo->prepare("
        SELECT si.*, s.store_name, COUNT(oi.item_id) as order_count
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN store_items si ON oi.item_id = si.id
        JOIN stores s ON si.store_id = s.id
        WHERE o.customer_id = ? AND o.status = 'delivered'
        GROUP BY si.id
        ORDER BY order_count DESC
        LIMIT 12
    ");
    $stmt->execute([$userId]);
    $recommended = $stmt->fetchAll();

    // If no past orders, show popular items instead (as a fallback)
    $isFallback = false;
    if (empty($recommended)) {
        $isFallback = true;
        $stmt = $pdo->prepare("
            SELECT si.*, s.store_name, COUNT(oi.item_id) as order_count
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN store_items si ON oi.item_id = si.id
            JOIN stores s ON si.store_id = s.id
            WHERE o.status = 'delivered' AND s.is_active = 1 AND si.is_available = 1
            GROUP BY si.id
            ORDER BY order_count DESC
            LIMIT 12
        ");
        $stmt->execute();
        $recommended = $stmt->fetchAll();
    }

    $placeholderImg = 'assets/images/placeholder.jpg';
    $title = $isFallback ? 'Popular Picks' : 'Personalized for you';

    if(count($recommended) > 0): ?>
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <div class="section-label"><?php echo ($isFallback ? '🔥 Trending' : '🎯 For You'); ?></div>
                <h2 style="font-family:var(--font-display);margin:0;"><?php echo $title; ?></h2>
            </div>
            <span style="font-size:0.75rem;color:var(--text-muted);"><i class="fas fa-arrows-alt-h me-1"></i>Drag to scroll</span>
        </div>

        <!-- Horizontal scroll container with drag -->
        <div style="overflow:hidden;position:relative;cursor:grab;" 
             id="recommendationScroll" 
             onmousedown="dragStart(event)" 
             onmousemove="dragMove(event)" 
             onmouseup="dragEnd(event)" 
             onmouseleave="dragEnd(event)">
            <div style="display:flex;gap:1rem;overflow-x:auto;scroll-behavior:smooth;padding:0.25rem 0.1rem;scrollbar-width:none;-ms-overflow-style:none;"
                 id="recommendationContainer">
                <?php foreach($recommended as $i=>$item):
                    $imgUrl = !empty($item['image']) ? $item['image'] : $placeholderImg;
                ?>
                <div style="flex:0 0 180px;min-width:160px;max-width:220px;" 
                     class="card card-hover" 
                     style="border-radius:var(--radius-lg);overflow:hidden;animation-delay:<?php echo $i*0.05; ?>s;animation:fadeInUp 0.4s ease both;">
                    <div style="height:110px;overflow:hidden;position:relative;">
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"
                             style="width:100%;height:100%;object-fit:cover;">
                        <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(13,12,18,0.6),transparent);"></div>
                    </div>
                    <div class="card-body" style="padding:0.65rem;">
                        <h6 style="font-size:0.82rem;font-weight:700;margin-bottom:0.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($item['name']); ?></h6>
                        <small style="color:var(--text-muted);display:block;margin-bottom:0.3rem;font-size:0.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($item['store_name']); ?></small>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="color:var(--primary);font-weight:700;font-size:0.85rem;">Rs. <?php echo number_format($item['price'], 2); ?></span>
                            <a href="index.php?page=store_menu&id=<?php echo $item['store_id']; ?>" class="btn btn-primary btn-sm" style="font-size:0.7rem;padding:0.2rem 0.6rem;">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            #recommendationContainer::-webkit-scrollbar { display: none; }
            #recommendationScroll { cursor: grab; }
            #recommendationScroll:active { cursor: grabbing; }
        </style>

        <script>
            let isDown = false, startX = 0, scrollLeft = 0;
            const container = document.getElementById('recommendationContainer');
            const wrapper = document.getElementById('recommendationScroll');

            function dragStart(e) {
                isDown = true;
                startX = e.pageX - wrapper.offsetLeft;
                scrollLeft = container.scrollLeft;
                wrapper.style.cursor = 'grabbing';
            }

            function dragMove(e) {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - wrapper.offsetLeft;
                const walk = (x - startX) * 1.5; // scroll speed
                container.scrollLeft = scrollLeft - walk;
            }

            function dragEnd(e) {
                isDown = false;
                wrapper.style.cursor = 'grab';
            }
        </script>
    </div>
    <?php endif; } ?>

<!-- How It Works Steps -->

<div style="text-align:center;margin-bottom:3rem;">
    <div class="section-label">Simple & Fast</div>
    <h2 style="font-family:var(--font-display);margin-bottom:2.5rem;">How It Works</h2>
    <div class="row g-4">
        <?php
        $steps = [
            ['fas fa-search','Browse','Find restaurants or grocery stores near you.','rgba(255,87,34,0.12)','var(--primary)'],
            ['fas fa-plus-circle','Choose','Pick your items and add them to your cart.','rgba(255,193,7,0.12)','var(--accent)'],
            ['fas fa-credit-card','Pay','Secure checkout in seconds.','rgba(16,185,129,0.12)','var(--success)'],
            ['fas fa-motorcycle','Receive','Fast delivery straight to your door.','rgba(62,207,207,0.12)','#3ECFCF'],
        ];
        foreach($steps as $i=>$s): ?>
        <div class="col-6 col-md-3">
            <div style="padding:1.5rem 0.8rem;height:100%;border:1px solid rgba(255,255,255,0.08);border-radius:18px;background:rgba(255,255,255,0.025);transition:0.3s;"
                 onmouseover="this.style.transform='translateY(-6px)';this.style.borderColor='<?php echo $s[4]; ?>';"
                 onmouseout="this.style.transform='translateY(0)';this.style.borderColor='rgba(255,255,255,0.08)';">
                <div style="width:65px;height:65px;background:<?php echo $s[3]; ?>;border:1px solid <?php echo $s[4]; ?>;border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.5rem;color:<?php echo $s[4]; ?>;position:relative;">
                    <i class="<?php echo $s[0]; ?>"></i>
                    <span style="position:absolute;top:-8px;right:-8px;width:24px;height:24px;background:<?php echo $s[4]; ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:0.7rem;font-weight:800;border:2px solid var(--bg);">
                        <?php echo $i + 1; ?>
                    </span>
                </div>
                <h6 style="font-weight:700;margin-bottom:0.5rem;font-size:1rem;"><?php echo $s[1]; ?></h6>
                <p style="color:var(--text-secondary);font-size:0.82rem;line-height:1.6;margin:0;"><?php echo $s[2]; ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Grocery Showcase Section -->
<div style="background:linear-gradient(135deg,rgba(16,185,129,0.08),rgba(16,185,129,0.02));border:1px solid rgba(16,185,129,0.15);border-radius:var(--radius-xl);padding:2rem;margin-bottom:2rem;">
    <div class="row align-items-center g-4">
        <div class="col-md-6">
            <div class="section-label" style="color:var(--success);">🛒 Fresh Daily</div>
            <h3 style="font-family:var(--font-display);margin-bottom:0.75rem;">Fresh Groceries,<br>Right to Your Door</h3>
            <p style="color:var(--text-secondary);font-size:0.9rem;line-height:1.7;margin-bottom:1.5rem;">From local farms to your kitchen — vegetables, dairy, pantry staples and more. Support local growers.</p>
            <a href="index.php?page=store_list&type=grocery" class="btn btn-success px-4">
                <i class="fas fa-shopping-basket me-2"></i>Shop Groceries
            </a>
        </div>
        <div class="col-md-6">
            <div class="row g-2">
                <?php
                $groceryImgs = [
                    ['🥦 Vegetables','https://images.unsplash.com/photo-1540420773420-3366772f4999?w=300&q=80'],
                    ['🥛 Dairy','https://images.unsplash.com/photo-1563636619-e9143da7973b?w=300&q=80'],
                    ['🍎 Fruits','https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=300&q=80'],
                    ['🥩 Meat','https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?w=300&q=80'],
                ];
                foreach($groceryImgs as $gi): ?>
                <div class="col-6">
                    <div style="border-radius:var(--radius-md);overflow:hidden;height:90px;position:relative;">
                        <img src="<?php echo $gi[1]; ?>" alt="<?php echo $gi[0]; ?>"
                             style="width:100%;height:100%;object-fit:cover;transition:transform 0.3s ease;"
                             onmouseenter="this.style.transform='scale(1.06)'"
                             onmouseleave="this.style.transform='scale(1)'">
                        <div style="position:absolute;inset:0;background:rgba(13,12,18,0.45);display:flex;align-items:flex-end;padding:0.5rem 0.6rem;">
                            <span style="font-size:0.75rem;font-weight:700;color:white;"><?php echo $gi[0]; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Copy coupon code script -->
<script>
document.querySelectorAll('.copy-code-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const code = this.dataset.code;
        navigator.clipboard.writeText(code).then(() => {
            const original = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => this.innerHTML = original, 2000);
        }).catch(() => {
            alert('Press Ctrl+C to copy: ' + code);
        });
    });
});
</script>