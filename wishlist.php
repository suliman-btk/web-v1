<?php
/**
 * Customer wishlist — list saved products, remove, add to cart.
 * Also handles wishlist toggle POSTs from product cards/detail.
 * Module: Frontend & Product (Kashtu).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$uid  = (int) $user['user_id'];

// Handle toggle / remove POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token mismatch.');
        redirect('wishlist.php');
    }

    $action  = $_POST['action'] ?? '';
    $pid     = (int) ($_POST['product_id'] ?? 0);
    $redir   = $_POST['redirect'] ?? '';
    // Only allow redirects to app paths (relative, no protocol)
    $safeDest = (preg_match('/^[\/a-zA-Z0-9_.?=&%-]+$/', $redir) && !preg_match('/^\/\//', $redir))
        ? $redir : null;

    if ($action === 'toggle' && $pid > 0) {
        $exists = db_one('SELECT wishlist_id FROM wishlists WHERE user_id = ? AND product_id = ?', [$uid, $pid]);
        if ($exists) {
            db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$uid, $pid]);
            set_flash('info', 'Removed from wishlist.');
        } else {
            db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$uid, $pid]);
            set_flash('success', 'Added to wishlist!');
        }
    } elseif ($action === 'remove' && $pid > 0) {
        db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$uid, $pid]);
        set_flash('info', 'Removed from wishlist.');
    }

    if ($safeDest) {
        header('Location: ' . $safeDest);
        exit;
    }
    redirect('wishlist.php');
}

$items = db_all(
    'SELECT p.*, c.name AS category_name, w.created_at AS saved_at
     FROM wishlists w
     JOIN products p ON p.product_id = w.product_id
     JOIN categories c ON c.category_id = p.category_id
     WHERE w.user_id = ?
     ORDER BY w.created_at DESC',
    [$uid]
);

$page_title = 'My Wishlist';
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › My Wishlist</nav>
<h1>My Wishlist <span class="muted">(<?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>)</span></h1>

<?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="ic">♡</div>
        <h3>Your wishlist is empty</h3>
        <p>Browse products and click ♡ to save them here.</p>
        <a class="btn btn-primary mt-2" href="<?= e(url('products.php')) ?>">Explore Products</a>
    </div>
<?php else: ?>
<div class="wishlist-grid mt-2">
    <?php foreach ($items as $item): ?>
    <?php
    $now      = effective_price($item);
    $onSale   = $item['discount_price'] !== null && (float)$item['discount_price'] > 0;
    $outStock = (int) $item['stock_quantity'] <= 0;
    $img      = $item['image_path'] ?: 'assets/images/products/accessories.svg';
    $ratingData = product_rating((int)$item['product_id']);
    ?>
    <div class="wishlist-card card">
        <div class="wishlist-thumb">
            <a href="<?= e(url('product_detail.php?id=' . (int)$item['product_id'])) ?>">
                <img src="<?= e(url($img)) ?>" alt="<?= e($item['name']) ?>">
            </a>
        </div>
        <div class="wishlist-info">
            <span class="product-cat"><?= e($item['category_name']) ?></span>
            <h3><a href="<?= e(url('product_detail.php?id=' . (int)$item['product_id'])) ?>"><?= e($item['name']) ?></a></h3>
            <?php if ($ratingData['count'] > 0): ?>
            <div class="card-rating">
                <?= stars_html($ratingData['avg']) ?>
                <span class="muted" style="font-size:.78rem">(<?= $ratingData['count'] ?>)</span>
            </div>
            <?php endif; ?>
            <div class="product-price">
                <span class="now"><?= e(money($now)) ?></span>
                <?php if ($onSale): ?><span class="was"><?= e(money($item['price'])) ?></span><?php endif; ?>
            </div>
            <p class="muted" style="font-size:.8rem">Saved <?= e(date('d M Y', strtotime($item['saved_at']))) ?></p>
        </div>
        <div class="wishlist-actions">
            <?php if (!$outStock): ?>
            <form method="post" action="<?= e(url('cart.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn btn-primary btn-sm">Add to Cart</button>
            </form>
            <?php else: ?>
                <span class="stock-out">● Out of stock</span>
            <?php endif; ?>
            <form method="post" action="<?= e(url('wishlist.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm wishlist-btn wishlisted">♥ Remove</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
