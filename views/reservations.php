<?php
// views/reservations.php - Table Reservation System with Validation

if (!isLoggedIn() || $_SESSION['user_role'] != 'customer') {
    redirect('index.php?page=login');
}

$userId = $_SESSION['user_id'];
$errors = [];
$old_input = [];

// Handle reservation submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['make_reservation'])) {
    $old_input = $_POST;
    $store_id = $_POST['store_id'];
    $reservation_date = $_POST['reservation_date'];
    $reservation_time = $_POST['reservation_time'];
    $number_of_people = $_POST['number_of_people'];

    // ── Store validation ─────────────────────────────
    if (empty($store_id)) {
        $errors['store'] = "Please select a restaurant.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND store_type = 'restaurant' AND is_active = 1");
        $stmt->execute([$store_id]);
        if (!$stmt->fetch()) {
            $errors['store'] = "Selected restaurant is invalid or inactive.";
        }
    }

    // ── Date validation ──────────────────────────────
    if (empty($reservation_date)) {
        $errors['date'] = "Please select a reservation date.";
    } else {
        $today = date('Y-m-d');
        if ($reservation_date < $today) {
            $errors['date'] = "Reservation date cannot be in the past.";
        }
        // Optional: limit to 6 months in advance
        $max_date = date('Y-m-d', strtotime('+6 months'));
        if ($reservation_date > $max_date) {
            $errors['date'] = "Reservations can only be made up to 6 months in advance.";
        }
    }

    // ── Time validation ──────────────────────────────
    if (empty($reservation_time)) {
        $errors['time'] = "Please select a reservation time.";
    } else {
        // Check if time is within business hours (e.g., 11:00 to 22:00)
        $time_val = strtotime($reservation_time);
        $min_time = strtotime('11:00');
        $max_time = strtotime('22:00');
        if ($time_val < $min_time || $time_val > $max_time) {
            $errors['time'] = "Reservation time must be between 11:00 AM and 10:00 PM.";
        }
        // If date is today, ensure time is not in the past
        if ($reservation_date == date('Y-m-d') && $time_val < time()) {
            $errors['time'] = "Cannot make a reservation for a past time today.";
        }
    }

    // ── People validation ────────────────────────────
    if (empty($number_of_people)) {
        $errors['people'] = "Number of people is required.";
    } elseif (!is_numeric($number_of_people) || $number_of_people < 1 || $number_of_people > 50) {
        $errors['people'] = "Number of people must be between 1 and 50.";
    }

    // ── Check for duplicate reservation ───────────────
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT id FROM reservations 
            WHERE store_id = ? AND reservation_date = ? AND reservation_time = ? 
            AND status != 'cancelled'
        ");
        $stmt->execute([$store_id, $reservation_date, $reservation_time]);
        if ($stmt->fetch()) {
            $errors['duplicate'] = "This time slot is already booked. Please choose another time.";
        }
    }

    // ── Insert if no errors ──────────────────────────
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, store_id, reservation_date, reservation_time, number_of_people) 
                               VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $store_id, $reservation_date, $reservation_time, $number_of_people])) {
            $_SESSION['success'] = "Reservation request sent! Awaiting confirmation.";
            addNotification($pdo, $userId, "Reservation Created", "Your table reservation has been submitted.");
            redirect('index.php?page=reservations');
        } else {
            $errors['general'] = "Failed to create reservation. Please try again.";
        }
    }
}

// Fetch user's existing reservations
$reservations = $pdo->prepare("SELECT r.*, s.store_name FROM reservations r 
                               JOIN stores s ON r.store_id = s.id 
                               WHERE r.user_id = ? ORDER BY r.reservation_date DESC, r.reservation_time DESC");
$reservations->execute([$userId]);
$userReservations = $reservations->fetchAll();

// Fetch all active restaurants for dropdown
$restaurants = $pdo->query("SELECT id, store_name FROM stores WHERE store_type = 'restaurant' AND is_active = 1 ORDER BY store_name")->fetchAll();
?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4>Book a Table</h4>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                <?php endif; ?>
                <?php if (isset($errors['duplicate'])): ?>
                    <div class="alert alert-warning"><?php echo $errors['duplicate']; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <!-- Restaurant selection -->
                    <div class="mb-3">
                        <label>Select Restaurant</label>
                        <select name="store_id" class="form-select <?php echo isset($errors['store']) ? 'is-invalid' : ''; ?>" required>
                            <option value="">-- Choose Restaurant --</option>
                            <?php foreach ($restaurants as $rest): ?>
                                <option value="<?php echo $rest['id']; ?>" <?php echo (isset($old_input['store_id']) && $old_input['store_id'] == $rest['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rest['store_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['store'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['store']; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Date -->
                    <div class="mb-3">
                        <label>Date</label>
                        <input type="date" name="reservation_date" class="form-control <?php echo isset($errors['date']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($old_input['reservation_date'] ?? ''); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+6 months')); ?>" required>
                        <?php if (isset($errors['date'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['date']; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Time -->
                    <div class="mb-3">
                        <label>Time</label>
                        <input type="time" name="reservation_time" class="form-control <?php echo isset($errors['time']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($old_input['reservation_time'] ?? ''); ?>" required>
                        <?php if (isset($errors['time'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['time']; ?></div>
                        <?php else: ?>
                            <small class="text-muted">Business hours: 11:00 AM – 10:00 PM</small>
                        <?php endif; ?>
                    </div>

                    <!-- Number of people -->
                    <div class="mb-3">
                        <label>Number of People</label>
                        <input type="number" name="number_of_people" class="form-control <?php echo isset($errors['people']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($old_input['number_of_people'] ?? ''); ?>" 
                               min="1" max="50" required>
                        <?php if (isset($errors['people'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['people']; ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="make_reservation" class="btn btn-primary w-100">Reserve Now</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h4>My Reservations</h4>
            </div>
            <div class="card-body">
                <?php if (count($userReservations) == 0): ?>
                    <p class="text-muted">No reservations yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>People</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userReservations as $res): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($res['store_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($res['reservation_date'])); ?></td>
                                        <td><?php echo date('g:i A', strtotime($res['reservation_time'])); ?></td>
                                        <td><?php echo $res['number_of_people']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $res['status'] == 'confirmed' ? 'success' : ($res['status'] == 'cancelled' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($res['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
