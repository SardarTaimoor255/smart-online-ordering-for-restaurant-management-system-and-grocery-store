<?php
// ==============================================
// VIEWS/STORE_LIST.PHP — with dynamic cover images + offline badge
// ==============================================
$type = isset($_GET['type']) ? $_GET['type'] : 'restaurant';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

$sql = "SELECT s.*, AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
        FROM stores s LEFT JOIN ratings r ON s.id = r.store_id
        WHERE s.store_type = ? AND s.is_active = 1";
$params = [$type];
if($search) { 
    $sql .= " AND (s.store_name LIKE ? OR s.location LIKE ?)"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}
if($category) { 
    // Case‑insensitive and trimmed match
    $sql .= " AND LOWER(TRIM(s.category)) = LOWER(TRIM(?))"; 
    $params[] = $category; 
}
// ─── ORDER BY oldest first ──────────────────────────────────────────
$sql .= " GROUP BY s.id ORDER BY s.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stores = $stmt->fetchAll();

// Image pools by category (fallback only)
$restaurantImages = [
    'fast food' => [
        'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&q=80',
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&q=80',
        'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&q=80',
    ],
    'desi' => [
        'https://images.unsplash.com/photo-1567188040759-fb8a883dc6d8?w=600&q=80',
        'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=600&q=80',
        'https://images.unsplash.com/photo-1596797038530-2c107229654b?w=600&q=80',
    ],
    'chinese' => [
        'https://images.unsplash.com/photo-1552611052-33e04de081de?w=600&q=80',
        'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&q=80',
        'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&q=80',
    ],
    'default_restaurant' => [
        'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&q=80',
        'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&q=80',
        'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=600&q=80',
        'https://images.unsplash.com/photo-1571091718767-18b5b1457add?w=600&q=80',
        'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=600&q=80',
    ],
    'vegetables' => [
        'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=600&q=80',
        'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&q=80',
    ],
    'dairy' => [
        'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=600&q=80',
        'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=600&q=80',
    ],
    'default_grocery' => [
        'https://images.unsplash.com/photo-1542838132-92c53300491e?w=600&q=80',
        'https://images.unsplash.com/photo-1506617564039-2f3b650b7010?w=600&q=80',
        'https://images.unsplash.com/photo-1488459716781-31db52582fe9?w=600&q=80',
    ],
];

function getStoreImage($store, $restaurantImages, $index) {
    $cat = strtolower(trim($store['category'] ?? ''));
    $type = $store['store_type'];
    if($type === 'grocery') {
        $pool = $restaurantImages[$cat] ?? $restaurantImages['default_grocery'];
    } else {
        $pool = $restaurantImages[$cat] ?? $restaurantImages['default_restaurant'];
    }
    return $pool[$index % count($pool)];
}

// ─── Category lists per store type ──────────────────────────────
$restaurantCategories = [
    'fast food' => '🍟 Fast Food',
    'desi'      => '🍛 Desi',
    'chinese'   => '🥡 Chinese',
    'bbq'       => '🔥 BBQ',
    'desserts'  => '🍰 Desserts',
    'seafood'   => '🦐 Seafood'
];

$groceryCategories = [
    'vegetables' => '🥦 Vegetables',
    'fruits'     => '🍎 Fruits',
    'dairy'      => '🥛 Dairy',
    'meat'       => '🥩 Meat',
    'pantry'     => '🏪 Pantry',
    'beverages'  => '🥤 Beverages'
];

$categoryOptions = '';
if ($type === 'grocery') {
    $categoryOptions .= '<option value="">All Categories</option>';
    foreach ($groceryCategories as $key => $label) {
        $sel = ($category === $key) ? 'selected' : '';
        $categoryOptions .= "<option value=\"$key\" $sel>$label</option>";
    }
} else {
    $categoryOptions .= '<option value="">All Categories</option>';
    foreach ($restaurantCategories as $key => $label) {
        $sel = ($category === $key) ? 'selected' : '';
        $categoryOptions .= "<option value=\"$key\" $sel>$label</option>";
    }
}
?>

<!-- Page Header with Banner -->
<div style="position:relative;border-radius:var(--radius-xl);overflow:hidden;margin-bottom:2.5rem;height:160px;">
    <img src="<?php echo $type==='grocery'
        ? 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=1200&q=80'
        : 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=80';
    ?>" alt="Banner" style="width:100%;height:100%;object-fit:cover;object-position:center;">
    <div style="position:absolute;inset:0;background:linear-gradient(90deg,rgba(13,12,18,0.92),rgba(13,12,18,0.5) 60%,rgba(13,12,18,0.1));display:flex;align-items:center;padding:0 2.5rem;justify-content:space-between;">
        <div>
            <div class="section-label"><?php echo $type==='grocery' ? '🛒 Fresh Daily' : '🔥 Near You'; ?></div>
            <h2 style="font-family:var(--font-display);margin:0;font-size:1.8rem;"><?php echo $type==='grocery' ? 'Grocery Stores' : 'Restaurants'; ?></h2>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=store_list&type=restaurant" class="category-pill <?php echo $type==='restaurant'?'active':''; ?>">
                <i class="fas fa-utensils"></i> Restaurants
            </a>
            <a href="?page=store_list&type=grocery" class="category-pill <?php echo $type==='grocery'?'active':''; ?>">
                <i class="fas fa-shopping-basket"></i> Grocery
            </a>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card mb-4" style="border-radius:var(--radius-lg);padding:1.25rem;">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="store_list">
        <input type="hidden" name="type" value="<?php echo $type; ?>">
        <div class="col-md-7">
            <label class="form-label"><i class="fas fa-search me-1"></i>Search</label>
            <div class="d-flex gap-2">
                <input type="text" name="search" class="form-control" placeholder="Search by name or location…" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-search"></i></button>
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label"><i class="fas fa-tag me-1"></i>Category</label>
            <select name="category" class="form-select" onchange="this.form.submit()">
                <?php echo $categoryOptions; ?>
            </select>
        </div>
    </form>
