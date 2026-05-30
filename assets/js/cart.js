/* =====================================================================
   TechNest - dynamic shopping cart
   Live quantity steppers, instant line-total + order-summary recalculation,
   and AJAX persistence to the server (session cart) without a page reload.
   Module: Cart & Checkout (Moaz).
   ===================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('cart-items');
        if (!container) return;

        var csrf      = container.getAttribute('data-csrf');
        var cartUrl   = container.getAttribute('data-cart-url');
        var freeAt    = parseFloat(container.getAttribute('data-free-threshold')) || 200;
        var flatFee   = parseFloat(container.getAttribute('data-flat-fee')) || 15;

        function money(n) { return 'RM ' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

        function recalcSummary() {
            var subtotal = 0;
            container.querySelectorAll('.cart-item').forEach(function (row) {
                var price = parseFloat(row.getAttribute('data-unit-price')) || 0;
                var qty   = parseInt(row.querySelector('.qty-input').value, 10) || 0;
                subtotal += price * qty;
            });
            var shipping = (subtotal > 0 && subtotal < freeAt) ? flatFee : 0;
            var subEl = document.getElementById('sum-subtotal');
            var shipEl = document.getElementById('sum-shipping');
            var totEl = document.getElementById('sum-total');
            if (subEl)  subEl.textContent  = money(subtotal);
            if (shipEl) shipEl.textContent = shipping > 0 ? money(shipping) : 'Free';
            if (totEl)  totEl.textContent  = money(subtotal + shipping);
        }

        function persist(productId, qty, row) {
            var body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('action', 'update');
            body.set('product_id', productId);
            body.set('quantity', qty);

            fetch(cartUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;
                if (window.updateCartBadge) window.updateCartBadge(data.cart_count);
                // Trust the server's clamped qty / line total
                if (typeof data.qty === 'number') {
                    row.querySelector('.qty-input').value = data.qty;
                }
                var lt = row.querySelector('.line-total');
                if (lt && typeof data.line_total === 'number') lt.textContent = money(data.line_total);
                recalcSummary();
            })
            .catch(function () { /* network error - leave optimistic values */ });
        }

        var timers = {};
        function onQtyChange(row) {
            var input = row.querySelector('.qty-input');
            var pid   = row.getAttribute('data-product-id');
            var max   = parseInt(input.max, 10) || 9999;
            var qty   = Math.max(1, Math.min(max, parseInt(input.value, 10) || 1));
            input.value = qty;

            // Instant (optimistic) line total + summary
            var price = parseFloat(row.getAttribute('data-unit-price')) || 0;
            var lt = row.querySelector('.line-total');
            if (lt) lt.textContent = money(price * qty);
            recalcSummary();

            // Debounced server persistence
            clearTimeout(timers[pid]);
            timers[pid] = setTimeout(function () { persist(pid, qty, row); }, 350);
        }

        container.querySelectorAll('.cart-item').forEach(function (row) {
            var input = row.querySelector('.qty-input');
            var minus = row.querySelector('.qty-minus');
            var plus  = row.querySelector('.qty-plus');

            if (minus) minus.addEventListener('click', function () {
                input.value = (parseInt(input.value, 10) || 1) - 1;
                onQtyChange(row);
            });
            if (plus) plus.addEventListener('click', function () {
                input.value = (parseInt(input.value, 10) || 1) + 1;
                onQtyChange(row);
            });
            if (input) input.addEventListener('change', function () { onQtyChange(row); });
        });
    });
})();
