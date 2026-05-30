<?php
/**
 * Admin: view all orders and update their status.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token mismatch.');
        redirect('admin/orders.php');
    }
    $oid    = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, $statuses, true)) {
        db()->prepare('UPDATE orders SET status = ? WHERE order_id = ?')->execute([$status, $oid]);
        set_flash('success', 'Order status updated.');
    } else {
        set_flash('error', 'Invalid status.');
    }
    redirect('admin/orders.php');
}

$filter = $_GET['status'] ?? '';
$params = [];
$sql = 'SELECT o.*, u.full_name AS customer_name, u.email
        FROM orders o JOIN users u ON u.user_id = o.user_id';
if (in_array($filter, $statuses, true)) {
    $sql .= ' WHERE o.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY o.created_at DESC';
$orders = db_all($sql, $params);

// Items grouped by order
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'order_id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT * FROM order_items WHERE order_id IN ($in)", $ids) as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

$page_title = 'Manage Orders';
$heading = 'Manage Orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar">
    <form method="get" action="<?= e(url('admin/orders.php')) ?>">
        <label>Filter by status:
            <select name="status" onchange="this.form.submit()">
                <option value="">All orders</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <span class="muted"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></span>
</div>

<?php if (empty($orders)): ?>
    <div class="panel"><p class="muted">No orders found.</p></div>
<?php else: foreach ($orders as $o): ?>
    <div class="panel" id="<?= e($o['order_number']) ?>">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;align-items:center">
            <div>
                <strong><?= e($o['order_number']) ?></strong>
                <span class="muted"> · <?= e($o['customer_name']) ?> (<?= e($o['email']) ?>)</span><br>
                <small class="muted"><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?> · Total <?= e(money($o['total'])) ?></small>
            </div>
            <form method="post" action="<?= e(url('admin/orders.php')) ?>" style="display:flex;gap:8px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                <select name="status">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Update</button>
            </form>
        </div>
        <div class="table-wrap mt-2">
            <table class="data">
                <thead><tr><th>Product</th><th>Unit Price</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($itemsByOrder[$o['order_id']] ?? [] as $l): ?>
                    <tr>
                        <td><?= e($l['product_name']) ?></td>
                        <td><?= e(money($l['unit_price'])) ?></td>
                        <td><?= (int) $l['quantity'] ?></td>
                        <td><?= e(money($l['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted mt-2" style="font-size:.85rem">Deliver to: <?= e($o['full_name']) ?>, <?= e($o['address']) ?>, <?= e($o['city']) ?> <?= e($o['postcode']) ?> · <?= e($o['phone']) ?></p>
    </div>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
