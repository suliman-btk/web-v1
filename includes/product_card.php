<?php
/**
 * Reusable product card partial.
 * Expects $product (a product row, ideally including category_name).
 * Used by index.php and products.php.
 */
$now      = effective_price($product);
$onSale   = isset($product['discount_price']) && $product['discount_price'] !== null
            && (float) $product['discount_price'] > 0;
$outStock = (int) $product['stock_quantity'] <= 0;
$img      = $product['image_path'] ?: 'assets/images/products/accessories.svg';
$detailUrl = url('product_detail.php?id=' . (int) $product['product_id']);

// Rating (use pre-fetched value if query joined it, else live lookup)
$cardRating = isset($product['avg_rating'])
    ? ['avg' => (float)$product['avg_rating'], 'count' => (int)($product['review_count'] ?? 0)]
    : product_rating((int)$product['product_id']);

// Wishlist state for logged-in user
$cardInWishlist = false;
if (is_logged_in()) {
    $cardUid = (int) current_user()['user_id'];
    $cardInWishlist = (bool) db_one(
        'SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?',
        [$cardUid, (int)$product['product_id']]
    );
}
?>
<article class="product-card">
    <div class="product-thumb">
        <?php if ($outStock): ?>
            <span class="product-badge badge-out">Out of Stock</span>
        <?php elseif ($onSale): ?>
            <span class="product-badge badge-sale">Sale</span>
        <?php elseif (!empty($product['is_featured'])): ?>
            <span class="product-badge badge-hot">Hot</span>
        <?php endif; ?>
        <a href="<?= e($detailUrl) ?>">
            <img src="<?= e(url($img)) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
        </a>
        <?php if (is_logged_in()): ?>
        <form method="post" action="<?= e(url('wishlist.php')) ?>" class="card-wishlist-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
            <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
            <button type="submit" class="wishlist-icon <?= $cardInWishlist ? 'wishlisted' : '' ?>"
                    title="<?= $cardInWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                <?= $cardInWishlist ? '♥' : '♡' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="product-body">
        <?php if (!empty($product['category_name'])): ?>
            <span class="product-cat"><?= e($product['category_name']) ?></span>
        <?php endif; ?>
        <h3 class="product-name"><a href="<?= e($detailUrl) ?>"><?= e($product['name']) ?></a></h3>
        <?php if ($cardRating['count'] > 0): ?>
        <div class="card-rating">
            <?= stars_html($cardRating['avg']) ?>
            <span class="muted" style="font-size:.78rem">(<?= $cardRating['count'] ?>)</span>
        </div>
        <?php endif; ?>
        <div class="product-price">
            <span class="now"><?= e(money($now)) ?></span>
            <?php if ($onSale): ?><span class="was"><?= e(money($product['price'])) ?></span><?php endif; ?>
        </div>
        <?php if ($outStock): ?>
            <span class="stock-out">● Out of stock</span>
            <button class="btn btn-ghost btn-block btn-sm" disabled>Unavailable</button>
        <?php else: ?>
            <span class="stock-ok">● In stock</span>
            <form method="post" action="<?= e(url('cart.php')) ?>" class="add-cart-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn btn-primary btn-block btn-sm">Add to Cart</button>
            </form>
        <?php endif; ?>
    </div>
</article>