</div>

<!-- Results -->
<?php if(count($stores) === 0): ?>
<div style="text-align:center;padding:5rem 2rem;">
    <img src="https://images.unsplash.com/photo-1483137140003-ae073b395549?w=400&q=80" alt="No results"
         style="width:200px;height:150px;object-fit:cover;border-radius:var(--radius-lg);margin-bottom:1.5rem;opacity:0.5;">
    <h4 style="font-family:var(--font-display);margin-bottom:0.5rem;">No stores found</h4>
    <p style="color:var(--text-secondary);">Try a different search term or category.</p>
    <a href="?page=store_list&type=<?php echo $type; ?>" class="btn btn-outline-primary mt-2">Clear Filters</a>
</div>
<?php else: ?>
<div style="margin-bottom:1rem;color:var(--text-muted);font-size:0.85rem;font-weight:500;">
    <?php echo count($stores); ?> store<?php echo count($stores)!==1?'s':''; ?> found
</div>
<div class="row g-4">
    <?php foreach($stores as $i=>$store):
        // Determine cover image: use uploaded cover_image if exists, else fallback to Unsplash
        $coverImage = !empty($store['cover_image']) 
            ? $store['cover_image'] 
            : getStoreImage($store, $restaurantImages, $i);
        $rating = round($store['avg_rating'] ?? 0, 1);
        $isOnline = $store['is_online'] ?? 1; // default online if column missing
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card store-card card-hover" style="animation-delay:<?php echo $i*0.07; ?>s;animation:fadeInUp 0.5s ease both;">
            <!-- Store cover photo -->
            <div style="height:170px;position:relative;overflow:hidden;">
                <img src="<?php echo htmlspecialchars($coverImage); ?>" alt="<?php echo htmlspecialchars($store['store_name']); ?>"
                     style="width:100%;height:100%;object-fit:cover;transition:transform 0.5s ease;"
                     onmouseenter="this.style.transform='scale(1.06)'"
                     onmouseleave="this.style.transform='scale(1)'">
                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(13,12,18,0.85) 0%,rgba(13,12,18,0.1) 60%);"></div>
                <!-- Badges on photo -->
                <div style="position:absolute;top:0.75rem;left:0.75rem;display:flex;gap:5px;flex-wrap:wrap;">
                    <?php if($store['store_type']==='grocery'): ?>
                    <span class="badge" style="background:rgba(16,185,129,0.85);font-size:0.7rem;backdrop-filter:blur(4px);">🛒 Grocery</span>
                    <?php else: ?>
                    <span class="badge" style="background:rgba(255,87,34,0.85);font-size:0.7rem;backdrop-filter:blur(4px);">🍽️ Restaurant</span>
                    <?php endif; ?>
                    <?php if($rating >= 4.5): ?>
                    <span class="badge" style="background:rgba(255,193,7,0.85);color:#1a1a1a;font-size:0.7rem;backdrop-filter:blur(4px);">⭐ Top Rated</span>
                    <?php endif; ?>
                    <?php if(!$isOnline): ?>
                    <span class="badge" style="background:rgba(239,68,68,0.85);font-size:0.7rem;backdrop-filter:blur(4px);">🔴 Offline</span>
                    <?php endif; ?>
                </div>
                <?php if($store['category']): ?>
                <div style="position:absolute;bottom:0.75rem;left:0.75rem;">
                    <span class="badge" style="background:rgba(13,12,18,0.7);border:1px solid rgba(255,255,255,0.15);font-size:0.72rem;backdrop-filter:blur(6px);"><?php echo ucfirst($store['category']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding:1.1rem;">
                <h5 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:0.25rem;"><?php echo htmlspecialchars($store['store_name']); ?></h5>
                <p style="color:var(--text-muted);font-size:0.82rem;margin-bottom:0.65rem;">
                    <i class="fas fa-map-marker-alt me-1" style="color:var(--primary);"></i>
                    <?php echo htmlspecialchars($store['location']); ?>
                </p>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="rating-stars" style="font-size:0.82rem;"><?php for($j=1;$j<=5;$j++) echo $j<=$rating?'★':'☆'; ?></div>
                        <small style="color:var(--text-muted);font-size:0.75rem;"><?php echo $rating>0 ? $rating.' ('.$store['review_count'].' reviews)' : 'No reviews yet'; ?></small>
                    </div>
                    <!-- Online/Offline indicator -->
                    <?php if($isOnline): ?>
                        <div style="display:flex;align-items:center;gap:4px;color:var(--success);font-size:0.78rem;font-weight:600;">
                            <i class="fas fa-circle" style="font-size:6px;"></i> Online
                        </div>
                    <?php else: ?>
                        <div style="display:flex;align-items:center;gap:4px;color:#EF4444;font-size:0.78rem;font-weight:600;">
                            <i class="fas fa-circle" style="font-size:6px;"></i> Offline
                        </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php?page=store_menu&id=<?php echo $store['id']; ?>" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-utensils me-1"></i> View Menu
                    </a>
                    <a href="index.php?page=qr_menu&id=<?php echo $store['id']; ?>" class="btn btn-outline-secondary btn-sm" style="border-radius:var(--radius-md);width:36px;padding:0;display:flex;align-items:center;justify-content:center;" title="QR Menu">
                        <i class="fas fa-qrcode"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>