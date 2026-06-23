<?php
/**
 * Customer support tickets — list my tickets + open new ticket.
 * Module: Frontend & Product (Kashtu).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = (int) $_SESSION['user_id'];

// ── POST: create new ticket ────────────────────────────────────────────────
$errors = [];
$old    = ['subject' => '', 'order_id' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch.';
    } else {
        $old['subject']  = trim($_POST['subject'] ?? '');
        $old['order_id'] = $_POST['order_id'] ?? '';
        $old['message']  = trim($_POST['message'] ?? '');

        if ($old['subject'] === '') $errors['subject'] = 'Subject is required.';
        if (mb_strlen($old['subject']) > 150) $errors['subject'] = 'Subject too long (max 150 chars).';
        if ($old['message'] === '') $errors['message'] = 'Please describe your issue.';
        if (mb_strlen($old['message']) < 10) $errors['message'] = 'Message too short (min 10 chars).';

        $linkedOrder = null;
        if ($old['order_id'] !== '') {
            $linkedOrder = db_one(
                'SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?',
                [(int)$old['order_id'], $uid]
            );
            if (!$linkedOrder) $errors['order_id'] = 'Invalid order.';
        }

        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO support_tickets (user_id, order_id, subject, status) VALUES (?, ?, ?, "open")'
                )->execute([$uid, $linkedOrder ? (int)$old['order_id'] : null, $old['subject']]);
                $ticketId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)'
                )->execute([$ticketId, $uid, $old['message']]);

                $pdo->commit();
                set_flash('success', 'Ticket opened. We\'ll reply shortly.');
                redirect('ticket.php?id=' . $ticketId);
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $errors['general'] = 'Could not create ticket: ' . $ex->getMessage();
            }
        }
    }
}

// ── Fetch my tickets ───────────────────────────────────────────────────────
$tickets = db_all(
    'SELECT t.*,
            (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.ticket_id) AS msg_count,
            (SELECT m2.message FROM ticket_messages m2 WHERE m2.ticket_id = t.ticket_id ORDER BY m2.created_at DESC LIMIT 1) AS last_message
     FROM support_tickets t
     WHERE t.user_id = ?
     ORDER BY t.updated_at DESC',
    [$uid]
);

$myOrders = db_all(
    'SELECT order_id, order_number, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC',
    [$uid]
);

$statusColors = [
    'open'        => 'pill-processing',
    'in_progress' => 'pill-shipped',
    'resolved'    => 'pill-delivered',
    'closed'      => 'pill-cancelled',
];

$page_title  = 'Support Tickets';
$page_scripts = ['validation.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › Support</nav>
<h1>Support Tickets</h1>
<p class="muted">Need help? Open a ticket and our team will get back to you.</p>

<?php if (!empty($errors['general'])): ?>
    <div class="flash flash-error mt-2"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="cart-layout mt-3">
    <div>
        <!-- New ticket form -->
        <div class="form-card mb-3">
            <h2 style="margin-bottom:16px">Open New Ticket</h2>
            <form method="post" action="<?= e(url('support.php')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field <?= isset($errors['subject']) ? 'has-error' : '' ?>">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" value="<?= e($old['subject']) ?>"
                           placeholder="Brief description of your issue" maxlength="150"
                           data-validate="required">
                    <span class="error-msg"><?= e($errors['subject'] ?? '') ?></span>
                </div>

                <div class="field <?= isset($errors['order_id']) ? 'has-error' : '' ?>">
                    <label for="order_id">Related Order <small class="muted">(optional)</small></label>
                    <select id="order_id" name="order_id">
                        <option value="">— Select an order —</option>
                        <?php foreach ($myOrders as $mo): ?>
                            <option value="<?= (int)$mo['order_id'] ?>" <?= $old['order_id'] == $mo['order_id'] ? 'selected' : '' ?>>
                                <?= e($mo['order_number']) ?> (<?= e(date('d M Y', strtotime($mo['created_at']))) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-msg"><?= e($errors['order_id'] ?? '') ?></span>
                </div>

                <div class="field <?= isset($errors['message']) ? 'has-error' : '' ?>">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5"
                              placeholder="Describe your issue in detail…"
                              data-validate="required|min:10"><?= e($old['message']) ?></textarea>
                    <span class="error-msg"><?= e($errors['message'] ?? '') ?></span>
                </div>

                <button type="submit" class="btn btn-primary">Submit Ticket</button>
            </form>
        </div>

        <!-- Ticket list -->
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <div style="font-size:2.5rem">💬</div>
                <h3>No tickets yet</h3>
                <p>Use the form above to open your first support ticket.</p>
            </div>
        <?php else: ?>
            <h2 class="mt-2">My Tickets</h2>
            <?php foreach ($tickets as $t): ?>
                <a href="<?= e(url('ticket.php?id=' . (int)$t['ticket_id'])) ?>" class="card" style="display:block;margin-top:12px;text-decoration:none;color:inherit">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap">
                        <div>
                            <strong><?= e($t['subject']) ?></strong>
                            <span class="pill <?= e($statusColors[$t['status']] ?? 'pill-processing') ?>" style="margin-left:8px"><?= e(ucfirst(str_replace('_',' ',$t['status']))) ?></span>
                        </div>
                        <small class="muted"><?= e(date('d M Y', strtotime($t['updated_at']))) ?></small>
                    </div>
                    <?php if ($t['last_message']): ?>
                        <p class="muted mt-1" style="font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= e(mb_substr($t['last_message'], 0, 100)) ?>
                        </p>
                    <?php endif; ?>
                    <small class="muted"><?= (int)$t['msg_count'] ?> message<?= $t['msg_count'] == 1 ? '' : 's' ?></small>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <aside>
        <div class="card">
            <h3>Need quick help?</h3>
            <p class="muted" style="font-size:.88rem">Check your order status in <a href="<?= e(url('order_history.php')) ?>">My Orders</a> first.</p>
            <p class="muted" style="font-size:.88rem;margin-top:8px">Typical response time: <strong>24 hours</strong>.</p>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
