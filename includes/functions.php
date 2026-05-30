<?php
/**
 * Shared helper functions used across the whole application.
 */
require_once __DIR__ . '/db.php';

/**
 * Escape output to prevent Cross-Site Scripting (XSS).
 * Every dynamic value printed into HTML should go through this.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Build an absolute URL within the app from a relative path. */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** Redirect to an app path and stop execution. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Format a number as Malaysian Ringgit. */
function money($amount): string
{
    return 'RM ' . number_format((float) $amount, 2);
}

/**
 * The effective price of a product (sale price when set, else normal price).
 */
function effective_price(array $product): float
{
    if (isset($product['discount_price']) && $product['discount_price'] !== null
        && (float) $product['discount_price'] > 0) {
        return (float) $product['discount_price'];
    }
    return (float) $product['price'];
}

/* ---------------------------------------------------------------------
 * Flash messages (one-shot notifications kept across a redirect)
 * ------------------------------------------------------------------- */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------------------------------------------------------------------
 * CSRF protection for state-changing POST forms
 * ------------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input markup to drop inside a <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Validate a submitted CSRF token; returns false if it does not match. */
function csrf_verify(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------------------------------------------------------------------
 * Small query helpers (still using prepared statements under the hood)
 * ------------------------------------------------------------------- */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Total quantity of items currently in the session cart (for the nav badge). */
function cart_count(): int
{
    $count = 0;
    foreach ($_SESSION['cart'] ?? [] as $qty) {
        $count += (int) $qty;
    }
    return $count;
}

/**
 * Build the cart line items from the session, joined with live product data.
 * Returns rows of: product fields + 'qty' + 'unit_price' + 'line_total'.
 * Silently drops products that no longer exist / are inactive.
 */
function cart_items(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all(
        "SELECT * FROM products WHERE product_id IN ($in) AND status = 'active'",
        $ids
    );

    $items = [];
    foreach ($rows as $p) {
        $qty = (int) $cart[$p['product_id']];
        if ($qty < 1) {
            continue;
        }
        // Never allow more than current stock.
        $qty = min($qty, max(0, (int) $p['stock_quantity']));
        if ($qty < 1) {
            continue;
        }
        $unit = effective_price($p);
        $p['qty']        = $qty;
        $p['unit_price'] = $unit;
        $p['line_total'] = $unit * $qty;
        $items[] = $p;
    }
    return $items;
}

/** Compute subtotal / shipping / total for a set of cart items. */
function cart_totals(array $items): array
{
    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += (float) $it['line_total'];
    }
    $shipping = ($subtotal > 0 && $subtotal < SHIPPING_FREE_THRESHOLD) ? SHIPPING_FLAT_FEE : 0.0;
    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total'    => $subtotal + $shipping,
    ];
}
