<?php
/**
 * User registration with real-time (JS) and authoritative server-side
 * (PHP) validation. On success the user is created and logged in.
 * Module: Authentication & User (Sulaiman).
 */
require_once __DIR__ . '/includes/auth.php';

// Already logged in? No need to register.
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old    = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch. Please try again.';
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $terms     = isset($_POST['terms']);
    $old = ['full_name' => $full_name, 'email' => $email, 'phone' => $phone];

    // --- Server-side validation ---
    if ($full_name === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($full_name) < 3) {
        $errors['full_name'] = 'Full name must be at least 3 characters.';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (db_one('SELECT user_id FROM users WHERE email = ?', [$email])) {
        $errors['email'] = 'An account with this email already exists.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain both letters and numbers.';
    }

    if ($confirm !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!$terms) {
        $errors['terms'] = 'You must agree to the Terms and Conditions.';
    }

    // --- Create the account ---
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'INSERT INTO users (full_name, email, password_hash, phone, role)
             VALUES (?, ?, ?, ?, "customer")'
        );
        $stmt->execute([$full_name, $email, $hash, $phone]);

        $userId = (int) db()->lastInsertId();
        $user   = db_one('SELECT * FROM users WHERE user_id = ?', [$userId]);
        login_user($user);
        set_flash('success', 'Welcome to TechNest, ' . $full_name . '! Your account has been created.');
        redirect('index.php');
    }
}

$page_title = 'Create Account';
$page_scripts = ['validation.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › Create Account</nav>

<div class="auth-narrow">
    <div class="form-card">
        <h1>Create Account</h1>
        <p class="muted">Join TechNest and start shopping today.</p>

        <?php if (!empty($errors['general'])): ?>
            <div class="flash flash-error mt-2"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('register.php')) ?>" id="register-form" novalidate class="mt-2">
            <?= csrf_field() ?>

            <div class="field <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>"
                       data-validate="required|min:3" autocomplete="name">
                <span class="error-msg"><?= e($errors['full_name'] ?? '') ?></span>
            </div>

            <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>"
                       data-validate="required|email" autocomplete="email">
                <span class="error-msg"><?= e($errors['email'] ?? '') ?></span>
            </div>

            <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>"
                       data-validate="required|phone" placeholder="e.g. 0123456789" autocomplete="tel">
                <span class="error-msg"><?= e($errors['phone'] ?? '') ?></span>
            </div>

            <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       data-validate="required|password" autocomplete="new-password">
                <div class="pw-meter" id="pw-meter"><span></span></div>
                <div class="pw-label" id="pw-label">Use at least 8 characters with letters and numbers.</div>
                <span class="error-msg"><?= e($errors['password'] ?? '') ?></span>
            </div>

            <div class="field <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       data-validate="required|match:password" autocomplete="new-password">
                <span class="error-msg"><?= e($errors['confirm_password'] ?? '') ?></span>
            </div>

            <div class="field <?= isset($errors['terms']) ? 'has-error' : '' ?>">
                <label style="font-weight:400;display:flex;gap:8px;align-items:flex-start">
                    <input type="checkbox" name="terms" value="1" data-validate="checked" style="width:auto">
                    <span>I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a>.</span>
                </label>
                <span class="error-msg"><?= e($errors['terms'] ?? '') ?></span>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="text-center mt-3 muted">Already have an account?
            <a href="<?= e(url('login.php')) ?>">Sign In</a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
