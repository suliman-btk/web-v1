/* TechNest — payment page interactivity */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var methods    = document.querySelectorAll('.method-card');
        var cardFields = document.getElementById('card-fields');
        var ewFields   = document.getElementById('ewallet-fields');
        var codFields  = document.getElementById('cod-fields');
        var cardInputs = cardFields ? cardFields.querySelectorAll('input') : [];
        var ewInputs   = ewFields   ? ewFields.querySelectorAll('input')   : [];

        function showSection(method) {
            if (cardFields) cardFields.style.display = method === 'card'    ? '' : 'none';
            if (ewFields)   ewFields.style.display   = method === 'ewallet' ? '' : 'none';
            if (codFields)  codFields.style.display  = method === 'cod'     ? '' : 'none';
            // Toggle required validation only on visible fields
            cardInputs.forEach(function (el) { el.disabled = method !== 'card'; });
            ewInputs.forEach(function (el)   { el.disabled = method !== 'ewallet'; });
        }

        methods.forEach(function (label) {
            var radio = label.querySelector('input[type="radio"]');
            if (radio) {
                radio.addEventListener('change', function () {
                    methods.forEach(function (l) { l.classList.remove('selected'); });
                    label.classList.add('selected');
                    showSection(radio.value);
                });
            }
            label.addEventListener('click', function () {
                var r = label.querySelector('input[type="radio"]');
                if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
            });
        });

        // Init visibility based on pre-selected value
        var checked = document.querySelector('.method-card input:checked');
        if (checked) showSection(checked.value);

        // Auto-format card number: add space every 4 digits
        var cardNum = document.getElementById('card_number');
        if (cardNum) {
            cardNum.addEventListener('input', function () {
                var v = cardNum.value.replace(/\D/g, '').slice(0, 16);
                cardNum.value = v.replace(/(.{4})/g, '$1 ').trim();
            });
        }

        // Auto-format expiry MM/YY
        var expiry = document.getElementById('card_expiry');
        if (expiry) {
            expiry.addEventListener('input', function () {
                var v = expiry.value.replace(/\D/g, '').slice(0, 4);
                if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
                expiry.value = v;
            });
        }
    });
})();
