<?php
/**
 * Admin: view all orders and update their status.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$statuses        = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
$adminStatuses   = ['pending', 'processing', 'shipped', 'cancelled']; // admin cannot set delivered
$paymentStatuses = ['unpaid', 'paid', 'refunded'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token mismatch.');
        redirect('admin/orders.php');
    }

    $action = $_POST['action'] ?? '';
    $oid    = (int) ($_POST['order_id'] ?? 0);

    if ($action === 'assign_delivery') {
        $did = $_POST['delivery_user_id'] === '' ? null : (int) $_POST['delivery_user_id'];
        if ($did !== null && !db_one('SELECT user_id FROM users WHERE user_id = ? AND role = ?', [$did, 'delivery'])) {
            set_flash('error', 'Invalid delivery user.');
            redirect('admin/orders.php');
        }
        db_exec('UPDATE orders SET assigned_delivery_id = ? WHERE order_id = ?', [$did, $oid]);
        set_flash('success', 'Delivery assignment updated.');
        redirect('admin/orders.php');
    }

    if ($action === 'update_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, $adminStatuses, true)) {
            set_flash('error', $status === 'delivered' ? 'Only delivery staff can mark orders as delivered.' : 'Invalid status.');
            redirect('admin/orders.php');
        }
        if ($status === 'shipped') {
            $order = db_one('SELECT assigned_delivery_id FROM orders WHERE order_id = ?', [$oid]);
            if (!$order || empty($order['assigned_delivery_id'])) {
                set_flash('error', 'Assign a delivery person before setting status to "Shipped".');
                redirect('admin/orders.php');
            }
        }
        db()->prepare('UPDATE orders SET status = ? WHERE order_id = ?')->execute([$status, $oid]);
        set_flash('success', 'Order status updated.');

    } elseif ($action === 'update_payment') {
        $payStatus = $_POST['payment_status'] ?? '';
        if (!in_array($payStatus, $paymentStatuses, true)) {
            set_flash('error', 'Invalid payment status.');
            redirect('admin/orders.php');
        }
        $order = db_one('SELECT * FROM orders WHERE order_id = ?', [$oid]);
        if ($order) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE orders SET payment_status = ? WHERE order_id = ?')
                    ->execute([$payStatus, $oid]);
                if ($payStatus === 'refunded' && $order['payment_status'] === 'paid') {
                    $txnRef = strtoupper('TXN-REF-' . bin2hex(random_bytes(4)));
                    $pdo->prepare(
                        'INSERT INTO payments (order_id, method, txn_ref, amount, status) VALUES (?, ?, ?, ?, "refunded")'
                    )->execute([$oid, $order['payment_method'] ?? 'card', $txnRef, $order['total']]);
                } elseif ($payStatus === 'paid' && $order['payment_status'] === 'unpaid') {
                    $method = $order['payment_method'] ?? 'cod';
                    $txnRef = strtoupper('TXN-ADM-' . bin2hex(random_bytes(4)));
                    $pdo->prepare(
                        'INSERT INTO payments (order_id, method, txn_ref, amount, status) VALUES (?, ?, ?, ?, "paid")'
                    )->execute([$oid, $method, $txnRef, $order['total']]);
                    $pdo->prepare('UPDATE orders SET payment_method = ? WHERE order_id = ?')
                        ->execute([$method, $oid]);
                }
                $pdo->commit();
                set_flash('success', 'Payment status updated.');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                set_flash('error', 'Update failed: ' . $ex->getMessage());
            }
        }
    }

    redirect('admin/orders.php');
}

$filter = $_GET['status'] ?? '';
$params = [];
$sql = 'SELECT o.*, u.full_name AS customer_name, u.email,
               p.txn_ref, p.method AS pay_method
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        LEFT JOIN payments p ON p.order_id = o.order_id AND p.status = "paid"';
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

// Delivery staff list for assignment dropdown
$deliveryStaff = db_all("SELECT user_id, full_name FROM users WHERE role = 'delivery' ORDER BY full_name");

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
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;align-items:flex-start">
            <div>
                <strong><?= e($o['order_number']) ?></strong>
                <span class="muted"> · <?= e($o['customer_name']) ?> (<?= e($o['email']) ?>)</span><br>
                <small class="muted"><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?>
                    · Total <?= e(money($o['total'])) ?>
                    <?php if ((float)$o['discount_amount'] > 0): ?>
                        · <span class="coupon-discount">−<?= e(money($o['discount_amount'])) ?> (<?= e($o['coupon_code']) ?>)</span>
                    <?php endif; ?>
                </small><br>
                <span class="pill pill-payment-<?= e($o['payment_status']) ?>"><?= e(ucfirst($o['payment_status'])) ?></span>
                <?php if ($o['payment_method']): ?>
                    <small class="muted"><?= e(strtoupper($o['payment_method'])) ?></small>
                <?php endif; ?>
                <?php if ($o['txn_ref']): ?>
                    <small class="muted"> · <code><?= e($o['txn_ref']) ?></code></small>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <form method="post" action="<?= e(url('admin/orders.php')) ?>" style="display:flex;gap:6px;align-items:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                    <select name="status">
                        <?php foreach ($adminStatuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                        <?php if ($o['status'] === 'delivered'): ?>
                            <option value="delivered" selected disabled>Delivered (by delivery staff)</option>
                        <?php endif; ?>
                    </select>
                    <button class="btn btn-primary btn-sm" type="submit">Update</button>
                </form>
                <form method="post" action="<?= e(url('admin/orders.php')) ?>" style="display:flex;gap:6px;align-items:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_payment">
                    <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                    <select name="payment_status">
                        <?php foreach ($paymentStatuses as $ps): ?>
                            <option value="<?= e($ps) ?>" <?= $o['payment_status'] === $ps ? 'selected' : '' ?>><?= e(ucfirst($ps)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline btn-sm" type="submit">Pay Status</button>
                </form>
            </div>
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

        <?php /* Delivery assignment (Khalid — Admin & Database) */ ?>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--line);display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-size:.85rem;color:var(--muted)">🚚 Assigned to:</span>
            <?php if (!empty($deliveryStaff)): ?>
            <form method="post" action="<?= e(url('admin/orders.php')) ?>" style="display:flex;gap:6px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_delivery">
                <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                <select name="delivery_user_id" style="font-size:.85rem;padding:5px 8px">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($deliveryStaff as $ds): ?>
                        <option value="<?= (int)$ds['user_id'] ?>" <?= (int)($o['assigned_delivery_id'] ?? 0) === (int)$ds['user_id'] ? 'selected' : '' ?>>
                            <?= e($ds['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-ghost" type="submit">Assign</button>
            </form>
            <?php else: ?>
                <span class="muted" style="font-size:.82rem">(no delivery staff registered)</span>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
