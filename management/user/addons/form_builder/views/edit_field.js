(function() {
    'use strict';

    var STORAGE_PREFIX = 'form_builder_edit_field_draft__';

    function getStorageKey() {
        var match = window.location.pathname.match(/edit_field\/(\d+)(?:\/(\d+))?/);
        if (!match) return null;
        var form_id  = match[1];
        var field_id = match[2] || 'new';
        return STORAGE_PREFIX + form_id + '_' + field_id;
    }

    function collectFormValues() {
        var values = {};
        var form = document.querySelector('form.form');
        if (!form) return values;
        var elements = form.querySelectorAll('input, select, textarea');
        elements.forEach(function(el) {
            if (!el.name) return;
            if (el.type === 'hidden' && el.name === 'csrf_token') return;
            if (el.type === 'submit') return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                values[el.name] = el.checked ? el.value : '';
            } else {
                values[el.name] = el.value;
            }
        });
        return values;
    }

    function restoreFormValues(values) {
        var form = document.querySelector('form.form');
        if (!form) return;
        Object.keys(values).forEach(function(name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = (values[name] !== '');
            } else {
                el.value = values[name];
            }
        });
    }

    function restoreOnLoad() {
        var key = getStorageKey();
        if (!key) return;
        try {
            var raw = sessionStorage.getItem(key);
            if (!raw) return;
            var draft = JSON.parse(raw);
            restoreFormValues(draft);
            sessionStorage.removeItem(key);
        } catch (e) {
            try { sessionStorage.removeItem(key); } catch (e2) {}
        }
    }

    function attachTypeChangeHandler() {
        var typeSelect = document.querySelector('select[name="field_type"]');
        if (!typeSelect) return;

        var initialType = typeSelect.value;

        typeSelect.addEventListener('change', function() {
            if (this.value === initialType) return;

            var key = getStorageKey();
            if (key) {
                try {
                    sessionStorage.setItem(key, JSON.stringify(collectFormValues()));
                } catch (e) {}
            }

            var url = new URL(window.location.href);
            url.searchParams.set('field_type', this.value);
            window.location.href = url.toString();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            restoreOnLoad();
            attachTypeChangeHandler();
        });
    } else {
        restoreOnLoad();
        attachTypeChangeHandler();
    }
})();
