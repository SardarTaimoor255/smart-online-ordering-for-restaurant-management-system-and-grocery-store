<?php
// ==============================================
// VIEWS/AUTH/LOGIN.PHP — Plain text comparison (temporary)
// Added: Remember Me (2 days)
// ==============================================
$errors = [];
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']); // checkbox
    $old_email = $email;

    // Email validation
    if (empty($email)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address (e.g., name@example.com).";
    }

    // Password validation
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    // If no validation errors, check credentials
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // TEMPORARY: plain text comparison
        if ($user && $password == $user['password']) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_points'] = $user['points'];

            // ─── Remember Me ──────────────────────────────────────────────
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $update = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $update->execute([$token, $user['id']]);

                // Cookie expires in 2 days
                setcookie('remember_token', $token, time() + (86400 * 2), '/');
                setcookie('remember_user_id', $user['id'], time() + (86400 * 2), '/');
            } else {
                // Clear any existing token/cookie
                $update = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                $update->execute([$user['id']]);
                setcookie('remember_token', '', time() - 3600, '/');
                setcookie('remember_user_id', '', time() - 3600, '/');
            }

            // Redirect based on role
            if ($user['role'] == 'admin') {
                redirect('index.php?page=admin_dashboard');
            } elseif ($user['role'] == 'owner') {
                redirect('index.php?page=owner_dashboard');
            } elseif ($user['role'] == 'rider') {
                redirect('index.php?page=rider_dashboard');
            } else {
                redirect('index.php?page=home');
            }
        } else {
            $errors['general'] = "Invalid email or password. Please try again.";
        }
    }
}
?>
<!-- HTML part (unchanged, added checkbox) -->
<div class="row justify-content-center" style="min-height:60vh;align-items:center;">
    <div class="col-md-10 col-lg-9">
        <div class="card" style="border-radius:28px;overflow:hidden;border:1px solid rgba(255,87,34,0.15);">
            <div class="row g-0">
                <!-- Left: Decorative Panel -->
                <div class="col-md-5 d-none d-md-flex" style="background:linear-gradient(135deg,#2d1408,#1a0d07);align-items:center;justify-content:center;padding:3rem;position:relative;overflow:hidden;flex-direction:column;text-align:center;">
                    <div style="position:absolute;top:-60px;right:-60px;width:250px;height:250px;background:radial-gradient(circle,rgba(255,87,34,0.18),transparent 70%);pointer-events:none;"></div>
                    <div style="font-size:4rem;margin-bottom:1.5rem;">🍽️</div>
                    <h3 style="font-family:var(--font-display);color:var(--text-primary);margin-bottom:0.75rem;">Welcome Back!</h3>
                    <p style="color:var(--text-secondary);font-size:0.9rem;line-height:1.7;">Your favourite local restaurants are waiting. Log in to continue your orders.</p>
                    <div style="width:48px;height:3px;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:2px;margin-top:1.5rem;"></div>
                    <div class="mt-4 d-flex flex-column gap-2">
                        <div class="stat-pill" style="justify-content:center;"><i class="fas fa-shield-alt" style="color:var(--success);"></i> Secure Login</div>
                        <div class="stat-pill" style="justify-content:center;"><i class="fas fa-bolt" style="color:var(--accent);"></i> Fast & Easy</div>
                    </div>
                </div>

                <!-- Right: Form -->
                <div class="col-md-7">
                    <div class="card-body" style="padding:3rem;">
                        <div class="section-label mb-2">Account Access</div>
                        <h2 style="font-family:var(--font-display);margin-bottom:0.5rem;">Sign In</h2>
                        <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:2rem;">Don't have an account? <a href="index.php?page=register" style="color:var(--primary);font-weight:600;text-decoration:none;">Create one free →</a></p>

                        <!-- Display general error (invalid credentials) -->
                        <?php if(isset($errors['general'])): ?>
                            <div class="alert alert-danger mb-3">
                                <i class="fas fa-circle-exclamation me-2"></i><?php echo $errors['general']; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <!-- Email field -->
                            <div class="mb-4">
                                <label class="form-label">Email Address</label>
                                <div class="input-group" style="border-radius:var(--radius-md);overflow:hidden;">
                                    <span style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-right:none;padding:0 1rem;display:flex;align-items:center;color:var(--text-muted);"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" placeholder="you@example.com" value="<?php echo htmlspecialchars($old_email); ?>" required>
                                </div>
                                <?php if(isset($errors['email'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Password field -->
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <div class="input-group" style="border-radius:var(--radius-md);overflow:hidden;">
                                    <span style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-right:none;padding:0 1rem;display:flex;align-items:center;color:var(--text-muted);"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" placeholder="••••••••" required>
                                </div>
                                <?php if(isset($errors['password'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Remember Me checkbox -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
                                    <label class="form-check-label" for="rememberMe" style="font-size:0.85rem;color:var(--text-secondary);">Remember Me</label>
                                </div>
                                <a href="#" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">Forgot password?</a>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3" style="font-size:1rem;">
                                <i class="fas fa-sign-in-alt me-2"></i> Sign In
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>