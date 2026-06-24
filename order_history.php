<?php
/**
 * Customer order history with line items and current status.
 * Module: Checkout & Orders (Abdelaziz).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user   = current_user();
$orders = db_all(
    'SELECT o.*, p.txn_ref, p.method AS pay_method,
            rr.status AS return_status
     FROM orders o
     LEFT JOIN payments p ON p.order_id = o.order_id AND p.status = \'paid\'
     LEFT JOIN return_requests rr ON rr.order_id = o.order_id
     WHERE o.user_id = ? ORDER BY o.created_at DESC',
    [$user['user_id']]
);

// Preload items grouped by order
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'order_id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT * FROM order_items WHERE order_id IN ($in)", $ids) as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

$page_title = 'My Orders';
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › My Orders</nav>
<h1>My Orders</h1>

<?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="ic">📦</div>
        <h3>No orders yet</h3>
        <p>When you place an order it will appear here.</p>
        <a class="btn btn-primary mt-2" href="<?= e(url('products.php')) ?>">Start Shopping</a>
    </div>
<?php else: ?>
    <?php foreach ($orders as $o): ?>
        <div class="card mt-2">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center">
                <div>
                    <strong><?= e($o['order_number']) ?></strong>
                    <span class="muted"> · <?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></span>
                </div>
                <div>
                    <span class="pill pill-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span>
                    <span class="pill pill-payment-<?= e($o['payment_status']) ?>"><?= e(ucfirst($o['payment_status'])) ?></span>
                    <strong style="margin-left:10px"><?= e(money($o['total'])) ?></strong>
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
            <div class="summary-row mt-2"><span>Subtotal</span><span><?= e(money($o['subtotal'])) ?></span></div>
            <div class="summary-row"><span>Shipping</span><span><?= $o['shipping_fee'] > 0 ? e(money($o['shipping_fee'])) : 'Free' ?></span></div>
            <?php if ((float)$o['discount_amount'] > 0): ?>
            <div class="summary-row coupon-discount">
                <span>Discount <?= $o['coupon_code'] ? '(' . e($o['coupon_code']) . ')' : '' ?></span>
                <span>− <?= e(money($o['discount_amount'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-total" style="padding:6px 0"><span>Total</span><span><?= e(money($o['total'])) ?></span></div>
            <p class="muted mt-2" style="font-size:.85rem">
                Deliver to: <?= e($o['full_name']) ?>, <?= e($o['address']) ?>, <?= e($o['city']) ?> <?= e($o['postcode']) ?>
                <?php if ($o['pay_method']): ?>
                 &nbsp;|&nbsp; Paid via <strong><?= e(strtoupper($o['pay_method'])) ?></strong>
                <?php endif; ?>
                <?php if ($o['txn_ref']): ?>
                 &nbsp;|&nbsp; Ref: <code><?= e($o['txn_ref']) ?></code>
                <?php endif; ?>
            </p>
            <?php if ($o['status'] === 'delivered'): ?>
                <?php if ($o['return_status'] === null): ?>
                    <div class="mt-2">
                        <a href="<?= e(url('return_request.php?order_id=' . (int)$o['order_id'])) ?>"
                           class="btn btn-outline btn-sm">↩ Request Return / Refund</a>
                    </div>
                <?php else: ?>
                    <div class="mt-2" style="font-size:.85rem">
                        Return request: <span class="pill pill-<?= e($o['return_status']) ?>"><?= e(ucfirst($o['return_status'])) ?></span>
                        <?php if ($o['return_status'] === 'rejected'): ?>
                            &nbsp;<a href="<?= e(url('return_request.php?order_id=' . (int)$o['order_id'])) ?>">Submit a new request</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
