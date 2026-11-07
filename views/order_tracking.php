<?php
// views/order_tracking.php
if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') {
    redirect('index.php?page=login');
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    echo "<div class='alert alert-danger'>Invalid order!</div>";
    exit;
}

// Fetch order details with rider name
$stmt = $pdo->prepare("
    SELECT o.*, s.store_name, u.name as rider_name
    FROM orders o
    LEFT JOIN stores s ON o.store_id = s.id
    LEFT JOIN users u ON o.rider_id = u.id AND u.role = 'rider'
    WHERE o.id = ? AND o.customer_id = ?
");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    echo "<div class='alert alert-danger'>Order not found!</div>";
    exit;
}

// Fetch delivery tracking history
$trackStmt = $pdo->prepare("
    SELECT status, updated_at
    FROM delivery_tracking
    WHERE order_id = ?
    ORDER BY updated_at ASC
");
$trackStmt->execute([$orderId]);
$trackingHistory = $trackStmt->fetchAll();

// Define readable status labels
$statusLabels = [
    'assigned'         => 'Rider Assigned',
    'ready_for_pickup' => 'Order Ready for Pickup',
    'picked_up'        => 'Rider Picked Up Order',
    'delivered'        => 'Order Delivered'
];

// Current order status for progress bar (map to same steps)
$statusFlow = ['pending', 'preparing', 'out_for_delivery', 'delivered'];
$currentStatus = strtolower(trim($order['status']));
$currentIndex = array_search($currentStatus, $statusFlow);
if ($currentIndex === false) $currentIndex = 0;
$progressPercent = (($currentIndex + 1) / count($statusFlow)) * 100;
?>

<style>
/* ========================================
   ORDER TRACKING PAGE
======================================== */
.tracking-page {
    max-width: 1250px;
    margin: 25px auto;
}

/* ========================================
   MAIN CARD
======================================== */
.tracking-card {
    background: linear-gradient(145deg, rgba(30, 30, 38, 0.98), rgba(20, 20, 27, 0.98));
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 15px 45px rgba(0,0,0,0.35);
}

/* ========================================
   HEADER
======================================== */
.tracking-header {
    position: relative;
    padding: 28px 30px;
    background: linear-gradient(135deg, var(--primary), #ff5b35);
    color: white;
    overflow: hidden;
}
.tracking-header::before {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    top: -120px;
    right: -60px;
}
.tracking-header::after {
    content: "";
    position: absolute;
    width: 150px;
    height: 150px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    bottom: -100px;
    right: 180px;
}
.order-header-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}
.order-title {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 800;
}
.order-subtitle {
    margin-top: 7px;
    opacity: 0.9;
    font-size: 0.95rem;
}
.status-badge {
    background: rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.25);
    padding: 10px 18px;
    border-radius: 50px;
    font-weight: 700;
    white-space: nowrap;
}

/* ========================================
   BODY
======================================== */
.tracking-body {
    padding: 32px;
}

/* ========================================
   LIVE STATUS
======================================== */
.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 13px;
    border-radius: 50px;
    background: rgba(33,199,155,0.08);
    border: 1px solid rgba(33,199,155,0.15);
    color: #21c79b;
    font-size: 0.78rem;
    font-weight: 700;
}
.live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #21c79b;
    animation: livePulse 1.5s infinite;
}
@keyframes livePulse {
    0% { box-shadow: 0 0 0 0 rgba(33,199,155,0.5); }
    70% { box-shadow: 0 0 0 7px rgba(33,199,155,0); }
    100% { box-shadow: 0 0 0 0 rgba(33,199,155,0); }
}

/* ========================================
   PROGRESS SECTION
======================================== */
.progress-section {
    margin-bottom: 38px;
}
.progress-line {
    width: 100%;
    height: 8px;
    background: rgba(255,255,255,0.08);
    border-radius: 50px;
    overflow: hidden;
    margin-bottom: 32px;
}
.progress-line-fill {
    height: 100%;
    width: <?php echo $progressPercent; ?>%;
    background: linear-gradient(90deg, #21c79b, #32d7aa);
    border-radius: 50px;
    transition: width 0.6s ease;
    box-shadow: 0 0 15px rgba(33,199,155,0.5);
}

/* ========================================
   STATUS STEPS
======================================== */
.status-tracker {
    display: flex;
    justify-content: space-between;
    position: relative;
}
.status-step {
    width: 25%;
    text-align: center;
    position: relative;
}
.step-circle {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    margin: 0 auto 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.06);
    border: 2px solid rgba(255,255,255,0.08);
    color: #888;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s ease;
}
.status-step.active .step-circle {
    background: linear-gradient(135deg, var(--primary), #ff643d);
    color: white;
    border-color: transparent;
    box-shadow: 0 0 0 7px rgba(255,90,55,0.08), 0 8px 20px rgba(255,90,55,0.25);
}
.status-step.current .step-circle {
    animation: pulseStep 1.8s infinite;
}
@keyframes pulseStep {
    0% { box-shadow: 0 0 0 0 rgba(255,90,55,0.45), 0 8px 20px rgba(255,90,55,0.25); }
    70% { box-shadow: 0 0 0 14px rgba(255,90,55,0), 0 8px 20px rgba(255,90,55,0.25); }
    100% { box-shadow: 0 0 0 0 rgba(255,90,55,0), 0 8px 20px rgba(255,90,55,0.25); }
}
.step-label {
    font-size: 0.88rem;
    color: #888;
    font-weight: 600;
}
.status-step.active .step-label {
    color: var(--primary);
    font-weight: 800;
}

/* ========================================
   SECTION TITLE
======================================== */
.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    font-size: 1.2rem;
    font-weight: 800;
}
.section-title i {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,90,55,0.1);
    color: var(--primary);
}

