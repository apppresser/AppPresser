(function () {
    'use strict';
    function initialize() {
        var navigation = document.querySelector('.apppresser-settings-nav');
        if (!navigation) return;
        var links = Array.from(navigation.querySelectorAll('[data-settings-section]'));
        var panels = Array.from(document.querySelectorAll('[data-settings-panel]'));
        function activate(link, focus) {
            var section = link.dataset.settingsSection;
            links.forEach(function (item) {
                if (item === link) item.setAttribute('aria-current', 'page');
                else item.removeAttribute('aria-current');
            });
            panels.forEach(function (panel) {
                panel.hidden = panel.dataset.settingsPanel !== section;
                if (panel.hidden) return;
                panel.querySelectorAll('input[name="_wp_http_referer"]').forEach(function (input) {
                    input.value = link.href;
                });
                panel.querySelectorAll('.CodeMirror').forEach(function (editor) {
                    editor.CodeMirror.refresh();
                });
                if (focus) panel.querySelector('h2').focus();
            });
        }
        document.querySelector('.apppresser_settings').addEventListener('click', function (event) {
            var clicked = event.target.closest('a[data-settings-section], a[data-settings-target]');
            if (!clicked || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            var section = clicked.dataset.settingsSection || clicked.dataset.settingsTarget;
            var link = links.find(function (item) { return item.dataset.settingsSection === section; });
            if (!link) return;
            event.preventDefault();
            if (link.href !== window.location.href) window.history.pushState(null, '', link.href);
            activate(link, true);
        });
        window.addEventListener('popstate', function () {
            var section = (new URL(window.location.href).searchParams.get('tab') || '').replace(/^tab-/, '');
            var link = links.find(function (item) { return item.dataset.settingsSection === section; }) || links[0];
            activate(link, false);
        });
        activate(links.find(function (link) { return link.hasAttribute('aria-current'); }) || links[0], false);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}());
