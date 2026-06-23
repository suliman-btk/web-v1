<?php
/**
 * Admin: coupon code management (list, toggle, delete).
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token mismatch.');
        redirect('admin/coupons.php');
    }

    $action = $_POST['action'] ?? '';
    $cid    = (int) ($_POST['coupon_id'] ?? 0);

    if ($action === 'toggle') {
        $coupon = db_one('SELECT active FROM coupons WHERE coupon_id = ?', [$cid]);
        if ($coupon) {
            db()->prepare('UPDATE coupons SET active = ? WHERE coupon_id = ?')
                ->execute([$coupon['active'] ? 0 : 1, $cid]);
            set_flash('success', 'Coupon status updated.');
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM coupons WHERE coupon_id = ?')->execute([$cid]);
        set_flash('success', 'Coupon deleted.');
    }
    redirect('admin/coupons.php');
}

$coupons    = db_all('SELECT * FROM coupons ORDER BY created_at DESC');
$page_title = 'Manage Coupons';
$heading    = 'Manage Coupons';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-toolbar no-print">
    <a class="btn btn-primary btn-sm" href="<?= e(url('admin/coupon_form.php')) ?>">➕ Add Coupon</a>
    <span class="muted"><?= count($coupons) ?> coupon<?= count($coupons) === 1 ? '' : 's' ?></span>
</div>

<?php if (empty($coupons)): ?>
    <div class="panel"><p class="muted">No coupons yet. <a href="<?= e(url('admin/coupon_form.php')) ?>">Create one</a>.</p></div>
<?php else: ?>
<div class="panel">
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Min. Order</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($coupons as $c): ?>
                <tr>
                    <td><strong><?= e($c['code']) ?></strong></td>
                    <td><?= e(ucfirst($c['type'])) ?></td>
                    <td><?= $c['type'] === 'percent' ? e($c['value']) . '%' : e(money($c['value'])) ?></td>
                    <td><?= (float)$c['min_subtotal'] > 0 ? e(money($c['min_subtotal'])) : '—' ?></td>
                    <td><?= $c['expires_at'] ? e(date('d M Y', strtotime($c['expires_at']))) : 'No expiry' ?></td>
                    <td>
                        <span class="pill <?= $c['active'] ? 'pill-delivered' : 'pill-cancelled' ?>">
                            <?= $c['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="<?= e(url('admin/coupon_form.php?id=' . (int)$c['coupon_id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(url('admin/coupons.php')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit"><?= $c['active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('admin/coupons.php')) ?>" style="display:inline"
                              onsubmit="return confirm('Delete coupon <?= e(addslashes($c['code'])) ?>?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
