<?php
// views/qr_menu.php
require_once __DIR__ . '/../bootstrap.php';

$storeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$storeId) {
    die("Invalid store ID.");
}

$stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ? AND is_active = 1");
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) {
    die("Store not found or inactive.");
}

$itemsStmt = $pdo->prepare("SELECT * FROM store_items WHERE store_id = ? AND is_available = 1 ORDER BY category, name");
$itemsStmt->execute([$storeId]);
$items = $itemsStmt->fetchAll();

// Group by category
$categories = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'General';
    $categories[$cat][] = $item;
}

// Cover image fallback
$coverImage = !empty($store['cover_image']) ? $store['cover_image'] : (
    $store['store_type'] == 'grocery'
    ? 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=800&q=80'
    : 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=800&q=80'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store['store_name']); ?> – QR Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', Roboto, sans-serif; }
        .header {
            background: linear-gradient(135deg, #2d2f36, #1a1c22);
            color: white;
            padding: 20px 0 15px;
            text-align: center;
            position: relative;
        }
        .header .store-name { font-size: 2rem; font-weight: 700; margin: 0; }
        .header .store-location { color: #ccc; font-size: 0.9rem; margin-top: 5px; }
        .header .store-type {
            position: absolute;
            right: 20px;
            top: 20px;
            background: rgba(255,255,255,0.15);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .category-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 25px 0 12px;
            padding-left: 15px;
            border-left: 5px solid #ff5722;
            color: #2d2f36;
        }
        .item-card {
            background: white;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.2s;
        }
        .item-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .item-name { font-weight: 600; font-size: 1rem; }
        .item-desc { font-size: 0.8rem; color: #6c757d; }
        .item-price { font-weight: 700; color: #ff5722; font-size: 1.1rem; }
        .no-items {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        .no-items i { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; }
        .footer {
            text-align: center;
            padding: 20px 0;
            color: #aaa;
            font-size: 0.8rem;
            border-top: 1px solid #eee;
            margin-top: 30px;
        }
        @media (max-width: 576px) {
            .header .store-name { font-size: 1.4rem; }
            .header .store-type { display: none; }
            .item-card { padding: 10px 12px; }
        }
    </style>
</head>
<body>
<div class="header">
    <div class="container">
        <div class="store-name"><?php echo htmlspecialchars($store['store_name']); ?></div>
        <div class="store-location"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($store['location']); ?></div>
        <span class="store-type"><?php echo ucfirst($store['store_type']); ?></span>
    </div>
</div>

<div class="container py-3">
    <?php if (empty($categories)): ?>
        <div class="no-items">
            <i class="fas fa-utensils"></i>
            <h5>No menu items available</h5>
            <p class="text-muted">This store hasn't added any items yet. Please check back later.</p>
        </div>
    <?php else: ?>
        <?php foreach ($categories as $cat => $items): ?>
            <div class="category-title"><?php echo htmlspecialchars($cat); ?></div>
            <?php foreach ($items as $item): ?>
                <div class="item-card">
                    <div>
                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <?php if (!empty($item['description'])): ?>
                            <div class="item-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="item-price">Rs. <?php echo number_format($item['price'], 2); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="footer">
    Powered by <strong>SmartOrder</strong> &bull; Scan to order
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>