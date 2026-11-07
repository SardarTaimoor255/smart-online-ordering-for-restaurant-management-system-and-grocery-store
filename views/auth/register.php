<?php
// ==============================================
// VIEWS/AUTH/REGISTER.PHP — Plain text storage (temporary)
// Added: rider approval flag (is_approved) and is_active
// ==============================================
$errors = [];
$old_input = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_input = $_POST;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Name validation
    if (empty($name)) {
        $errors['name'] = "Full name is required.";
    } elseif (strlen($name) < 3) {
        $errors['name'] = "Name must be at least 3 characters.";
    } elseif (strlen($name) > 100) {
        $errors['name'] = "Name is too long (max 100 characters).";
    } elseif (!preg_match("/^[a-zA-Z\s\-'\.]+$/", $name)) {
        $errors['name'] = "Name can only contain letters, spaces, hyphens, apostrophes, and periods.";
    }

    // Email validation
    if (empty($email)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address (e.g., name@example.com).";
    }

    // Phone validation (Pakistani format)
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required.";
    } elseif (!preg_match('/^03[0-9]{9}$/', $phone)) {
        $errors['phone'] = "Phone must be a valid Pakistani number (e.g., 03xxxxxxxxx).";
    }

    // Password validation
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = "Password must contain at least one number.";
    }

    // Role validation
    $allowed_roles = ['customer', 'owner', 'rider'];
    if (!in_array($role, $allowed_roles)) {
        $errors['role'] = "Invalid role selected.";
    }

    // Check if email already exists
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = "This email is already registered. Please login.";
        }
    }

    // If no errors, insert user
    if (empty($errors)) {
        // Plain text password (temporary for university demo)
        $hashed_password = $password;

        // Determine approval status:
        // - Riders require admin approval -> is_approved = 0, is_active = 0
        // - Customers and owners are auto-approved -> is_approved = 1, is_active = 1
        $is_approved = ($role == 'rider') ? 0 : 1;
        $is_active   = ($role == 'rider') ? 0 : 1;

        try {
            // Insert with new columns is_approved and is_active
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password, role, is_approved, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $email, $phone, $hashed_password, $role, $is_approved, $is_active]);

            // If a rider registered, notify admin (optional)
            if ($role == 'rider') {
                // Find admin user (role = 'admin') – you may adjust the admin ID
                $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $adminStmt->execute();
                $adminId = $adminStmt->fetchColumn();
                if ($adminId) {
                    addNotification($pdo, $adminId, "New Rider Registration", "Rider $name ($email) has registered and is pending approval.");
                }
            }

            $_SESSION['success'] = "Account created successfully! " . ($role == 'rider' ? "Your rider account is pending admin approval. You will be notified once approved." : "Please login.");
            redirect('index.php?page=login');
        } catch (PDOException $e) {
            $errors['general'] = "Registration failed. Please try again later.";
        }
    }
}
?>
<!-- HTML part (unchanged) -->
<div class="row justify-content-center" style="min-height:60vh;align-items:center;">
    <div class="col-md-10 col-lg-9">
        <div class="card" style="border-radius:28px;overflow:hidden;border:1px solid rgba(255,193,7,0.15);">
            <div class="row g-0">
                <div class="col-md-5 d-none d-md-flex" style="background:linear-gradient(135deg,#1a1506,#12111a);align-items:center;justify-content:center;padding:3rem;flex-direction:column;text-align:center;position:relative;overflow:hidden;">
                    <div style="position:absolute;top:-60px;left:-60px;width:250px;height:250px;background:radial-gradient(circle,rgba(255,193,7,0.12),transparent 70%);pointer-events:none;"></div>
                    <div style="font-size:4rem;margin-bottom:1.5rem;">🚀</div>
                    <h3 style="font-family:var(--font-display);color:var(--text-primary);margin-bottom:0.75rem;">Join SmartOrder</h3>
                    <p style="color:var(--text-secondary);font-size:0.9rem;line-height:1.7;">Be part of a growing community supporting local businesses. Free forever.</p>
                    <div style="width:48px;height:3px;background:linear-gradient(90deg,var(--accent),var(--primary));border-radius:2px;margin-top:1.5rem;"></div>
                    <div class="mt-4 d-flex flex-column gap-2">
                        <div class="stat-pill" style="justify-content:center;"><i class="fas fa-percent" style="color:var(--primary);"></i> Zero Commission</div>
                        <div class="stat-pill" style="justify-content:center;"><i class="fas fa-trophy" style="color:var(--accent);"></i> Earn Rewards</div>
                        <div class="stat-pill" style="justify-content:center;"><i class="fas fa-heart" style="color:#F87171;"></i> Support Locals</div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="card-body" style="padding:3rem;">
                        <div class="section-label mb-2">Create Account</div>
                        <h2 style="font-family:var(--font-display);margin-bottom:0.5rem;">Get Started Free</h2>
                        <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:2rem;">Already have an account? <a href="index.php?page=login" style="color:var(--primary);font-weight:600;text-decoration:none;">Sign in →</a></p>

                        <?php if(isset($errors['general'])): ?>
                            <div class="alert alert-danger mb-3"><i class="fas fa-circle-exclamation me-2"></i><?php echo $errors['general']; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <!-- Name field -->
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-right:none;padding:0 1rem;display:flex;align-items:center;color:var(--text-muted);border-radius:var(--radius-md) 0 0 var(--radius-md);"><i class="fas fa-user"></i></span>
                                    <input type="text" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" placeholder="Your full name" value="<?php echo htmlspecialchars($old_input['name'] ?? ''); ?>" required>
                                </div>
                                <?php if(isset($errors['name'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['name']; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Email field -->
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-right:none;padding:0 1rem;display:flex;align-items:center;color:var(--text-muted);border-radius:var(--radius-md) 0 0 var(--radius-md);"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" placeholder="you@example.com" value="<?php echo htmlspecialchars($old_input['email'] ?? ''); ?>" required>
                                </div>
                                <?php if(isset($errors['email'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Phone field -->
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-right:none;padding:0 1rem;display:flex;align-items:center;color:var(--text-muted);border-radius:var(--radius-md) 0 0 var(--radius-md);"><i class="fas fa-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" placeholder="03xxxxxxxxx" value="<?php echo htmlspecialchars($old_input['phone'] ?? ''); ?>" required>
                                </div>
                                <?php if(isset($errors['phone'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Password field -->
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-right:none;padding:0 1rem;display:flex;align-items:center;color:var(--text-muted);border-radius:var(--radius-md) 0 0 var(--radius-md);"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" placeholder="Min. 6 characters, 1 uppercase, 1 number" required>
                                </div>
                                <?php if(isset($errors['password'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                                <?php else: ?>
                                    <small class="text-muted">Must be at least 6 characters, include one uppercase letter and one number.</small>
                                <?php endif; ?>
                            </div>

                            <!-- Role field -->
                            <div class="mb-4">
                                <label class="form-label">I am a</label>
                                <select name="role" class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>">
                                    <option value="customer" <?php echo ($old_input['role'] ?? '') == 'customer' ? 'selected' : ''; ?>>🛒 Customer — Order food & groceries</option>
                                    <option value="owner" <?php echo ($old_input['role'] ?? '') == 'owner' ? 'selected' : ''; ?>>🏪 Store Owner — Sell on SmartOrder</option>
                                    <option value="rider" <?php echo ($old_input['role'] ?? '') == 'rider' ? 'selected' : ''; ?>>🏍️ Rider — Deliver orders</option>
                                </select>
                                <?php if(isset($errors['role'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['role']; ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Rider accounts require admin approval before you can start delivering.</small>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3" style="font-size:1rem;">
                                <i class="fas fa-user-plus me-2"></i> Create My Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>