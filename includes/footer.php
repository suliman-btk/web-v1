<?php
/** Common customer-facing footer + script includes. */
?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h3 class="footer-brand">Tech<span>Nest</span></h3>
            <p>Your one-stop online store for the latest consumer electronics, delivered with quality and care.</p>
        </div>
        <div>
            <h4>Shop</h4>
            <ul>
                <li><a href="<?= e(url('products.php?cat=smartphones')) ?>">Smartphones</a></li>
                <li><a href="<?= e(url('products.php?cat=laptops')) ?>">Laptops</a></li>
                <li><a href="<?= e(url('products.php?cat=audio')) ?>">Audio</a></li>
                <li><a href="<?= e(url('products.php?cat=accessories')) ?>">Accessories</a></li>
            </ul>
        </div>
        <div>
            <h4>Account</h4>
            <ul>
                <li><a href="<?= e(url('login.php')) ?>">Login</a></li>
                <li><a href="<?= e(url('register.php')) ?>">Register</a></li>
                <li><a href="<?= e(url('order_history.php')) ?>">My Orders</a></li>
                <li><a href="<?= e(url('profile.php')) ?>">Profile</a></li>
            </ul>
        </div>
        <div>
            <h4>About</h4>
            <ul>
                <li><a href="<?= e(url('about.php')) ?>">About TechNest</a></li>
                <li><a href="<?= e(url('members.php')) ?>">Our Team</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            &copy; <?= date('Y') ?> TechNest Electronics — CIT6224 Group 10. All rights reserved.
        </div>
    </div>
</footer>

<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<?php if (!empty($page_scripts)) foreach ($page_scripts as $script): ?>
<script src="<?= e(url('assets/js/' . $script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
