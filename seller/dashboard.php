<?php
/**
 * Seller dashboard — product stats + low stock + recent orders.
 * Module: Admin & Database (Khalid).
 */
require_once __DIR__ . '/../includes/auth.php';
require_seller();

// Stats
$totalProducts  = db_one('SELECT COUNT(*) AS n FROM products')['n'] ?? 0;
$activeProducts = db_one('SELECT COUNT(*) AS n FROM products WHERE status="active"')['n'] ?? 0;
$lowStock       = db_all('SELECT name, stock_quantity FROM products WHERE stock_quantity <= ? AND status="active" ORDER BY stock_quantity ASC', [LOW_STOCK_THRESHOLD]);
$recentOrders   = db_all('SELECT o.order_number, o.created_at, o.total, o.status, u.full_name AS customer
                           FROM orders o JOIN users u ON u.user_id = o.user_id
                           ORDER BY o.created_at DESC LIMIT 8');

$page_title = 'Seller Dashboard';
$heading    = 'Seller Dashboard';
require __DIR__ . '/../includes/seller_header.php';
?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$totalProducts ?></div>
            <div class="stat-label">Total Products</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$activeProducts ?></div>
            <div class="stat-label">Active Products</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚠️</div>
        <div class="stat-body">
            <div class="stat-value"><?= count($lowStock) ?></div>
            <div class="stat-label">Low Stock Items</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🛒</div>
        <div class="stat-body">
            <div class="stat-value"><?= count($recentOrders) ?></div>
            <div class="stat-label">Recent Orders</div>
        </div>
    </div>
</div>

<?php if (!empty($lowStock)): ?>
<div class="panel mt-3">
    <h2>⚠️ Low Stock Alert</h2>
    <div class="table-wrap mt-2">
        <table class="data">
            <thead><tr><th>Product</th><th>Stock</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $ls): ?>
                <tr>
                    <td><?= e($ls['name']) ?></td>
                    <td><span class="pill pill-pending"><?= (int)$ls['stock_quantity'] ?> left</span></td>
                    <td><a href="<?= e(url('seller/products.php?q=' . urlencode($ls['name']))) ?>" class="btn btn-sm btn-outline">Update Stock</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="panel mt-3">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2>Recent Orders</h2>
    </div>
    <?php if (empty($recentOrders)): ?>
        <p class="muted">No orders yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><strong><?= e($o['order_number']) ?></strong></td>
                    <td><?= e($o['customer']) ?></td>
                    <td><?= e(money($o['total'])) ?></td>
                    <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                    <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/seller_footer.php'; ?>
