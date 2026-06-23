<?php
/**
 * Single product detail page with specifications and add-to-cart.
 * Module: Frontend & Product (Kashtu).
 */
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$product = db_one(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.category_id = p.category_id
     WHERE p.product_id = ? AND p.status = "active"',
    [$id]
);

// Handle review submission
$reviewErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to leave a review.');
        redirect('product_detail.php?id=' . $id);
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $reviewErrors['general'] = 'Security token mismatch.';
    } else {
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $uid     = (int) current_user()['user_id'];
        if ($rating < 1 || $rating > 5)  $reviewErrors['rating']  = 'Please select a star rating.';
        if (mb_strlen($comment) < 10)     $reviewErrors['comment'] = 'Review must be at least 10 characters.';
        if (!$reviewErrors) {
            try {
                db()->prepare(
                    'INSERT INTO reviews (product_id, user_id, rating, comment)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)'
                )->execute([$id, $uid, $rating, $comment]);
                set_flash('success', 'Review submitted!');
            } catch (Throwable $ex) {
                $reviewErrors['general'] = 'Could not save review: ' . $ex->getMessage();
            }
            redirect('product_detail.php?id=' . $id . '#reviews');
        }
    }
}

// Handle wishlist toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'wishlist_toggle') {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to save items.');
    } elseif (csrf_verify($_POST['csrf_token'] ?? null)) {
        $uid  = (int) current_user()['user_id'];
        $wPid = (int) ($_POST['product_id'] ?? 0);
        $exists = db_one('SELECT wishlist_id FROM wishlists WHERE user_id = ? AND product_id = ?', [$uid, $wPid]);
        if ($exists) {
            db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$uid, $wPid]);
            set_flash('info', 'Removed from wishlist.');
        } else {
            db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$uid, $wPid]);
            set_flash('success', 'Added to wishlist!');
        }
    }
    redirect('product_detail.php?id=' . $id);
}

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

$ratingData = product_rating($id);
$reviews    = db_all(
    'SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.user_id = r.user_id
     WHERE r.product_id = ? ORDER BY r.created_at DESC',
    [$id]
);

// Check if current user can review (delivered order containing this product)
$canReview = false;
$hasReview = false;
if (is_logged_in()) {
    $uid = (int) current_user()['user_id'];
    $canReview = (bool) db_one(
        'SELECT 1 FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         WHERE o.user_id = ? AND o.status = "delivered" AND oi.product_id = ?',
        [$uid, $id]
    );
    $hasReview = (bool) db_one(
        'SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ?', [$id, $uid]
    );
    $inWishlist = (bool) db_one(
        'SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?', [$uid, $id]
    );
} else {
    $inWishlist = false;
}

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
        <div class="rating-summary" style="margin:6px 0">
            <?= stars_html($ratingData['avg']) ?>
            <span class="muted" style="font-size:.88rem">
                <?= number_format($ratingData['avg'], 1) ?> / 5
                (<?= $ratingData['count'] ?> review<?= $ratingData['count'] === 1 ? '' : 's' ?>)
            </span>
        </div>

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

        <!-- Wishlist toggle -->
        <?php if (is_logged_in()): ?>
        <form method="post" action="<?= e(url('product_detail.php?id=' . $id)) ?>" style="margin-top:12px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="wishlist_toggle">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-ghost wishlist-btn <?= $inWishlist ? 'wishlisted' : '' ?>">
                <?= $inWishlist ? '♥ Saved to Wishlist' : '♡ Add to Wishlist' ?>
            </button>
        </form>
        <?php else: ?>
        <p class="muted mt-2" style="font-size:.85rem"><a href="<?= e(url('login.php')) ?>">Log in</a> to save to wishlist.</p>
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

<!-- ---- Reviews Section ---- -->
<section id="reviews" class="reviews-section mt-3">
    <div class="section-head">
        <h2>Customer Reviews
            <?php if ($ratingData['count'] > 0): ?>
                <span class="muted" style="font-size:1rem;font-weight:400">
                    &nbsp;<?= stars_html($ratingData['avg']) ?>
                    <?= number_format($ratingData['avg'], 1) ?> / 5
                </span>
            <?php endif; ?>
        </h2>
    </div>

    <?php if (!empty($reviewErrors['general'])): ?>
        <div class="flash flash-error"><?= e($reviewErrors['general']) ?></div>
    <?php endif; ?>

    <!-- Review form (only for customers with a delivered order containing this product) -->
    <?php if ($canReview): ?>
    <div class="review-form-wrap card mt-2">
        <h3><?= $hasReview ? 'Update Your Review' : 'Write a Review' ?></h3>
        <?php
        $myReview = $hasReview ? db_one('SELECT * FROM reviews WHERE product_id = ? AND user_id = ?', [$id, $uid]) : null;
        ?>
        <form method="post" action="<?= e(url('product_detail.php?id=' . $id)) ?>"
              id="review-form" novalidate class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_review">

            <div class="field <?= isset($reviewErrors['rating']) ? 'has-error' : '' ?>">
                <label>Your Rating</label>
                <div class="star-picker" role="radiogroup" aria-label="Star rating">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <label class="star-label" aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">
                            <input type="radio" name="rating" value="<?= $s ?>"
                                   <?= ($myReview && (int)$myReview['rating'] === $s) ? 'checked' : '' ?>>
                            <span class="star-pick">★</span>
                        </label>
                    <?php endfor; ?>
                </div>
                <span class="error-msg"><?= e($reviewErrors['rating'] ?? '') ?></span>
            </div>

            <div class="field <?= isset($reviewErrors['comment']) ? 'has-error' : '' ?>">
                <label for="review-comment">Your Review</label>
                <textarea id="review-comment" name="comment" rows="4"
                          placeholder="Share your experience with this product (min. 10 characters)..."
                          data-validate="required|min:10"><?= e($myReview['comment'] ?? '') ?></textarea>
                <span class="error-msg"><?= e($reviewErrors['comment'] ?? '') ?></span>
            </div>
            <button class="btn btn-primary" type="submit"><?= $hasReview ? 'Update Review' : 'Submit Review' ?></button>
        </form>
    </div>
    <?php elseif (is_logged_in()): ?>
        <p class="muted mt-2">You can write a review after receiving a delivered order of this product.</p>
    <?php else: ?>
        <p class="muted mt-2"><a href="<?= e(url('login.php')) ?>">Log in</a> to write a review.</p>
    <?php endif; ?>

    <!-- Existing reviews list -->
    <?php if (empty($reviews)): ?>
        <p class="muted mt-2">No reviews yet. Be the first!</p>
    <?php else: ?>
        <div class="reviews-list mt-3">
            <?php foreach ($reviews as $rev): ?>
            <div class="review-card">
                <div class="review-header">
                    <strong><?= e($rev['full_name']) ?></strong>
                    <span class="review-stars"><?= stars_html((float)$rev['rating']) ?></span>
                    <span class="muted" style="font-size:.82rem"><?= e(date('d M Y', strtotime($rev['created_at']))) ?></span>
                </div>
                <p class="review-body"><?= nl2br(e($rev['comment'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php $page_scripts = ['products.js', 'validation.js']; require __DIR__ . '/includes/footer.php'; ?>
