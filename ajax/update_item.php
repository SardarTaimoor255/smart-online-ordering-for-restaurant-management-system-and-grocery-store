<?php
require_once '../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'owner') {
    echo 'error';
    exit();
}

$item_id = $_POST['item_id'];
$name = trim($_POST['name']);
$price = $_POST['price'];
$category = trim($_POST['category']);
$description = trim($_POST['description']);
$owner_id = $_SESSION['user_id'];

// Validate inputs
$errors = [];
if (empty($name)) $errors[] = "Item name required.";
if (!is_numeric($price) || $price <= 0) $errors[] = "Invalid price.";
if (strlen($description) > 500) $errors[] = "Description too long.";

// Verify the item belongs to a store owned by this owner
$check = $pdo->prepare("
    SELECT si.id, si.image 
    FROM store_items si
    JOIN stores s ON si.store_id = s.id
    WHERE si.id = ? AND s.owner_id = ?
");
$check->execute([$item_id, $owner_id]);
$item = $check->fetch();
if (!$item) {
    echo 'error';
    exit();
}

$imagePath = $item['image']; // keep current by default

// Handle new image upload
if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['new_image'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $maxSize = 2 * 1024 * 1024;
    if (!in_array($file['type'], $allowed)) {
        $errors[] = "Only JPG, PNG, WebP allowed.";
    }
    if ($file['size'] > $maxSize) {
        $errors[] = "Image max 2MB.";
    }
    if (empty($errors)) {
        if (!is_dir('uploads/items')) mkdir('uploads/items', 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique = uniqid() . '_' . time() . '.' . $ext;
        $dest = 'uploads/items/' . $unique;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Delete old image if exists (optional)
            if ($imagePath && file_exists($imagePath)) {
                unlink($imagePath);
            }
            $imagePath = $dest;
        } else {
            $errors[] = "Failed to upload image.";
        }
    }
}

if (empty($errors)) {
    $stmt = $pdo->prepare("UPDATE store_items SET name = ?, price = ?, category = ?, description = ?, image = ? WHERE id = ?");
    $stmt->execute([$name, $price, $category, $description, $imagePath, $item_id]);
    echo 'success';
} else {
    echo implode('<br>', $errors);
}
?>