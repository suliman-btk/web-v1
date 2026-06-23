<?php
/**
 * Admin: manage registered users — delete.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$me = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) { set_flash('error', 'Invalid token.'); header('Location: users.php'); exit; }
    $uid = (int) ($_POST['user_id'] ?? 0);
    if (isset($_POST['delete_user']) && $uid && $uid !== $me) {
        db('DELETE FROM users WHERE user_id = ?', [$uid]);
        set_flash('success', 'User deleted.');
    }
    header('Location: users.php'); exit;
}

$users = db_all(
    'SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) AS order_count
     FROM users u ORDER BY u.created_at DESC'
);

$page_title = 'Manage Users';
$heading    = 'Manage Users';
require __DIR__ . '/../includes/admin_header.php';
?>
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
                <td><span class="pill <?= $u['role'] === 'admin' ? 'pill-shipped' : 'pill-processing' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
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
