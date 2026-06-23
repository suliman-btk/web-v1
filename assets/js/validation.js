/* =====================================================================
   TechNest - real-time client-side form validation
   Reads `data-validate` rules on inputs, e.g. data-validate="required|email".
   Rules: required, email, phone, min:N, password, match:fieldName, checked
   Shows inline messages and blocks submission until valid. PHP re-validates
   everything on the server (this is a usability layer, not the gatekeeper).
   ===================================================================== */
(function () {
    'use strict';

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var PHONE_RE = /^[0-9+\-\s]{7,20}$/;

    var MESSAGES = {
        required: 'This field is required.',
        email:    'Please enter a valid email address.',
        phone:    'Please enter a valid phone number.',
        password: 'Use at least 8 characters with letters and numbers.',
        match:    'Values do not match.',
        checked:  'Please tick this box to continue.',
        luhn:     'Invalid card number. Please check and try again.',
        expiry:   'Invalid or expired expiry date (MM/YY).',
        cvv:      'CVV must be 3 or 4 digits.'
    };

    function luhnCheck(number) {
        var s = number.replace(/\s/g, '');
        if (!/^\d+$/.test(s)) return false;
        var sum = 0, alt = false;
        for (var i = s.length - 1; i >= 0; i--) {
            var n = parseInt(s[i], 10);
            if (alt) { n *= 2; if (n > 9) n -= 9; }
            sum += n; alt = !alt;
        }
        return sum % 10 === 0;
    }

    function expiryValid(val) {
        if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(val)) return false;
        var parts = val.split('/');
        var exp = new Date(2000 + parseInt(parts[1], 10), parseInt(parts[0], 10), 1);
        return exp > new Date();
    }

    function validateField(input) {
        if (input.disabled) return true;
        var field = input.closest('.field');
        if (!field) return true;
        var rules = (input.getAttribute('data-validate') || '').split('|');
        var value = input.type === 'checkbox' ? (input.checked ? 'on' : '') : input.value.trim();
        var error = '';

        for (var i = 0; i < rules.length && !error; i++) {
            var rule = rules[i];
            var arg = null;
            if (rule.indexOf(':') !== -1) { var p = rule.split(':'); rule = p[0]; arg = p[1]; }

            switch (rule) {
                case 'required':
                    if (input.type === 'checkbox' ? !input.checked : value === '') error = MESSAGES.required;
                    break;
                case 'checked':
                    if (!input.checked) error = MESSAGES.checked;
                    break;
                case 'email':
                    if (value !== '' && !EMAIL_RE.test(value)) error = MESSAGES.email;
                    break;
                case 'phone':
                    if (value !== '' && !PHONE_RE.test(value)) error = MESSAGES.phone;
                    break;
                case 'min':
                    if (value !== '' && value.length < parseInt(arg, 10)) error = 'Must be at least ' + arg + ' characters.';
                    break;
                case 'password':
                    if (value !== '' && (value.length < 8 || !/[A-Za-z]/.test(value) || !/[0-9]/.test(value)))
                        error = MESSAGES.password;
                    break;
                case 'match':
                    var other = document.getElementById(arg) || document.querySelector('[name="' + arg + '"]');
                    if (other && value !== other.value) error = MESSAGES.match;
                    break;
                case 'luhn':
                    if (value !== '' && !luhnCheck(value)) error = MESSAGES.luhn;
                    break;
                case 'expiry':
                    if (value !== '' && !expiryValid(value)) error = MESSAGES.expiry;
                    break;
                case 'cvv':
                    if (value !== '' && !/^\d{3,4}$/.test(value)) error = MESSAGES.cvv;
                    break;
            }
        }

        var msgEl = field.querySelector('.error-msg');
        if (error) {
            field.classList.add('has-error');
            field.classList.remove('valid');
            if (msgEl) msgEl.textContent = error;
            return false;
        }
        field.classList.remove('has-error');
        if (value !== '') field.classList.add('valid');
        if (msgEl) msgEl.textContent = '';
        return true;
    }

    function passwordStrength(value) {
        var score = 0;
        if (value.length >= 8) score++;
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
        if (/[0-9]/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;
        if (score <= 1) return 'weak';
        if (score <= 3) return 'medium';
        return 'strong';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form[novalidate]');

        forms.forEach(function (form) {
            var inputs = form.querySelectorAll('[data-validate]');

            inputs.forEach(function (input) {
                var ev = input.type === 'checkbox' ? 'change' : 'input';
                input.addEventListener(ev, function () { validateField(input); });
                input.addEventListener('blur', function () { validateField(input); });
            });

            form.addEventListener('submit', function (e) {
                var ok = true;
                inputs.forEach(function (input) { if (!validateField(input)) ok = false; });
                if (!ok) {
                    e.preventDefault();
                    var firstError = form.querySelector('.field.has-error input');
                    if (firstError) firstError.focus();
                }
            });
        });

        // Password strength meter
        var pw = document.getElementById('password');
        var meter = document.getElementById('pw-meter');
        var label = document.getElementById('pw-label');
        if (pw && meter) {
            pw.addEventListener('input', function () {
                meter.className = 'pw-meter';
                if (pw.value === '') { if (label) label.textContent = 'Use at least 8 characters with letters and numbers.'; return; }
                var s = passwordStrength(pw.value);
                meter.classList.add('pw-' + s);
                if (label) label.textContent = 'Password strength: ' + s.charAt(0).toUpperCase() + s.slice(1);
            });
        }

        // Star picker hover + click interactivity
        var pickers = document.querySelectorAll('.star-picker');
        pickers.forEach(function (picker) {
            var labels = picker.querySelectorAll('.star-label');
            function highlight(upTo) {
                labels.forEach(function (l, idx) {
                    l.classList.toggle('active', idx < upTo);
                });
            }
            labels.forEach(function (lbl, idx) {
                var radio = lbl.querySelector('input[type="radio"]');
                lbl.addEventListener('mouseenter', function () { highlight(idx + 1); });
                picker.addEventListener('mouseleave', function () {
                    var checked = picker.querySelector('input:checked');
                    highlight(checked ? parseInt(checked.value, 10) : 0);
                });
                if (radio) {
                    radio.addEventListener('change', function () { highlight(idx + 1); });
                    if (radio.checked) highlight(idx + 1);
                }
            });
        });
    });
})();
