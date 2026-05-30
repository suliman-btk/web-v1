<?php
/**
 * Order confirmation page shown after a successful checkout.
 * Module: Cart & Checkout (Moaz).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$orderNumber = trim($_GET['order'] ?? '');

$order = db_one(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ?',
    [$orderNumber, $user['user_id']]
);
if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('order_history.php');
}
$lines = db_all('SELECT * FROM order_items WHERE order_id = ?', [$order['order_id']]);

$page_title = 'Order Confirmed';
require __DIR__ . '/includes/header.php';
?>
<div class="card text-center" style="max-width:680px;margin:0 auto">
    <div style="font-size:3rem">✅</div>
    <h1>Thank you for your order!</h1>
    <p class="muted">Your order <strong><?= e($order['order_number']) ?></strong> has been placed successfully.</p>
    <p><span class="pill pill-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></p>
</div>

<div class="card mt-3" style="max-width:680px;margin:24px auto 0">
    <h2>Order Summary</h2>
    <div class="table-wrap mt-2">
        <table class="data">
            <thead><tr><th>Product</th><th>Unit Price</th><th>Qty</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
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
    <div class="summary-row mt-2"><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
    <div class="summary-row"><span>Shipping</span><span><?= $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : 'Free' ?></span></div>
    <div class="summary-total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>

    <h3 class="mt-3">Delivery To</h3>
    <p class="muted">
        <?= e($order['full_name']) ?><br>
        <?= e($order['address']) ?>, <?= e($order['city']) ?> <?= e($order['postcode']) ?><br>
        <?= e($order['phone']) ?>
    </p>

    <div class="mt-3">
        <a href="<?= e(url('order_history.php')) ?>" class="btn btn-primary">View My Orders</a>
        <a href="<?= e(url('products.php')) ?>" class="btn btn-outline">Continue Shopping</a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
