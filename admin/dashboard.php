<?php
/**
 * Admin dashboard: business summary cards, recent orders and low-stock alerts.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stats = [
    'orders'    => (int) db_one('SELECT COUNT(*) c FROM orders')['c'],
    'customers' => (int) db_one('SELECT COUNT(*) c FROM users WHERE role = "customer"')['c'],
    'products'  => (int) db_one('SELECT COUNT(*) c FROM products')['c'],
    'revenue'   => (float) (db_one('SELECT COALESCE(SUM(total),0) s FROM orders WHERE status <> "cancelled"')['s']),
];

$recent = db_all(
    'SELECT o.order_number, o.total, o.status, o.created_at, u.full_name
     FROM orders o JOIN users u ON u.user_id = o.user_id
     ORDER BY o.created_at DESC LIMIT 8'
);

$lowStock = db_all(
    'SELECT p.name, p.stock_quantity, c.name AS category
     FROM products p JOIN categories c ON c.category_id = p.category_id
     WHERE p.stock_quantity <= ? AND p.status = "active"
     ORDER BY p.stock_quantity ASC LIMIT 8',
    [LOW_STOCK_THRESHOLD]
);

$page_title = 'Dashboard';
$heading = 'Dashboard Overview';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="stat-grid">
    <div class="stat-card"><span class="ic">🧾</span><div class="label">Total Orders</div><div class="value"><?= $stats['orders'] ?></div></div>
    <div class="stat-card"><span class="ic">👥</span><div class="label">Total Customers</div><div class="value"><?= $stats['customers'] ?></div></div>
    <div class="stat-card"><span class="ic">📦</span><div class="label">Total Products</div><div class="value"><?= $stats['products'] ?></div></div>
    <div class="stat-card"><span class="ic">💰</span><div class="label">Total Revenue</div><div class="value"><?= e(money($stats['revenue'])) ?></div></div>
</div>

<div class="admin-cols">
    <div class="panel">
        <div class="panel-head"><h2>Recent Orders</h2><a href="<?= e(url('admin/orders.php')) ?>">View All →</a></div>
        <?php if (empty($recent)): ?>
            <p class="muted">No orders yet.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $o): ?>
                    <tr>
                        <td><?= e($o['order_number']) ?></td>
                        <td><?= e($o['full_name']) ?></td>
                        <td><?= e(money($o['total'])) ?></td>
                        <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                        <td class="actions"><a class="btn btn-outline" href="<?= e(url('admin/orders.php')) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="panel">
            <h2>Quick Actions</h2>
            <a class="btn btn-primary btn-block mt-2" href="<?= e(url('admin/product_form.php')) ?>">➕ Add New Product</a>
            <a class="btn btn-outline btn-block mt-2" href="<?= e(url('admin/orders.php')) ?>">🧾 Manage Orders</a>
            <a class="btn btn-outline btn-block mt-2" href="<?= e(url('admin/users.php')) ?>">👥 View Customers</a>
        </div>

        <div class="panel">
            <h2>Low Stock Alert</h2>
            <?php if (empty($lowStock)): ?>
                <p class="muted">All products are well stocked. 👍</p>
            <?php else: ?>
                <?php foreach ($lowStock as $p): ?>
                    <div class="low-stock-item">
                        <div class="left"><b><?= e($p['name']) ?></b><small class="muted"><?= e($p['category']) ?></small></div>
                        <div class="qty"><?= (int) $p['stock_quantity'] ?> left</div>
                    </div>
                <?php endforeach; ?>
                <a class="btn btn-outline btn-block mt-2" href="<?= e(url('admin/products.php')) ?>">Manage Inventory →</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
