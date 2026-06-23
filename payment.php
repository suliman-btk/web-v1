<?php
/**
 * Simulated payment gateway — card / e-wallet / cash on delivery.
 * Called after checkout.php creates the order in 'pending'/'unpaid' state.
 * Module: Checkout & Orders (Abdelaziz).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user        = current_user();
$orderNumber = trim($_GET['order'] ?? '');

$order = db_one(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ?',
    [$orderNumber, $user['user_id']]
);

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('order_history.php');
}

// Already paid — skip to confirmation
if ($order['payment_status'] === 'paid') {
    redirect('order_confirm.php?order=' . urlencode($orderNumber));
}

$errors = [];
$method = 'card';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch. Please try again.';
    } else {
        $method = $_POST['method'] ?? 'card';
        if (!in_array($method, ['card', 'ewallet', 'cod'], true)) {
            $errors['method'] = 'Please select a payment method.';
        }

        if ($method === 'card') {
            $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
            $cardName   = trim($_POST['card_name'] ?? '');
            $cardExpiry = trim($_POST['card_expiry'] ?? '');
            $cardCvv    = trim($_POST['card_cvv'] ?? '');

            if (!preg_match('/^\d{13,19}$/', $cardNumber) || !luhn_check($cardNumber)) {
                $errors['card_number'] = 'Invalid card number.';
            }
            if (mb_strlen($cardName) < 3) {
                $errors['card_name'] = 'Please enter the cardholder name.';
            }
            if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry) || !expiry_valid($cardExpiry)) {
                $errors['card_expiry'] = 'Invalid or expired expiry date (MM/YY).';
            }
            if (!preg_match('/^\d{3,4}$/', $cardCvv)) {
                $errors['card_cvv'] = 'CVV must be 3 or 4 digits.';
            }
        }

        if ($method === 'ewallet') {
            $ewalletPhone = preg_replace('/\s+/', '', $_POST['ewallet_phone'] ?? '');
            if (!preg_match('/^[0-9+\-]{8,15}$/', $ewalletPhone)) {
                $errors['ewallet_phone'] = 'Please enter a valid phone number linked to your e-wallet.';
            }
        }

        if (!$errors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();

                $txnRef      = strtoupper('TXN-' . bin2hex(random_bytes(6)));
                $payStatus   = $method === 'cod' ? 'paid' : 'paid';
                $orderPaySt  = $method === 'cod' ? 'unpaid' : 'paid';

                $pdo->prepare(
                    'INSERT INTO payments (order_id, method, txn_ref, amount, status)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$order['order_id'], $method, $txnRef, $order['total'], $payStatus]);

                $pdo->prepare(
                    'UPDATE orders SET payment_status = ?, payment_method = ? WHERE order_id = ?'
                )->execute([$orderPaySt, $method, $order['order_id']]);

                $pdo->commit();
                set_flash('success', 'Payment ' . ($method === 'cod' ? 'registered (Cash on Delivery)' : 'successful') . '!');
                redirect('order_confirm.php?order=' . urlencode($orderNumber));

            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors['general'] = 'Payment processing failed: ' . $ex->getMessage();
            }
        }
    }
}

/** Luhn algorithm check for card number validation. */
function luhn_check(string $number): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int) $number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

/** Check card expiry MM/YY is not in the past. */
function expiry_valid(string $expiry): bool
{
    [$month, $year] = explode('/', $expiry);
    $expTs = mktime(0, 0, 0, (int) $month + 1, 1, 2000 + (int) $year);
    return $expTs >= time();
}

