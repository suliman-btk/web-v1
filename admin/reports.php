<?php
/**
 * Admin sales reports: KPI cards, Chart.js charts, CSV export, print.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// ---- Date range (default last 30 days) ----
$today  = date('Y-m-d');
$from   = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to     = $_GET['to']   ?? $today;

// Sanitise: must be valid dates, from <= to
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;
if ($from > $to) [$from, $to] = [$to, $from];

$toEnd = $to . ' 23:59:59';

// ---- CSV export ----
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all(
        'SELECT o.order_number, u.full_name, u.email, o.subtotal, o.discount_amount,
                o.shipping_fee, o.total, o.payment_status, o.payment_method,
                o.status, o.coupon_code, o.created_at
         FROM orders o JOIN users u ON u.user_id = o.user_id
         WHERE o.created_at BETWEEN ? AND ?
         ORDER BY o.created_at DESC',
        [$from, $toEnd]
    );
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="technest-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #', 'Customer', 'Email', 'Subtotal', 'Discount', 'Shipping', 'Total', 'Payment Status', 'Payment Method', 'Order Status', 'Coupon', 'Date']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['order_number'], $r['full_name'], $r['email'],
            $r['subtotal'], $r['discount_amount'], $r['shipping_fee'],
            $r['total'], $r['payment_status'], $r['payment_method'] ?? '',
            $r['status'], $r['coupon_code'] ?? '', $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ---- KPI cards (exclude cancelled) ----
$kpi = db_one(
    'SELECT COUNT(*) AS order_count,
            COALESCE(SUM(total),0) AS revenue,
            COALESCE(AVG(total),0) AS avg_order,
            COALESCE(SUM(discount_amount),0) AS total_discount
     FROM orders
     WHERE status <> "cancelled" AND created_at BETWEEN ? AND ?',
    [$from, $toEnd]
);
$itemsSold = (int)(db_one(
    'SELECT COALESCE(SUM(oi.quantity),0) AS qty
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     WHERE o.status <> "cancelled" AND o.created_at BETWEEN ? AND ?',
    [$from, $toEnd]
)['qty'] ?? 0);

// ---- Daily revenue (line chart) ----
$daily = db_all(
    'SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS rev
     FROM orders WHERE status <> "cancelled" AND created_at BETWEEN ? AND ?
     GROUP BY DATE(created_at) ORDER BY day',
    [$from, $toEnd]
);

// ---- Top products (bar chart) ----
$topProducts = db_all(
    'SELECT oi.product_name, SUM(oi.quantity) AS qty_sold
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     WHERE o.status <> "cancelled" AND o.created_at BETWEEN ? AND ?
     GROUP BY oi.product_name ORDER BY qty_sold DESC LIMIT 10',
    [$from, $toEnd]
);

// ---- Revenue by category (doughnut chart) ----
$byCat = db_all(
    'SELECT c.name AS cat, COALESCE(SUM(oi.line_total),0) AS rev
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     JOIN products p ON p.product_id = oi.product_id
     JOIN categories c ON c.category_id = p.category_id
     WHERE o.status <> "cancelled" AND o.created_at BETWEEN ? AND ?
     GROUP BY c.name ORDER BY rev DESC',
    [$from, $toEnd]
);

// ---- Orders by status (bar chart) ----
$byStatus = db_all(
    'SELECT status, COUNT(*) AS cnt FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY status',
    [$from, $toEnd]
);

// ---- Revenue by payment method (pie chart) ----
$byMethod = db_all(
    'SELECT COALESCE(payment_method, "unpaid") AS method, COALESCE(SUM(total),0) AS rev
     FROM orders WHERE created_at BETWEEN ? AND ?
     GROUP BY payment_method',
    [$from, $toEnd]
);

$page_title = 'Sales Reports';
$heading    = 'Sales Reports';
require __DIR__ . '/../includes/admin_header.php';

// Encode chart data as JSON for JavaScript
$chartDailyLabels  = json_encode(array_column($daily, 'day'));
$chartDailyData    = json_encode(array_map('floatval', array_column($daily, 'rev')));
$chartProdLabels   = json_encode(array_column($topProducts, 'product_name'));
$chartProdData     = json_encode(array_map('intval', array_column($topProducts, 'qty_sold')));
$chartCatLabels    = json_encode(array_column($byCat, 'cat'));
$chartCatData      = json_encode(array_map('floatval', array_column($byCat, 'rev')));
$chartStatLabels   = json_encode(array_column($byStatus, 'status'));
$chartStatData     = json_encode(array_map('intval', array_column($byStatus, 'cnt')));
$chartMethodLabels = json_encode(array_column($byMethod, 'method'));
$chartMethodData   = json_encode(array_map('floatval', array_column($byMethod, 'rev')));
?>

<!-- Date range filter -->
<form method="get" action="<?= e(url('admin/reports.php')) ?>" class="reports-filter no-print">
    <label>From: <input type="date" name="from" value="<?= e($from) ?>" max="<?= e($today) ?>"></label>
    <label>To: <input type="date" name="to" value="<?= e($to) ?>" max="<?= e($today) ?>"></label>
    <button class="btn btn-primary btn-sm" type="submit">Apply</button>
    <a class="btn btn-outline btn-sm" href="<?= e(url('admin/reports.php')) ?>">Reset</a>
    <a class="btn btn-success btn-sm" href="<?= e(url('admin/reports.php?export=csv&from=' . urlencode($from) . '&to=' . urlencode($to))) ?>">⬇ Export CSV</a>
    <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">🖨 Print</button>
</form>

<p class="muted print-only" style="margin-bottom:12px">Report period: <?= e($from) ?> to <?= e($to) ?> &mdash; Generated <?= e(date('d M Y H:i')) ?></p>

<!-- KPI Cards -->
<div class="stat-grid fiveup">
    <div class="stat-card"><span class="ic">💰</span><div class="label">Revenue</div><div class="value"><?= e(money($kpi['revenue'])) ?></div></div>
    <div class="stat-card"><span class="ic">🧾</span><div class="label">Orders</div><div class="value"><?= (int)$kpi['order_count'] ?></div></div>
    <div class="stat-card"><span class="ic">📦</span><div class="label">Items Sold</div><div class="value"><?= $itemsSold ?></div></div>
    <div class="stat-card"><span class="ic">📊</span><div class="label">Avg Order</div><div class="value"><?= e(money($kpi['avg_order'])) ?></div></div>
    <div class="stat-card"><span class="ic">🏷</span><div class="label">Total Discounts</div><div class="value"><?= e(money($kpi['total_discount'])) ?></div></div>
</div>

<!-- Charts row 1 -->
<div class="report-charts">
    <div class="panel chart-panel">
        <h2>Daily Revenue</h2>
        <canvas id="chartDaily" height="200"></canvas>
    </div>
    <div class="panel chart-panel">
        <h2>Revenue by Category</h2>
        <canvas id="chartCat" height="200"></canvas>
    </div>
</div>

<!-- Charts row 2 -->
<div class="report-charts">
    <div class="panel chart-panel">
        <h2>Top 10 Products (by Qty Sold)</h2>
        <canvas id="chartProducts" height="200"></canvas>
    </div>
    <div class="panel chart-panel">
        <h2>Orders by Status</h2>
        <canvas id="chartStatus" height="200"></canvas>
    </div>
</div>

<!-- Revenue by payment method -->
<div class="report-charts">
    <div class="panel chart-panel" style="max-width:420px">
        <h2>Revenue by Payment Method</h2>
        <canvas id="chartMethod" height="220"></canvas>
    </div>
    <div class="panel" style="flex:1">
        <h2>Orders Summary Table</h2>
        <?php
        $summaryOrders = db_all(
            'SELECT o.order_number, u.full_name, o.total, o.payment_status, o.status, o.created_at
             FROM orders o JOIN users u ON u.user_id = o.user_id
             WHERE o.created_at BETWEEN ? AND ?
             ORDER BY o.created_at DESC LIMIT 20',
            [$from, $toEnd]
        );
        ?>
        <div class="table-wrap mt-2">
            <table class="data">
                <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($summaryOrders as $r): ?>
                    <tr>
                        <td><?= e($r['order_number']) ?></td>
                        <td><?= e($r['full_name']) ?></td>
                        <td><?= e(money($r['total'])) ?></td>
                        <td><span class="pill pill-payment-<?= e($r['payment_status']) ?>"><?= e(ucfirst($r['payment_status'])) ?></span></td>
                        <td><span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    var BLUE_PALETTE = ['#2563eb','#22d3ee','#16a34a','#d97706','#dc2626','#7c3aed','#db2777','#0891b2','#65a30d','#f59e0b'];

    function makeChart(id, type, labels, data, label) {
        var ctx = document.getElementById(id);
        if (!ctx) return;
        new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{ label: label, data: data,
                    backgroundColor: type === 'line' ? 'rgba(37,99,235,.15)' : BLUE_PALETTE,
                    borderColor: type === 'line' ? '#2563eb' : BLUE_PALETTE,
                    borderWidth: type === 'line' ? 2 : 1,
                    fill: type === 'line',
                    tension: 0.35,
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: type !== 'line' && type !== 'bar' } },
                scales: (type === 'line' || type === 'bar') ? {
                    y: { beginAtZero: true, ticks: { callback: function(v){ return 'RM ' + v.toLocaleString(); } } }
                } : {}
            }
        });
    }

    makeChart('chartDaily',    'line',   <?= $chartDailyLabels ?>,  <?= $chartDailyData ?>,  'Revenue (RM)');
    makeChart('chartCat',      'doughnut',<?= $chartCatLabels ?>,   <?= $chartCatData ?>,    'Revenue (RM)');
    makeChart('chartProducts', 'bar',    <?= $chartProdLabels ?>,   <?= $chartProdData ?>,   'Qty Sold');
    makeChart('chartStatus',   'bar',    <?= $chartStatLabels ?>,   <?= $chartStatData ?>,   'Orders');
    makeChart('chartMethod',   'pie',    <?= $chartMethodLabels ?>, <?= $chartMethodData ?>, 'Revenue (RM)');
})();
</script>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
