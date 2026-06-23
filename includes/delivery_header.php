<?php
/**
 * Delivery panel layout header. Enforces delivery-role access.
 * Module: Authentication & Cart (Sulaiman).
 */
require_once __DIR__ . '/auth.php';
require_delivery();

$delivery_user = current_user();
$page_title    = isset($page_title) ? $page_title . ' | TechNest Delivery' : 'TechNest Delivery';
$current       = basename($_SERVER['SCRIPT_NAME'] ?? '');
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
        <div class="admin-brand">Tech<span>Nest</span><small>Delivery Panel</small></div>
        <nav class="admin-nav">
            <a href="<?= e(url('delivery/dashboard.php')) ?>" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
                <span class="ic">🚚</span> My Deliveries
            </a>
            <hr>
            <a href="<?= e(url('index.php')) ?>"><span class="ic">🏬</span> View Store</a>
            <a href="<?= e(url('logout.php')) ?>"><span class="ic">🚪</span> Log Out</a>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-burger" id="admin-burger" aria-label="Toggle menu">☰</button>
            <h1 class="admin-title"><?= e($heading ?? 'My Deliveries') ?></h1>
            <div class="admin-user">
                <span class="admin-avatar"><?= e(strtoupper(substr($delivery_user['full_name'], 0, 2))) ?></span>
                <div><strong><?= e($delivery_user['full_name']) ?></strong><br><small>Delivery Staff</small></div>
            </div>
        </header>
        <div class="admin-content">
        <?php foreach (get_flashes() as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?>
                <button type="button" class="flash-close" aria-label="Dismiss">&times;</button></div>
        <?php endforeach; ?>
