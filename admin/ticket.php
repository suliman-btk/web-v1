<?php
/**
 * Admin: view ticket thread, reply, and manage status.
 * Module: Admin & Database (Khalid).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId  = (int) $_SESSION['user_id'];
$ticketId = (int) ($_GET['id'] ?? 0);

$ticket = db_one(
    'SELECT t.*, u.full_name AS customer_name, u.email, o.order_number
     FROM support_tickets t
     JOIN users u ON u.user_id = t.user_id
     LEFT JOIN orders o ON o.order_id = t.order_id
     WHERE t.ticket_id = ?',
    [$ticketId]
);

if (!$ticket) {
    set_flash('error', 'Ticket not found.');
    redirect('admin/tickets.php');
}

$validStatuses = ['open', 'in_progress', 'resolved', 'closed'];
$errors = [];

// ── POST: reply + optional status change ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch.';
    } else {
        $message   = trim($_POST['message'] ?? '');
        $newStatus = $_POST['status'] ?? $ticket['status'];
        if (!in_array($newStatus, $validStatuses, true)) $newStatus = $ticket['status'];

        if ($message === '') $errors['message'] = 'Reply cannot be empty.';

        if (!$errors) {
            $pdo = db();
            if ($message !== '') {
                $pdo->prepare(
                    'INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)'
                )->execute([$ticketId, $adminId, $message]);
            }
            $pdo->prepare(
                'UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE ticket_id = ?'
            )->execute([$newStatus, $ticketId]);

            set_flash('success', 'Reply sent.');
            redirect('admin/ticket.php?id=' . $ticketId);
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

$page_title = 'Ticket #' . $ticketId;
$heading    = 'Ticket #' . $ticketId . ' — ' . e($ticket['subject']);
require __DIR__ . '/../includes/admin_header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px">
    <div>
        <span class="pill <?= e($statusColors[$ticket['status']] ?? 'pill-processing') ?>">
            <?= e(ucfirst(str_replace('_', ' ', $ticket['status']))) ?>
        </span>
        <span class="muted" style="margin-left:10px;font-size:.88rem">
            From: <strong><?= e($ticket['customer_name']) ?></strong> (<?= e($ticket['email']) ?>)
            <?php if ($ticket['order_number']): ?>
                · Order: <strong><?= e($ticket['order_number']) ?></strong>
            <?php endif; ?>
        </span>
    </div>
    <a href="<?= e(url('admin/tickets.php')) ?>" class="btn btn-ghost btn-sm">← All Tickets</a>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="flash flash-error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<!-- Chat thread -->
<div class="ticket-thread">
    <?php foreach ($messages as $msg): ?>
        <?php $isAdmin = ($msg['role'] === 'admin'); ?>
        <div style="display:flex;flex-direction:column;align-items:<?= $isAdmin ? 'flex-end' : 'flex-start' ?>">
            <div class="msg-bubble <?= $isAdmin ? 'msg-admin' : 'msg-customer' ?>" style="<?= $isAdmin ? 'background:var(--blue-100);color:var(--ink)' : '' ?>">
                <?= e($msg['message']) ?>
                <div class="msg-meta">
                    <?= e($msg['full_name']) ?> (<?= e(ucfirst($msg['role'])) ?>)
                    · <?= e(date('d M Y H:i', strtotime($msg['created_at']))) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Reply + status form -->
<div class="form-card mt-3">
    <h3>Reply & Update Status</h3>
    <form method="post" action="<?= e(url('admin/ticket.php?id=' . $ticketId)) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field <?= isset($errors['message']) ? 'has-error' : '' ?>">
            <label for="message">Reply</label>
            <textarea id="message" name="message" rows="4"
                      placeholder="Type your reply to the customer…"><?= e($_POST['message'] ?? '') ?></textarea>
            <span class="error-msg"><?= e($errors['message'] ?? '') ?></span>
        </div>

        <div class="field" style="max-width:220px">
            <label for="status">Ticket Status</label>
            <select id="status" name="status">
                <?php foreach ($validStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>>
                        <?= e(ucfirst(str_replace('_', ' ', $s))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Send Reply</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
