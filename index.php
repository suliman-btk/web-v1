<?php
/**
 * Home / landing page.
 * Module: Frontend & Product (Kashtu).
 * Shows hero, category cards, and featured products pulled from the DB.
 */
require_once __DIR__ . '/includes/functions.php';

$categories = db_all(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.category_id
                  AND p.status = "active") AS product_count
     FROM categories c ORDER BY c.category_id'
);

$featured = db_all(
    'SELECT p.*, c.name AS category_name
     FROM products p JOIN categories c ON c.category_id = p.category_id
     WHERE p.is_featured = 1 AND p.status = "active"
     ORDER BY p.created_at DESC LIMIT 8'
);

$catIcons = [
    'smartphones' => 'assets/images/icons/smartphones.svg',
    'laptops'     => 'assets/images/icons/laptops.svg',
    'audio'       => 'assets/images/icons/audio.svg',
    'smart-home'  => 'assets/images/icons/smart-home.svg',
    'accessories' => 'assets/images/icons/accessories.svg',
];

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div>
        <span class="tag">★ NEW ARRIVALS 2026</span>
        <h1>Your One-Stop <span>Tech Shop</span></h1>
        <p>Discover the latest gadgets and electronics at unbeatable prices.
           Quality guaranteed, always.</p>
        <div class="hero-actions">
            <a href="<?= e(url('products.php')) ?>" class="btn btn-primary">Shop Now</a>
            <a href="<?= e(url('products.php?sort=discount')) ?>" class="btn btn-outline" style="color:#fff;border-color:#fff">View Deals</a>
        </div>
        <div class="hero-trust">
            <span>✓ Free Shipping over RM 200</span>
            <span>✓ 50k+ Customers</span>
            <span>✓ 2yr Warranty</span>
        </div>
    </div>
    <div class="hero-card">💻</div>
</section>

<section>
    <div class="section-head">
        <h2>Browse by Category</h2>
        <a href="<?= e(url('products.php')) ?>">View All →</a>
    </div>
    <div class="category-grid">
        <?php foreach ($categories as $cat): ?>
            <a class="category-card" href="<?= e(url('products.php?cat=' . urlencode($cat['slug']))) ?>">
                <div class="ic">
                    <?php if (isset($catIcons[$cat['slug']])): ?>
                        <img src="<?= e(url($catIcons[$cat['slug']])) ?>" alt="<?= e($cat['name']) ?>">
                    <?php else: ?>
                        <?= e($cat['icon']) ?>
                    <?php endif; ?>
                </div>
                <h3><?= e($cat['name']) ?></h3>
                <small><?= (int) $cat['product_count'] ?> items</small>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section>
    <div class="section-head">
        <h2>Featured Products</h2>
        <a href="<?= e(url('products.php')) ?>">View All →</a>
    </div>
    <div class="product-grid">
        <?php foreach ($featured as $product): ?>
            <?php require __DIR__ . '/includes/product_card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
