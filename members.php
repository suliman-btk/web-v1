<?php
/**
 * Group details page (required deliverable): names, student IDs, roles
 * and individual contributions for Group 10.
 */
require_once __DIR__ . '/includes/functions.php';

$members = [
    [
        'name' => 'Kashtu, Nasr Abraheem Barkah',
        'id'   => '1221301196',
        'role' => 'Frontend & Product Module',
        'icon' => '🛍️',
        'work' => [
            'Home page, product listing and product detail pages',
            'Hero section, category navigation and featured products',
            'Product search, sorting and filtering',
        ],
    ],
    [
        'name' => 'Alkatheri, Sulaiman Ali Mahdi',
        'id'   => '1211305566',
        'role' => 'Authentication & User Module',
        'icon' => '🔐',
        'work' => [
            'Register, login and user profile pages',
            'User registration with client- and server-side validation',
            'Secure session-based login/logout and profile management',
        ],
    ],
    [
        'name' => 'Moaz Mohamed Salama',
        'id'   => '—',
        'role' => 'Cart & Checkout Module',
        'icon' => '🛒',
        'work' => [
            'Shopping cart, checkout and order confirmation pages',
            'Dynamic cart quantity and total updates using JavaScript',
            'Checkout processing and order history management',
        ],
    ],
    [
        'name' => 'Abdelaziz Khalid Moussa',
        'id'   => '—',
        'role' => 'Admin & Database Module',
        'icon' => '⚙️',
        'work' => [
            'Admin dashboard, product management and order management',
            'Product CRUD and order status management',
            'Database schema, ERD design and security implementation',
        ],
    ],
];

$page_title = 'Our Team';
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> › Our Team</nav>

<section class="text-center">
    <h1>Meet Group 10</h1>
    <p class="muted">CIT6224 Web Application Development — TechNest Project Team</p>
</section>

<div class="product-grid mt-3" style="grid-template-columns:repeat(2,1fr)">
    <?php foreach ($members as $m): ?>
        <article class="card">
            <div style="font-size:2.4rem"><?= e($m['icon']) ?></div>
            <h3 style="margin-top:8px"><?= e($m['name']) ?></h3>
            <p class="muted">Student ID: <?= e($m['id']) ?></p>
            <p><span class="pill pill-processing"><?= e($m['role']) ?></span></p>
            <h4 class="mt-2" style="font-size:.85rem;text-transform:uppercase;color:var(--muted)">Contributions</h4>
            <ul class="spec-list">
                <?php foreach ($m['work'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?>
            </ul>
        </article>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
