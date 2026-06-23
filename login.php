<?php
/**
 * User login. Validates credentials against the database and starts a
 * secure session (with session ID regeneration).
 * Module: Authentication & User (Sulaiman).
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old    = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch. Please try again.';
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old['email'] = $email;

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (!$errors) {
        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        // Verify against the stored hash. Generic message avoids leaking which field was wrong.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors['general'] = 'Invalid email or password.';
        } else {
            login_user($user);
            set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

            // Honour an intended destination, else send admins to their panel.
            $dest = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);
            if ($dest) {
                header('Location: ' . $dest);
                exit;
            }
            if ($user['role'] === 'admin')    redirect('admin/dashboard.php');
            if ($user['role'] === 'delivery') redirect('delivery/dashboard.php');
            if ($user['role'] === 'seller')   redirect('seller/dashboard.php');
            redirect('index.php');
        }
    }
}

$page_title = 'Sign In';
$page_scripts = ['validation.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › Sign In</nav>

<div class="auth-narrow">
    <div class="form-card">
        <h1>Sign In</h1>
        <p class="muted">Welcome back! Please enter your details.</p>

        <?php if (!empty($errors['general'])): ?>
            <div class="flash flash-error mt-2"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" id="login-form" novalidate class="mt-2">
            <?= csrf_field() ?>

            <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>"
                       data-validate="required|email" autocomplete="email">
                <span class="error-msg"><?= e($errors['email'] ?? '') ?></span>
            </div>

            <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       data-validate="required" autocomplete="current-password">
                <span class="error-msg"><?= e($errors['password'] ?? '') ?></span>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <p class="text-center mt-3 muted">Don't have an account?
            <a href="<?= e(url('register.php')) ?>">Create Account</a></p>

        <div class="card mt-3" style="background:var(--bg);font-size:.85rem">
            <strong>Demo accounts</strong><br>
            Admin: <code>admin@technest.com</code> / <code>Admin@123</code><br>
            Customer: <code>customer@technest.com</code> / <code>Customer@123</code>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
