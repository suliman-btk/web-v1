<?php
/**
 * Customer: view and reply to a support ticket thread.
 * Module: Frontend & Product (Kashtu).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid      = (int) $_SESSION['user_id'];
$ticketId = (int) ($_GET['id'] ?? 0);

$ticket = db_one(
    'SELECT t.*, o.order_number FROM support_tickets t
     LEFT JOIN orders o ON o.order_id = t.order_id
     WHERE t.ticket_id = ? AND t.user_id = ?',
    [$ticketId, $uid]
);

if (!$ticket) {
    set_flash('error', 'Ticket not found.');
    redirect('support.php');
}

$errors = [];

// ── POST: add reply ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch.';
    } elseif ($ticket['status'] === 'closed') {
        $errors['general'] = 'This ticket is closed and cannot receive new replies.';
    } else {
        $message = trim($_POST['message'] ?? '');
        if ($message === '')          $errors['message'] = 'Reply cannot be empty.';
        if (mb_strlen($message) < 5)  $errors['message'] = 'Reply too short.';

        if (!$errors) {
            $pdo = db();
            $pdo->prepare(
                'INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)'
            )->execute([$ticketId, $uid, $message]);

            // If resolved, reopen ticket
            if ($ticket['status'] === 'resolved') {
                $pdo->prepare('UPDATE support_tickets SET status = "open", updated_at = NOW() WHERE ticket_id = ?')
                    ->execute([$ticketId]);
            } else {
                $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE ticket_id = ?')
                    ->execute([$ticketId]);
            }
            redirect('ticket.php?id=' . $ticketId);
        }
    }
}

// ── Load messages ──────────────────────────────────────────────────────────
$messages = db_all(
    'SELECT m.*, u.full_name, u.role
     FROM ticket_messages m
     JOIN users u ON u.user_id = m.sender_id
     WHERE m.ticket_id = ?
     ORDER BY m.created_at ASC',
    [$ticketId]
);

$statusColors = [
    'open'        => 'pill-processing',
    'in_progress' => 'pill-shipped',
    'resolved'    => 'pill-delivered',
    'closed'      => 'pill-cancelled',
];

$page_title  = 'Ticket #' . $ticketId;
$page_scripts = ['validation.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb">
    <a href="<?= e(url('index.php')) ?>">Home</a> ›
    <a href="<?= e(url('support.php')) ?>">Support</a> ›
    Ticket #<?= $ticketId ?>
</nav>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px" class="mt-2">
    <div>
        <h1><?= e($ticket['subject']) ?></h1>
        <span class="pill <?= e($statusColors[$ticket['status']] ?? 'pill-processing') ?>">
            <?= e(ucfirst(str_replace('_', ' ', $ticket['status']))) ?>
        </span>
        <?php if ($ticket['order_number']): ?>
            <span class="muted" style="font-size:.85rem;margin-left:8px">
                Related order: <a href="<?= e(url('order_history.php')) ?>"><?= e($ticket['order_number']) ?></a>
            </span>
        <?php endif; ?>
    </div>
    <a href="<?= e(url('support.php')) ?>" class="btn btn-ghost btn-sm">← Back to tickets</a>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="flash flash-error mt-2"><?= e($errors['general']) ?></div>
<?php endif; ?>

<!-- Chat thread -->
<div class="ticket-thread mt-3">
    <?php foreach ($messages as $msg): ?>
        <?php $isMe = ((int)$msg['sender_id'] === $uid); ?>
        <div style="display:flex;flex-direction:column;align-items:<?= $isMe ? 'flex-end' : 'flex-start' ?>">
            <div class="msg-bubble <?= $isMe ? 'msg-customer' : 'msg-admin' ?>">
                <?= e($msg['message']) ?>
                <div class="msg-meta">
                    <?= $isMe ? 'You' : e($msg['full_name']) ?>
                    · <?= e(date('d M Y H:i', strtotime($msg['created_at']))) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Reply form -->
<?php if ($ticket['status'] !== 'closed'): ?>
<div class="form-card mt-3">
    <h3>Add Reply</h3>
    <form method="post" action="<?= e(url('ticket.php?id=' . $ticketId)) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field <?= isset($errors['message']) ? 'has-error' : '' ?>">
            <textarea name="message" rows="4" placeholder="Type your reply…"
                      data-validate="required|min:5"><?= e($_POST['message'] ?? '') ?></textarea>
            <span class="error-msg"><?= e($errors['message'] ?? '') ?></span>
        </div>
        <button type="submit" class="btn btn-primary">Send Reply</button>
    </form>
</div>
<?php else: ?>
    <div class="flash flash-info mt-3">This ticket is closed. <a href="<?= e(url('support.php')) ?>">Open a new ticket</a> if you need further help.</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
