<?php
/**
 * Single product detail page with specifications and add-to-cart.
 * Module: Frontend & Product (Kashtu).
 */
require_once __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$product = db_one(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.category_id = p.category_id
     WHERE p.product_id = ? AND p.status = "active"',
    [$id]
);

if (!$product) {
    http_response_code(404);
    $page_title = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><div class="ic">😕</div><h3>Product not found</h3>'
       . '<a class="btn btn-primary mt-2" href="' . e(url('products.php')) . '">Back to Products</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$now      = effective_price($product);
$onSale   = $product['discount_price'] !== null && (float) $product['discount_price'] > 0;
$outStock = (int) $product['stock_quantity'] <= 0;
$img      = $product['image_path'] ?: 'assets/images/products/accessories.svg';

// Related products (same category, excluding this one)
$related = db_all(
    'SELECT p.*, c.name AS category_name FROM products p
     JOIN categories c ON c.category_id = p.category_id
     WHERE p.category_id = ? AND p.product_id <> ? AND p.status = "active"
     ORDER BY p.is_featured DESC, RAND() LIMIT 4',
    [$product['category_id'], $product['product_id']]
);

$page_title = $product['name'];
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= e(url('index.php')) ?>">Home</a> ›
    <a href="<?= e(url('products.php?cat=' . urlencode($product['category_slug']))) ?>"><?= e($product['category_name']) ?></a> ›
    <?= e($product['name']) ?>
</nav>

<div class="detail-layout">
    <div class="detail-media">
        <img src="<?= e(url($img)) ?>" alt="<?= e($product['name']) ?>">
    </div>
    <div>
        <span class="product-cat"><?= e($product['brand'] ?: $product['category_name']) ?></span>
        <h1><?= e($product['name']) ?></h1>

        <div class="detail-price">
            <span class="now"><?= e(money($now)) ?></span>
            <?php if ($onSale): ?>
                <span class="was"><?= e(money($product['price'])) ?></span>
                <span class="pill pill-cancelled">Save <?= e(money($product['price'] - $now)) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($outStock): ?>
            <p class="stock-out">● Currently out of stock</p>
        <?php else: ?>
            <p class="stock-ok">● In stock (<?= (int) $product['stock_quantity'] ?> available)</p>
        <?php endif; ?>

        <p class="mt-2"><?= nl2br(e($product['description'])) ?></p>

        <ul class="spec-list">
            <li>Brand: <?= e($product['brand'] ?: '—') ?></li>
            <li>Category: <?= e($product['category_name']) ?></li>
            <li>Warranty: 2 years manufacturer warranty</li>
            <li>Free shipping on orders over <?= e(money(SHIPPING_FREE_THRESHOLD)) ?></li>
        </ul>

        <?php if ($outStock): ?>
            <button class="btn btn-ghost" disabled>Out of Stock</button>
        <?php else: ?>
            <form method="post" action="<?= e(url('cart.php')) ?>" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                <div class="qty-control">
                    <button type="button" class="qty-minus" aria-label="Decrease">−</button>
                    <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock_quantity'] ?>" class="qty-input">
                    <button type="button" class="qty-plus" aria-label="Increase">+</button>
                </div>
                <button type="submit" class="btn btn-primary">Add to Cart</button>
                <a href="<?= e(url('products.php')) ?>" class="btn btn-outline">Continue Shopping</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($related): ?>
<section>
    <div class="section-head"><h2>Related Products</h2></div>
    <div class="product-grid">
        <?php foreach ($related as $product): ?>
            <?php require __DIR__ . '/includes/product_card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $page_scripts = ['products.js']; require __DIR__ . '/includes/footer.php'; ?>
