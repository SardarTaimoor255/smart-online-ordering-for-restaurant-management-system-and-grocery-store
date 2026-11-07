<?php
// bootstrap.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';
define('BASE_URL', '/smart_ordering_system/');

// ─── Auto‑login via Remember Me cookie ──────────────────────────────
if (!isLoggedIn() && isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user_id'])) {
    $token = $_COOKIE['remember_token'];
    $userId = (int)$_COOKIE['remember_user_id'];

    $stmt = $pdo->prepare("SELECT id, name, role, points, remember_token FROM users WHERE id = ? AND remember_token = ?");
    $stmt->execute([$userId, $token]);
    $user = $stmt->fetch();

    if ($user) {
        // Valid token – log the user in silently
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_points'] = $user['points'] ?? 0;

        // Refresh the cookie expiration (extend for another 2 days)
        setcookie('remember_token', $token, time() + (86400 * 2), '/');
        setcookie('remember_user_id', $userId, time() + (86400 * 2), '/');
    } else {
        // Invalid token – clear cookies
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_user_id', '', time() - 3600, '/');
    }
}
?>