<?php
/**
 * Customer: submit a return/refund request for a delivered order.
 * Module: Checkout & Orders (Abdelaziz).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = (int) $_SESSION['user_id'];
$oid = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

$order = $oid ? db_one(
    'SELECT * FROM orders WHERE order_id = ? AND user_id = ? AND status = ? AND payment_status = ?',
    [$oid, $uid, 'delivered', 'paid']
) : null;

if (!$order) {
    set_flash('error', 'This order is not eligible for a return request.');
    redirect('order_history.php');
}

$existing = db_one('SELECT request_id, status FROM return_requests WHERE order_id = ?', [$oid]);
if ($existing) {
    set_flash('info', 'A return request for this order already exists (status: ' . $existing['status'] . ').');
    redirect('order_history.php');
}

$errors = [];
$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch.';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '')           $errors['reason'] = 'Please describe your reason for the return.';
        elseif (mb_strlen($reason) < 10)  $errors['reason'] = 'Reason too short (min 10 characters).';
        elseif (mb_strlen($reason) > 1000) $errors['reason'] = 'Reason too long (max 1000 characters).';

        if (!$errors) {
            db_exec(
                'INSERT INTO return_requests (order_id, user_id, reason) VALUES (?, ?, ?)',
                [$oid, $uid, $reason]
            );
            set_flash('success', 'Return request submitted. We will review it within 2–3 business days.');
            redirect('order_history.php');
        }
    }
}

$page_title = 'Request Return';
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb">
    <a href="<?= e(url('index.php')) ?>">Home</a> ›
    <a href="<?= e(url('order_history.php')) ?>">My Orders</a> ›
    Return Request
</nav>
<h1>Request a Return</h1>

<?php if (!empty($errors['general'])): ?>
    <div class="flash flash-error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="card mt-2" style="max-width:640px">
    <p><strong>Order:</strong> <?= e($order['order_number']) ?> &nbsp;|&nbsp;
       <strong>Total:</strong> <?= e(money($order['total'])) ?> &nbsp;|&nbsp;
       <strong>Placed:</strong> <?= e(date('d M Y', strtotime($order['created_at']))) ?>
    </p>

    <form method="post" action="<?= e(url('return_request.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">

        <div class="field <?= isset($errors['reason']) ? 'has-error' : '' ?>">
            <label for="reason">Reason for return <span style="color:var(--red)">*</span></label>
            <textarea id="reason" name="reason" rows="5" placeholder="Please describe why you want to return this order (e.g. wrong item, damaged, changed mind)..."><?= e($reason) ?></textarea>
            <?php if (isset($errors['reason'])): ?>
                <span class="error-msg"><?= e($errors['reason']) ?></span>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:12px;margin-top:16px">
            <button type="submit" class="btn btn-primary">Submit Request</button>
            <a href="<?= e(url('order_history.php')) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