$lines      = db_all('SELECT * FROM order_items WHERE order_id = ?', [$order['order_id']]);
$page_title = 'Payment — ' . e($order['order_number']);
$page_scripts = ['validation.js', 'payment.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb">
    <a href="<?= e(url('index.php')) ?>">Home</a> ›
    <a href="<?= e(url('checkout.php')) ?>">Checkout</a> ›
    Payment
</nav>
<h1>Secure Payment</h1>
<p class="muted">Order <strong><?= e($order['order_number']) ?></strong> &mdash; Total: <strong><?= e(money($order['total'])) ?></strong></p>

<?php if (!empty($errors['general'])): ?>
    <div class="flash flash-error mt-2"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="cart-layout mt-2">
    <div class="form-card">
        <h2>Choose Payment Method</h2>
        <form method="post" action="<?= e(url('payment.php?order=' . urlencode($orderNumber))) ?>"
              id="payment-form" novalidate class="mt-2">
            <?= csrf_field() ?>

            <!-- Method selector -->
            <div class="payment-methods">
                <label class="method-card <?= $method === 'card' ? 'selected' : '' ?>">
                    <input type="radio" name="method" value="card" <?= $method === 'card' ? 'checked' : '' ?>> 💳 Credit / Debit Card
                </label>
                <label class="method-card <?= $method === 'ewallet' ? 'selected' : '' ?>">
                    <input type="radio" name="method" value="ewallet" <?= $method === 'ewallet' ? 'checked' : '' ?>> 📱 E-Wallet (Touch 'n Go / GrabPay)
                </label>
                <label class="method-card <?= $method === 'cod' ? 'selected' : '' ?>">
                    <input type="radio" name="method" value="cod" <?= $method === 'cod' ? 'checked' : '' ?>> 🏠 Cash on Delivery
                </label>
            </div>
            <?php if (!empty($errors['method'])): ?>
                <p class="error-msg" style="color:var(--red)"><?= e($errors['method']) ?></p>
            <?php endif; ?>

            <!-- Card fields -->
            <div id="card-fields" style="<?= $method !== 'card' ? 'display:none' : '' ?>">
                <div class="field <?= isset($errors['card_number']) ? 'has-error' : '' ?>">
                    <label for="card_number">Card Number</label>
                    <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456"
                           maxlength="19" inputmode="numeric" data-validate="required|luhn" autocomplete="cc-number">
                    <span class="error-msg"><?= e($errors['card_number'] ?? '') ?></span>
                </div>
                <div class="field <?= isset($errors['card_name']) ? 'has-error' : '' ?>">
                    <label for="card_name">Cardholder Name</label>
                    <input type="text" id="card_name" name="card_name" placeholder="Name as on card"
                           data-validate="required|min:3" autocomplete="cc-name">
                    <span class="error-msg"><?= e($errors['card_name'] ?? '') ?></span>
                </div>
                <div class="grid-2">
                    <div class="field <?= isset($errors['card_expiry']) ? 'has-error' : '' ?>">
                        <label for="card_expiry">Expiry (MM/YY)</label>
                        <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY"
                               maxlength="5" data-validate="required|expiry" autocomplete="cc-exp">
                        <span class="error-msg"><?= e($errors['card_expiry'] ?? '') ?></span>
                    </div>
                    <div class="field <?= isset($errors['card_cvv']) ? 'has-error' : '' ?>">
                        <label for="card_cvv">CVV</label>
                        <input type="text" id="card_cvv" name="card_cvv" placeholder="123"
                               maxlength="4" inputmode="numeric" data-validate="required|cvv" autocomplete="cc-csc">
                        <span class="error-msg"><?= e($errors['card_cvv'] ?? '') ?></span>
                    </div>
                </div>
                <p class="muted" style="font-size:.8rem;margin-top:4px">🔒 Demo only — enter any valid-format card number (e.g. 4111 1111 1111 1111).</p>
            </div>

            <!-- E-wallet fields -->
            <div id="ewallet-fields" style="<?= $method !== 'ewallet' ? 'display:none' : '' ?>">
                <div class="field <?= isset($errors['ewallet_phone']) ? 'has-error' : '' ?>">
                    <label for="ewallet_phone">Phone Number (linked to e-wallet)</label>
                    <input type="tel" id="ewallet_phone" name="ewallet_phone"
                           placeholder="e.g. 0123456789" data-validate="required|phone">
                    <span class="error-msg"><?= e($errors['ewallet_phone'] ?? '') ?></span>
                </div>
                <p class="muted" style="font-size:.8rem;margin-top:4px">📱 Simulated — no real charge is made.</p>
            </div>

            <!-- COD note -->
            <div id="cod-fields" style="<?= $method !== 'cod' ? 'display:none' : '' ?>">
                <div class="cod-note">
                    <p>💵 Pay in cash when your order arrives. Payment will be collected by the delivery agent.</p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-3" id="pay-btn">
                Confirm Payment — <?= e(money($order['total'])) ?>
            </button>
            <a href="<?= e(url('order_history.php')) ?>" class="btn btn-ghost btn-block mt-2">Cancel & View Orders</a>
        </form>
    </div>

    <aside class="summary-card">
        <h2>Order Summary</h2>
        <?php foreach ($lines as $l): ?>
            <div class="summary-row">
                <span><?= e($l['product_name']) ?> × <?= (int) $l['quantity'] ?></span>
                <span><?= e(money($l['line_total'])) ?></span>
            </div>
        <?php endforeach; ?>
        <hr style="border:none;border-top:1px solid var(--line);margin:10px 0">
        <div class="summary-row"><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
        <div class="summary-row"><span>Shipping</span><span><?= $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : 'Free' ?></span></div>
        <?php if ((float)$order['discount_amount'] > 0): ?>
        <div class="summary-row coupon-discount">
            <span>Discount <?= $order['coupon_code'] ? '(' . e($order['coupon_code']) . ')' : '' ?></span>
            <span>− <?= e(money($order['discount_amount'])) ?></span>
        </div>
        <?php endif; ?>
        <div class="summary-total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
        <p class="muted mt-2" style="font-size:.8rem">Order #<?= e($order['order_number']) ?></p>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
