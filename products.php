<?php
/**
 * Product listing with search, category filter, price range, availability
 * and sorting. All filters applied server-side via prepared statements.
 * Module: Frontend & Product (Kashtu).
 */
require_once __DIR__ . '/includes/functions.php';

$categories = db_all('SELECT * FROM categories ORDER BY category_id');

// ---- Read & sanitise filters ----
$q        = trim($_GET['q'] ?? '');
$catSlug  = trim($_GET['cat'] ?? '');
$minPrice = ($_GET['min'] ?? '') !== '' ? (float) $_GET['min'] : null;
$maxPrice = ($_GET['max'] ?? '') !== '' ? (float) $_GET['max'] : null;
$avail    = $_GET['avail'] ?? 'all';      // all | in | out
$sort     = $_GET['sort'] ?? 'newest';

// ---- Build WHERE clause with bound parameters (no SQL injection) ----
$where  = ['p.status = "active"'];
$params = [];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($catSlug !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $catSlug;
}
if ($minPrice !== null) {
    $where[] = 'COALESCE(p.discount_price, p.price) >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'COALESCE(p.discount_price, p.price) <= ?';
    $params[] = $maxPrice;
}
if ($avail === 'in') {
    $where[] = 'p.stock_quantity > 0';
} elseif ($avail === 'out') {
    $where[] = 'p.stock_quantity <= 0';
}

$orderBy = match ($sort) {
    'price_low'  => 'COALESCE(p.discount_price, p.price) ASC',
    'price_high' => 'COALESCE(p.discount_price, p.price) DESC',
    'name'       => 'p.name ASC',
    'discount'   => '(p.discount_price IS NOT NULL) DESC, p.created_at DESC',
    default      => 'p.created_at DESC',
};

$sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p JOIN categories c ON c.category_id = p.category_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
$products = db_all($sql, $params);

// Active category name for the heading
$activeCat = null;
foreach ($categories as $c) {
    if ($c['slug'] === $catSlug) { $activeCat = $c; break; }
}

$page_title = $activeCat ? $activeCat['name'] : ($q !== '' ? 'Search: ' . $q : 'All Products');
$page_scripts = ['products.js'];
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> ›
    <?= e($activeCat ? $activeCat['name'] : 'All Products') ?></nav>

<div class="shop-layout">
    <!-- Filter sidebar -->
    <aside class="filter-panel">
        <h3>Filter Products</h3>
        <form method="get" action="<?= e(url('products.php')) ?>" id="filter-form">
            <div class="filter-group">
                <h4>Search</h4>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products...">
            </div>

            <div class="filter-group">
                <h4>Category</h4>
                <label><input type="radio" name="cat" value="" <?= $catSlug === '' ? 'checked' : '' ?>> All Products</label>
                <?php foreach ($categories as $c): ?>
                    <label><input type="radio" name="cat" value="<?= e($c['slug']) ?>"
                        <?= $catSlug === $c['slug'] ? 'checked' : '' ?>> <?= e($c['name']) ?></label>
                <?php endforeach; ?>
            </div>

            <div class="filter-group">
                <h4>Price Range (RM)</h4>
                <div class="price-inputs">
                    <input type="number" name="min" min="0" step="1" placeholder="Min" value="<?= e($_GET['min'] ?? '') ?>">
                    <input type="number" name="max" min="0" step="1" placeholder="Max" value="<?= e($_GET['max'] ?? '') ?>">
                </div>
            </div>

            <div class="filter-group">
                <h4>Availability</h4>
                <label><input type="radio" name="avail" value="all" <?= $avail === 'all' ? 'checked' : '' ?>> All</label>
                <label><input type="radio" name="avail" value="in"  <?= $avail === 'in'  ? 'checked' : '' ?>> In Stock</label>
                <label><input type="radio" name="avail" value="out" <?= $avail === 'out' ? 'checked' : '' ?>> Out of Stock</label>
            </div>

            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
            <a href="<?= e(url('products.php')) ?>" class="btn btn-ghost btn-block mt-2">Clear All Filters</a>
        </form>
    </aside>

    <!-- Results -->
    <section>
        <div class="shop-toolbar">
            <span class="count"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> found</span>
            <form method="get" action="<?= e(url('products.php')) ?>" id="sort-form">
                <?php foreach (['q','cat','min','max','avail'] as $keep): ?>
                    <?php if (($_GET[$keep] ?? '') !== ''): ?>
                        <input type="hidden" name="<?= e($keep) ?>" value="<?= e($_GET[$keep]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label>Sort by:
                    <select name="sort" onchange="this.form.submit()">
                        <option value="newest"     <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="price_low"  <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name"       <?= $sort === 'name' ? 'selected' : '' ?>>Name (A–Z)</option>
                        <option value="discount"   <?= $sort === 'discount' ? 'selected' : '' ?>>On Sale</option>
                    </select>
                </label>
            </form>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="ic">🔍</div>
                <h3>No products found</h3>
                <p>Try adjusting your search or filters.</p>
                <a href="<?= e(url('products.php')) ?>" class="btn btn-primary mt-2">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="product-grid" id="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php require __DIR__ . '/includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
