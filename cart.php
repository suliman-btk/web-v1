<?php
/**
 * Shopping cart: add / update / remove / clear items (session-based) and
 * display the cart with a live order summary.
 * Supports an AJAX (?ajax=1) JSON response for dynamic updates from cart.js.
 * Module: Cart & Checkout (Moaz).
 */
require_once __DIR__ . '/includes/auth.php';

$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$isAjax = isset($_GET['ajax']);

/** Helper: emit JSON for AJAX callers and stop. */
function cart_json(array $extra = []): void
{
    $items  = cart_items();
    $totals = cart_totals($items);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'ok'         => true,
        'cart_count' => cart_count(),
        'subtotal'   => $totals['subtotal'],
        'shipping'   => $totals['shipping'],
        'total'      => $totals['total'],
    ], $extra));
    exit;
}

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        if ($isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'csrf']); exit; }
        set_flash('error', 'Security token mismatch.');
        redirect('cart.php');
    }

    $action = $_POST['action'] ?? '';
    $pid    = (int) ($_POST['product_id'] ?? 0);
    $qty    = (int) ($_POST['quantity'] ?? 1);

    switch ($action) {
        case 'add':
            $product = db_one('SELECT * FROM products WHERE product_id = ? AND status = "active"', [$pid]);
            if ($product && (int) $product['stock_quantity'] > 0) {
                $qty = max(1, $qty);
                $current = $_SESSION['cart'][$pid] ?? 0;
                $newQty  = min($current + $qty, (int) $product['stock_quantity']);
                $_SESSION['cart'][$pid] = $newQty;
                set_flash('success', $product['name'] . ' added to your cart.');
            } else {
                set_flash('error', 'Sorry, that product is unavailable.');
            }
            redirect('cart.php');
            break;

        case 'update':
            $product = db_one('SELECT stock_quantity FROM products WHERE product_id = ?', [$pid]);
            if ($product) {
                $qty = max(1, min($qty, (int) $product['stock_quantity']));
                $_SESSION['cart'][$pid] = $qty;
            }
            if ($isAjax) {
                // include the recomputed line total for this row
                $line = 0.0;
                foreach (cart_items() as $it) {
                    if ((int) $it['product_id'] === $pid) { $line = $it['line_total']; break; }
                }
                cart_json(['product_id' => $pid, 'qty' => $_SESSION['cart'][$pid] ?? 0, 'line_total' => $line]);
            }
            set_flash('success', 'Cart updated.');
            redirect('cart.php');
            break;

        case 'remove':
            unset($_SESSION['cart'][$pid]);
            if ($isAjax) { cart_json(['removed' => $pid]); }
            set_flash('info', 'Item removed from cart.');
            redirect('cart.php');
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            set_flash('info', 'Your cart has been cleared.');
            redirect('cart.php');
            break;
    }
}

// ---- Display ----
$items  = cart_items();
$totals = cart_totals($items);

$page_title = 'Shopping Cart';
$page_scripts = ['cart.js'];
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › Shopping Cart</nav>

<h1>Your Cart <?php if ($items): ?><span class="muted">(<?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>)</span><?php endif; ?></h1>

<?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="ic">🛒</div>
        <h3>Your cart is empty</h3>
        <p>Browse our products and add something you like.</p>
        <a class="btn btn-primary mt-2" href="<?= e(url('products.php')) ?>">Start Shopping</a>
    </div>
<?php else: ?>
<div class="cart-layout">
    <div class="card" id="cart-items" data-csrf="<?= e(csrf_token()) ?>" data-cart-url="<?= e(url('cart.php?ajax=1')) ?>"
         data-free-threshold="<?= e(SHIPPING_FREE_THRESHOLD) ?>" data-flat-fee="<?= e(SHIPPING_FLAT_FEE) ?>">
        <?php foreach ($items as $it): ?>
            <div class="cart-item" data-product-id="<?= (int) $it['product_id'] ?>"
                 data-unit-price="<?= e($it['unit_price']) ?>" data-stock="<?= (int) $it['stock_quantity'] ?>">
                <div class="thumb">
                    <img src="<?= e(url($it['image_path'] ?: 'assets/images/products/accessories.svg')) ?>" alt="<?= e($it['name']) ?>">
                </div>
                <div>
                    <a href="<?= e(url('product_detail.php?id=' . (int) $it['product_id'])) ?>" style="font-weight:600;color:var(--ink)"><?= e($it['name']) ?></a>
                    <div class="muted" style="font-size:.85rem"><?= e($it['brand']) ?></div>
                    <div class="muted" style="font-size:.85rem">Unit price: <?= e(money($it['unit_price'])) ?></div>
                    <form method="post" action="<?= e(url('cart.php')) ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= (int) $it['product_id'] ?>">
                        <button type="submit" class="js-remove" style="background:none;border:0;color:var(--red);cursor:pointer;padding:4px 0;font-size:.85rem">Remove</button>
                    </form>
                </div>
                <div style="text-align:right">
                    <div class="qty-control">
                        <button type="button" class="qty-minus" aria-label="Decrease">−</button>
                        <input type="number" class="qty-input" value="<?= (int) $it['qty'] ?>" min="1" max="<?= (int) $it['stock_quantity'] ?>">
                        <button type="button" class="qty-plus" aria-label="Increase">+</button>
                    </div>
                    <div class="line-total" style="font-weight:700;margin-top:8px"><?= e(money($it['line_total'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div style="display:flex;justify-content:space-between;margin-top:16px">
            <a href="<?= e(url('products.php')) ?>" class="btn btn-ghost btn-sm">← Continue Shopping</a>
            <form method="post" action="<?= e(url('cart.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-ghost btn-sm">Clear Cart</button>
            </form>
        </div>
    </div>

    <aside class="summary-card">
        <h2>Order Summary</h2>
        <div class="summary-row"><span>Subtotal</span><span id="sum-subtotal"><?= e(money($totals['subtotal'])) ?></span></div>
        <div class="summary-row"><span>Shipping</span><span id="sum-shipping"><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free' ?></span></div>
        <div class="summary-total"><span>Total</span><span id="sum-total"><?= e(money($totals['total'])) ?></span></div>
        <p class="muted mt-2" style="font-size:.8rem">Free shipping on orders over <?= e(money(SHIPPING_FREE_THRESHOLD)) ?>.</p>
        <a href="<?= e(url('checkout.php')) ?>" class="btn btn-primary btn-block mt-2">Proceed to Checkout →</a>
        <p class="text-center muted mt-2" style="font-size:.78rem">🔒 Secure checkout guaranteed</p>
    </aside>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
