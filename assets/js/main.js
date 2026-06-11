/* =====================================================================
   TechNest - site-wide JavaScript
   Handles: mobile nav toggle, account dropdown, flash dismiss,
   and keeping the cart badge in sync.
   ===================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // Mobile navigation toggle
        var navToggle = document.getElementById('nav-toggle');
        var mainNav = document.getElementById('main-nav');
        if (navToggle && mainNav) {
            navToggle.addEventListener('click', function () {
                var open = mainNav.classList.toggle('open');
                navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        // Account dropdown
        var accountMenu = document.querySelector('.account-menu');
        var accountToggle = document.querySelector('.account-toggle');
        if (accountMenu && accountToggle) {
            accountToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                accountMenu.classList.toggle('open');
            });
            document.addEventListener('click', function () {
                accountMenu.classList.remove('open');
            });
        }

        // Dismiss flash messages
        document.querySelectorAll('.flash-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.flash').remove();
            });
        });
    });

    /**
     * Update the cart badge number in the header.
     * Exposed globally so cart.js / add-to-cart actions can refresh it.
     */
    window.updateCartBadge = function (count) {
        var badge = document.getElementById('cart-badge');
        if (badge && typeof count === 'number') {
            badge.textContent = count;
        }
    };
})();
