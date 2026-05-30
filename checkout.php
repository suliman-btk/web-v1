<?php
/**
 * Checkout: collect delivery details, validate, then persist the order and
 * its line items in a single transaction (decrementing stock atomically).
 * Login is required before checkout.
 * Module: Cart & Checkout (Moaz).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$items  = cart_items();
if (empty($items)) {
    set_flash('info', 'Your cart is empty.');
    redirect('cart.php');
}
$totals = cart_totals($items);
$user   = current_user();

$errors = [];
$old = [
    'full_name' => $user['full_name'],
    'phone'     => $user['phone'],
    'address'   => '',
    'city'      => '',
    'postcode'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch. Please try again.';
    }

    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) < 3) $errors['full_name'] = 'Please enter the recipient name.';
    if ($old['phone'] === '' || !preg_match('/^[0-9+\-\s]{7,20}$/', $old['phone'])) $errors['phone'] = 'Please enter a valid phone number.';
    if ($old['address'] === '' || mb_strlen($old['address']) < 5) $errors['address'] = 'Please enter your delivery address.';
    if ($old['city'] === '') $errors['city'] = 'Please enter your city.';
    if ($old['postcode'] === '' || !preg_match('/^[0-9]{4,6}$/', $old['postcode'])) $errors['postcode'] = 'Please enter a valid postcode.';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $orderNumberPlaceholder = 'TMP-' . bin2hex(random_bytes(4));
            $stmt = $pdo->prepare(
                'INSERT INTO orders
                 (user_id, order_number, full_name, phone, address, city, postcode, subtotal, shipping_fee, total, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([
                $user['user_id'], $orderNumberPlaceholder, $old['full_name'], $old['phone'],
                $old['address'], $old['city'], $old['postcode'],
                $totals['subtotal'], $totals['shipping'], $totals['total'],
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $orderNumber = 'TN-' . (1000 + $orderId);
            $pdo->prepare('UPDATE orders SET order_number = ? WHERE order_id = ?')
                ->execute([$orderNumber, $orderId]);

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE products SET stock_quantity = stock_quantity - ?
                 WHERE product_id = ? AND stock_quantity >= ?'
            );

            foreach ($items as $it) {
                $itemStmt->execute([
                    $orderId, $it['product_id'], $it['name'],
                    $it['unit_price'], $it['qty'], $it['line_total'],
                ]);
                $stockStmt->execute([$it['qty'], $it['product_id'], $it['qty']]);
                if ($stockStmt->rowCount() === 0) {
                    throw new RuntimeException('Insufficient stock for ' . $it['name']);
                }
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            set_flash('success', 'Your order has been placed successfully!');
            redirect('order_confirm.php?order=' . urlencode($orderNumber));

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'We could not complete your order: ' . $ex->getMessage()
                . '. Please review your cart and try again.';
        }
    }
}

$page_title = 'Checkout';
$page_scripts = ['validation.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('cart.php')) ?>">Cart</a> › Checkout</nav>
<h1>Checkout</h1>

<?php if (!empty($errors['general'])): ?>
    <div class="flash flash-error mt-2"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="cart-layout mt-2">
    <div class="form-card">
        <h2>Delivery Details</h2>
        <form method="post" action="<?= e(url('checkout.php')) ?>" id="checkout-form" novalidate class="mt-2">
            <?= csrf_field() ?>
            <div class="field <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
                <label for="full_name">Recipient Name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" data-validate="required|min:3">
                <span class="error-msg"><?= e($errors['full_name'] ?? '') ?></span>
            </div>
            <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" data-validate="required|phone">
                <span class="error-msg"><?= e($errors['phone'] ?? '') ?></span>
            </div>
            <div class="field <?= isset($errors['address']) ? 'has-error' : '' ?>">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="2" data-validate="required|min:5"><?= e($old['address']) ?></textarea>
                <span class="error-msg"><?= e($errors['address'] ?? '') ?></span>
            </div>
            <div class="grid-2">
                <div class="field <?= isset($errors['city']) ? 'has-error' : '' ?>">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?= e($old['city']) ?>" data-validate="required">
                    <span class="error-msg"><?= e($errors['city'] ?? '') ?></span>
                </div>
                <div class="field <?= isset($errors['postcode']) ? 'has-error' : '' ?>">
                    <label for="postcode">Postcode</label>
                    <input type="text" id="postcode" name="postcode" value="<?= e($old['postcode']) ?>" data-validate="required" inputmode="numeric">
                    <span class="error-msg"><?= e($errors['postcode'] ?? '') ?></span>
                </div>
            </div>
            <p class="muted" style="font-size:.85rem">💳 This is a demo store — no real payment is taken. Your order is recorded as "Pending".</p>
            <button type="submit" class="btn btn-primary btn-block mt-2">Place Order</button>
        </form>
    </div>

    <aside class="summary-card">
        <h2>Order Summary</h2>
        <?php foreach ($items as $it): ?>
            <div class="summary-row">
                <span><?= e($it['name']) ?> × <?= (int) $it['qty'] ?></span>
                <span><?= e(money($it['line_total'])) ?></span>
            </div>
        <?php endforeach; ?>
        <hr style="border:none;border-top:1px solid var(--line);margin:10px 0">
        <div class="summary-row"><span>Subtotal</span><span><?= e(money($totals['subtotal'])) ?></span></div>
        <div class="summary-row"><span>Shipping</span><span><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free' ?></span></div>
        <div class="summary-total"><span>Total</span><span><?= e(money($totals['total'])) ?></span></div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
