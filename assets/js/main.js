/* =====================================================================
   TechNest — main.js  (vanilla JS only, no frameworks)
   ===================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* ── Mobile nav ─────────────────────────────────────────────── */
        var navToggle = document.getElementById('nav-toggle');
        var mainNav   = document.getElementById('main-nav');
        if (navToggle && mainNav) {
            navToggle.addEventListener('click', function () {
                var open = mainNav.classList.toggle('open');
                navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                document.body.style.overflow = open ? 'hidden' : '';
            });
            // close on outside click
            document.addEventListener('click', function (e) {
                if (mainNav.classList.contains('open') &&
                    !mainNav.contains(e.target) && e.target !== navToggle) {
                    mainNav.classList.remove('open');
                    navToggle.setAttribute('aria-expanded', 'false');
                    document.body.style.overflow = '';
                }
            });
            // close button inside mobile nav
            var closeNav = mainNav.querySelector('.close-nav');
            if (closeNav) {
                closeNav.addEventListener('click', function () {
                    mainNav.classList.remove('open');
                    navToggle.setAttribute('aria-expanded', 'false');
                    document.body.style.overflow = '';
                });
            }
        }

        /* ── Header scroll shadow ────────────────────────────────────── */
        var header = document.querySelector('.site-header');
        if (header) {
            window.addEventListener('scroll', function () {
                header.classList.toggle('scrolled', window.scrollY > 8);
            }, { passive: true });
        }

        /* ── Active nav link by pathname ─────────────────────────────── */
        var path = window.location.pathname.split('/').pop() || 'index.php';
        document.querySelectorAll('.main-nav a').forEach(function (link) {
            var href = link.getAttribute('href') || '';
            var linkPage = href.split('/').pop().split('?')[0];
            if (linkPage && path === linkPage && !link.classList.contains('active')) {
                if (!href.includes('?')) link.classList.add('active');
            }
        });

        /* ── Account dropdown ─────────────────────────────────────────── */
        var accountMenu   = document.querySelector('.account-menu');
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

        /* ── Flash auto-dismiss (4 s) ─────────────────────────────────── */
        document.querySelectorAll('.flash').forEach(function (flash) {
            var close = flash.querySelector('.flash-close');
            if (close) {
                close.addEventListener('click', function () { flash.remove(); });
            }
            setTimeout(function () {
                flash.classList.add('hidden');
                setTimeout(function () { flash.remove(); }, 400);
            }, 4000);
        });

        /* ── Wishlist heart toggle ────────────────────────────────────── */
        document.querySelectorAll('.btn-wishlist').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                btn.classList.toggle('active');
            });
        });

        /* ── Add-to-cart feedback ─────────────────────────────────────── */
        document.querySelectorAll('.add-cart-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('.btn-add-cart');
                if (btn) {
                    var orig = btn.textContent;
                    btn.textContent = 'Added ✓';
                    btn.classList.add('added');
                    btn.disabled = true;
                    setTimeout(function () {
                        btn.textContent = orig;
                        btn.classList.remove('added');
                        btn.disabled = false;
                    }, 1800);
                }
            });
        });

        /* ── Quantity controls (cart page) ──────────────────────────── */
        document.querySelectorAll('.qty-controls').forEach(function (ctrl) {
            var input = ctrl.querySelector('.qty-input');
            var minus = ctrl.querySelector('[data-dir="-1"]');
            var plus  = ctrl.querySelector('[data-dir="1"]');
            if (!input) return;
            function clamp(v) { return Math.max(1, Math.min(999, v)); }
            if (minus) minus.addEventListener('click', function () { input.value = clamp(parseInt(input.value || 1) - 1); input.dispatchEvent(new Event('change')); });
            if (plus)  plus.addEventListener('click',  function () { input.value = clamp(parseInt(input.value || 1) + 1); input.dispatchEvent(new Event('change')); });
        });

        /* ── Payment tabs (checkout) ─────────────────────────────────── */
        document.querySelectorAll('.pay-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.pay-tab').forEach(function (t) { t.classList.remove('active'); });
                document.querySelectorAll('.pay-panel').forEach(function (p) { p.classList.remove('active'); });
                tab.classList.add('active');
                var target = document.getElementById(tab.dataset.target);
                if (target) target.classList.add('active');
            });
        });

        /* ── Admin sidebar burger ─────────────────────────────────────── */
        var adminBurger  = document.querySelector('.admin-burger');
        var adminSidebar = document.querySelector('.admin-sidebar');
        if (adminBurger && adminSidebar) {
            adminBurger.addEventListener('click', function () {
                adminSidebar.classList.toggle('open');
            });
            document.addEventListener('click', function (e) {
                if (adminSidebar.classList.contains('open') &&
                    !adminSidebar.contains(e.target) && e.target !== adminBurger) {
                    adminSidebar.classList.remove('open');
                }
            });
        }

        /* ── Smooth scroll for anchor links ─────────────────────────── */
        document.querySelectorAll('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var id = a.getAttribute('href').slice(1);
                var el = document.getElementById(id);
                if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });
    });

    /* ── Global cart badge updater ──────────────────────────────────── */
    window.updateCartBadge = function (count) {
        var badge = document.getElementById('cart-badge');
        if (badge && typeof count === 'number') badge.textContent = count;
    };

    /* ── Global toast notification ──────────────────────────────────── */
    window.showToast = function (msg, duration) {
        duration = duration || 3000;
        var container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('fade-out');
            setTimeout(function () { toast.remove(); }, 320);
        }, duration);
    };
})();
