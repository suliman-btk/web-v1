<?php
/**
 * Admin: review and resolve customer return/refund requests.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token mismatch.');
        redirect('admin/returns.php');
    }

    $rid    = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $req = $rid ? db_one(
        'SELECT rr.*, o.payment_status, o.total, o.payment_method
         FROM return_requests rr
         JOIN orders o ON o.order_id = rr.order_id
         WHERE rr.request_id = ?',
        [$rid]
    ) : null;

    if (!$req || $req['status'] !== 'pending') {
        set_flash('error', 'Request not found or already resolved.');
        redirect('admin/returns.php');
    }

    if ($action === 'approve') {
        $note = trim($_POST['admin_note'] ?? '');
        $pdo  = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE return_requests SET status = ?, admin_note = ?, resolved_at = NOW() WHERE request_id = ?'
            )->execute(['approved', $note, $rid]);

            if ($req['payment_status'] === 'paid') {
                $pdo->prepare('UPDATE orders SET payment_status = ? WHERE order_id = ?')
                    ->execute(['refunded', $req['order_id']]);

                $txnRef = strtoupper('TXN-RET-' . bin2hex(random_bytes(4)));
                $pdo->prepare(
                    'INSERT INTO payments (order_id, method, txn_ref, amount, status) VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $req['order_id'],
                    $req['payment_method'] ?? 'card',
                    $txnRef,
                    $req['total'],
                    'refunded',
                ]);
            }

            $pdo->commit();
            set_flash('success', 'Return approved and refund recorded.');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            set_flash('error', 'Transaction failed: ' . $ex->getMessage());
        }

    } elseif ($action === 'reject') {
        $note = trim($_POST['admin_note'] ?? '');
        if ($note === '') {
            set_flash('error', 'Provide a rejection reason for the customer.');
            redirect('admin/returns.php');
        }
        db_exec(
            'UPDATE return_requests SET status = ?, admin_note = ?, resolved_at = NOW() WHERE request_id = ?',
            ['rejected', $note, $rid]
        );
        set_flash('success', 'Return request rejected.');
    }

    redirect('admin/returns.php');
}

$filter  = $_GET['status'] ?? '';
$allowed = ['pending', 'approved', 'rejected'];
$params  = [];
$sql = 'SELECT rr.*, o.order_number, o.total, o.payment_status,
               u.full_name AS customer_name, u.email AS customer_email
        FROM return_requests rr
        JOIN orders o ON o.order_id = rr.order_id
        JOIN users  u ON u.user_id  = rr.user_id';
if (in_array($filter, $allowed, true)) {
    $sql   .= ' WHERE rr.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY rr.created_at DESC';
$requests = db_all($sql, $params);

$pending_count = db_one('SELECT COUNT(*) AS n FROM return_requests WHERE status = ?', ['pending'])['n'] ?? 0;

$page_title = 'Return Requests';
$heading    = 'Return Requests';
require __DIR__ . '/../includes/admin_header.php';
?>

<div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center">
    <a href="<?= e(url('admin/returns.php')) ?>"
       class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline' ?>">All</a>
    <a href="<?= e(url('admin/returns.php?status=pending')) ?>"
       class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-outline' ?>">
        Pending <?= $pending_count > 0 ? '<span class="badge">' . (int)$pending_count . '</span>' : '' ?>
    </a>
    <a href="<?= e(url('admin/returns.php?status=approved')) ?>"
       class="btn btn-sm <?= $filter === 'approved' ? 'btn-primary' : 'btn-outline' ?>">Approved</a>
    <a href="<?= e(url('admin/returns.php?status=rejected')) ?>"
       class="btn btn-sm <?= $filter === 'rejected' ? 'btn-primary' : 'btn-outline' ?>">Rejected</a>
</div>

<?php if (empty($requests)): ?>
    <div class="empty-state"><p>No return requests<?= $filter ? ' with status "' . e($filter) . '"' : '' ?>.</p></div>
<?php else: ?>
<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Order</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Reason</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
            <tr>
                <td><?= (int) $r['request_id'] ?></td>
                <td>
                    <a href="<?= e(url('admin/orders.php')) ?>"><?= e($r['order_number']) ?></a><br>
                    <span class="pill pill-payment-<?= e($r['payment_status']) ?>"><?= e(ucfirst($r['payment_status'])) ?></span>
                </td>
                <td><?= e($r['customer_name']) ?><br><small class="muted"><?= e($r['customer_email']) ?></small></td>
                <td><?= e(money($r['total'])) ?></td>
                <td style="max-width:260px;font-size:.85rem"><?= e(mb_strimwidth($r['reason'], 0, 120, '…')) ?></td>
                <td><small><?= e(date('d M Y', strtotime($r['created_at']))) ?></small></td>
                <td><span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
                    <?php if ($r['admin_note']): ?>
                        <br><small class="muted" style="font-size:.75rem"><?= e(mb_strimwidth($r['admin_note'], 0, 60, '…')) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                <?php if ($r['status'] === 'pending'): ?>
                    <details style="display:inline">
                        <summary class="btn btn-sm btn-primary" style="cursor:pointer;list-style:none">Resolve ▾</summary>
                        <div class="card mt-1" style="min-width:280px;position:absolute;z-index:10;padding:14px">
                            <p style="font-size:.85rem;margin-bottom:8px"><strong>Full reason:</strong><br><?= e($r['reason']) ?></p>
                            <form method="post" action="<?= e(url('admin/returns.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                                <div class="field">
                                    <label>Note to customer (optional for approve, required for reject)</label>
                                    <textarea name="admin_note" rows="3" placeholder="e.g. Refund will be processed in 3–5 days…"></textarea>
                                </div>
                                <div style="display:flex;gap:8px;margin-top:8px">
                                    <button type="submit" name="action" value="approve"
                                            class="btn btn-sm btn-primary"
                                            onclick="return confirm('Approve this return and process a refund?')">✓ Approve</button>
                                    <button type="submit" name="action" value="reject"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Reject this return request?')">✗ Reject</button>
                                </div>
                            </form>
                        </div>
                    </details>
                <?php else: ?>
                    <span class="muted" style="font-size:.8rem">—</span>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