/* ========================================
   DELIVERY TIMELINE
======================================== */
.delivery-timeline {
    position: relative;
    padding-left: 8px;
    margin-bottom: 38px;
}
.timeline-item {
    position: relative;
    display: flex;
    gap: 18px;
    padding-bottom: 24px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-icon-wrapper {
    position: relative;
    z-index: 2;
}
.timeline-dot {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,90,55,0.12);
    color: var(--primary);
    border: 1px solid rgba(255,90,55,0.2);
    font-size: 1rem;
}
.timeline-item:not(:last-child)::before {
    content: "";
    position: absolute;
    left: 21px;
    top: 44px;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, rgba(255,90,55,0.4), rgba(255,255,255,0.05));
}
.timeline-content {
    flex: 1;
    padding: 12px 16px;
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 13px;
    transition: all 0.25s ease;
}
.timeline-content:hover {
    transform: translateX(4px);
    background: rgba(255,255,255,0.055);
    border-color: rgba(255,90,55,0.25);
}
.timeline-status {
    font-weight: 750;
    font-size: 0.98rem;
    margin-bottom: 4px;
}
.timeline-date {
    color: #888;
    font-size: 0.8rem;
}

/* ========================================
   ORDER INFO CARDS - UPDATED
======================================== */
.order-info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}
.info-box {
    position: relative;
    padding: 18px;
    min-height: 175px;
    border-radius: 16px;
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.09);
    overflow: hidden;
    transition: all 0.3s ease;
}
.info-box::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), #ff643d);
    opacity: 0;
    transition: opacity 0.25s ease;
}
.info-box:hover {
    transform: translateY(-4px);
    background: rgba(255,255,255,0.055);
    border-color: rgba(255,90,55,0.28);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}
.info-box:hover::before {
    opacity: 1;
}
.info-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    background: rgba(255,90,55,0.10);
    color: var(--primary);
    font-size: 1.05rem;
}
.info-label {
    font-size: 0.72rem;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    margin-bottom: 6px;
    font-weight: 700;
}
.info-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f1f1f1;
    word-break: break-word;
    line-height: 1.4;
}
.total-box {
    background: linear-gradient(135deg, rgba(255,90,55,0.14), rgba(255,255,255,0.035));
    border-color: rgba(255,90,55,0.15);
}
.total-price {
    font-size: 1.2rem;
    color: var(--primary);
    font-weight: 850;
    line-height: 1.3;
}

/* ========================================
   DELIVERY ADDRESS
======================================== */
.address-card {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    padding: 20px;
    border-radius: 16px;
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.07);
    transition: all 0.25s ease;
}
.address-card:hover {
    background: rgba(255,255,255,0.05);
    border-color: rgba(33,199,155,0.2);
}
.address-icon {
    min-width: 46px;
    height: 46px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(33,199,155,0.12);
    color: #21c79b;
    font-size: 1.1rem;
}
.address-label {
    font-size: 0.78rem;
    color: #888;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    font-weight: 700;
}
.address-value {
    font-weight: 600;
    line-height: 1.7;
}

/* ========================================
   EMPTY TIMELINE
======================================== */
.empty-tracking {
    text-align: center;
    padding: 38px 20px;
    background: rgba(255,255,255,0.025);
    border-radius: 16px;
    border: 1px dashed rgba(255,255,255,0.1);
}
.empty-tracking i {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 12px;
}

