<?php
/**
 * Common customer-facing page chrome: <head>, top header, navigation bar,
 * and flash messages. Pull in with require once auth.php is loaded.
 *
 * Optional variables a page may set before including:
 *   $page_title  - appended to the site name in <title>
 */
require_once __DIR__ . '/auth.php';

$page_title = isset($page_title) ? $page_title . ' | ' . SITE_NAME : SITE_NAME;
$nav_categories = db_all('SELECT name, slug FROM categories ORDER BY category_id');
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TechNest - your one-stop online store for consumer electronics.">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-bar">
        <a href="<?= e(url('index.php')) ?>" class="logo">Tech<span>Nest</span></a>

        <form class="search-form" action="<?= e(url('products.php')) ?>" method="get" role="search">
            <input type="search" name="q" placeholder="Search products, brands..."
                   value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search products">
            <button type="submit">Search</button>
        </form>

        <div class="header-actions">
            <a href="<?= e(url('cart.php')) ?>" class="cart-link" aria-label="Shopping cart">
                🛒 Cart <span class="cart-badge" id="cart-badge"><?= cart_count() ?></span>
            </a>
            <?php if (is_logged_in()): ?>
                <div class="account-menu">
                    <button type="button" class="account-toggle">👤 <?= e($_SESSION['name'] ?? 'Account') ?> ▾</button>
                    <ul class="account-dropdown">
                        <li><a href="<?= e(url('profile.php')) ?>">My Profile</a></li>
                        <li><a href="<?= e(url('order_history.php')) ?>">My Orders</a></li>
                        <li><a href="<?= e(url('wishlist.php')) ?>">♡ Wishlist</a></li>
                        <li><a href="<?= e(url('support.php')) ?>">💬 Support</a></li>
                        <?php if (is_admin()): ?>
                            <li><a href="<?= e(url('admin/dashboard.php')) ?>">Admin Panel</a></li>
                        <?php endif; ?>
                        <li><a href="<?= e(url('logout.php')) ?>">Log Out</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-primary btn-sm">Login</a>
            <?php endif; ?>
        </div>

        <button class="nav-toggle" id="nav-toggle" aria-label="Toggle menu" aria-expanded="false">☰</button>
    </div>

    <nav class="main-nav" id="main-nav" aria-label="Primary">
        <div class="container">
            <ul>
                <li><a href="<?= e(url('index.php')) ?>" class="<?= $current === 'index.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="<?= e(url('products.php')) ?>" class="<?= $current === 'products.php' && !isset($_GET['cat']) ? 'active' : '' ?>">Products</a></li>
                <?php foreach ($nav_categories as $cat): ?>
                    <li><a href="<?= e(url('products.php?cat=' . urlencode($cat['slug']))) ?>"
                           class="<?= (($_GET['cat'] ?? '') === $cat['slug']) ? 'active' : '' ?>">
                        <?= e($cat['name']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= e(url('about.php')) ?>" class="<?= $current === 'about.php' ? 'active' : '' ?>">About</a></li>
                <li><a href="<?= e(url('members.php')) ?>" class="<?= $current === 'members.php' ? 'active' : '' ?>">Our Team</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="container page">
<?php foreach (get_flashes() as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="flash-close" aria-label="Dismiss">&times;</button>
    </div>
<?php endforeach; ?>
