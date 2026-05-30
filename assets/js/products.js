/* =====================================================================
   TechNest - product listing & detail page enhancements
   Quantity steppers + live client-side search filtering on the grid.
   ===================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // --- Quantity steppers (product detail page) ---
        document.querySelectorAll('.qty-control').forEach(function (ctrl) {
            var input = ctrl.querySelector('.qty-input');
            var minus = ctrl.querySelector('.qty-minus');
            var plus  = ctrl.querySelector('.qty-plus');
            if (!input) return;

            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || 9999;

            function clamp(v) { return Math.max(min, Math.min(max, v)); }

            if (minus) minus.addEventListener('click', function () {
                input.value = clamp((parseInt(input.value, 10) || min) - 1);
            });
            if (plus) plus.addEventListener('click', function () {
                input.value = clamp((parseInt(input.value, 10) || min) + 1);
            });
            input.addEventListener('change', function () {
                input.value = clamp(parseInt(input.value, 10) || min);
            });
        });

        // --- Instant client-side filtering of the product grid by name ---
        var sidebarSearch = document.querySelector('#filter-form input[name="q"]');
        var grid = document.getElementById('product-grid');
        if (sidebarSearch && grid) {
            sidebarSearch.addEventListener('input', function () {
                var term = sidebarSearch.value.trim().toLowerCase();
                var visible = 0;
                grid.querySelectorAll('.product-card').forEach(function (card) {
                    var nameEl = card.querySelector('.product-name');
                    var name = nameEl ? nameEl.textContent : '';
                    var show = name.toLowerCase().indexOf(term) !== -1;
                    card.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                var counter = document.querySelector('.shop-toolbar .count');
                if (counter && term !== '') {
                    counter.textContent = visible + ' product' + (visible === 1 ? '' : 's') + ' shown';
                }
            });
        }
    });
})();
