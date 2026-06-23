<?php
/**
 * Seller panel layout header. Allows seller or admin access.
 * Module: Admin & Database (Khalid).
 */
require_once __DIR__ . '/auth.php';
require_seller();

$seller_user = current_user();
$page_title  = isset($page_title) ? $page_title . ' | TechNest Seller' : 'TechNest Seller';
$current     = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-brand">Tech<span>Nest</span><small>Seller Panel</small></div>
        <nav class="admin-nav">
            <a href="<?= e(url('seller/dashboard.php')) ?>" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
                <span class="ic">📊</span> Dashboard
            </a>
            <a href="<?= e(url('seller/products.php')) ?>" class="<?= in_array($current, ['products.php','product_form.php']) ? 'active' : '' ?>">
                <span class="ic">📦</span> Products
            </a>
            <a href="<?= e(url('seller/product_form.php')) ?>">
                <span class="ic">➕</span> Add Product
            </a>
            <hr>
            <a href="<?= e(url('index.php')) ?>"><span class="ic">🏬</span> View Store</a>
            <a href="<?= e(url('logout.php')) ?>"><span class="ic">🚪</span> Log Out</a>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-burger" id="admin-burger" aria-label="Toggle menu">☰</button>
            <h1 class="admin-title"><?= e($heading ?? 'Seller Dashboard') ?></h1>
            <div class="admin-user">
                <span class="admin-avatar"><?= e(strtoupper(substr($seller_user['full_name'], 0, 2))) ?></span>
                <div><strong><?= e($seller_user['full_name']) ?></strong><br><small>Seller</small></div>
            </div>
        </header>
        <div class="admin-content">
        <?php foreach (get_flashes() as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?>
                <button type="button" class="flash-close" aria-label="Dismiss">&times;</button></div>
        <?php endforeach; ?>
