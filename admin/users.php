<?php
/**
 * Admin: view registered users (customers + admins) with order counts.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$users = db_all(
    'SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) AS order_count
     FROM users u ORDER BY u.created_at DESC'
);

$page_title = 'Manage Users';
$heading = 'Manage Users';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Orders</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int) $u['user_id'] ?></td>
                <td><strong><?= e($u['full_name']) ?></strong></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['phone'] ?: '—') ?></td>
                <td><span class="pill <?= $u['role'] === 'admin' ? 'pill-shipped' : 'pill-processing' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                <td><?= (int) $u['order_count'] ?></td>
                <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
