(function () {
    'use strict';

    function initialize() {
        document.querySelectorAll('[data-iap-integration]').forEach(function (integration) {
            var toggle = integration.querySelector('[data-iap-toggle]');
            var fields = integration.querySelector('[data-iap-fields]');
            var rows = integration.querySelector('[data-iap-rows]');
            var empty = integration.querySelector('[data-iap-empty]');
            var addButton = integration.querySelector('[data-iap-add]');

            function refresh() {
                fields.hidden = !toggle.checked;
                integration.querySelector('[data-iap-disabled]').hidden = toggle.checked;
                toggle.setAttribute('aria-expanded', String(toggle.checked));
                empty.hidden = !!rows.children.length;
            }

            function announce(message) {
                if (window.wp && wp.a11y) wp.a11y.speak(message);
            }

            toggle.addEventListener('change', refresh);
            addButton.addEventListener('click', function () {
                var index = Number(integration.dataset.nextIndex);
                integration.dataset.nextIndex = String(index + 1);
                var row = integration.querySelector('[data-iap-template]').content.cloneNode(true);
                row.querySelectorAll('[id], [for], [name], [aria-describedby]').forEach(function (element) {
                    ['id', 'for', 'name', 'aria-describedby'].forEach(function (attribute) {
                        if (element.hasAttribute(attribute)) element.setAttribute(attribute, element.getAttribute(attribute).replace(/__index__/g, String(index)));
                    });
                });
                rows.appendChild(row);
                refresh();
                rows.lastElementChild.querySelector('select, input').focus();
                announce(apppresserIAPSettings.added);
            });

            rows.addEventListener('click', function (event) {
                var button = event.target.closest('[data-iap-remove]');
                if (!button) return;
                var row = button.closest('[data-iap-row]');
                var next = row.nextElementSibling || row.previousElementSibling;
                row.remove();
                refresh();
                (next ? next.querySelector('select, input') : addButton).focus();
                announce(apppresserIAPSettings.removed);
            });
            refresh();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}());
