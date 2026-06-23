<?php
/**
 * Delivery staff dashboard — view assigned orders, mark delivered.
 * Module: Authentication & Cart (Sulaiman).
 */
require_once __DIR__ . '/../includes/auth.php';
require_delivery();

$uid = (int) $_SESSION['user_id'];

// ── POST: mark order as delivered ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid token.');
        header('Location: dashboard.php'); exit;
    }
    $oid = (int) ($_POST['order_id'] ?? 0);
    if ($oid) {
        db(
            'UPDATE orders SET status = ? WHERE order_id = ? AND assigned_delivery_id = ? AND status = ?',
            ['delivered', $oid, $uid, 'shipped']
        );
        set_flash('success', 'Order marked as delivered.');
    }
    header('Location: dashboard.php'); exit;
}

// ── Fetch assigned orders ──────────────────────────────────────────────────
$orders = db_all(
    'SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     WHERE o.assigned_delivery_id = ?
     ORDER BY o.created_at DESC',
    [$uid]
);

$page_title = 'My Deliveries';
$heading    = 'My Deliveries';
require __DIR__ . '/../includes/delivery_header.php';
?>

<?php if (empty($orders)): ?>
    <div class="empty-state">
        <div style="font-size:3rem">🚚</div>
        <h3>No deliveries assigned</h3>
        <p>You have no orders assigned to you yet. Check back later.</p>
    </div>
<?php else: ?>
<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong><?= e($o['order_number']) ?></strong><br>
                    <small class="muted"><?= e(date('d M Y', strtotime($o['created_at']))) ?></small></td>
                <td><?= e($o['customer_name']) ?><br>
                    <small class="muted"><?= e($o['customer_phone'] ?: '—') ?></small></td>
                <td style="max-width:220px;font-size:.85rem"><?= e($o['shipping_address'] ?? '—') ?></td>
                <td><?= e(money($o['total'])) ?></td>
                <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                <td>
                <?php if ($o['status'] === 'shipped'): ?>
                    <form method="post" onsubmit="return confirm('Mark order <?= e($o['order_number']) ?> as delivered?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                        <button name="mark_delivered" class="btn btn-sm btn-primary">✓ Mark Delivered</button>
                    </form>
                <?php elseif ($o['status'] === 'delivered'): ?>
                    <span class="pill pill-delivered">Delivered</span>
                <?php else: ?>
                    <span class="muted" style="font-size:.8rem"><?= e(ucfirst($o['status'])) ?></span>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/delivery_footer.php'; ?>
