(function () {
    'use strict';
    var watchedFieldNames = { email: true, phone: true };
    var debounceByField = {};
    var lastSentValueByField = {};
    var pendingByField = {};
    var watchersBound = false;

    function getMs3() {
        if (typeof window !== 'undefined' && window.ms3) return window.ms3;
        if (typeof ms3 !== 'undefined') return ms3;
        return null;
    }

    function getOrderFormSelector() {
        var ref = getMs3();
        if (ref && ref.selectors && ref.selectors.formOrder) {
            return ref.selectors.formOrder;
        }
        return '[data-ms3-form="order"], .ms3_order_form';
    }

    function triggerCostUpdate() {
        var ref = getMs3();
        if (!ref) return;
        if (ref.orderUI && typeof ref.orderUI.updateOrderCosts === 'function') {
            ref.orderUI.updateOrderCosts();
            return;
        }
        if (ref.orderAPI && typeof ref.orderAPI.getCost === 'function') {
            ref.orderAPI.getCost();
        }
    }

    function getBannerEl() {
        return document.getElementById('ms3ftb-discount-banner');
    }

    function updateBannerByEligibility(eligible) {
        var el = getBannerEl();
        if (!el) return;
        el.style.display = eligible ? '' : 'none';
        el.setAttribute('aria-hidden', eligible ? 'false' : 'true');
    }

    function checkEligibilityAndUpdateBanner(form) {
        if (typeof window.MS3FTB_ELIGIBLE_URL !== 'string' || !window.MS3FTB_ELIGIBLE_URL) return;
        if (!form || !form.elements) return;
        var email = (form.elements.email && form.elements.email.value) ? String(form.elements.email.value).trim() : '';
        var phone = (form.elements.phone && form.elements.phone.value) ? String(form.elements.phone.value).trim() : '';
        if (email === '' && phone === '') {
            updateBannerByEligibility(true);
            return;
        }
        var url = window.MS3FTB_ELIGIBLE_URL + '?email=' + encodeURIComponent(email) + '&phone=' + encodeURIComponent(phone);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                updateBannerByEligibility(!!data.eligible);
            })
            .catch(function () {});
    }

    function saveFieldAndRecalculate(input) {
        var ref = getMs3();
        if (!ref || !ref.orderAPI || typeof ref.orderAPI.add !== 'function') return;
        var field = (input.name || '').trim();
        var value = input.value;
        if (!field) return;
        var fieldKey = field + '::' + value;
        if (lastSentValueByField[field] === fieldKey) return;
        if (pendingByField[field] === fieldKey) return;

        pendingByField[field] = fieldKey;
        ref.orderAPI.add(field, value)
            .then(function (response) {
                if (response && response.success) {
                    lastSentValueByField[field] = fieldKey;
                    triggerCostUpdate();
                    if (watchedFieldNames[field]) {
                        var form = input.form || input.closest(getOrderFormSelector());
                        if (form) checkEligibilityAndUpdateBanner(form);
                    }
                }
            })
            .catch(function () {})
            .finally(function () {
                if (pendingByField[field] === fieldKey) {
                    delete pendingByField[field];
                }
            });
    }

    function scheduleSaveAndRecalculate(input) {
        var field = (input.name || '').trim();
        if (!field) return;
        if (debounceByField[field]) clearTimeout(debounceByField[field]);
        debounceByField[field] = setTimeout(function () {
            saveFieldAndRecalculate(input);
        }, 350);
    }

    function isWatchedField(input) {
        if (!input || input.tagName !== 'INPUT') return false;
        var name = (input.name || '').trim();
        return !!watchedFieldNames[name];
    }

    function bindWatchers() {
        if (watchersBound) return;
        var orderFormSelector = getOrderFormSelector();

        document.addEventListener('input', function (event) {
            var input = event.target;
            if (!isWatchedField(input)) return;
            if (!input.closest(orderFormSelector)) return;
            scheduleSaveAndRecalculate(input);
        });

        document.addEventListener('change', function (event) {
            var input = event.target;
            if (!isWatchedField(input)) return;
            if (!input.closest(orderFormSelector)) return;
            var field = (input.name || '').trim();
            if (debounceByField[field]) {
                clearTimeout(debounceByField[field]);
                delete debounceByField[field];
            }
            saveFieldAndRecalculate(input);
        });

        watchersBound = true;
    }

    function init() {
        bindWatchers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    document.addEventListener('ms3:ready', init);
})();
