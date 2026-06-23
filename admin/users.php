<?php
/**
 * Admin: manage registered users — create staff accounts, delete.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$me          = $_SESSION['user_id'];
$validRoles  = ['customer', 'seller', 'delivery', 'admin'];
$createErrors = [];
$createOld    = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'seller'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid token.');
        header('Location: users.php'); exit;
    }

    // ── Create user ──────────────────────────────────────────────────────
    if (isset($_POST['create_user'])) {
        $createOld['full_name'] = trim($_POST['full_name'] ?? '');
        $createOld['email']     = trim($_POST['email'] ?? '');
        $createOld['phone']     = trim($_POST['phone'] ?? '');
        $createOld['role']      = $_POST['role'] ?? 'seller';
        $pass                   = $_POST['password'] ?? '';

        if ($createOld['full_name'] === '') $createErrors['full_name'] = 'Name required.';
        if ($createOld['email'] === '' || !filter_var($createOld['email'], FILTER_VALIDATE_EMAIL)) $createErrors['email'] = 'Valid email required.';
        if ($pass === '' || strlen($pass) < 8) $createErrors['password'] = 'Password min 8 chars.';
        if (!in_array($createOld['role'], $validRoles, true)) $createErrors['role'] = 'Invalid role.';

        if (!$createErrors) {
            if (db_one('SELECT user_id FROM users WHERE email = ?', [$createOld['email']])) {
                $createErrors['email'] = 'Email already in use.';
            }
        }

        if (!$createErrors) {
            db(
                'INSERT INTO users (full_name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)',
                [$createOld['full_name'], $createOld['email'], password_hash($pass, PASSWORD_DEFAULT), $createOld['phone'], $createOld['role']]
            );
            set_flash('success', ucfirst($createOld['role']) . ' account created for ' . $createOld['full_name'] . '.');
            header('Location: users.php'); exit;
        }

    // ── Delete user ───────────────────────────────────────────────────────
    } elseif (isset($_POST['delete_user'])) {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid && $uid !== $me) {
            db('DELETE FROM users WHERE user_id = ?', [$uid]);
            set_flash('success', 'User deleted.');
        }
        header('Location: users.php'); exit;
    }
}

$users = db_all(
    'SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) AS order_count
     FROM users u ORDER BY u.created_at DESC'
);

$roleColors = [
    'admin'    => 'pill-shipped',
    'seller'   => 'pill-processing',
    'delivery' => 'pill-pending',
    'customer' => '',
];

$page_title = 'Manage Users';
$heading    = 'Manage Users';
require __DIR__ . '/../includes/admin_header.php';
?>

<!-- Create Staff Account -->
<div class="panel" style="max-width:680px;margin-bottom:24px">
    <h2 style="margin-bottom:16px">Create Staff Account</h2>
    <?php if ($createErrors): ?>
        <div class="flash flash-error">Please fix the errors below.</div>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field <?= isset($createErrors['full_name']) ? 'has-error' : '' ?>">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= e($createOld['full_name']) ?>" placeholder="Full Name">
                <span class="error-msg"><?= e($createErrors['full_name'] ?? '') ?></span>
            </div>
            <div class="field <?= isset($createErrors['email']) ? 'has-error' : '' ?>">
                <label>Email</label>
                <input type="email" name="email" value="<?= e($createOld['email']) ?>" placeholder="email@example.com">
                <span class="error-msg"><?= e($createErrors['email'] ?? '') ?></span>
            </div>
        </div>
        <div class="grid-2">
            <div class="field <?= isset($createErrors['password']) ? 'has-error' : '' ?>">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min 8 characters">
                <span class="error-msg"><?= e($createErrors['password'] ?? '') ?></span>
            </div>
            <div class="field">
                <label>Phone (optional)</label>
                <input type="text" name="phone" value="<?= e($createOld['phone']) ?>" placeholder="01XXXXXXXX">
            </div>
        </div>
        <div class="field" style="max-width:200px">
            <label>Role</label>
            <select name="role">
                <option value="seller"   <?= $createOld['role'] === 'seller'   ? 'selected' : '' ?>>Seller</option>
                <option value="delivery" <?= $createOld['role'] === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                <option value="admin"    <?= $createOld['role'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
                <option value="customer" <?= $createOld['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
            </select>
        </div>
        <button name="create_user" class="btn btn-primary">Create Account</button>
    </form>
</div>

<!-- User list -->
<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>#</th><th>Name</th><th>Email</th><th>Phone</th>
                <th>Role</th><th>Orders</th><th>Joined</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): $self = ((int)$u['user_id'] === (int)$me); ?>
            <tr>
                <td><?= (int)$u['user_id'] ?></td>
                <td><strong><?= e($u['full_name']) ?></strong></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['phone'] ?: '—') ?></td>
                <td><span class="pill <?= e($roleColors[$u['role']] ?? '') ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                <td><?= (int)$u['order_count'] ?></td>
                <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                <td>
                <?php if (!$self): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= e(addslashes($u['full_name'])) ?>?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                        <button name="delete_user" class="btn btn-sm btn-danger">🗑 Delete</button>
                    </form>
                <?php else: ?>
                    <span class="muted" style="font-size:.8rem">(you)</span>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
