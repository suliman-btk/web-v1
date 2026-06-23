<?php
/**
 * Admin: list all support tickets.
 * Module: Admin & Database (Khalid).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$validStatuses = ['open', 'in_progress', 'resolved', 'closed'];
$filter = in_array($_GET['status'] ?? '', $validStatuses) ? $_GET['status'] : '';

$params = [];
$sql = 'SELECT t.*, u.full_name AS customer_name, u.email,
               (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.ticket_id) AS msg_count
        FROM support_tickets t
        JOIN users u ON u.user_id = t.user_id';
if ($filter) {
    $sql .= ' WHERE t.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY t.updated_at DESC';
$tickets = db_all($sql, $params);

$statusColors = [
    'open'        => 'pill-processing',
    'in_progress' => 'pill-shipped',
    'resolved'    => 'pill-delivered',
    'closed'      => 'pill-cancelled',
];

$page_title = 'Support Tickets';
$heading    = 'Support Tickets';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar">
    <form method="get">
        <label>Filter:
            <select name="status" onchange="this.form.submit()">
                <option value="">All tickets</option>
                <?php foreach ($validStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filter === $s ? 'selected' : '' ?>>
                        <?= e(ucfirst(str_replace('_', ' ', $s))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <span class="muted"><?= count($tickets) ?> ticket<?= count($tickets) === 1 ? '' : 's' ?></span>
</div>

<?php if (empty($tickets)): ?>
    <div class="panel"><p class="muted">No tickets found.</p></div>
<?php else: ?>
<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>#</th><th>Customer</th><th>Subject</th><th>Status</th>
                <th>Messages</th><th>Last Updated</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr>
                <td><?= (int)$t['ticket_id'] ?></td>
                <td><strong><?= e($t['customer_name']) ?></strong><br>
                    <small class="muted"><?= e($t['email']) ?></small></td>
                <td><?= e($t['subject']) ?></td>
                <td><span class="pill <?= e($statusColors[$t['status']] ?? 'pill-processing') ?>">
                    <?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?>
                </span></td>
                <td><?= (int)$t['msg_count'] ?></td>
                <td><?= e(date('d M Y H:i', strtotime($t['updated_at']))) ?></td>
                <td><a href="<?= e(url('admin/ticket.php?id=' . (int)$t['ticket_id'])) ?>" class="btn btn-sm btn-outline">View / Reply</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
