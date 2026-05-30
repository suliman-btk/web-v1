<?php
/**
 * User profile: view and update account details, optionally change password.
 * Shows a snapshot of recent orders.
 * Module: Authentication & User (Sulaiman).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user   = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch. Please try again.';
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $newpass   = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || mb_strlen($full_name) < 3) {
        $errors['full_name'] = 'Full name must be at least 3 characters.';
    }
    if ($phone === '' || !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    // Password change is optional
    $changePass = $newpass !== '' || $confirm !== '';
    if ($changePass) {
        if (strlen($newpass) < 8 || !preg_match('/[A-Za-z]/', $newpass) || !preg_match('/[0-9]/', $newpass)) {
            $errors['password'] = 'Password must be 8+ characters with letters and numbers.';
        } elseif ($newpass !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
    }

    if (!$errors) {
        if ($changePass) {
            $hash = password_hash($newpass, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET full_name=?, phone=?, password_hash=? WHERE user_id=?')
                ->execute([$full_name, $phone, $hash, $user['user_id']]);
        } else {
            db()->prepare('UPDATE users SET full_name=?, phone=? WHERE user_id=?')
                ->execute([$full_name, $phone, $user['user_id']]);
        }
        $_SESSION['name'] = $full_name;
        set_flash('success', 'Your profile has been updated.');
        redirect('profile.php');
    }
    // Keep submitted values on error
    $user['full_name'] = $full_name;
    $user['phone']     = $phone;
}

$recent = db_all(
    'SELECT order_number, total, status, created_at FROM orders
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
    [$user['user_id']]
);

$page_title = 'My Profile';
$page_scripts = ['validation.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › My Profile</nav>

<h1>My Profile</h1>

<div class="grid-2 mt-2">
    <div class="form-card">
        <h2>Account Details</h2>
        <?php if (!empty($errors['general'])): ?>
            <div class="flash flash-error mt-2"><?= e($errors['general']) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('profile.php')) ?>" id="profile-form" novalidate class="mt-2">
            <?= csrf_field() ?>

            <div class="field">
                <label>Email (cannot be changed)</label>
                <input type="email" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="field <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" data-validate="required|min:3">
                <span class="error-msg"><?= e($errors['full_name'] ?? '') ?></span>
            </div>
            <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?= e($user['phone']) ?>" data-validate="required|phone">
                <span class="error-msg"><?= e($errors['phone'] ?? '') ?></span>
            </div>

            <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
            <p class="muted">Leave the password fields blank to keep your current password.</p>
            <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password">
                <span class="error-msg"><?= e($errors['password'] ?? '') ?></span>
            </div>
            <div class="field <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" data-validate="match:password" autocomplete="new-password">
                <span class="error-msg"><?= e($errors['confirm_password'] ?? '') ?></span>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>

    <div class="card">
        <h2>Recent Orders</h2>
        <?php if (empty($recent)): ?>
            <p class="muted mt-2">You have no orders yet.</p>
            <a class="btn btn-primary mt-2" href="<?= e(url('products.php')) ?>">Start Shopping</a>
        <?php else: ?>
            <div class="table-wrap mt-2">
                <table class="data">
                    <thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td><?= e($o['order_number']) ?></td>
                            <td><?= e(money($o['total'])) ?></td>
                            <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                            <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a class="btn btn-outline mt-3" href="<?= e(url('order_history.php')) ?>">View All Orders</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
