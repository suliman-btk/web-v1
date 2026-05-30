<?php
/**
 * Admin: list / search products and delete them (the "R" + "D" of CRUD).
 * Create & Update live in product_form.php.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token mismatch.');
        redirect('admin/products.php');
    }
    $pid = (int) ($_POST['product_id'] ?? 0);
    db()->prepare('DELETE FROM products WHERE product_id = ?')->execute([$pid]);
    set_flash('success', 'Product deleted.');
    redirect('admin/products.php');
}

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = 'SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.category_id = p.category_id';
if ($q !== '') {
    $sql .= ' WHERE p.name LIKE ? OR p.brand LIKE ?';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
$sql .= ' ORDER BY p.product_id DESC';
$products = db_all($sql, $params);

$page_title = 'Manage Products';
$heading = 'Manage Products';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar">
    <form method="get" action="<?= e(url('admin/products.php')) ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products...">
        <button class="btn btn-ghost btn-sm" type="submit">Search</button>
        <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/products.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <a class="btn btn-primary" href="<?= e(url('admin/product_form.php')) ?>">➕ Add New Product</a>
</div>

<div class="table-wrap">
    <table class="data">
        <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($products)): ?>
            <tr><td colspan="7" class="text-center muted" style="padding:30px">No products found.</td></tr>
        <?php else: foreach ($products as $p): ?>
            <tr>
                <td><img class="thumb-sm" src="<?= e(url($p['image_path'] ?: 'assets/images/products/accessories.svg')) ?>" alt=""></td>
                <td><strong><?= e($p['name']) ?></strong><br><small class="muted"><?= e($p['brand']) ?></small></td>
                <td><?= e($p['category_name']) ?></td>
                <td><?= e(money(effective_price($p))) ?>
                    <?php if ($p['discount_price']): ?><br><small class="muted"><s><?= e(money($p['price'])) ?></s></small><?php endif; ?></td>
                <td><?= (int) $p['stock_quantity'] ?>
                    <?php if ((int) $p['stock_quantity'] <= LOW_STOCK_THRESHOLD): ?><span class="pill pill-pending">Low</span><?php endif; ?></td>
                <td><span class="pill <?= $p['status'] === 'active' ? 'pill-delivered' : 'pill-cancelled' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                <td class="actions">
                    <a class="btn btn-outline" href="<?= e(url('admin/product_form.php?id=' . (int) $p['product_id'])) ?>">Edit</a>
                    <form class="inline-form" method="post" action="<?= e(url('admin/products.php')) ?>"
                          onsubmit="return confirm('Delete this product? This cannot be undone.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