/* ========================================
   TABLET RESPONSIVE
======================================== */
@media (max-width: 1100px) {
    .order-info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* ========================================
   MOBILE RESPONSIVE
======================================== */
@media (max-width: 768px) {
    .tracking-page {
        margin: 15px auto;
    }
    .tracking-header {
        padding: 25px 20px;
    }
    .order-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    .tracking-body {
        padding: 25px 18px;
    }
    .order-title {
        font-size: 1.4rem;
    }
    .progress-section {
        margin-bottom: 30px;
    }
    .progress-line {
        margin-bottom: 25px;
    }
    .step-circle {
        width: 42px;
        height: 42px;
        font-size: 0.8rem;
        margin-bottom: 8px;
    }
    .step-label {
        font-size: 0.65rem;
        line-height: 1.3;
    }
    .status-tracker {
        gap: 3px;
    }
    .status-step {
        width: 25%;
    }
    .order-info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .info-box {
        padding: 16px;
    }
    .timeline-item {
        gap: 14px;
    }
    .timeline-content {
        padding: 11px 14px;
    }
    .address-card {
        padding: 17px;
        gap: 14px;
    }
}

/* ========================================
   SMALL MOBILE
======================================== */
@media (max-width: 480px) {
    .tracking-body {
        padding: 20px 14px;
    }
    .tracking-header {
        padding: 22px 16px;
    }
    .order-title {
        font-size: 1.3rem;
    }
    .status-badge {
        padding: 8px 14px;
        font-size: 0.85rem;
    }
    .section-title {
        font-size: 1.05rem;
    }
    .section-title i {
        width: 36px;
        height: 36px;
    }
}
</style>

<div class="tracking-page">
    <div class="tracking-card">
        <!-- ========================================
             ORDER HEADER
        ======================================== -->
        <div class="tracking-header">
            <div class="order-header-content">
                <div>
                    <h2 class="order-title">Order #<?php echo $orderId; ?></h2>
                    <div class="order-subtitle">Track your order in real time</div>
                </div>
                <div class="status-badge">
                    <i class="fas fa-truck"></i>
                    <?php echo ucwords(str_replace('_', ' ', $order['status'])); ?>
                </div>
            </div>
        </div>

        <div class="tracking-body">
            <!-- ========================================
                 LIVE STATUS
            ======================================== -->
            <?php if ($currentStatus != 'delivered'): ?>
                <div class="live-indicator mb-4">
                    <span class="live-dot"></span>
                    Live order tracking
                </div>
            <?php endif; ?>

            <!-- ========================================
                 PROGRESS
            ======================================== -->
            <div class="progress-section">
                <div class="progress-line">
                    <div class="progress-line-fill"></div>
                </div>

                <div class="status-tracker">
                    <?php foreach ($statusFlow as $index => $step):
                        $isActive = ($index <= $currentIndex);
                        $isCurrent = ($index == $currentIndex);
                        $stepLabel = ucwords(str_replace('_', ' ', $step));
                        $stepIcons = [
                            'pending' => 'fa-clock',
                            'preparing' => 'fa-utensils',
                            'out_for_delivery' => 'fa-motorcycle',
                            'delivered' => 'fa-check'
                        ];
                    ?>
                        <div class="status-step <?php echo $isActive ? 'active' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>">
                            <div class="step-circle">
                                <i class="fas <?php echo $stepIcons[$step]; ?>"></i>
                            </div>
                            <div class="step-label">
                                <?php echo $stepLabel; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ========================================
                 DELIVERY TIMELINE
            ======================================== -->
            <div class="section-title">
                <i class="fas fa-route"></i>
                <span>Delivery Timeline</span>
            </div>

            <?php if (empty($trackingHistory)): ?>
                <div class="empty-tracking">
                    <i class="fas fa-clock"></i>
                    <h6>No delivery updates yet</h6>
                    <p class="text-muted mb-0">Your delivery updates will appear here.</p>
                </div>
            <?php else: ?>
                <div class="delivery-timeline">
                    <?php foreach ($trackingHistory as $track):
                        $timelineIcons = [
                            'assigned' => 'fa-user-check',
                            'ready_for_pickup' => 'fa-box',
                            'picked_up' => 'fa-box-open',
                            'out_for_delivery' => 'fa-motorcycle',
                            'delivered' => 'fa-check-circle'
                        ];
                        $timelineIcon = $timelineIcons[$track['status']] ?? 'fa-circle-info';
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-icon-wrapper">
                                <div class="timeline-dot">
                                    <i class="fas <?php echo $timelineIcon; ?>"></i>
                                </div>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-status">
                                    <?php echo $statusLabels[$track['status']] ?? ucfirst(str_replace('_', ' ', $track['status'])); ?>
                                </div>
                                <div class="timeline-date">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($track['updated_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ========================================
                 ORDER DETAILS
            ======================================== -->
            <div class="order-info-grid">
                <!-- STORE -->
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="info-label">Store</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['store_name']); ?></div>
                </div>

                <!-- TOTAL -->
                <div class="info-box total-box">
                    <div class="info-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="info-label">Order Total</div>
                    <div class="total-price">Rs. <?php echo number_format($order['total_amount'], 2); ?></div>
                </div>

                <!-- RIDER -->
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <div class="info-label">Delivery Rider</div>
                    <div class="info-value">
                        <?php echo $order['rider_name'] ? htmlspecialchars($order['rider_name']) : 'Not assigned yet'; ?>
                    </div>
                </div>

                <!-- PAYMENT METHOD -->
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="info-label">Payment Method</div>
                    <div class="info-value">
                        <?php echo !empty($order['payment_method']) ? htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method']))) : 'Not specified'; ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 DELIVERY ADDRESS
            ======================================== -->
            <div class="address-card">
                <div class="address-icon">
                    <i class="fas fa-location-dot"></i>
                </div>
                <div>
                    <div class="address-label">Delivery Address</div>
                    <div class="address-value"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 10 seconds
setInterval(function() {
    $.get('ajax/order_status.php?id=<?php echo $orderId; ?>', function(data) {
        if (data.status && data.status !== '<?php echo $order['status']; ?>') {
            location.reload();
        }
    });
}, 10000);
</script>