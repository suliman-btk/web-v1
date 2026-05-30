<?php
/** About TechNest page. */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'About';
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › About</nav>

<section class="card">
    <h1>About TechNest</h1>
    <p class="mt-2">TechNest is a single-vendor, business-to-consumer (B2C) e-commerce platform
        dedicated to consumer electronics and digital gadgets. We provide a smooth, secure and
        reliable online shopping experience — from smartphones and laptops to audio gear, smart
        home devices and accessories.</p>

    <h2 class="mt-3">Why shop with us?</h2>
    <ul class="spec-list">
        <li>Curated, single-vendor catalogue — every product meets our quality standards.</li>
        <li>Transparent pricing with clear specifications on every product page.</li>
        <li>Secure registration, login and checkout with protected customer data.</li>
        <li>Real-time stock availability and order tracking from your profile.</li>
    </ul>

    <h2 class="mt-3">Our Mission</h2>
    <p>To make buying technology simple, trustworthy and enjoyable through a clean, modern and
        responsive online storefront — backed by efficient inventory and order management.</p>

    <p class="mt-3"><a class="btn btn-primary" href="<?= e(url('products.php')) ?>">Start Shopping</a>
       <a class="btn btn-outline" href="<?= e(url('members.php')) ?>">Meet the Team</a></p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
