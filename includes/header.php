<?php

$hideConsumerNav = (isset($_GET['page']) && (
    $_GET['page'] === 'admin_dashboard' || 
    $_GET['page'] === 'owner_dashboard' ||
    $_GET['page'] === 'rider_dashboard'
));

// ─── Helper: Extract order ID from a string like "order #123" ───
function extractOrderId($text) {
    if (preg_match('/#(\d+)/', $text, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

// ─── Fetch counts & notifications for logged‑in customers ───
$unreadNotifications = 0;
$activeOrdersCount = 0;
$recentNotifications = [];

if (isLoggedIn() && $_SESSION['user_role'] == 'customer') {
    $userId = $_SESSION['user_id'];

    // Unread notifications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadNotifications = (int)$stmt->fetchColumn();

    // Active orders (pending, awaiting_rider, preparing, out_for_delivery)
    $activeStatuses = ['pending', 'awaiting_rider', 'preparing', 'out_for_delivery'];
    $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status IN ($placeholders)");
    $stmt->execute(array_merge([$userId], $activeStatuses));
    $activeOrdersCount = (int)$stmt->fetchColumn();

    // Recent unread notifications (limit 5)
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentNotifications = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartOrder — Local Restaurants & Grocery</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-base: #1A1820;
            --bg-card: rgba(255,255,255,0.07);
            --bg-card-hover: rgba(255,255,255,0.12);
            --glass: rgba(26,24,32,0.85);
            --border: rgba(255,255,255,0.10);
            --primary: #FF5722;
            --primary-dark: #E64A19;
            --accent: #FFC107;
            --success: #10B981;
            --text-primary: #F0EBE6;
            --text-secondary: #B0A8A0;
            --text-muted: #7A727A;
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body: 'Outfit', sans-serif;
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
            --shadow-md: 0 8px 32px rgba(0,0,0,0.5);
            --shadow-glow: 0 0 40px rgba(255,87,34,0.15);
            --shadow-card: 0 20px 60px rgba(0,0,0,0.6);
            --transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            background-color: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        body::after {
            content: '';
            position: fixed;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(255,87,34,0.05) 0%, transparent 70%);
            top: -250px;
            right: -250px;
            pointer-events: none;
            z-index: 0;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-base); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        h1,h2,h3,h4 { font-family: var(--font-display); font-weight: 700; line-height: 1.2; }
        .display-heading { font-family: var(--font-display); font-size: clamp(2.5rem,6vw,5rem); font-weight: 900; letter-spacing: -0.02em; line-height: 1.05; }
        .text-gradient { background: linear-gradient(135deg,var(--primary) 0%,var(--accent) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .text-muted { color: var(--text-secondary) !important; }

        /* NAVBAR */
        .navbar { background: var(--glass) !important; backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); padding: 0.85rem 0; position: sticky; top: 0; z-index: 1000; transition: var(--transition); }
        .navbar.scrolled { background: rgba(26,24,32,0.97) !important; border-bottom-color: rgba(255,87,34,0.2); box-shadow: 0 4px 30px rgba(0,0,0,0.6); }
        .navbar-brand { font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; color: var(--text-primary) !important; display: flex; align-items: center; gap: 10px; }
        .brand-icon { width: 34px; height: 34px; background: linear-gradient(135deg,var(--primary),var(--accent)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .brand-word-order { color: var(--primary); }
        .nav-link { font-weight: 500; font-size: 0.9rem; color: var(--text-secondary) !important; padding: 0.45rem 0.9rem !important; border-radius: var(--radius-sm); transition: var(--transition); }
        .nav-link:hover { color: var(--text-primary) !important; background: var(--bg-card-hover); }
        .btn-nav-login { border: 1px solid var(--border); border-radius: 50px; padding: 0.4rem 1.2rem !important; }
        .btn-nav-login:hover { border-color: var(--primary); color: var(--primary) !important; }
        .btn-nav-register { background: linear-gradient(135deg,var(--primary),var(--primary-dark)); color: white !important; border-radius: 50px; padding: 0.4rem 1.3rem !important; box-shadow: 0 4px 15px rgba(255,87,34,0.3); }
        .btn-nav-register:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,87,34,0.45); color: white !important; }
        .cart-nav-btn { position: relative; display: flex; align-items: center; gap: 6px; border: 1px solid var(--border); border-radius: 50px; padding: 0.4rem 1.1rem !important; }
        .cart-nav-btn:hover { border-color: var(--accent); color: var(--accent) !important; }
        .cart-badge { position: absolute; top: -6px; right: -6px; background: var(--primary); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; border: 2px solid var(--bg-base); }

        /* Dropdown */
        .dropdown-menu { background: #24222C; border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); padding: 0.5rem; margin-top: 0.5rem !important; min-width: 240px; }
        .dropdown-item { color: var(--text-secondary); font-weight: 500; font-size: 0.88rem; border-radius: var(--radius-sm); padding: 0.5rem 0.9rem; transition: var(--transition); }
        .dropdown-item:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        .dropdown-item i { width: 18px; text-align: center; margin-right: 8px; opacity: 0.6; }
        .dropdown-divider { border-color: var(--border); }
        .dropdown-header { color: var(--text-muted); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.3rem 0.9rem; }

        .navbar-toggler { border: 1px solid var(--border); border-radius: var(--radius-sm); }
        .navbar-toggler-icon { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(245,240,235,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e"); }

        /* Badge pulse */
        .badge-pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        .profile-avatar-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .profile-avatar-wrapper .notif-badge {
            position: absolute;
            top: -6px;
            right: -8px;
            font-size: 0.6rem;
            padding: 0.15rem 0.45rem;
            border: 2px solid var(--bg-base);
            background: #EF4444;
            color: white;
            border-radius: 50px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        /* CARDS, BUTTONS, FORMS, ALERTS, TABLES – unchanged from original */
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-primary); transition: var(--transition); }
        .card-hover:hover { background: var(--bg-card-hover); border-color: rgba(255,87,34,0.3); transform: translateY(-6px); box-shadow: var(--shadow-card), var(--shadow-glow); }
        .card-header { background: transparent; border-bottom: 1px solid var(--border); padding: 1rem 1.25rem; }
        .card-body { padding: 1.25rem; }
        .store-card { border-radius: var(--radius-lg) !important; overflow: hidden; }

        .btn { font-family: var(--font-body); font-weight: 600; border-radius: 50px; transition: var(--transition); letter-spacing: 0.02em; }
        .btn-primary { background: linear-gradient(135deg,var(--primary),#E64A19); border: none; color: white; box-shadow: 0 4px 15px rgba(255,87,34,0.35); }
        .btn-primary:hover, .btn-primary:focus { background: linear-gradient(135deg,#FF6D47,var(--primary)); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255,87,34,0.5); color: white; }
        .btn-outline-primary { border: 1.5px solid var(--primary); color: var(--primary); background: transparent; }
        .btn-outline-primary:hover { background: var(--primary); color: white; }
        .btn-success { background: linear-gradient(135deg,#10B981,#059669); border: none; box-shadow: 0 4px 15px rgba(16,185,129,0.3); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.45); color: white; }
        .btn-outline-secondary { border: 1.5px solid var(--border); color: var(--text-secondary); background: transparent; }
        .btn-outline-secondary:hover { background: var(--bg-card-hover); border-color: var(--text-secondary); color: var(--text-primary); }
        .btn-danger { background: linear-gradient(135deg,#EF4444,#DC2626); border: none; color: white; }
        .btn-warning { background: linear-gradient(135deg,var(--accent),#f59e0b); border: none; color: #1a1a1a; }
        .btn-info { background: linear-gradient(135deg,#3ECFCF,#0891b2); border: none; color: white; }
        .btn-secondary { background: rgba(255,255,255,0.08); border: 1px solid var(--border); color: var(--text-secondary); }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); color: var(--text-primary); }

        .form-control, .form-select { background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary); padding: 0.72rem 1rem; font-family: var(--font-body); transition: var(--transition); }
        .form-control:focus, .form-select:focus { background: rgba(255,255,255,0.08); border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255,87,34,0.15); color: var(--text-primary); outline: none; }
        .form-control::placeholder { color: var(--text-muted); }
        select.form-control, select.form-select { background-color: #24212D; color: #F8FAFC; }
        select.form-control option, select.form-select option, select option { background-color: #1A1820; color: #F8FAFC; }
        select.form-control option:checked, select.form-select option:checked, select option:checked { background-color: var(--primary); color: #FFFFFF; }
        select.form-control option:disabled, select.form-select option:disabled, select option:disabled { color: #A7A3B4; }
        .form-label { font-weight: 600; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.4rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .input-group .form-control { border-radius: var(--radius-md) 0 0 var(--radius-md); }
        .input-group .btn { border-radius: 0 var(--radius-md) var(--radius-md) 0; }
        textarea.form-control { border-radius: var(--radius-md); }

        .modal-content { background: #1E1C26; border: 1px solid rgba(255,255,255,0.12); border-radius: var(--radius-lg); color: var(--text-primary); box-shadow: var(--shadow-card); overflow: hidden; }
        .modal-header, .modal-footer { border-color: var(--border); }
        .modal-body { background: #1E1C26; color: var(--text-primary); }
        .modal-body label, .modal-body .form-label { color: var(--text-secondary); }
        .modal-backdrop.show { opacity: 0.72; }

        .alert { border-radius: var(--radius-md); font-weight: 500; }
        .alert-success { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.3); color: #6EE7B7; }
        .alert-danger { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); color: #FCA5A5; }
        .alert-info { background: rgba(62,207,207,0.1); border-color: rgba(62,207,207,0.3); color: #9DECEC; }
        .alert-warning { background: rgba(255,193,7,0.1); border-color: rgba(255,193,7,0.3); color: #FDE68A; }
        .btn-close { filter: invert(1) opacity(0.6); }

        .table { color: var(--text-primary); border-color: var(--border); }
        .table th { font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); border-color: var(--border); background: rgba(255,255,255,0.02); padding: 0.85rem 1rem; }
        .table td { border-color: var(--border); vertical-align: middle; padding: 0.85rem 1rem; }
        .table-bordered { border-color: var(--border); }
        .table-striped > tbody > tr:nth-of-type(odd) > td { background: rgba(255,255,255,0.02); }
        .table-hover > tbody > tr:hover > td { background: rgba(255,255,255,0.04); }

        .badge { font-weight: 600; font-size: 0.72rem; border-radius: 50px; padding: 0.35em 0.8em; }
        .badge.bg-primary, .bg-primary { background: var(--primary) !important; }
        .badge.bg-success, .bg-success { background: var(--success) !important; }
        .badge.bg-warning, .bg-warning { background: var(--accent) !important; color: #1a1a1a !important; }
        .badge.bg-info, .bg-info { background: #3ECFCF !important; color: #0a2525 !important; }
        .badge.bg-secondary, .bg-secondary { background: rgba(255,255,255,0.1) !important; }

        .rating-stars { color: var(--accent); letter-spacing: 2px; }

        .section-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--primary); margin-bottom: 0.4rem; }
        .divider { height: 1px; background: linear-gradient(90deg,transparent,var(--border),transparent); border: none; margin: 2rem 0; }
        .container { position: relative; z-index: 1; }
        .page-content { animation: fadeInUp 0.5s ease both; }
        .site-footer { margin-top: auto !important; flex-shrink: 0; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .category-pill { display: inline-flex; align-items: center; gap: 6px; padding: 0.4rem 1rem; border-radius: 50px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; }
        .category-pill:hover, .category-pill.active { background: var(--primary); border-color: var(--primary); color: white; transform: translateY(-1px); }
        .progress { background: rgba(255,255,255,0.06); border-radius: 50px; height: 6px; }
        .progress-bar { background: linear-gradient(90deg,var(--primary),var(--accent)); border-radius: 50px; }
        .stat-pill { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.06); border: 1px solid var(--border); border-radius: 50px; padding: 0.4rem 1rem; font-size: 0.85rem; font-weight: 600; }

        /* ECO MODE */
        <?php if(isset($_SESSION['eco_mode']) && $_SESSION['eco_mode']): ?>
        *, *::before, *::after { animation: none !important; transition: none !important; }
        .card-hover:hover { transform: none !important; }
        <?php endif; ?>

        .eco-toggle { position: fixed; bottom: 24px; right: 24px; z-index: 1000; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #6EE7B7; border-radius: 50px; padding: 9px 16px; cursor: pointer; font-size: 0.78rem; font-weight: 600; font-family: var(--font-body); transition: var(--transition); display: flex; align-items: center; gap: 5px; backdrop-filter: blur(10px); }
        .eco-toggle:hover { background: rgba(16,185,129,0.2); border-color: #10B981; }

        /* Chat widget – unchanged */
        .chat-widget {
            position: fixed;
            left: 20px;
            bottom: 20px;
            z-index: 1060;
        }
        .chat-toggle {
            width: 56px;
            height: 56px;
            border-radius: 28px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            transition: transform 0.2s;
        }
        .chat-toggle:hover {
            transform: scale(1.08);
            box-shadow: 0 12px 28px rgba(255,87,34,0.5);
        }
        .chat-panel {
            position: fixed;
            left: 20px;
            bottom: 90px;
            width: 360px;
            max-width: calc(100vw - 40px);
            height: 500px;
            max-height: calc(100vh - 120px);
            background: #1A1820;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow-card);
            display: none;
            z-index: 1061;
            backdrop-filter: blur(12px);
            overflow: hidden;
        }
        .chat-header {
            padding: 14px 18px;
            background: rgba(255,87,34,0.1);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-primary);
        }
        .chat-panel > .chat-header {
            border-radius: 18px 18px 0 0;
        }
        .chat-store-list {
            height: calc(100% - 55px);
            overflow-y: auto;
            padding: 10px;
        }
        .chat-store-item {
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s;
            margin-bottom: 8px;
            border: 1px solid transparent;
            background: rgba(255,255,255,0.02);
        }
        .chat-store-item:hover {
            background: var(--bg-card-hover);
            border-color: var(--border);
        }
        .chat-store-name {
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-last-msg {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 4px;
        }
        #chatConversation {
            position: relative;
            height: calc(100% - 55px);
            display: none;
        }
        #chatConversation .chat-header:first-child {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 2;
        }
        .chat-messages-area {
            position: absolute;
            top: 60px;
            bottom: 70px;
            left: 0;
            right: 0;
            overflow-y: auto;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-input-area {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 12px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            background: rgba(0,0,0,0.2);
            z-index: 2;
            border-radius: 0 0 18px 18px;
        }
        .chat-message {
            max-width: 82%;
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            word-wrap: break-word;
            line-height: 1.4;
        }
        .chat-message.customer {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-bottom-right-radius: 3px;
        }
        .chat-message.owner {
            align-self: flex-start;
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-bottom-left-radius: 3px;
        }
        .chat-message strong {
            font-size: 0.75rem;
            opacity: 0.8;
            display: block;
            margin-bottom: 3px;
        }
        .chat-input-area input {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 16px;
            color: var(--text-primary);
            font-size: 0.85rem;
            outline: none;
        }
        .chat-input-area input:focus {
            border-color: var(--primary);
        }
        .chat-input-area button {
            background: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 0 18px;
            color: white;
            font-weight: 600;
            transition: 0.2s;
        }
        .chat-input-area button:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        .chat-back {
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
        }
        .chat-back:hover {
            opacity: 0.8;
        }
        @keyframes slideUpIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media(max-width:768px) {
            .display-heading{font-size:2.2rem;}
            .chat-panel{width:calc(100vw - 40px); left:20px; right:20px;}
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="index.php?page=home">
            <div class="brand-icon">🍽️</div>
            <span>Smart<span class="brand-word-order">Order</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto ms-3 gap-1">
                <?php if (!$hideConsumerNav): ?>
                <li class="nav-item"><a class="nav-link" href="index.php?page=home"><i class="fas fa-home me-1" style="opacity:0.5;"></i>Home</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php?page=store_list"><i class="fas fa-store me-1" style="opacity:0.5;"></i>Restaurants</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php?page=store_list&type=grocery"><i class="fas fa-shopping-basket me-1" style="opacity:0.5;"></i>Grocery</a></li>
                <?php endif; ?>
                <?php if(isLoggedIn() && $_SESSION['user_role'] == 'customer'): ?>
                <li class="nav-item"><a class="nav-link" href="index.php?page=reservations"><i class="fas fa-calendar me-1" style="opacity:0.5;"></i>Reservations</a></li>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="index.php?page=customer_dashboard">
                        <i class="fas fa-receipt me-1" style="opacity:0.5;"></i>My Orders
                        <?php if ($activeOrdersCount > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill ms-1 badge-pulse" style="font-size:0.65rem; vertical-align:top;"><?php echo $activeOrdersCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <?php if(isLoggedIn()): ?>
                    <?php if($_SESSION['user_role'] == 'customer'): ?>
                    <li class="nav-item">
                        <a class="nav-link cart-nav-btn" href="index.php?page=cart">
                            <i class="fas fa-shopping-bag"></i><span>Cart</span>
                            <span class="cart-badge" id="cartCount">0</span>
                        </a>
                    </li>
                    <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle btn-nav-login d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" id="userDropdown">
                        <div class="profile-avatar-wrapper">
                            <span style="width:28px;height:28px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:white;flex-shrink:0;">
                                <?php echo strtoupper(substr($_SESSION['user_name'],0,1)); ?>
                            </span>
                            <?php if ($unreadNotifications > 0): ?>
                            <span class="notif-badge badge-pulse"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </div>
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2">
                            <small style="color:var(--text-muted);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.06em;display:block;">Signed in as</small>
                            <strong style="font-size:0.88rem;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                        </li>
                        <li><hr class="dropdown-divider"></li>

                        <!-- ─── Active Orders (count only) ─── -->
                        <?php if ($_SESSION['user_role'] == 'customer'): ?>
                        <li>
                            <a class="dropdown-item d-flex justify-content-between align-items-center" href="index.php?page=customer_dashboard#orders">
                                <span><i class="fas fa-receipt"></i> Active Orders</span>
                                <?php if ($activeOrdersCount > 0): ?>
                                <span class="badge bg-warning text-dark rounded-pill badge-pulse"><?php echo $activeOrdersCount; ?></span>
                                <?php else: ?>
                                <span class="badge bg-secondary text-light">0</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>

                        <!-- ─── Notifications (dynamic links) ─── -->
                        <li class="dropdown-header"><i class="fas fa-bell me-1"></i> Notifications</li>
                        <?php if ($unreadNotifications > 0): ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <?php 
                                    $orderId = extractOrderId($notif['message']); 
                                    $link = $orderId ? 'index.php?page=order_tracking&id='.$orderId : 'index.php?page=customer_dashboard#notifications';
                                ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $link; ?>" style="white-space:normal; line-height:1.3;">
                                        <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                        <span class="text-muted d-block" style="font-size:0.75rem;"><?php echo htmlspecialchars(substr($notif['message'],0,60)); ?>…</span>
                                        <small style="font-size:0.65rem; color:var(--text-muted);"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-center" href="index.php?page=customer_dashboard#notifications">
                                    <i class="fas fa-bell"></i> View all notifications
                                    <span class="badge bg-danger ms-1"><?php echo $unreadNotifications; ?> unread</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li><span class="dropdown-item text-muted" style="cursor:default;">No new notifications</span></li>
                        <?php endif; ?>

                        <li><hr class="dropdown-divider"></li>

                        <!-- ─── Dashboard & Logout ─── -->
                        <li>
                            <a class="dropdown-item" href="index.php?page=<?php 
                                echo $_SESSION['user_role'] == 'customer' ? 'customer_dashboard' : 
                                     ($_SESSION['user_role'] == 'owner' ? 'owner_dashboard' : 
                                     ($_SESSION['user_role'] == 'admin' ? 'admin_dashboard' : 'rider_dashboard')); 
                            ?>">
                                <i class="fas fa-gauge"></i> Dashboard
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?page=logout" style="color:#FCA5A5;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link btn-nav-login" href="index.php?page=login">Login</a></li>
                <li class="nav-item"><a class="nav-link btn-nav-register" href="index.php?page=register">Get Started</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Alert messages (same as before) -->
<div class="container mt-3 page-content">
    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-circle-check me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-circle-exclamation me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
</div>

<div class="container mt-4 mb-5 page-content">
    <!-- Your main content goes here -->
</div>

<!-- Chat Widget (unchanged) -->
<?php if(isLoggedIn() && $_SESSION['user_role'] == 'customer'): ?>
<div class="chat-widget">
    <div class="chat-toggle" id="chatToggle">
        <i class="fas fa-comment-dots"></i>
    </div>
    <div class="chat-panel" id="chatPanel">
        <div class="chat-header">
            <span><i class="fas fa-store me-2"></i> Messages</span>
            <i class="fas fa-times" id="chatClose" style="cursor:pointer;"></i>
        </div>
        <div id="chatStoreList" class="chat-store-list">
            <!-- Stores will load here -->
        </div>
        <div id="chatConversation">
            <div class="chat-header" style="justify-content: flex-start; gap: 12px;">
                <i class="fas fa-arrow-left chat-back" id="chatBack"></i>
                <span id="chatStoreName"></span>
            </div>
            <div id="chatMessagesArea" class="chat-messages-area"></div>
            <div class="chat-input-area">
                <input type="text" id="chatMessageInput" placeholder="Type a message...">
                <button id="chatSendBtn"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="eco-toggle" onclick="window.location.href='?toggle_eco=1'">
    <i class="fas fa-leaf"></i>
    <?php echo isset($_SESSION['eco_mode']) && $_SESSION['eco_mode'] ? 'Eco On' : 'Eco Mode'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
window.addEventListener('scroll',()=>{ document.getElementById('mainNav').classList.toggle('scrolled',window.scrollY>20); });
function updateCartCount(){$.get('ajax/cart_count.php',function(d){$('#cartCount').text(d);});}
setInterval(updateCartCount,3000); updateCartCount();
document.querySelectorAll('.card-hover').forEach(function(c,i){c.style.animationDelay=i*0.07+'s';c.style.animation='fadeInUp 0.5s ease both';});

// Floating Chat Widget Logic (customer only)
<?php if(isLoggedIn() && $_SESSION['user_role'] == 'customer'): ?>
let currentStoreId = null;
let chatRefreshInterval = null;

$('#chatToggle').click(function() {
    $('#chatPanel').toggle();
    if ($('#chatPanel').is(':visible')) {
        loadChatStoreList();
    }
});
$('#chatClose').click(function() { $('#chatPanel').hide(); });
$('#chatBack').click(function() {
    $('#chatStoreList').show();
    $('#chatConversation').css('display', 'none');
    if (chatRefreshInterval) clearInterval(chatRefreshInterval);
});

function loadChatStoreList() {
    $.get('ajax/get_customer_chats.php', function(stores) {
        let html = '';
        if (stores.length === 0) {
            html = '<div class="text-center text-muted p-4">No conversations yet.<br>Click "Chat with this Store" on any restaurant page to start.</div>';
        } else {
            stores.forEach(store => {
                let lastMsg = store.last_message ? store.last_message.substring(0, 40) : 'Start chatting';
                let time = store.last_time ? new Date(store.last_time).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '';
                html += `<div class="chat-store-item" data-store-id="${store.id}" data-store-name="${escapeHtml(store.store_name)}">
                            <div class="chat-store-name">${escapeHtml(store.store_name)} <span style="font-size:0.7rem;">(${store.store_type})</span></div>
                            <div class="chat-last-msg">${escapeHtml(lastMsg)} ${time ? '· '+time : ''}</div>
                         </div>`;
            });
        }
        $('#chatStoreList').html(html);
        
        $('.chat-store-item').click(function() {
            currentStoreId = $(this).data('store-id');
            let storeName = $(this).data('store-name');
            $('#chatStoreName').text(storeName);
            $('#chatStoreList').hide();
            $('#chatConversation').css('display', 'block');
            if (chatRefreshInterval) clearInterval(chatRefreshInterval);
            loadConversation(currentStoreId);
            chatRefreshInterval = setInterval(() => {
                if ($('#chatConversation').is(':visible')) loadConversation(currentStoreId);
            }, 5000);
        });
    }, 'json').fail(function() {
        $('#chatStoreList').html('<div class="text-center text-muted p-4">Unable to load conversations.</div>');
    });
}

function loadConversation(storeId) {
    currentStoreId = storeId;
    $.get('ajax/get_conversation_messages.php', { store_id: storeId }, function(messages) {
        let html = '';
        if (!Array.isArray(messages)) {
            messages = [];
        }
        if (messages.length === 0) {
            html = '<div class="text-center text-muted p-4">No messages yet.</div>';
        }
        messages.forEach(msg => {
            let cls = (msg.sender_type === 'customer') ? 'customer' : 'owner';
            let name = (msg.sender_type === 'customer') ? 'You' : msg.sender_name;
            html += `<div class="chat-message ${cls}">
                        <div><strong>${escapeHtml(name)}</strong></div>
                        <div>${escapeHtml(msg.message)}</div>
                        <div style="font-size:0.65rem; opacity:0.6;">${new Date(msg.created_at).toLocaleTimeString()}</div>
                    </div>`;
        });
        $('#chatMessagesArea').html(html);
        $('#chatMessagesArea').scrollTop($('#chatMessagesArea')[0].scrollHeight);
        $('#chatConversation').css('display','block');
    }, 'json').fail(function() {
        $('#chatMessagesArea').html('<div class="text-center text-muted p-4">Unable to load messages.</div>');
    });
}

$('#chatSendBtn').click(sendMessage);
$('#chatMessageInput').keypress(function(e) { if(e.which === 13) sendMessage(); });

function sendMessage() {
    let msg = $('#chatMessageInput').val().trim();
    if (!msg || !currentStoreId) return;
    $('#chatSendBtn').prop('disabled', true);
    $.post('ajax/send_customer_message.php', { store_id: currentStoreId, message: msg }, function(res) {
        if (res === 'success') {
            $('#chatMessageInput').val('');
            loadConversation(currentStoreId);
            loadChatStoreList();
        } else {
            alert('Failed to send');
        }
    }).fail(function() {
        alert('Failed to send');
    }).always(function() {
        $('#chatSendBtn').prop('disabled', false);
    });
}

function escapeHtml(str) {
    return $('<div/>').text(str).html();
}
<?php endif; ?>
</script>
</body>
</html>