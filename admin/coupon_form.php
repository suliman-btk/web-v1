<?php
/**
 * Admin: add or edit a coupon code.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$cid    = (int) ($_GET['id'] ?? 0);
$coupon = $cid ? db_one('SELECT * FROM coupons WHERE coupon_id = ?', [$cid]) : null;
$isNew  = !$coupon;

$errors = [];
$old = [
    'code'         => $coupon['code'] ?? '',
    'type'         => $coupon['type'] ?? 'percent',
    'value'        => $coupon['value'] ?? '',
    'min_subtotal' => $coupon['min_subtotal'] ?? '0',
    'active'       => $coupon['active'] ?? 1,
    'expires_at'   => $coupon['expires_at'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Security token mismatch.';
    } else {
        $old['code']         = strtoupper(trim($_POST['code'] ?? ''));
        $old['type']         = $_POST['type'] ?? 'percent';
        $old['value']        = trim($_POST['value'] ?? '');
        $old['min_subtotal'] = trim($_POST['min_subtotal'] ?? '0');
        $old['active']       = (int) ($_POST['active'] ?? 1);
        $old['expires_at']   = trim($_POST['expires_at'] ?? '');

        if (!preg_match('/^[A-Z0-9_]{2,30}$/', $old['code'])) {
            $errors['code'] = 'Code must be 2–30 uppercase letters, numbers or underscores.';
        }
        if (!in_array($old['type'], ['percent', 'fixed'], true)) {
            $errors['type'] = 'Invalid type.';
        }
        if (!is_numeric($old['value']) || (float)$old['value'] <= 0) {
            $errors['value'] = 'Value must be a positive number.';
        }
        if ($old['type'] === 'percent' && (float)$old['value'] > 100) {
            $errors['value'] = 'Percent value cannot exceed 100.';
        }
        if (!is_numeric($old['min_subtotal']) || (float)$old['min_subtotal'] < 0) {
            $errors['min_subtotal'] = 'Minimum subtotal must be 0 or more.';
        }
        if ($old['expires_at'] !== '' && !strtotime($old['expires_at'])) {
            $errors['expires_at'] = 'Invalid date.';
        }

        // Check code uniqueness (allow same code on edit)
        if (!isset($errors['code'])) {
            $existing = db_one('SELECT coupon_id FROM coupons WHERE code = ?', [$old['code']]);
            if ($existing && (int)$existing['coupon_id'] !== $cid) {
                $errors['code'] = 'This coupon code already exists.';
            }
        }

        if (!$errors) {
            $params = [
                $old['code'], $old['type'], (float)$old['value'],
                (float)$old['min_subtotal'], $old['active'],
                $old['expires_at'] ?: null,
            ];
            if ($isNew) {
                db()->prepare(
                    'INSERT INTO coupons (code, type, value, min_subtotal, active, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute($params);
                set_flash('success', 'Coupon "' . $old['code'] . '" created.');
            } else {
                $params[] = $cid;
                db()->prepare(
                    'UPDATE coupons SET code=?, type=?, value=?, min_subtotal=?, active=?, expires_at=?
                     WHERE coupon_id=?'
                )->execute($params);
                set_flash('success', 'Coupon updated.');
            }
            redirect('admin/coupons.php');
        }
    }
}

$page_title = $isNew ? 'Add Coupon' : 'Edit Coupon';
$heading    = $isNew ? 'Add Coupon' : 'Edit Coupon: ' . e($coupon['code']);
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel" style="max-width:560px">
    <?php if (!empty($errors['general'])): ?>
        <div class="flash flash-error"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/coupon_form.php' . ($cid ? '?id=' . $cid : ''))) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field <?= isset($errors['code']) ? 'has-error' : '' ?>">
            <label for="code">Coupon Code</label>
            <input type="text" id="code" name="code" value="<?= e($old['code']) ?>"
                   placeholder="e.g. SAVE50" maxlength="30"
                   data-validate="required" style="text-transform:uppercase">
            <span class="error-msg"><?= e($errors['code'] ?? '') ?></span>
        </div>

        <div class="field <?= isset($errors['type']) ? 'has-error' : '' ?>">
            <label for="type">Discount Type</label>
            <select id="type" name="type">
                <option value="percent" <?= $old['type'] === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                <option value="fixed"   <?= $old['type'] === 'fixed'   ? 'selected' : '' ?>>Fixed Amount (RM)</option>
            </select>
            <span class="error-msg"><?= e($errors['type'] ?? '') ?></span>
        </div>

        <div class="field <?= isset($errors['value']) ? 'has-error' : '' ?>">
            <label for="value">Value</label>
            <input type="number" id="value" name="value" value="<?= e($old['value']) ?>"
                   min="0.01" step="0.01" data-validate="required">
            <span class="error-msg"><?= e($errors['value'] ?? '') ?></span>
        </div>

        <div class="field <?= isset($errors['min_subtotal']) ? 'has-error' : '' ?>">
            <label for="min_subtotal">Minimum Order Subtotal (RM, 0 = no minimum)</label>
            <input type="number" id="min_subtotal" name="min_subtotal" value="<?= e($old['min_subtotal']) ?>"
                   min="0" step="0.01">
            <span class="error-msg"><?= e($errors['min_subtotal'] ?? '') ?></span>
        </div>

        <div class="field <?= isset($errors['expires_at']) ? 'has-error' : '' ?>">
            <label for="expires_at">Expiry Date (leave blank = no expiry)</label>
            <input type="date" id="expires_at" name="expires_at" value="<?= e($old['expires_at']) ?>">
            <span class="error-msg"><?= e($errors['expires_at'] ?? '') ?></span>
        </div>

        <div class="field">
            <label for="active">Status</label>
            <select id="active" name="active">
                <option value="1" <?= $old['active'] ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= !$old['active'] ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px">
            <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create Coupon' : 'Save Changes' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('admin/coupons.php')) ?>">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
