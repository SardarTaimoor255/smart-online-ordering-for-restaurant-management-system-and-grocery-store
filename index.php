<?php
// index.php
require_once 'bootstrap.php';   // includes database.php and starts session

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Public pages (no login required)
$public_pages = ['home', 'login', 'register', 'store_list', 'store_menu', 'qr_menu'];

if (!isLoggedIn() && !in_array($page, $public_pages)) {
    redirect('index.php?page=login');
}

// Role-based access control
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    
    if ($role == 'admin') {
        // Admin can access: pages with 'admin' in name, public pages, and logout
        $allowed = array_merge($public_pages, ['logout']);
        if (strpos($page, 'admin') === false && !in_array($page, $allowed)) {
            redirect('index.php?page=admin_dashboard');
        }
    }
    elseif ($role == 'owner') {
        // Allow 'qr_menu' for owners so they can view the QR menu without redirect
        if (strpos($page, 'owner') === false && $page != 'logout' && $page != 'store_menu' && $page != 'qr_menu') {
            redirect('index.php?page=owner_dashboard');
        }
    } 
    elseif ($role == 'rider') {
        // Allow rider_respond page for accept/reject actions
        if (strpos($page, 'rider') === false && $page != 'logout' && $page != 'rider_respond') {
            redirect('index.php?page=rider_dashboard');
        }
    } 
    elseif ($role == 'customer') {
        // Customers cannot access admin/owner/rider pages
        $blocked = ['admin_dashboard', 'owner_dashboard', 'rider_dashboard'];
        if (in_array($page, $blocked)) {
            redirect('index.php?page=customer_dashboard');
        }
        // All other pages are allowed
    }
}

// Include header (except for qr_menu)
if ($page != 'qr_menu') {
    include 'includes/header.php';
}

// Routing
switch($page) {
    case 'home':               include 'views/home.php'; break;
    case 'login':              include 'views/auth/login.php'; break;
    case 'register':           include 'views/auth/register.php'; break;
    case 'store_list':         include 'views/store_list.php'; break;
    case 'store_menu':         include 'views/store_menu.php'; break;
    case 'qr_menu':            include 'views/qr_menu.php'; break;
    case 'cart':               include 'views/cart.php'; break;
    case 'checkout':           include 'views/checkout.php'; break;
    case 'order_tracking':     include 'views/order_tracking.php'; break;
    case 'reservations':       include 'views/reservations.php'; break;
    case 'customer_dashboard': include 'views/customer/dashboard.php'; break;
    case 'owner_dashboard':    include 'views/owner/dashboard.php'; break;
    case 'admin_dashboard':    include 'views/admin/dashboard.php'; break;
    case 'rider_dashboard':    include 'views/rider/dashboard.php'; break;
    case 'easypaisa_simulator': include 'views/easypaisa_simulator.php'; break;
    case 'easypaisa_callback':   include 'views/easypaisa_callback.php'; break;
    case 'jazzcash_simulator': include 'views/jazzcash_simulator.php'; break;
    case 'jazzcash_callback':   include 'views/jazzcash_callback.php'; break;
    case 'rider_respond':      include 'views/rider_respond.php'; break;
    case 'logout':
        // Clear remember‑me token from database and cookies
        if (isset($_SESSION['user_id'])) {
            $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
        }
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_user_id', '', time() - 3600, '/');
        session_destroy();
        redirect('index.php?page=home');
        break;
    default:                   include 'views/home.php';
}

// Include footer
if ($page != 'qr_menu') {
    include 'includes/footer.php';
}
?>