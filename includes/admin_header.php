<?php
/**
 * Admin panel chrome: sidebar + topbar layout. Enforces admin-only access.
 * Pages in /admin/ include this at the top.
 * Module: Admin & Database (Abdelaziz).
 */
require_once __DIR__ . '/auth.php';
require_admin();

$admin = current_user();
$page_title = isset($page_title) ? $page_title . ' | TechNest Admin' : 'TechNest Admin';
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');

$nav = [
    'dashboard.php' => ['📊', 'Dashboard'],
    'products.php'  => ['📦', 'Manage Products'],
    'orders.php'    => ['🧾', 'Manage Orders'],
    'users.php'     => ['👥', 'Manage Users'],
    'coupons.php'   => ['🏷', 'Coupons'],
    'reports.php'   => ['📈', 'Reports'],
];
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
        <div class="admin-brand">Tech<span>Nest</span><small>Admin Panel</small></div>
        <nav class="admin-nav">
            <?php foreach ($nav as $file => [$icon, $label]): ?>
                <a href="<?= e(url('admin/' . $file)) ?>" class="<?= $current === $file ? 'active' : '' ?>">
                    <span class="ic"><?= $icon ?></span> <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <hr>
            <a href="<?= e(url('index.php')) ?>"><span class="ic">🏬</span> View Store</a>
            <a href="<?= e(url('logout.php')) ?>"><span class="ic">🚪</span> Log Out</a>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-burger" id="admin-burger" aria-label="Toggle menu">☰</button>
            <h1 class="admin-title"><?= e($heading ?? 'Dashboard') ?></h1>
            <div class="admin-user">
                <span class="admin-avatar"><?= e(strtoupper(substr($admin['full_name'], 0, 2))) ?></span>
                <div><strong><?= e($admin['full_name']) ?></strong><br><small>Administrator</small></div>
            </div>
        </header>
        <div class="admin-content">
        <?php foreach (get_flashes() as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?>
                <button type="button" class="flash-close" aria-label="Dismiss">&times;</button></div>
        <?php endforeach; ?>
